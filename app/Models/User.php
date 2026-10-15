<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// `role` is deliberately NOT fillable: it can only be changed by an admin via forceFill.
#[Fillable(['name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['user', 'staff', 'admin'];

    protected $attributes = ['role' => 'user'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function staffAssignments()
    {
        return $this->hasMany(StaffAssignment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** Location ids this account may operate. Admins: all. Staff: approved assignments only. */
    public function staffLocationIds(): array
    {
        if ($this->isAdmin()) {
            return Location::pluck('id')->all();
        }
        if (! $this->isStaff()) {
            return [];
        }

        return $this->staffAssignments()->where('is_approved', true)->pluck('location_id')->all();
    }

    public function canOperateLocation(int $locationId): bool
    {
        return $this->isAdmin() || in_array($locationId, $this->staffLocationIds(), true);
    }
}
