<x-mail::message>
# {{ __('messages.mail.invitation.heading', ['org' => $org->name]) }}

{!! __('messages.mail.invitation.invited_by', [
    'name'        => $invitedBy?->name ?? __('messages.mail.team_signoff'),
    'org'         => $org->name,
    'role'        => str_replace('_', ' ', $role),
    'teamSuffix'  => $team ? __('messages.mail.invitation.team_suffix', ['team' => $team->name]) : '',
]) !!}

@if($role === 'team_owner')
{{ __('messages.mail.invitation.role_team_owner_blurb') }}
@elseif($role === 'auctioneer')
{{ __('messages.mail.invitation.role_auctioneer_blurb') }}
@endif

<x-mail::button :url="$acceptUrl">
{{ __('messages.mail.invitation.cta_accept') }}
</x-mail::button>

{!! __('messages.mail.invitation.expires_note', ['date' => $expiresAt]) !!}

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
