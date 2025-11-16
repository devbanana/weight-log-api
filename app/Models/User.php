<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $display_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $date_of_birth
 * @property \Illuminate\Support\Carbon|null $profile_completed_at
 * @property int|null $age
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'display_name',
        'email',
        'password',
        'date_of_birth',
    ];

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
            'date_of_birth' => 'date',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * Check if the user has completed their profile.
     */
    public function hasCompletedProfile(): bool
    {
        return ! is_null($this->profile_completed_at);
    }

    /**
     * Get the user's age based on their date of birth.
     */
    public function getAgeAttribute(): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return (int) $this->date_of_birth->diffInYears(now());
    }
}
