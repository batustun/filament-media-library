<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\Article;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('articles', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
    });
});

function media(string $name = 'a.png'): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => $name,
        'kind' => 'image',
        'size' => 1,
    ]);
}

it('attaches media to any model through a named collection', function () {
    $article = Article::create(['title' => 'Hello']);
    $cover = media('cover.png');
    $inline = media('inline.png');

    $article->attachMedia($cover, 'cover');
    $article->attachMedia($inline, 'gallery');

    expect($article->media()->count())->toBe(2)
        ->and($article->media('cover')->count())->toBe(1)
        ->and($article->media('cover')->first()->name)->toBe('cover.png');
});

it('detaches only from the named collection', function () {
    $article = Article::create(['title' => 'Hello']);
    $item = media();

    $article->attachMedia($item, 'cover');
    $article->attachMedia($item, 'gallery');

    $article->detachMedia($item, 'cover');

    expect($article->media('cover')->count())->toBe(0)
        ->and($article->media('gallery')->count())->toBe(1);
});

it('syncs a collection in order without touching other collections', function () {
    $article = Article::create(['title' => 'Hello']);
    $a = media('a.png');
    $b = media('b.png');
    $other = media('other.png');

    $article->attachMedia($other, 'cover');
    $article->syncMedia([$b->id, $a->id], 'gallery');

    expect($article->media('gallery')->pluck('name')->all())->toBe(['b.png', 'a.png'])
        ->and($article->media('cover')->count())->toBe(1);
});

it('cascades the pivot row when the media is deleted', function () {
    $article = Article::create(['title' => 'Hello']);
    $item = media();

    $article->attachMedia($item, 'cover');
    $item->delete();

    expect($article->media()->count())->toBe(0);
});
