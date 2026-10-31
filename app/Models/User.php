<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];

    public const ROLES = ['admin' => 'Admin', 'sales' => 'Sales', 'team' => 'Team'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function assignedSetups(): HasMany
    {
        return $this->hasMany(Setup::class, 'assignee_id');
    }

    /** Setups this person handed to someone (sales → team). */
    public function setupsHandedOut(): HasMany
    {
        return $this->hasMany(Setup::class, 'assigned_by');
    }

    /** Admins see earnings, payouts and settings; the team doesn't. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
