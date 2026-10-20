<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['username', 'password', 'full_name', 'email', 'phone', 'role', 'branch_name', 'avatar', 'status', 'archived'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['archived' => 'boolean', 'password' => 'hashed']; }

    /** Sees every branch and manages accounts, settings, pricing and stock. */
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }

    /** An admin limited to the branch in branch_name. */
    public function isBranchAdmin(): bool { return $this->role === 'admin'; }

    /** Clients are registered by the branch that serves them; the super admin only looks at them. */
    public function canRegisterClients(): bool { return $this->isBranchAdmin(); }

    /** Payments are recorded by the branch that serves the client; the super admin only looks at the bills and payments. */
    public function canRecordPayments(): bool { return $this->isBranchAdmin(); }
}
