<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Support\AlpineExpressions;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * The browser is the one part of this package PHP cannot see working.
 *
 * Every other test asserts on state; these render the actual markup, because a
 * Blade or Alpine mistake produces a page that returns 200, renders, and does
 * nothing at all when clicked.
 */
beforeEach(function () {
    Storage::fake('public');

    foreach ([
        ['advertisements/banner.png', 'banner.png', 'image'],
        ['advertisements/nested/inner.png', 'inner.png', 'image'],
        ['documents/terms.pdf', 'terms.pdf', 'document'],
        ['clip.mp4', 'clip.mp4', 'video'],
    ] as [$path, $name, $kind]) {
        Storage::disk('public')->put($path, 'x');

        Media::create([
            'disk' => 'public',
            'path' => $path,
            'name' => $name,
            'kind' => $kind,
            'size' => 2048,
            'mime_type' => $kind === 'image' ? 'image/png' : ($kind === 'video' ? 'video/mp4' : 'application/pdf'),
            'title' => 'A title',
            'alt' => 'Alt text',
        ]);
    }
});

/** Renders the picker with folders, a selection and an open detail pane. */
function pickerHtml(string $viewMode = 'grid'): string
{
    $first = Media::query()->orderBy('path')->first();

    return Livewire::test(MediaPicker::class, [
        'multiple' => true,
        'disk' => 'public',
        'directory' => '',
        'kinds' => [],
        'targetStatePath' => 'data.image',
    ])
        ->set('viewMode', $viewMode)
        ->call('toggleSelect', $first->id)
        ->call('showDetail', $first->id)
        ->html();
}

function libraryPageHtml(): string
{
    return Livewire::test(MediaLibrary::class)
        ->call('showDetail', Media::query()->orderBy('path')->first()->id)
        ->html();
}

/**
 * The bridge the picker writes the chosen file through. It is rendered inside
 * the *field's* Livewire component, not the picker's, so nothing above covers
 * it — and it is the one piece that makes "Select" do anything at all.
 */
function pickerModalHtml(bool $multiple, string $returns): string
{
    return View::make('filament-media-library::components.picker-modal', [
        'multiple' => $multiple,
        'disk' => 'public',
        'directory' => '',
        'uploadDirectory' => 'advertisements',
        'kinds' => ['image'],
        'statePath' => 'data.image',
        'returns' => $returns,
    ])->render();
}

dataset('rendered markup', [
    'picker (grid)' => [fn () => pickerHtml('grid')],
    'picker (list)' => [fn () => pickerHtml('list')],
    'library page' => [fn () => libraryPageHtml()],
    'picker modal (single, url)' => [fn () => pickerModalHtml(false, 'url')],
    'picker modal (multiple, path)' => [fn () => pickerModalHtml(true, 'path')],
]);

it('leaves no Blade directive uncompiled', function (Closure $render) {
    $html = (string) $render();

    // A directive inside a component-tag attribute is never compiled: the
    // component tag compiler lifts the attribute into a PHP string before the
    // directive compiler ever sees it, so the directive ships to the browser as
    // literal text and the handler dies on the `@`.
    foreach (['@js(', '@php', '@if (', '@foreach (', '<?php'] as $directive) {
        expect(str_contains($html, $directive))
            ->toBeFalse($directive.' survived uncompiled into the rendered markup');
    }
})->with('rendered markup');

it('renders only valid JavaScript in Alpine attributes', function (Closure $render) {
    $expressions = AlpineExpressions::extract((string) $render());

    expect($expressions)->not->toBeEmpty('no Alpine attributes were found — the extractor is broken');

    $scratch = sys_get_temp_dir().'/fml-alpine-'.getmypid();
    File::ensureDirectoryExists($scratch);

    try {
        $errors = AlpineExpressions::errors($expressions, $scratch);
    } finally {
        File::deleteDirectory($scratch);
    }

    expect($errors)->toBe([], "\n  Invalid Alpine expressions:\n    - ".implode("\n    - ", $errors)."\n");
})->with('rendered markup')->skip(
    fn () => ! shell_exec('command -v node'),
    'Node is needed to parse the rendered JavaScript.',
);

