<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Providers;

use Batustun\FilamentMediaLibrary\Providers\Contracts\MediaProvider;
use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured providers and hands them out by key.
 *
 * Only providers that are both enabled and actually configured (credentials
 * present) are ever returned, so a half-filled .env can never put a broken
 * source in front of an editor.
 */
class MediaProviderRegistry
{
    /** @var array<string, MediaProvider>|null */
    private ?array $resolved = null;

    public function __construct(private readonly Container $container) {}

    /** @return array<string, MediaProvider> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $providers = [];

        foreach (MediaLibraryConfig::providers() as $key => $settings) {
            $class = $settings['driver'] ?? null;

            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (! ($settings['enabled'] ?? false)) {
                continue;
            }

            $provider = $this->container->make($class);

            if ($provider instanceof MediaProvider && $provider->isConfigured()) {
                $providers[$provider->key()] = $provider;
            }
        }

        return $this->resolved = $providers;
    }

    public function get(?string $key): ?MediaProvider
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->all()[$key] ?? null;
    }

    public function has(?string $key): bool
    {
        return $this->get($key) !== null;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /** Forget the resolved set, so a config change in a test takes effect. */
    public function flush(): void
    {
        $this->resolved = null;
    }
}
