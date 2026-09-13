<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A tenant, for exercising the library inside a tenanted panel. */
class Workspace extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'workspaces';
}
