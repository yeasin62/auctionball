<x-mail::message>
# {{ $autoRenew ? __('messages.mail.reminder.heading_auto') : __('messages.mail.reminder.heading_manual') }}

{{ __('messages.mail.reminder.body_intro', ['org' => $org->name]) }}

@if ($autoRenew)
{!! __('messages.mail.reminder.auto_body', [
    'plan'     => ucfirst($sub->plan),
    'date'     => $sub->current_period_end->format('F j, Y'),
    'days'     => trans_choice(':count day|:count days', $days, ['count' => $days]),
    'provider' => ucfirst($sub->provider),
    'amount'   => $amount,
]) !!}

{{ __('messages.mail.reminder.auto_followup') }}
@else
{!! __('messages.mail.reminder.manual_body', [
    'plan' => ucfirst($sub->plan),
    'date' => $sub->current_period_end->format('F j, Y'),
    'days' => trans_choice(':count day|:count days', $days, ['count' => $days]),
]) !!}
@endif

<x-mail::button :url="$billingUrl">
{{ __('messages.mail.reminder.cta') }}
</x-mail::button>

{{ __('messages.mail.thanks_signoff') }}<br>
{{ __('messages.mail.team_signoff') }}
</x-mail::message>
