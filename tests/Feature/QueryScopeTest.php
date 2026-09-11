<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;

function makeMedia(array $attributes = []): Media
{
    return Media::create(array_merge([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => 'file.png',
        'kind' => 'image',
        'size' => 1,
    ], $attributes));
}

it('searches case-insensitively on every database driver', function () {
    // The suite runs on SQLite; a hard-coded ILIKE would fatal here, which is
    // exactly the PostgreSQL-only regression this guards.
    makeMedia(['name' => 'Annual-REPORT.pdf', 'kind' => 'pdf']);
    makeMedia(['name' => 'holiday.png']);

    expect(Media::query()->search('report')->count())->toBe(1)
        ->and(Media::query()->search('REPORT')->count())->toBe(1)
        ->and(Media::query()->search('nothing-here')->count())->toBe(0);
});

it('searches across title, alt and description too', function () {
    makeMedia(['name' => 'a.png', 'title' => 'Quarterly Figures']);
    makeMedia(['name' => 'b.png', 'alt' => 'A cat on a roof']);
    makeMedia(['name' => 'c.png', 'description' => 'Signed by the board']);

    expect(Media::query()->search('quarterly')->count())->toBe(1)
        ->and(Media::query()->search('cat')->count())->toBe(1)
        ->and(Media::query()->search('board')->count())->toBe(1);
});

it('treats like wildcards typed by a user as literal characters', function () {
    makeMedia(['name' => 'invoice.png']);
    makeMedia(['name' => '100%-done.png']);

    expect(Media::query()->search('%')->count())->toBe(1)
        ->and(Media::query()->search('_')->count())->toBe(0);
});

it('does not leak an OR out of the root directory scope', function () {
    makeMedia(['disk' => 'public', 'directory' => null]);
    makeMedia(['disk' => 'other', 'directory' => null]);
    makeMedia(['disk' => 'other', 'directory' => '']);

    // Without grouping, the whereNull/orWhere pair escapes the disk filter and
    // returns rows from every disk.
    $count = Media::query()->where('disk', 'public')->inDirectory('')->count();

    expect($count)->toBe(1);
});

it('matches a directory and its whole subtree separately', function () {
    makeMedia(['directory' => 'gallery']);
    makeMedia(['directory' => 'gallery/2026']);
    makeMedia(['directory' => 'gallery/2026/spring']);
    makeMedia(['directory' => 'gallery-archive']);

    expect(Media::query()->inDirectory('gallery')->count())->toBe(1)
        ->and(Media::query()->inDirectoryTree('gallery')->count())->toBe(3);
});