it('puts no Blade directive inside a component tag', function () {
    // Blade compiles component tags before directives, lifting each attribute
    // into a PHP string literal — so a directive written in one is never
    // compiled and reaches the browser as text. Only echoes survive the trip.
    $offenders = [];

    foreach (File::allFiles(__DIR__.'/../../resources/views') as $file) {
        // The opening tag only: quoted values may contain '>', children may not.
        preg_match_all(
            '/<x-[\w.:-]+((?:[^>"\']|"[^"]*"|\'[^\']*\')*)\/?>/s',
            $file->getContents(),
            $tags,
        );

        foreach ($tags[1] as $attributes) {
            if (preg_match('/@[a-z]\w*\s*\(/', $attributes, $directive)) {
                $offenders[] = $file->getRelativePathname().': '.$directive[0].'…)';
            }
        }
    }

    expect($offenders)->toBe([], "\n    - ".implode("\n    - ", $offenders)."\n");
});

it('never uses the @js directive in a view', function () {
    $offenders = [];

    foreach (File::allFiles(__DIR__.'/../../resources/views') as $file) {
        if (str_contains($file->getContents(), '@js(')) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([], implode(', ', $offenders)
        .' use the @js directive. Blade directives are not compiled inside'
        .' component-tag attributes and they swallow the newline that follows'
        .' them, which silently breaks the JavaScript. Use {!! $js(...) !!}.');
});

it('confirms the Blade mechanics the views depend on', function () {
    // 1. A raw echo is compiled inside a component-tag attribute; a directive is not.
    $icon = fn (string $interpolation) => Blade::render(
        '<x-filament::icon icon="heroicon-m-folder" x-on:click="go('.$interpolation.')" />',
    );

    expect($icon("@js('p')"))->toContain("go(@js('p'))")
        ->and($icon('{!! \Illuminate\Support\Js::from(\'p\') !!}'))->toContain("go('p')");

    // 2. Blade pads the whitespace after an echo to survive PHP eating the
    //    newline that follows a closing tag; it does not do so for a directive.
    expect(Blade::render("a = @js('p')\nb = 2"))->toBe("a = 'p'b = 2")
        ->and(Blade::render("a = {!! \Illuminate\Support\Js::from('p') !!}\nb = 2"))->toBe("a = 'p'\nb = 2");
});

it('ships JavaScript that parses', function () {
    foreach (File::glob(__DIR__.'/../../resources/dist/*.js') as $file) {
        exec('node --check '.escapeshellarg($file).' 2>&1', $output, $status);

        expect($status)->toBe(0, basename($file).': '.implode(' ', $output));
    }
})->skip(fn () => ! shell_exec('command -v node'), 'Node is needed to parse the shipped JavaScript.');

it('renders every view the package ships', function () {
    // Coverage that cannot silently lapse: a view added later but left out of
    // the datasets above would never be parsed, and the checks are only worth
    // what they reach.
    $rendered = [];

    View::composer('*', function ($view) use (&$rendered): void {
        $rendered[$view->name()] = true;
    });

    pickerHtml('grid');
    pickerHtml('list');
    libraryPageHtml();
    pickerModalHtml(true, 'path');

    $shipped = array_map(
        fn (SplFileInfo $file): string => 'filament-media-library::'
            .str_replace(['/', '.blade.php'], ['.', ''], $file->getRelativePathname()),
        File::allFiles(__DIR__.'/../../resources/views'),
    );

    $missed = array_values(array_diff($shipped, array_keys($rendered)));

    expect($missed)->toBe([], 'never rendered by a test: '.implode(', ', $missed));
});
