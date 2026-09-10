<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['username', 'password', 'full_name', 'email', 'phone', 'role', 'avatar', 'status', 'archived'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['archived' => 'boolean', 'password' => 'hashed']; }
}
