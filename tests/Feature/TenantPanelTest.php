<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Livewire\MediaPicker;
use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\Marketplace;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\Workspace;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The scope where it actually has to work: a panel serving one tenant.
 *
 * Every other tenancy test holds a key by hand. This one puts Filament in the
 * state a real request puts it in, because that is the state the scope reads.
 */
function tenantPickerHtml(): string
{
    return (string) Livewire::test(MediaPicker::class, [
        'multiple' => false,
        'disk' => 'public',
        'directory' => '',
        'kinds' => [],
        'targetStatePath' => 'data.image',
    ])->html();
}

beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
    config()->set('filament-media-library.tenancy.enabled', true);

    Schema::create('workspaces', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    $mine = Workspace::create(['name' => 'Mine']);
    Workspace::create(['name' => 'Theirs']);

    foreach ([[1, 'mine.png'], [2, 'theirs.png'], [null, 'common.png']] as [$tenant, $name]) {
        Storage::disk('public')->put('vault/'.$name, 'x');

        Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
            'disk' => 'public', 'path' => 'vault/'.$name, 'directory' => 'vault',
            'name' => $name, 'kind' => 'image', 'size' => 10,
            'tenant_id' => $tenant, 'tenant_type' => $tenant === null ? null : Workspace::class,
        ]);
    }

    Filament::setCurrentPanel('workspace');
    Filament::setTenant($mine, isQuiet: true);
});

it('shows a tenant only its own files in the picker', function () {
    $html = tenantPickerHtml();

    expect($html)->toContain('mine.png');
    expect($html)->not->toContain('theirs.png');
    expect($html)->not->toContain('common.png');
});

it('shows a tenant the shared files too when sharing is on', function () {
    config()->set('filament-media-library.tenancy.shared', true);

    $html = tenantPickerHtml();

    expect($html)->toContain('mine.png');
    expect($html)->toContain('common.png');
    expect($html)->not->toContain('theirs.png');
});

it('stamps an upload made inside a tenant panel with that tenant', function () {
    Livewire::test(MediaPicker::class, [
        'multiple' => false,
        'disk' => 'public',
        'directory' => '',
        'kinds' => [],
        'targetStatePath' => 'data.image',
    ])->set('uploads', [UploadedFile::fake()->image('fresh.png')]);

    expect(Media::where('name', 'like', 'fresh%')->sole()->tenant_id)->toEqual(1);
});

it('keeps the folder sidebar inside the tenant as well', function () {
    // Folders are derived from the media index, so an unscoped folder tree
    // would leak the names of every other tenant's directories.
    Storage::disk('public')->put('theirsecret/x.png', 'x');

    Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
        'disk' => 'public', 'path' => 'theirsecret/x.png', 'directory' => 'theirsecret',
        'name' => 'x.png', 'kind' => 'image', 'size' => 10,
        'tenant_id' => 2, 'tenant_type' => Workspace::class,
    ]);

    $html = tenantPickerHtml();

    expect($html)->not->toContain('theirsecret');
});

it('keeps two kinds of tenant apart even when they share a key', function () {
    // An application can have more than one tenanted panel, and their primary
    // keys are separate sequences: both have a tenant 1. Keyed on the id alone
    // those two were the same tenant and saw each other's media.
    Schema::create('marketplaces', function (Blueprint $table): void {
        $table->id();
    });

    $marketplace = Marketplace::create([]);

    expect($marketplace->getKey())->toBe(1); // the same key as workspace "Mine"

    Storage::disk('public')->put('vault/marketplace.png', 'x');

    Media::actingForTenant(
        $marketplace->getKey(),
        fn () => Media::create([
            'disk' => 'public', 'path' => 'vault/marketplace.png', 'directory' => 'vault',
            'name' => 'marketplace.png', 'kind' => 'image', 'size' => 10,
        ]),
        $marketplace->getMorphClass(),
    );

    // The workspace panel is still current, on workspace 1.
    expect(tenantPickerHtml())->not->toContain('marketplace.png');

    // And the marketplace does not see the workspace's file either.
    Media::actingForTenant($marketplace->getKey(), function (): void {
        expect(Media::pluck('name')->all())->toBe(['marketplace.png']);
    }, $marketplace->getMorphClass());
});

it('still shows a library written before the type column existed', function () {
    // Upgrading must not hide what an existing single-tenant install already
    // has: those rows carry a key and no type.
    Storage::disk('public')->put('vault/legacy.png', 'x');

    Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
        'disk' => 'public', 'path' => 'vault/legacy.png', 'directory' => 'vault',
        'name' => 'legacy.png', 'kind' => 'image', 'size' => 10,
        'tenant_id' => 1, 'tenant_type' => null,
    ]);

    expect(tenantPickerHtml())->toContain('legacy.png');
});
