@extends('layouts.landing')

@section('title', 'PaperTrail | AI-Assisted Procurement Management and Information System')

@section('content')
    <section class="pt-hero">
        <div class="pt-hero-overlay"></div>
        <div class="pt-hero-inner">
            <div class="pt-hero-copy">
                <p class="pt-eyebrow">Internal LGU Procurement Document Portal</p>
                <h1>PaperTrail</h1>
                <h2>AI-Assisted Procurement Management and Information System</h2>
                <p>
                    A centralized platform for tracking, routing, reviewing, and managing LGU procurement documents.
                </p>
                <div class="pt-hero-actions">
                    @guest
                        <a class="pt-button pt-button-primary" href="{{ route('login') }}">Login</a>
                    @endguest
                    @auth
                        <a class="pt-button pt-button-primary" href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}">Dashboard</a>
                    @endauth
                    <a class="pt-button pt-button-ghost" href="#about">Learn More</a>
                </div>
            </div>

            <aside class="pt-portal-card" id="portal" aria-label="Procurement portal summary">
                @if (file_exists(public_path('images/logos/lgu-logo.png')))
                    <img src="{{ asset('images/logos/lgu-logo.png') }}" alt="LGU Logo" class="pt-lgu-seal">
                @else
                    <span class="pt-lgu-seal-fallback">LGU</span>
                @endif
                <div>
                    <span>Municipality of Tomas Oppus</span>
                    <strong>Procurement Portal</strong>
                    <p>Document routing, AI checks, audit trail, and e-signature support in one workspace.</p>
                </div>
            </aside>
        </div>
    </section>

    <section class="pt-section" id="about">
        <div class="pt-section-heading">
            <p class="pt-eyebrow">System Purpose</p>
            <h2>Built for transparent procurement document control</h2>
            <p>PaperTrail supports LGU offices with a clearer way to prepare, route, monitor, and review procurement records.</p>
        </div>
    </section>

    <section class="pt-section pt-features-section" id="features">
        <div class="pt-feature-grid">
            @foreach ([
                ['Document Tracking', 'Monitor PPMP, APP, PR, RFQ, Abstract, PO, and inspection records from creation to completion.', '01'],
                ['Workflow Routing', 'Guide procurement documents through office review, approval, return, and release stages.', '02'],
                ['AI-Assisted Processing', 'Prepare the system for completeness checks, classification, metadata extraction, and delay insights.', '03'],
                ['Electronic Signature', 'Support controlled document signing for authorized users and approval stages.', '04'],
                ['Audit Trail', 'Record user actions and document movement for accountability and compliance review.', '05'],
                ['Smart Procurement Chatbot', 'Provide a guided assistant interface for workflow help and future AI document tracking.', '06'],
            ] as [$title, $copy, $number])
                <article class="pt-feature-card">
                    <span>{{ $number }}</span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $copy }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
