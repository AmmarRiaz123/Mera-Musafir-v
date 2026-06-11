<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->wasChanged('preferences')) {
                Cache::forget("match:trips:user:{$user->id}");
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'type',
        'avatar',
        'bio',
        'city',
        'gender',
        'reputation_score',
        'is_verified',
        'is_blocked',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function agency()
    {
        return $this->hasOne(Agency::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'is_blocked' => 'boolean',
            'preferences' => 'array',
        ];
    }
}

