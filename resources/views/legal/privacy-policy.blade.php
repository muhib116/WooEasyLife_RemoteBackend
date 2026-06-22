@extends('layouts.legal')

@section('content')
    <div class="meta">
        <span>Effective: {{ $effectiveDate }}</span>
        <span>Last updated: {{ $lastUpdated }}</span>
    </div>

    <article class="legal-content">
        {!! $content !!}
    </article>
@endsection
