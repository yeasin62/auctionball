<x-mail::message>
# {{ __('messages.mail.renewal_due.heading') }}

{{ __('messages.mail.renewal_due.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.renewal_due.body_main', [
    'plan'     => ucfirst($sub->plan),
    'provider' => ucfirst($sub->provider),
]) !!}

<x-mail::button :url="$billingUrl">
{{ __('messages.mail.renewal_due.cta') }}
</x-mail::button>

{!! __('messages.mail.renewal_due.grace_warning', ['days' => \App\Models\Subscription::GRACE_DAYS]) !!}

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
