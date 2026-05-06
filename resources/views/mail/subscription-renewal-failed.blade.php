<x-mail::message>
# {{ __('messages.mail.renewal_failed.heading') }}

{{ __('messages.mail.renewal_failed.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.renewal_failed.body_main', [
    'plan'     => ucfirst($sub->plan),
    'provider' => ucfirst($sub->provider),
]) !!}

- {{ __('messages.mail.renewal_failed.attempt') }}: **{{ $attempt }} / {{ $maxAttempts }}**
- {{ __('messages.mail.renewal_failed.reason') }}: `{{ $reason }}`
@if($graceUntil)
- {{ __('messages.mail.renewal_failed.grace_until') }}: **{{ $graceUntil }}**
@endif

{{ __('messages.mail.renewal_failed.will_retry') }}

<x-mail::button :url="$billingUrl">
{{ __('messages.mail.renewal_failed.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
