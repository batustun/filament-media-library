<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionlessUser;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
});

function previewable(string $name, string $mime, string $kind, string $contents = 'x'): Media
{
    Storage::disk('public')->put("files/{$name}", $contents);

    return Media::create([
        'disk' => 'public',
        'path' => "files/{$name}",
        'name' => $name,
        'mime_type' => $mime,
        'kind' => $kind,
        'size' => strlen($contents),
    ]);
}

it('picks the right preview strategy for each kind', function (string $name, string $mime, string $kind, string $expected) {
    expect(previewable($name, $mime, $kind)->previewStrategy())->toBe($expected);
})->with([
    ['a.jpg', 'image/jpeg', 'image', 'image'],
    ['a.png', 'image/png', 'image', 'image'],
    ['a.svg', 'image/svg+xml', 'image', 'image'],
    ['a.mp4', 'video/mp4', 'video', 'video'],
    ['a.mp3', 'audio/mpeg', 'audio', 'audio'],
    ['a.pdf', 'application/pdf', 'pdf', 'pdf'],
    ['a.json', 'application/json', 'code', 'text'],
    ['a.txt', 'text/plain', 'code', 'text'],
    ['a.csv', 'text/csv', 'sheet', 'text'],
    ['a.zip', 'application/zip', 'archive', 'archive'],
    // No viewer configured, so an Office file has nothing to show.
    ['a.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc', 'none'],
]);

it('falls back to an icon for images a browser cannot decode', function (string $name, string $mime) {
    // Stored and indexed happily, but an <img> would show a broken-image glyph.
    $media = previewable($name, $mime, 'image');

    expect($media->isRenderableImage())->toBeFalse()
        ->and($media->previewStrategy())->toBe('none');
})->with([
    ['a.heic', 'image/heic'],
    ['a.heif', 'image/heif'],
    ['a.tiff', 'image/tiff'],
]);

it('previews an office document once a viewer is configured', function () {
    config()->set('filament-media-library.preview.office_viewer', 'microsoft');

    // A third-party viewer fetches the file itself, so it only works for an
    // absolute URL — which is what a CDN-backed disk produces in production.
    config()->set('filament-media-library.url_resolvers', [
        'public' => fn (string $path): string => 'https://cdn.example.test/'.$path,
    ]);

    $media = previewable('report.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc');

    expect($media->previewStrategy())->toBe('office')
        ->and($media->officeViewerUrl())->toStartWith('https://view.officeapps.live.com/op/embed.aspx?src=');

    config()->set('filament-media-library.preview.office_viewer', 'google');

    expect($media->officeViewerUrl())->toStartWith('https://docs.google.com/viewer?embedded=1&url=');
});

it('refuses an office viewer for a url the internet cannot reach', function () {
    config()->set('filament-media-library.preview.office_viewer', 'microsoft');
    config()->set('filament-media-library.url_resolvers', []);
    config()->set('filesystems.disks.private', ['driver' => 'local', 'root' => storage_path('app/private')]);

    $media = Media::create([
        'disk' => 'private', 'path' => 'a.docx', 'name' => 'a.docx', 'kind' => 'doc',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => 1,
    ]);

    expect($media->officeViewerUrl())->toBeNull();
});

it('returns a bounded window of a text file', function () {
    $media = previewable('notes.txt', 'text/plain', 'code', "line one\nline two");

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/text")
        ->assertOk()
        ->assertJson(['ok' => true, 'content' => "line one\nline two", 'truncated' => false, 'csv' => false]);
});

it('truncates a large text file instead of loading all of it', function () {
    $media = previewable('huge.log', 'text/plain', 'code', str_repeat('a', 100 * 1024));

    $response = $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/text")
        ->assertOk();

    expect($response->json('truncated'))->toBeTrue()
        ->and(strlen($response->json('content')))->toBe(64 * 1024);
});

it('flags a csv so the panel can render it as a table', function () {
    $media = previewable('rows.csv', 'text/csv', 'sheet', "a,b\n1,2");

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/text")
        ->assertOk()
        ->assertJson(['csv' => true]);
});

it('refuses to render binary dressed as text', function () {
    $media = previewable('fake.txt', 'text/plain', 'code', "\x00\xFF\xFE binary \x01");

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/text")
        ->assertOk()
        ->assertJson(['ok' => false, 'reason' => 'binary']);
});

it('lists what is inside an archive', function () {
    $path = storage_path('app/preview-test.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('readme.txt', 'hello');
    $zip->addFromString('img/logo.png', 'binary');
    $zip->close();

    Storage::disk('public')->put('files/bundle.zip', file_get_contents($path));
    @unlink($path);

    $media = Media::create([
        'disk' => 'public', 'path' => 'files/bundle.zip', 'name' => 'bundle.zip',
        'mime_type' => 'application/zip', 'kind' => 'archive', 'size' => 1,
    ]);

    $response = $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/archive")
        ->assertOk();

    expect($response->json('total'))->toBe(2)
        ->and(collect($response->json('entries'))->pluck('name')->all())
        ->toEqualCanonicalizing(['readme.txt', 'img/logo.png']);
});

it('requires authentication and the view permission', function () {
    $media = previewable('a.txt', 'text/plain', 'code');

    $this->getJson("/media-library/{$media->id}/preview/text")->assertStatus(401);

    config()->set('filament-media-library.permissions.enabled', true);

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$media->id}/preview/text")
        ->assertForbidden();
});

it('can be switched off', function () {
    config()->set('filament-media-library.preview.text', false);
    config()->set('filament-media-library.preview.archives', false);

    $text = previewable('a.txt', 'text/plain', 'code');

    expect($text->previewStrategy())->toBe('none');

    $this->actingAs(new PermissionlessUser(['id' => 1]))
        ->getJson("/media-library/{$text->id}/preview/text")
        ->assertNotFound();
});
