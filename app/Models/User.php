<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    /**
     * Schools assigned to this user (for school-scoped roles like school_viewer).
     */
    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class)->withTimestamps();
    }

    /**
     * Determine whether the user is restricted to their assigned schools.
     */
    public function isSchoolScoped(): bool
    {
        return $this->hasRole('school_viewer')
            && ! $this->hasAnyRole(['super_admin', 'superadmin', 'manager']);
    }

    /**
     * Determine whether the user can access the given school.
     */
    public function canAccessSchool(School $school): bool
    {
        if (! $this->isSchoolScoped()) {
            return true;
        }

        return $this->schools()->whereKey($school->getKey())->exists();
    }
}
