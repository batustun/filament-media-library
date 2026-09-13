<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A second kind of tenant, whose keys run in their own sequence. */
class Marketplace extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'marketplaces';
}
