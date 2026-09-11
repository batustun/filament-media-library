<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Models\Concerns;

use Batustun\FilamentMediaLibrary\Support\MediaLibraryConfig;

/** Extra, application-defined fields stored under `meta.custom`. */
trait HasCustomMetadata
{
    /** @return array<string, mixed> */
    public function customMeta(): array
    {
        $custom = $this->meta['custom'] ?? [];

        return is_array($custom) ? $custom : [];
    }

    public function customMetaValue(string $key): mixed
    {
        return $this->customMeta()[$key] ?? null;
    }

    /**
     * Merge values into the configured custom fields.
     *
     * Anything not declared in `metadata_fields` is dropped, so a crafted
     * request cannot write arbitrary keys into the item's meta.
     *
     * @param  array<string, mixed>  $values
     */
    public function setCustomMeta(array $values): void
    {
        $allowed = array_column(MediaLibraryConfig::metadataFields(), 'key');

        $this->forceFill([
            'meta' => [
                ...($this->meta ?? []),
                'custom' => [...$this->customMeta(), ...array_intersect_key($values, array_flip($allowed))],
            ],
        ]);
    }
}
