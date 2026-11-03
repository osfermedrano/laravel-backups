<?php

namespace Osfermedrano\Backups\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Usuario de un proyecto SIN Spatie Permission (autoriza con is_admin). */
class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = ['is_admin' => 'boolean'];
}
