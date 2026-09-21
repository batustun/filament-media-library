<?php

declare(strict_types=1);

use Batustun\FilamentMediaLibrary\Tests\SpatiePermissionTestCase;
use Batustun\FilamentMediaLibrary\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

// Authorisation is the one area where a polite fixture proves nothing, so
// these boot the real spatie/laravel-permission instead.
uses(SpatiePermissionTestCase::class)->in(__DIR__.'/Permissions');
