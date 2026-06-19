<?php

namespace Entitlements\Tests\Fixtures;

use Entitlements\Concerns\HasFeatures;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFeatures;

    protected $guarded = [];

    protected $casts = ['is_admin' => 'boolean'];
}
