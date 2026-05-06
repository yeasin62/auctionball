<x-mail::message>
# {{ __('messages.mail.payment_rejected.heading') }}

{{ __('messages.mail.payment_rejected.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.payment_rejected.body_main', ['plan' => ucfirst($txn->plan)]) !!}

- {{ __('messages.mail.payment_rejected.amount') }}: **{{ $amount }}**
- {{ __('messages.mail.payment_rejected.trx_id') }}: `{{ $txn->provider_txn_id }}`
@if ($reason)
- {{ __('messages.mail.payment_rejected.reason') }}: **{{ $reason }}**
@endif

{{ __('messages.mail.payment_rejected.help') }}

<x-mail::button :url="route('dashboard.billing.index')">
{{ __('messages.mail.payment_rejected.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
