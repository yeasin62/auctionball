<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Implementing HasLocalePreference makes Mail::to($user) auto-render
 * mailables in $user->locale — no per-call ->locale() needed.
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
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

    /**
     * The user's active organization for this request.
     *
     * Falls back to alphabetical-first org when no session id is set — MUST
     * match HandleInertiaRequests::share() so the sidebar (rendered from
     * shared props) and the page controller (rendered from request
     * attributes) agree on which org is "current". Without matching orders,
     * a user with multiple orgs sees one org in the sidebar but data from
     * a different org in the page — most visibly: seasons list showing
     * empty when the chosen org has none, while the user is actually
     * looking at the wrong org.
     *
     * Also writes the resolved id back to the session so subsequent requests
     * are immediately consistent — no flicker on first load after login.
     */
    public function currentOrganization(): ?Organization
    {
        $id = session('current_organization_id');
        if ($id) {
            $org = $this->organizations()->where('organizations.id', $id)->first();
            if ($org) return $org;
            // Stale session id (user was removed from that org, or it was
            // deleted). Fall through to the default pick below.
        }
        $org = $this->organizations()->orderBy('name')->first();
        if ($org) {
            session(['current_organization_id' => $org->id]);
        }
        return $org;
    }

    public function roleIn(Organization $org): ?string
    {
        $row = $this->organizations()->where('organizations.id', $org->id)->first();
        return $row?->pivot->role;
    }
}
