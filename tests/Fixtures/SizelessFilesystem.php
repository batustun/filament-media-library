<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Filesystem\FilesystemAdapter;
use RuntimeException;

/** A disk that writes fine but cannot answer a size query, as a broken listing does. */
class SizelessFilesystem extends FilesystemAdapter
{
    public function size($path): int
    {
        throw new RuntimeException('Call to a member function getTimestamp() on false');
    }
}
