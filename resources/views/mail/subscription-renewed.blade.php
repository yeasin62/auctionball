<x-mail::message>
# {{ __('messages.mail.renewed.heading') }}

{{ __('messages.mail.renewed.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.renewed.body_main', ['plan' => ucfirst($sub->plan)]) !!}

- {{ __('messages.mail.renewed.amount') }}: **{{ $amount }} {{ $sub->currency }}**
- {{ __('messages.mail.renewed.next_billing') }}: **{{ $sub->current_period_end->format('F j, Y') }}**
- {{ __('messages.mail.renewed.provider') }}: {{ ucfirst($sub->provider) }}

<x-mail::button :url="route('dashboard.billing.index')">
{{ __('messages.mail.renewed.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
