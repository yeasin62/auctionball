<x-mail::message>
# {{ __('messages.mail.payment_approved.heading') }}

{{ __('messages.mail.payment_approved.body_intro', ['org' => $org->name]) }}

{!! __('messages.mail.payment_approved.body_main', ['plan' => ucfirst($txn->plan)]) !!}

- {{ __('messages.mail.payment_approved.amount') }}: **{{ $amount }}**
- {{ __('messages.mail.payment_approved.trx_id') }}: `{{ $txn->provider_txn_id }}`
- {{ __('messages.mail.payment_approved.activated_at') }}: **{{ $txn->completed_at?->format('F j, Y · H:i') }}**

{{ __('messages.mail.payment_approved.unlock_note') }}

<x-mail::button :url="route('dashboard.billing.index')">
{{ __('messages.mail.payment_approved.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
