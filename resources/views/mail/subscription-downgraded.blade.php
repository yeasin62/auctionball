<x-mail::message>
# {{ __('messages.mail.downgraded.heading') }}

{{ __('messages.mail.downgraded.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.downgraded.body_main', ['max' => \App\Models\Subscription::MAX_ATTEMPTS]) !!}

- {{ __('messages.mail.downgraded.last_failure') }}: `{{ $reason }}`

{{ __('messages.mail.downgraded.data_safe') }}

<x-mail::button :url="$billingUrl">
{{ __('messages.mail.downgraded.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
