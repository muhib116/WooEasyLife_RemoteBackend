<x-mail::message>
# Hello {{ $merchantName }},

@if ($domain)
**Website:** {{ $domain }}
@endif

@if ($severity === 'danger')
**Urgent:** {{ $message }}
@elseif ($severity === 'warning')
**Reminder:** {{ $message }}
@else
{{ $message }}
@endif

<x-mail::button :url="$portalUrl">
View Billing & Renew
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
