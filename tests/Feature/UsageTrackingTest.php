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

function trackedMedia(): Media
{
    return Media::create([
        'disk' => 'public',
        'path' => 'p/'.uniqid().'.png',
        'name' => 'a.png',
        'kind' => 'image',
        'size' => 1,
    ]);
}

it('reports how many models an item is attached to', function () {
    $item = trackedMedia();

    expect($item->usageCount())->toBe(0)
        ->and($item->isInUse())->toBeFalse();

    Article::create(['title' => 'One'])->attachMedia($item, 'cover');
    Article::create(['title' => 'Two'])->attachMedia($item, 'cover');

    expect($item->usageCount())->toBe(2)
        ->and($item->isInUse())->toBeTrue();
});

it('breaks usage down by model and collection', function () {
    $item = trackedMedia();

    $article = Article::create(['title' => 'One']);
    $article->attachMedia($item, 'cover');
    $article->attachMedia($item, 'gallery');

    $breakdown = collect($item->usageBreakdown())->keyBy('collection');

    expect($breakdown)->toHaveCount(2)
        ->and($breakdown['cover']['type'])->toBe(Article::class)
        ->and($breakdown['cover']['count'])->toBe(1);
});

it('answers usage from an eager-loaded aggregate without another query', function () {
    $item = trackedMedia();
    Article::create(['title' => 'One'])->attachMedia($item, 'cover');

    $loaded = Media::query()->withUsageCount()->whereKey($item->id)->first();

    expect($loaded->usageCount())->toBe(1);

    // Proving it came from the aggregate: no pivot rows are read afterwards.
    DB::table('media_library_attachables')->delete();

    expect($loaded->usageCount())->toBe(1)
        ->and($item->fresh()->usageCount())->toBe(0);
});
