<?php

declare(strict_types=1);

it('ships every locale with exactly the English key set', function () {
    $dir = __DIR__.'/../../resources/lang';
    $locales = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $out = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $out = is_array($value)
                ? [...$out, ...$flatten($value, $path)]
                : [...$out, $path];
        }

        return $out;
    };

    $english = $flatten(require "{$dir}/en/filament-media-library.php");

    expect($locales)->toContain('en', 'tr');

    foreach ($locales as $locale) {
        $keys = $flatten(require "{$dir}/{$locale}/filament-media-library.php");

        expect(array_diff($english, $keys))->toBe([], "[{$locale}] is missing keys");
        expect(array_diff($keys, $english))->toBe([], "[{$locale}] has keys English does not");
    }
});

it('keeps every :placeholder that English uses in each locale', function () {
    $dir = __DIR__.'/../../resources/lang';
    $locales = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $out = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $out = [...$out, ...$flatten($value, $path)];

                continue;
            }

            $out[$path] = (string) $value;
        }

        return $out;
    };

    $placeholders = function (string $text): array {
        preg_match_all('/:[a-z_]+/', $text, $matches);

        return array_values(array_unique($matches[0]));
    };

    $english = $flatten(require "{$dir}/en/filament-media-library.php");

    foreach ($locales as $locale) {
        if ($locale === 'en') {
            continue;
        }

        $translated = $flatten(require "{$dir}/{$locale}/filament-media-library.php");

        foreach ($english as $key => $source) {
            $expected = $placeholders($source);

            if ($expected === []) {
                continue;
            }

            // A dropped :count or :folder renders as literal text in the UI.
            expect($placeholders($translated[$key] ?? ''))
                ->toEqualCanonicalizing($expected, "[{$locale}] {$key}");
        }
    }
});

it('defines every translation key the views and classes ask for', function () {
    $en = require __DIR__.'/../../resources/lang/en/filament-media-library.php';
    $has = function (array $tree, string $key): bool {
        foreach (explode('.', $key) as $part) {
            if (! is_array($tree) || ! array_key_exists($part, $tree)) {
                return false;
            }

            $tree = $tree[$part];
        }

        return true;
    };

    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2)));

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (! $file->isFile() || ! preg_match('#/(resources/views|src)/.+\.php$#', $path)) {
            continue;
        }

        $source = (string) file_get_contents($path);

        preg_match_all('/\$t\s*\.\s*\'\.([a-z0-9_.]+)\'/i', $source, $viaPrefix);
        preg_match_all('/filament-media-library::filament-media-library\.([a-z0-9_.]+)[\'"]/i', $source, $viaNamespace);

        foreach ([...$viaPrefix[1], ...$viaNamespace[1]] as $key) {
            // A key ending in a dot is the head of a dynamic one ('kinds.'.$kind).
            if (! str_ends_with($key, '.') && ! $has($en, $key)) {
                $missing[] = $key.' ('.basename($path).')';
            }
        }
    }

    expect($missing)->toBe([]);
});
