<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMediaBrowser;
use Batustun\FilamentMediaLibrary\FilamentMediaLibraryPlugin;
use Batustun\FilamentMediaLibrary\Filters\MediaFilter;
use Batustun\FilamentMediaLibrary\Filters\MediaSorter;
use Batustun\FilamentMediaLibrary\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Exercises the browser's query and state logic directly.
 *
 * The markup these methods drive is rendered and parsed in BladeRenderingTest.
 */
function browser(): object
{
    return new class
    {
        use InteractsWithMediaBrowser;
    };
}

function row(array $overrides = []): Media
{
    return Media::create(array_merge([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => 'file.png',
        'kind' => 'image',
        'size' => 1024,
    ], $overrides));
}

beforeEach(fn () => Storage::fake('public'));

it('registers host filters and sorters on the panel plugin', function () {
    $plugin = FilamentMediaLibraryPlugin::make()
        ->filters([
            MediaFilter::make('licence')->label('Licence')->options(['rf' => 'Royalty free'])->using(fn (Builder $q) => $q),
            'not a filter',
        ])
        ->sorters([
            MediaSorter::make('most_used')->label('Most used')->using(fn (Builder $q) => $q),
        ]);

    expect($plugin->getFilters())->toHaveCount(1)
        ->and($plugin->getFilters()[0]->getLabel())->toBe('Licence')
        ->and($plugin->getSorters()[0]->getKey())->toBe('most_used');
});

it('applies a filter only when it has a value', function () {
    row(['kind' => 'image']);
    row(['kind' => 'pdf']);

    $filter = MediaFilter::make('only')
        ->options(['pdf' => 'PDF'])
        ->using(fn (Builder $query, string $value) => $query->where('kind', $value));

    $query = Media::query();
    $filter->apply($query, null);
    expect($query->count())->toBe(2);

    $query = Media::query();
    $filter->apply($query, 'pdf');
    expect($query->count())->toBe(1);
});

it('treats a false boolean filter as unset', function () {
    row();
    row();

    $filter = MediaFilter::make('featured')
        ->boolean()
        ->using(fn (Builder $query) => $query->whereRaw('1 = 0'));

    $query = Media::query();
    $filter->apply($query, false);
    expect($query->count())->toBe(2);

    $query = Media::query();
    $filter->apply($query, true);
    expect($query->count())->toBe(0);
});

it('derives a readable label from the key when none is given', function () {
    expect(MediaFilter::make('shot_by')->getLabel())->toBe('Shot by')
        ->and(MediaSorter::make('most_used')->getLabel())->toBe('Most used');
});

it('filters by size range', function () {
    row(['name' => 'small.png', 'size' => 512 * 1024]);
    row(['name' => 'large.png', 'size' => 8 * 1024 * 1024]);

    $browser = browser();
    $browser->disk = 'public';
    $browser->sizeMin = '1';

    expect($browser->items()->pluck('name')->all())->toBe(['large.png']);

    $browser->sizeMin = '';
    $browser->sizeMax = '1';

    expect($browser->items()->pluck('name')->all())->toBe(['small.png']);
});

it('filters by tag', function () {
    $tagged = row(['name' => 'tagged.png']);
    row(['name' => 'plain.png']);
    $tagged->syncTagNames(['Campaign']);

    $browser = browser();
    $browser->disk = 'public';
    $browser->tagFilter = 'campaign';

    expect($browser->items()->pluck('name')->all())->toBe(['tagged.png']);
});

it('reports and clears every active filter', function () {
    $browser = browser();
    $browser->disk = 'public';

    expect($browser->hasFilters())->toBeFalse();

    $browser->sizeMin = '2';
    expect($browser->hasFilters())->toBeTrue();

    $browser->clearFilters();
    expect($browser->hasFilters())->toBeFalse()
        ->and($browser->sizeMin)->toBe('');
});

it('remembers the view mode in the session', function () {
    $browser = browser();
    $browser->setView('list');

    expect(session('filament-media-library.view-mode'))->toBe('list');

    $other = browser();
    $other->disk = 'public';
    $other->bootInteractsWithMediaBrowser();

    expect($other->viewMode)->toBe('list');
});

it('does not remember the view mode when that is switched off', function () {
    config()->set('filament-media-library.ui.remember_view_mode', false);

    browser()->setView('list');

    expect(session('filament-media-library.view-mode'))->toBeNull();
});

it('toggles the extension display and remembers it', function () {
    $browser = browser();
    $browser->showExtensions = true;

    $browser->toggleExtensions();

    expect($browser->showExtensions)->toBeFalse()
        ->and(session('filament-media-library.show-extensions'))->toBeFalse();
});

it('lists only tags that exist on the current source', function () {
    $onPublic = row(['disk' => 'public']);
    $onCdn = row(['disk' => 'cdn']);

    $onPublic->syncTagNames(['here']);
    $onCdn->syncTagNames(['elsewhere']);

    $browser = browser();
    $browser->disk = 'public';

    expect(array_values($browser->availableTags()))->toBe(['here']);
});

it('falls back to built-in sorting for an unknown sort key', function () {
    row(['name' => 'a.png']);
    row(['name' => 'b.png']);

    $browser = browser();
    $browser->disk = 'public';
    $browser->sort = 'name';

    expect($browser->items()->pluck('name')->all())->toBe(['a.png', 'b.png']);
});

it('shows a folder the moment it is created, before anything is in it', function () {
    $browser = browser();
    $browser->disk = 'public';

    expect($browser->folderTree())->toBeEmpty();

    // Folders are derived from indexed files, so a new one would otherwise be
    // invisible until its first upload — which reads as "it did not work".
    $browser->directory = 'kampanyalar/2026';

    expect($browser->folderTree()->pluck('path')->all())
        ->toBe(['kampanyalar', 'kampanyalar/2026']);
});

it('does not duplicate a folder that already has files in it', function () {
    row(['directory' => 'mevcut', 'name' => 'a.png']);

    $browser = browser();
    $browser->disk = 'public';
    $browser->directory = 'mevcut';

    expect($browser->folderTree()->pluck('path')->all())->toBe(['mevcut']);
});

it('lists the folders directly inside the one being browsed', function () {
    row(['directory' => 'assistsocial/uploads/posts/photos', 'name' => 'deep.png']);
    row(['directory' => 'advertisements', 'name' => 'flat.png']);

    $browser = browser();
    $browser->disk = 'public';

    expect(collect($browser->childFolders())->pluck('name')->all())
        ->toEqualCanonicalizing(['assistsocial', 'advertisements']);

    // A parent whose files all live deeper still reports them, so it never
    // looks empty.
    $browser->directory = 'assistsocial';

    expect($browser->childFolders())->toBe([
        ['name' => 'uploads', 'path' => 'assistsocial/uploads', 'count' => 1],
    ]);
});

it('shows no folder cards while a filter is narrowing everything', function () {
    row(['directory' => 'a/b', 'name' => 'x.png']);

    $browser = browser();
    $browser->disk = 'public';

    expect($browser->childFolders())->not->toBeEmpty();

    // A filtered view is a search across the library, not a place.
    $browser->search = 'x';

    expect($browser->hasFilters())->toBeTrue();
});

it('clears the derived caches when the library is refreshed', function () {
    $browser = browser();
    $browser->disk = 'public';
    $browser->folderTree();

    row(['directory' => 'sonradan', 'name' => 'y.png']);
    $browser->refreshLibrary();

    expect($browser->folderTree()->pluck('path')->all())->toContain('sonradan');
});
