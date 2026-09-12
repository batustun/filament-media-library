<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Support;

/**
 * Pulls the Alpine expressions out of rendered markup and checks that each one
 * is valid JavaScript.
 *
 * An Alpine attribute is compiled at runtime, in the browser, so a syntax error
 * in one is invisible to PHP: the page renders, the tests pass, and the feature
 * is simply dead. Compiling them here is the only way the suite can see it.
 */
final class AlpineExpressions
{
    /**
     * Attributes Alpine evaluates as JavaScript. `x-model` and `x-ref` take a
     * plain property name, and `wire:` belongs to Livewire, so both are out.
     *
     * @var array<int, string>
     */
    private const EVALUATED = ['x-data', 'x-init', 'x-effect', 'x-show', 'x-text', 'x-html', 'x-if'];

    /** @return array<string, string> expression keyed by the attribute it came from */
    public static function extract(string $html): array
    {
        preg_match_all('/\s((?:x-on:|x-bind:|@|:)[\w.:\-\[\]$]+|x-[\w\-]+)="([^"]*)"/', $html, $matches, PREG_SET_ORDER);

        $found = [];

        foreach ($matches as [, $attribute, $value]) {
            if (! self::isEvaluated($attribute)) {
                continue;
            }

            $expression = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);

            if (trim($expression) === '') {
                continue;
            }

            $found[$attribute.' :: '.md5($expression)] = $expression;
        }

        return $found;
    }

    private static function isEvaluated(string $attribute): bool
    {
        if (in_array($attribute, self::EVALUATED, true)) {
            return true;
        }

        return str_starts_with($attribute, 'x-on:')
            || str_starts_with($attribute, 'x-bind:')
            || str_starts_with($attribute, '@')
            || str_starts_with($attribute, ':');
    }

    /**
     * Wraps an expression exactly the way Alpine does before handing it to the
     * AsyncFunction constructor, so what Node parses is what the browser parses.
     *
     * Alpine only treats an expression as a statement body when it *begins*
     * with `if`, `let` or `const` — a leading comment defeats that test, which
     * is a syntax error the browser reports and nothing else does.
     */
    public static function wrap(string $expression): string
    {
        $trimmed = trim($expression);

        $body = preg_match('/^[\n\s]*if.*\(.*\)/', $trimmed) || preg_match('/^(let|const)\s/', $trimmed)
            ? '(async()=>{ '.$expression.' })()'
            : $expression;

        return "async function __evaluator(__scope) { let __self = this; with (__scope) { __self.result = {$body} } }\n";
    }

    /**
     * @param  array<string, string>  $expressions
     * @return array<int, string> one human-readable failure per invalid expression
     */
    public static function errors(array $expressions, string $scratch): array
    {
        $errors = [];

        foreach ($expressions as $label => $expression) {
            // .cjs keeps Node in sloppy mode, where `with` is legal — the same
            // mode the AsyncFunction body runs in.
            $file = $scratch.'/'.md5($label).'.cjs';
            file_put_contents($file, self::wrap($expression));

            exec('node --check '.escapeshellarg($file).' 2>&1', $output, $status);

            if ($status !== 0) {
                $attribute = explode(' :: ', $label)[0];
                $errors[] = $attribute.': '.implode(' ', array_slice($output, 0, 4))
                    ."\n        expression: ".trim(preg_replace('/\s+/', ' ', $expression) ?? '');
            }

            $output = [];
            @unlink($file);
        }

        return $errors;
    }
}
