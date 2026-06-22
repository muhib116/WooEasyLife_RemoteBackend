@extends('layouts.legal')

@section('hero')
    <div class="page-hero">
        <div class="page-hero__inner">
            <span class="page-hero__eyebrow">Legal · Privacy</span>
            <h1 class="page-hero__title">Privacy Policy</h1>
            <p class="page-hero__lead">
                How WooEasyLife collects, uses, and protects data when you manage your WooCommerce store from our Android app.
            </p>
            <div class="meta-badges">
                <span class="meta-badge"><strong>Effective</strong> {{ $effectiveDate }}</span>
                <span class="meta-badge"><strong>Updated</strong> {{ $lastUpdated }}</span>
                <span class="meta-badge"><strong>App</strong> com.wooeasylife.woo_easy_life</span>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <article class="legal-content">
        {!! $content !!}
    </article>

    <div class="contact-card">
        <p class="contact-card__title">Questions about this policy?</p>
        <p class="contact-card__sub">Reach out to the WPSaleHub team anytime.</p>
        <div class="contact-links">
            <a class="contact-link" href="mailto:{{ $contactEmail }}">
                <svg class="contact-link__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </svg>
                {{ $contactEmail }}
            </a>
            <a class="contact-link" href="{{ $contactWebsite }}" target="_blank" rel="noopener noreferrer">
                <svg class="contact-link__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5a17.92 17.92 0 01-8.716-2.247m0 0A8.966 8.966 0 013 12c0-1.264.26-2.47.732-3.565" />
                </svg>
                {{ $contactWebsite }}
            </a>
        </div>
    </div>
@endsection
