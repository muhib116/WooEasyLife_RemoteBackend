<x-mail::message>
# Your WooEasyLife account is ready

সালাম {{ $merchant->name }},

আপনার সাবস্ক্রিপশন রিকোয়েস্ট যাচাই হয়ে গেছে এবং অ্যাকাউন্ট প্রস্তুত।

| Field | Details |
|:--|:--|
| Plan | {{ $planTitle ?: '—' }} |
| Website | {{ $inquiry->domain ?: '—' }} |
| Login email | {{ $merchant->email }} |

@if ($userCreated)
**Temporary password:** your mobile number (`{{ $inquiry->contact_number }}`)

First login-এ নতুন পাসওয়ার্ড সেট করতে হবে।
@else
আপনার আগের মার্চেন্ট অ্যাকাউন্টে প্ল্যান ও লাইসেন্স যোগ করা হয়েছে। পাসওয়ার্ড পরিবর্তন করা হয়নি।
@endif

Plugin activate করতে Admin প্যানেল থেকে লাইসেন্স কী নিন — সাপোর্ট টিমও হেল্প করতে পারবে।

<x-mail::button :url="$loginUrl">
Merchant login
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
