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
