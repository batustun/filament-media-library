<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Tests\Fixtures\PermissionedUser;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Tenancy is a security boundary, so it is tested from the outside: not "is the
 * scope applied" but "can this request reach another tenant's file".
 */
beforeEach(function () {
    Storage::fake('public');
    config()->set('filament-media-library.permissions.enabled', false);
    config()->set('filament-media-library.tenancy.enabled', true);

    // The package's HTTP routes run on plain web middleware, so no panel is
    // current and Filament knows of no tenant. The test harness pins one for
    // every other test; clearing it is what makes this the real situation.
    Filament::setCurrentPanel(null);
});

function tenantMedia(int $tenant, string $name = 'secret.png'): Media
{
    Storage::disk('public')->put('vault/'.$name, 'x');

    return Media::withoutGlobalScope('filament-media-library-tenant')->create([
        'disk' => 'public',
        'path' => 'vault/'.$name,
        'directory' => 'vault',
        'name' => $name,
        'kind' => 'image',
        'size' => 10,
        'tenant_id' => $tenant,
    ]);
}

it('does not hand another tenant a file over the download route', function () {
    $theirs = tenantMedia(99);

    $this->actingAs(new PermissionedUser(['id' => 1]))
        ->get(route('filament-media-library.download', $theirs->id))
        ->assertNotFound();
});

it('does not preview another tenant file', function () {
    $theirs = tenantMedia(99, 'notes.txt');

    $this->actingAs(new PermissionedUser(['id' => 1]))
        ->get(route('filament-media-library.preview.text', $theirs->id))
        ->assertNotFound();
});

it('does not let one tenant delete another tenant file', function () {
    $theirs = tenantMedia(99);

    $this->actingAs(new PermissionedUser(['id' => 1]))
        ->delete(route('filament-media-library.destroy', $theirs->id))
        ->assertNotFound();

    expect(Media::withoutGlobalScope('filament-media-library-tenant')->count())->toBe(1);
});

it('still lets a panel without tenancy see everything', function () {
    // The admin panel is the legitimate cross-tenant view.
    tenantMedia(99);
    Filament::setCurrentPanel(Filament::getDefaultPanel());

    expect(Media::count())->toBe(1);
});

it('still lets the console see everything', function () {
    // Sync, doctor and queued work belong to no tenant.
    tenantMedia(99);
    app('request')->setRouteResolver(fn () => null);

    expect(Media::count())->toBe(1);
});

it('still serves a file through the signed link the panel minted', function () {
    // Closing the hole must not close the feature: the panel mints these links
    // while the tenant is known and the query behind them is already scoped.
    $mine = tenantMedia(7);

    $this->actingAs(new PermissionedUser(['id' => 1]))
        ->get(URL::signedRoute('filament-media-library.download', ['media' => $mine->id]))
        ->assertOk();
});

it('refuses a signed link whose id has been swapped for another tenant file', function () {
    $mine = tenantMedia(7, 'mine.png');
    $theirs = tenantMedia(99, 'theirs.png');

    $forged = str_replace(
        $mine->id,
        $theirs->id,
        URL::signedRoute('filament-media-library.download', ['media' => $mine->id]),
    );

    // A broken signature is simply not a signature: the request falls back to
    // the tenant scope, which cannot see the file, so it is missing rather than
    // refused — nothing confirms that the id exists.
    $this->actingAs(new PermissionedUser(['id' => 1]))->get($forged)->assertNotFound();
});

it('stamps a chunked upload with the tenant its signed endpoint names', function () {
    // The endpoint runs outside the panel, so without this the file would be
    // stored belonging to nobody and vanish from the tenant that uploaded it.
    config()->set('filament-media-library.chunked_uploads.enabled', true);

    $url = URL::signedRoute('filament-media-library.chunk', ['tenant' => '7']);

    $this->actingAs(new PermissionedUser(['id' => 1]))->post($url, [
        'uuid' => (string) Str::uuid(),
        'index' => 0,
        'total' => 1,
        'name' => 'clip.mp4',
        'chunk' => UploadedFile::fake()->create('chunk', 8),
        'disk' => 'public',
        'directory' => 'vault',
    ])->assertCreated();

    $stored = Media::withoutGlobalScope(Media::TENANT_SCOPE)->sole();

    expect($stored->tenant_id)->toBe('7');
});

it('keeps one tenant out of another even when shared media is switched on', function () {
    // 'shared' opens up media belonging to NO tenant. It must not open up media
    // belonging to a different one.
    config()->set('filament-media-library.tenancy.shared', true);

    tenantMedia(99, 'theirs.png');
    $shared = Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
        'disk' => 'public', 'path' => 'vault/common.png', 'directory' => 'vault',
        'name' => 'common.png', 'kind' => 'image', 'size' => 10, 'tenant_id' => null,
    ]);

    Media::actingForTenant(7, function () use ($shared): void {
        expect(Media::pluck('id')->all())->toBe([$shared->id]);
    });
});

it('shows a tenant only its own media when sharing is off', function () {
    $mine = tenantMedia(7, 'mine.png');
    tenantMedia(99, 'theirs.png');
    Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
        'disk' => 'public', 'path' => 'vault/common.png', 'directory' => 'vault',
        'name' => 'common.png', 'kind' => 'image', 'size' => 10, 'tenant_id' => null,
    ]);

    Media::actingForTenant(7, function () use ($mine): void {
        expect(Media::pluck('id')->all())->toBe([$mine->id]);
    });
});

it('says so when the doctor is asked and tenancy is off', function () {
    // "Why does a tenant still see everything" is almost always this.
    config()->set('filament-media-library.tenancy.enabled', false);

    $this->artisan('media-library:doctor', ['--disk' => 'public'])
        ->expectsOutputToContain('Tenancy is OFF')
        ->assertSuccessful();
});

it('reports how many rows no tenant can reach', function () {
    Media::withoutGlobalScope(Media::TENANT_SCOPE)->create([
        'disk' => 'public', 'path' => 'vault/orphan.png', 'directory' => 'vault',
        'name' => 'orphan.png', 'kind' => 'image', 'size' => 10, 'tenant_id' => null,
    ]);

    $this->artisan('media-library:doctor', ['--disk' => 'public'])
        ->expectsOutputToContain('belong to no tenant')
        ->assertSuccessful();
});
