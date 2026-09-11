<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Strips the executable surface out of an SVG.
 *
 * An SVG is an XML document that a browser will happily run scripts from, so
 * an unsanitised upload served from the application's own origin is stored
 * XSS. WordPress refuses SVG uploads outright for exactly this reason; this
 * class is what lets the library accept them safely instead.
 *
 * The policy is a deny-list of the elements and attributes that can execute or
 * fetch, applied to a parsed DOM rather than to the raw text — regex over
 * markup is trivially bypassed with entities, newlines and mixed case.
 */
final class SvgSanitizer
{
    /** Elements that can execute script, load remote content, or navigate. */
    private const FORBIDDEN_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'embed', 'object', 'audio', 'video',
        'animate', 'animatemotion', 'animatetransform', 'set', 'handler', 'listener',
    ];

    /** Attributes that take a URL and may therefore carry a javascript: payload. */
    private const URL_ATTRIBUTES = ['href', 'xlink:href', 'src', 'data', 'action', 'formaction', 'from', 'to', 'values'];

    /** URL schemes an SVG may reference. Anything else is dropped. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'data'];

    /**
     * Returns sanitised SVG markup, or null when the input is not parseable as
     * SVG at all (in which case the caller should reject the upload).
     */
    public static function sanitize(string $svg): ?string
    {
        if (trim($svg) === '') {
            return null;
        }

        // PHP 8 disables the external entity loader by default, and this
        // package requires ^8.2, so there is nothing to toggle here.
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $document->preserveWhiteSpace = false;

            // LIBXML_NONET blocks network access during parsing and NOENT is
            // deliberately NOT set, so entities are never expanded — together
            // they close the billion-laughs and XXE doors.
            $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            if (! $loaded || ! $document->documentElement) {
                return null;
            }

            if (strtolower($document->documentElement->nodeName) !== 'svg') {
                return null;
            }

            self::stripDoctype($document);
            self::stripElements($document);
            self::stripAttributes($document);

            $clean = $document->saveXML($document->documentElement);

            return is_string($clean) ? $clean : null;
        } catch (Throwable) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function isSvg(?string $mimeType, string $filename): bool
    {
        return strtolower((string) $mimeType) === 'image/svg+xml'
            || strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) === 'svg';
    }

    /** A DOCTYPE can declare entities, so it never survives. */
    private static function stripDoctype(DOMDocument $document): void
    {
        if ($document->doctype !== null) {
            $document->doctype->parentNode?->removeChild($document->doctype);
        }
    }

    private static function stripElements(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (self::FORBIDDEN_ELEMENTS as $name) {
            // Matched case-insensitively on the local name, so <SCRIPT> and a
            // namespaced <svg:script> are both caught.
            $nodes = $xpath->query(
                sprintf("//*[translate(local-name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='%s']", $name),
            );

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                /** @var DOMNode $node */
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private static function stripAttributes(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*');

        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                /** @var DOMAttr $attribute */
                $name = strtolower($attribute->nodeName);
                $value = $attribute->nodeValue ?? '';

                // Every event handler: onload, onclick, onmouseover, …
                if (str_starts_with($name, 'on')) {
                    $node->removeAttributeNode($attribute);

                    continue;
                }

                if (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl($value)) {
                    $node->removeAttributeNode($attribute);

                    continue;
                }

                // Inline CSS can pull in remote resources or run expressions.
                if ($name === 'style' && self::hasDangerousCss($value)) {
                    $node->removeAttributeNode($attribute);
                }
            }
        }
    }

    private static function isSafeUrl(string $value): bool
    {
        // Collapse the whitespace and control characters used to smuggle
        // "java\nscript:" past naive checks.
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $value) ?? '');

        if ($normalised === '') {
            return true;
        }

        // In-document references (#gradient) and relative paths are fine.
        if (str_starts_with($normalised, '#') || ! str_contains($normalised, ':')) {
            return true;
        }

        $scheme = strstr($normalised, ':', true);

        if ($scheme === false || ! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        // data: is only safe for images, never for markup or scripts.
        return $scheme !== 'data' || str_starts_with($normalised, 'data:image/');
    }

    private static function hasDangerousCss(string $css): bool
    {
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $css) ?? '');

        foreach (['javascript:', 'expression(', '@import', 'behavior:', '-moz-binding'] as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }
}
