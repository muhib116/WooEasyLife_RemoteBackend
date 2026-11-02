@php
    $isEn = str_starts_with((string) ($seo['html_lang'] ?? 'bn-BD'), 'en');
    $portrait = '/images/seo/about/muhibbullah-ansary.jpg';
    $products = [
        ['href' => '/', 'label' => 'WooEasyLife'],
        ['href' => '/courier-check-plugin', 'label' => 'EasyLife Courier Check (free WordPress plugin)'],
        ['href' => \App\Services\LandingSettingsService::PUBLIC_PLAY_STORE_URL, 'label' => 'Woo Easy Life Android app'],
        ['href' => 'https://courierfraudbd.com/', 'label' => 'Courier Fraud Check BD'],
        ['href' => 'https://webguidebd.site/', 'label' => 'Web Guide BD'],
    ];
    $profileLabels = [
        'https://www.linkedin.com/in/dev-muhib' => 'LinkedIn',
        'https://www.facebook.com/muhib116' => 'Facebook',
        'https://www.instagram.com/muhibbullah611/' => 'Instagram',
        'https://www.youtube.com/channel/UCiEVvaV8O6VBKzU3d7ezKGg' => 'YouTube',
        'https://www.tiktok.com/@muhibbullah116' => 'TikTok',
        'https://medium.com/@dev.muhibbullah' => 'Medium',
        'https://www.pinterest.com/devmuhibbullah/' => 'Pinterest',
        'https://github.com/muhib116' => 'GitHub',
        'https://dev.to/muhib116' => 'DEV',
        'https://profiles.wordpress.org/muhibbullah/' => 'WordPress.org',
        'https://webguidebd.site/muhibbullah-ansary/' => 'Web Guide BD',
        'https://courierfraudbd.com/about/' => 'Courier Fraud Check BD',
    ];
    $profiles = config('seo.organization.founder_same_as', []);
@endphp
<p>{{ $isEn ? 'Founder of WPSaleHub · Creator of WooEasyLife' : 'WPSaleHub-এর প্রতিষ্ঠাতা · WooEasyLife-এর নির্মাতা' }}</p>
<figure>
    <img
        src="{{ $portrait }}"
        alt="Muhibbullah Ansary (মুহিব্বুল্লাহ আনসারী), Founder of WPSaleHub"
        width="1200"
        height="1200"
        loading="eager"
        decoding="async"
        fetchpriority="high"
    />
</figure>
<p>
    @if ($isEn)
        Muhibbullah Ansary (মুহিব্বুল্লাহ আনসারী, also called Muhib) is a web developer in Bangladesh and the founder of WPSaleHub. He built WooEasyLife for WooCommerce and cash on delivery sellers in Bangladesh, with courier history check, fake order protection, and courier auto entry. He also runs the free EasyLife Courier Check plugin, courierfraudbd.com, and Web Guide BD, a Bangla learning site.
    @else
        মুহিব্বুল্লাহ আনসারী (Muhibbullah Ansary, অনেকে ডাকেন Muhib) বাংলাদেশের একজন web developer এবং WPSaleHub-এর প্রতিষ্ঠাতা। তিনি বাংলাদেশের WooCommerce ও cash on delivery seller-দের জন্য WooEasyLife বানিয়েছেন, যেখানে courier history check, fake order protection আর courier auto entry আছে। তিনি ফ্রি EasyLife Courier Check plugin, courierfraudbd.com আর বাংলা শেখার সাইট Web Guide BD-ও চালান।
    @endif
</p>
<section>
    <h2>{{ $isEn ? 'Products' : 'যা বানিয়েছি' }}</h2>
    <ul>
        @foreach ($products as $product)
            <li><a href="{{ $product['href'] }}">{{ $product['label'] }}</a></li>
        @endforeach
    </ul>
</section>
<section>
    <h2>Profiles</h2>
    <ul>
        @foreach ($profiles as $profileUrl)
            @if (is_string($profileUrl) && $profileUrl !== '')
                <li><a href="{{ $profileUrl }}" rel="me">{{ $profileLabels[$profileUrl] ?? $profileUrl }}</a></li>
            @endif
        @endforeach
    </ul>
</section>
