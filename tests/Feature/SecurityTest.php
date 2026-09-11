<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Services\MediaService;
use Batustun\FilamentMediaLibrary\Support\SvgSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('public'));

/** Every one of these is a working stored-XSS payload if served unsanitised. */
dataset('svg payloads', [
    'inline script' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    'uppercase script' => '<svg xmlns="http://www.w3.org/2000/svg"><SCRIPT>alert(1)</SCRIPT></svg>',
    'onload handler' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>',
    'onmouseover handler' => '<svg xmlns="http://www.w3.org/2000/svg"><rect onmouseover="alert(1)"/></svg>',
    'javascript href' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>x</text></a></svg>',
    'obfuscated javascript href' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="java&#09;script:alert(1)"><text>x</text></a></svg>',
    'foreignObject html' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>',
    'animate href' => '<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="href" values="javascript:alert(1)"/></svg>',
    'use external' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.test/x.svg#a"/></svg>',
    'css expression' => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="width:expression(alert(1))"/></svg>',
    'css import' => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="background:url(x);@import url(//evil.test/x.css)"/></svg>',
    'iframe' => '<svg xmlns="http://www.w3.org/2000/svg"><iframe src="https://evil.test"></iframe></svg>',
]);

it('strips every executable construct from an svg', function (string $payload) {
    $clean = SvgSanitizer::sanitize($payload);

    expect($clean)->not->toBeNull();

    $lowered = strtolower($clean);

    expect($lowered)->not->toContain('<script')
        ->and($lowered)->not->toContain('foreignobject')
        ->and($lowered)->not->toContain('<iframe')
        ->and($lowered)->not->toContain('javascript:')
        ->and($lowered)->not->toContain('expression(')
        ->and($lowered)->not->toContain('@import')
        ->and($lowered)->not->toMatch('/\son[a-z]+\s*=/');
})->with('svg payloads');

it('keeps the drawing intact while removing the danger', function () {
    $clean = SvgSanitizer::sanitize(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
        .'<circle cx="5" cy="5" r="4" fill="#f00"/><script>alert(1)</script></svg>',
    );

    expect($clean)->toContain('<circle')
        ->and($clean)->toContain('fill="#f00"')
        ->and($clean)->toContain('viewBox="0 0 10 10"')
        ->and($clean)->not->toContain('onload')
        ->and($clean)->not->toContain('script');
});

it('keeps safe in-document and https references', function () {
    $clean = SvgSanitizer::sanitize(
        '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        .'<rect fill="url(#grad)"/><a href="https://example.test/ok"><text>ok</text></a>'
        .'<image xlink:href="data:image/png;base64,AAAA"/></svg>',
    );

    expect($clean)->toContain('https://example.test/ok')
        ->and($clean)->toContain('data:image/png;base64');
});

it('refuses a document that is not svg at all', function () {
    expect(SvgSanitizer::sanitize('<html><body>nope</body></html>'))->toBeNull()
        ->and(SvgSanitizer::sanitize('not markup'))->toBeNull()
        ->and(SvgSanitizer::sanitize(''))->toBeNull();
});

it('does not expand entities, so it cannot be used for xxe or billion laughs', function () {
    $xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
        .'<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';

    $clean = SvgSanitizer::sanitize($xxe);

    expect($clean)->not->toBeNull()
        ->and($clean)->not->toContain('root:')
        ->and($clean)->not->toContain('/etc/passwd');
});

it('writes the sanitised document to disk, not the original bytes', function () {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><rect/></svg>',
        ),
        'public',
    );

    $stored = Storage::disk('public')->get($media->path);

    expect($stored)->not->toContain('script')
        ->and($stored)->not->toContain('onload')
        ->and($stored)->toContain('<rect')
        // The recorded size must match what actually landed on the disk.
        ->and($media->size)->toBe(strlen($stored));
});

it('rejects an svg that cannot be parsed', function () {
    expect(fn () => app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent('broken.svg', '<svg><unclosed>'),
        'public',
    ))->toThrow(ValidationException::class);
});

it('can be switched off for a disk that is known to be safe', function () {
    config()->set('filament-media-library.security.sanitize_svg', false);

    $media = app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent('raw.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'),
        'public',
    );

    expect(Storage::disk('public')->get($media->path))->toContain('script');
});

it('blocks server-executable and same-origin-html uploads', function (string $name) {
    expect(fn () => app(MediaService::class)->store(
        UploadedFile::fake()->create($name, 2),
        'public',
    ))->toThrow(ValidationException::class);
})->with(['shell.php', 'payload.PHTML', 'x.phar', 'run.sh', 'tool.exe', 'page.html', 'conf.htaccess']);

it('reads the blocked extension from the original filename, not the temp path', function () {
    // Livewire stores temporary uploads under a generated name; checking the
    // temp path would see no extension and let everything through.
    $file = UploadedFile::fake()->create('shell.php', 2);

    expect(pathinfo($file->getPathname(), PATHINFO_EXTENSION))->not->toBe('php')
        ->and(fn () => app(MediaService::class)->store($file, 'public'))
        ->toThrow(ValidationException::class);
});

it('still accepts ordinary media', function (string $name) {
    expect(app(MediaService::class)->store(UploadedFile::fake()->create($name, 4), 'public'))
        ->not->toBeNull();
})->with(['photo.jpg', 'clip.mp4', 'clip.mov', 'shot.heic', 'doc.pdf', 'sheet.xlsx', 'deck.pptx', 'song.mp3', 'bundle.zip']);
