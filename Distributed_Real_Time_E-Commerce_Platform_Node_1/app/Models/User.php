<?php

namespace App\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * `role` is deliberately not fillable so a client can never promote itself.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => Role::class,
    ];

    protected $attributes = [
        'role' => 'Customer',
    ];

    public function isResponsible(): bool
    {
        return $this->role === Role::Responsible;
    }

    public function isCustomer(): bool
    {
        return $this->role === Role::Customer;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
