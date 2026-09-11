<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Concerns\Browser;

use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Providers\MediaProviderRegistry;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Livewire\Attributes\Url;

/**
 * Where the browser is looking: a Flysystem disk, or a provider that behaves
 * like one in the UI but stores an external id instead of a path.
 */
trait InteractsWithMediaSources
{
    #[Url(as: 'disk', except: '')]
    public string $disk = '';

    /**
     * Never trust the disk: it arrives from a query string and from mount()
     * arguments, so it is always forced back into the allow-list.
     */
    protected function sanitizeDisk(?string $disk): string
    {
        $disk = trim((string) $disk);
        $available = $this->availableSources();

        if ($disk !== '' && in_array($disk, $available, true)) {
            return $disk;
        }

        $default = MediaLibraryConfig::defaultDisk();

        if ($available === [] || in_array($default, $available, true)) {
            return $default;
        }

        return $available[0];
    }

    /** @return array<int, string> */
    public function availableDisks(): array
    {
        return MediaLibraryConfig::disks();
    }

    /**
     * Everything an editor can browse or upload to: filesystem disks plus any
     * configured provider (a video platform), which behaves like a disk in the
     * UI but stores an external id rather than a path.
     *
     * @return array<int, string>
     */
    public function availableSources(): array
    {
        return [...$this->availableDisks(), ...app(MediaProviderRegistry::class)->keys()];
    }

    /** @return array<string, string> Source key => human label. */
    public function sourceOptions(): array
    {
        $options = [];

        foreach ($this->availableDisks() as $disk) {
            $options[$disk] = $disk;
        }

        foreach (app(MediaProviderRegistry::class)->all() as $key => $provider) {
            $options[$key] = $provider->label();
        }

        return $options;
    }

    public function currentProvider(): ?MediaProvider
    {
        return app(MediaProviderRegistry::class)->get($this->disk);
    }

    public function updatedDisk(): void
    {
        $this->disk = $this->sanitizeDisk($this->disk);
        $this->directory = '';
        $this->resetPage();
    }
}
