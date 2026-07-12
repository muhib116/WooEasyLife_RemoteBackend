<x-mail::message>
# New subscription request

A customer submitted a subscription purchase request from the pricing page.

**Inquiry #{{ $inquiry->id }}** · {{ $inquiry->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}

| Field | Details |
|:--|:--|
| Plan | {{ $planTitle ?: '—' }} |
| Domain | {{ $inquiry->domain ?: '—' }} |
| Name | {{ $inquiry->customer_name ?: '—' }} |
| Email | {{ $inquiry->email ?: '—' }} |
| Mobile | {{ $inquiry->contact_number ?: '—' }} |
| WhatsApp | {{ $inquiry->whatsapp_number ?: '—' }} |
| Address | {{ $inquiry->address ?: '—' }} |
| Amount | ৳{{ number_format((float) $inquiry->total_amount, 2) }} |
| Payment method | {{ $inquiry->transaction_method ?: '—' }} |
| Transaction ID | {{ $inquiry->transaction_id ?: '—' }} |
| Sender number | {{ $inquiry->account_number ?: '—' }} |
| Status | {{ $inquiry->status }} |

@if ($inquiry->note)
**Note**

{{ $inquiry->note }}
@endif

<x-mail::button :url="$adminOrdersUrl">
Open orders admin
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
