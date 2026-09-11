<?php

declare(strict_types=1);

namespace Batustun\FilamentMediaLibrary\Policies;

use Batustun\FilamentMediaLibrary\Models\Media;
use Batustun\FilamentMediaLibrary\Support\Authorize;
use Illuminate\Contracts\Auth\Authenticatable;

class MediaPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return Authorize::check('view', $user);
    }

    public function view(?Authenticatable $user, Media $media): bool
    {
        return Authorize::check('view', $user);
    }

    public function create(?Authenticatable $user): bool
    {
        return Authorize::check('upload', $user);
    }

    public function upload(?Authenticatable $user): bool
    {
        return Authorize::check('upload', $user);
    }

    public function update(?Authenticatable $user, Media $media): bool
    {
        return Authorize::check('manage', $user);
    }

    public function delete(?Authenticatable $user, Media $media): bool
    {
        return Authorize::check('delete', $user);
    }

    public function deleteAny(?Authenticatable $user): bool
    {
        return Authorize::check('delete', $user);
    }

    public function manage(?Authenticatable $user): bool
    {
        return Authorize::check('manage', $user);
    }
}
