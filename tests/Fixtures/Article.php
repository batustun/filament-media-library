<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Tests\Fixtures;

use Batustun\FilamentMediaLibrary\Concerns\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use InteractsWithMedia;

    protected $guarded = [];

    public $timestamps = false;

    protected $table = 'articles';
}
