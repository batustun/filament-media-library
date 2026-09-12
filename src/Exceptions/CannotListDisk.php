<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A disk refused to enumerate its contents.
 *
 * Distinct from a generic failure because the causes are specific and
 * actionable: wrong or placeholder credentials, an adapter that cannot do a
 * deep listing, or a bucket too large to walk. Commands catch this and report
 * the reason instead of unrolling a Flysystem stack trace at the operator.
 */
class CannotListDisk extends RuntimeException
{
    public function __construct(public readonly string $disk, Throwable $previous)
    {
        parent::__construct(
            "The disk [{$disk}] could not be listed: {$previous->getMessage()}",
            0,
            $previous,
        );
    }

    /** The root cause, stripped of Flysystem's wrapping. */
    public function reason(): string
    {
        $message = $this->getPrevious()?->getMessage() ?? $this->getMessage();

        // Flysystem prefixes its own sentence and appends the whole HTTP body.
        if (($at = strpos($message, 'Reason: ')) !== false) {
            $message = substr($message, $at + 8);
        }

        return trim((string) strtok($message, "\n"));
    }
}
