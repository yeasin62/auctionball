<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /** Admin sends an invite. */
    public function store(Request $request): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');

        $this->authorizeAdmin($request, $org);

        $data = $request->validate([
            'email'   => 'required|email|max:255',
            'name'    => 'nullable|string|max:255',
            'role'    => ['required', Rule::in(['auctioneer', 'team_owner', 'viewer'])],
            'team_id' => 'nullable|integer',
        ]);

        $email = strtolower(trim($data['email']));

        // Already a member of this org? (lowercase compare — emails are
        // normalised on signup but defensive in case admin types mixed case)
        $alreadyMember = $org->users()
            ->whereRaw('LOWER(users.email) = ?', [$email])
            ->exists();
        if ($alreadyMember) {
            return back()->with('error', "{$email} is already a member of this organization.");
        }

        // A pending (unaccepted, unexpired) invitation for the same email blocks
        // the new one — otherwise admins can spam two emails to the same person.
        $pending = Invitation::where('organization_id', $org->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($pending) {
            return back()->with('error', "An invitation to {$email} is already pending. Revoke it first if you want to send a new one.");
        }

        if ($data['role'] === 'team_owner' && empty($data['team_id'])) {
            return back()->with('error', 'Pick a team for the team-owner invitation.');
        }
        // Team must belong to this org. Non-team-owner roles ignore team_id.
        $teamId = null;
        if ($data['role'] === 'team_owner' && ! empty($data['team_id'])) {
            $team = Team::where('id', $data['team_id'])->where('organization_id', $org->id)->first();
            if (! $team) return back()->with('error', 'Team not found in this organization.');
            $teamId = $team->id;
        }

        $invite = Invitation::create([
            'organization_id'    => $org->id,
            'email'              => $email,
            'name'               => $data['name'] ?? null,
            'role'               => $data['role'],
            'team_id'            => $teamId,
            'token'              => Invitation::generateToken(),
            'invited_by_user_id' => Auth::id(),
            'expires_at'         => now()->addDays(14),
        ]);

        try {
            Mail::to($invite->email)->send(new InvitationMail($invite));
        } catch (\Throwable $e) {
            // Mailer not configured / SMTP down — surface the link so the admin
            // can copy-paste. We log so prod issues are visible, but keep the
            // user flow moving (the invite row itself is created either way).
            \Illuminate\Support\Facades\Log::warning('Invitation mail failed', [
                'invitation_id' => $invite->id,
                'error'         => $e->getMessage(),
            ]);
            return back()->with('warning',
                "Invitation created but email failed to send. Copy the accept link: " . route('invite.show', $invite->token));
        }

        return back()->with('success', "Invitation sent to {$invite->email}.");
    }

    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        /** @var Organization $org */
        $org = $request->attributes->get('current_organization');
        $this->authorizeAdmin($request, $org);
        abort_if($invitation->organization_id !== $org->id, 404);
        $invitation->delete();
        return back()->with('success', 'Invitation revoked.');
    }

    /**
     * Only org_admin (or super_admin acting in this org) may manage invitations.
     * Without this check, any team_owner / viewer / auctioneer with a dashboard
     * session could invite or revoke users in their own org.
     */
    private function authorizeAdmin(Request $request, Organization $org): void
    {
        $user = $request->user();
        if ($user->is_super_admin) return;

        $pivot = $user->organizations()->where('organizations.id', $org->id)->first()?->pivot;
        if (! $pivot || $pivot->role !== 'org_admin') {
            abort(403, 'Only organization admins can manage invitations.');
        }
    }

    /** Public — invitee opens the link. */
    public function show(string $token): Response|RedirectResponse
    {
        $invite = Invitation::where('token', $token)->first();
        if (! $invite || ! $invite->isUsable()) {
            return redirect()->route('login')->with('error', 'This invitation link is no longer valid.');
        }

        $existing = User::where('email', $invite->email)->first();

        return Inertia::render('Invite/Accept', [
            'invitation' => [
                'token'   => $invite->token,
                'email'   => $invite->email,
                'name'    => $invite->name,
                'role'    => $invite->role,
                'org'     => ['name' => $invite->organization->name, 'slug' => $invite->organization->slug],
                'team'    => $invite->team ? ['id' => $invite->team->id, 'name' => $invite->team->name] : null,
                'has_account' => (bool) $existing,
            ],
        ]);
    }

    /** Public — invitee submits credentials, joins org. */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = Invitation::where('token', $token)->first();
        if (! $invite || ! $invite->isUsable()) {
            return redirect()->route('login')->with('error', 'This invitation link is no longer valid.');
        }

        $existing = User::where('email', $invite->email)->first();

        if (! $existing) {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);
        }

        DB::transaction(function () use ($invite, $existing, $request, &$user) {
            if ($existing) {
                $user = $existing;
            } else {
                $user = User::create([
                    'name'     => $request->input('name'),
                    'email'    => $invite->email,
                    'password' => Hash::make($request->input('password')),
                ]);
            }

            // Attach (or update pivot) for the org
            $org = $invite->organization;
            if ($org->users()->where('users.id', $user->id)->exists()) {
                $org->users()->updateExistingPivot($user->id, [
                    'role'           => $invite->role,
                    'team_id'        => $invite->team_id,
                    'last_active_at' => now(),
                ]);
            } else {
                $org->users()->attach($user->id, [
                    'role'           => $invite->role,
                    'team_id'        => $invite->team_id,
                    'last_active_at' => now(),
                ]);
            }

            // Link team owner pointer too
            if ($invite->team_id) {
                $invite->team?->update(['owner_user_id' => $user->id]);
            }

            $invite->update(['accepted_at' => now()]);
            session(['current_organization_id' => $org->id]);
        });

        Auth::login($user);

        Audit::log(
            'invitation.accepted',
            "{$user->name} ({$user->email}) joined as {$invite->role}" . ($invite->team_id ? " for team #{$invite->team_id}" : ''),
            ['role' => $invite->role, 'team_id' => $invite->team_id],
            $invite,
            $invite->organization_id,
        );

        return redirect()->route('dashboard.home')->with('success', 'Welcome to ' . $invite->organization->name . '!');
    }
}
