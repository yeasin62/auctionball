<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implementing HasLocalePreference makes Mail::to($user) auto-render
 * mailables in $user->locale — no per-call ->locale() needed.
 */
class User extends Authenticatable implements HasLocalePreference
{
    use HasFactory, Notifiable;

    /**
     * `is_super_admin` is intentionally NOT in $fillable. It can only be set
     * via explicit assignment in trusted paths: the `make:super-admin` artisan
     * command, the super-admin "Create user" controller, the seeder, and the
     * super-admin toggle action. This prevents any future endpoint that does
     * `User::create($request->all())` or `$user->update($request->all())` from
     * silently granting platform-wide privilege.
     */
    protected $fillable = ['name', 'email', 'password', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: 'en';
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'team_id', 'last_active_at'])
            ->withTimestamps();
    }

    public function currentOrganization(): ?Organization
    {
        $id = session('current_organization_id');
        if (! $id) {
            return $this->organizations()->first();
        }
        return $this->organizations()->where('organizations.id', $id)->first();
    }

    public function roleIn(Organization $org): ?string
    {
        $row = $this->organizations()->where('organizations.id', $org->id)->first();
        return $row?->pivot->role;
    }
}
