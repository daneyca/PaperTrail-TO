@extends('layouts.landing')

@section('title', 'PaperTrail | AI-Assisted Procurement Management')

@section('content')
    <section class="pt-saas-hero" id="home">
        <div class="pt-saas-hero-inner">
            <div class="pt-saas-hero-copy">
                <p class="pt-eyebrow">Municipality of Tomas Oppus</p>
                <h1>PaperTrail</h1>
                <h2>AI-Assisted Procurement Management System</h2>
                <p>A centralized workspace for LGU procurement document tracking, routing, review, and approval.</p>

                <div class="pt-saas-actions" aria-label="Landing page actions">
                    @guest
                        <a class="pt-button pt-button-primary" href="{{ route('login') }}">Access Portal</a>
                    @endguest
                    @auth
                        <a class="pt-button pt-button-primary" href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}">Open Dashboard</a>
                    @endauth
                    <a class="pt-button pt-button-ghost" href="#features">Learn More</a>
                </div>
            </div>

            <div class="pt-product-preview" aria-label="PaperTrail product preview">
                <x-floating-documents variant="public" />

                <div class="pt-preview-shell">
                    <div class="pt-preview-topbar">
                        <span></span>
                        <strong>Procurement Workspace</strong>
                        <em>Live</em>
                    </div>

                    <div class="pt-preview-grid">
                        <article class="pt-preview-card">
                            <span>Documents</span>
                            <strong>128</strong>
                            <small>Tracked records</small>
                        </article>
                        <article class="pt-preview-card">
                            <span>Approvals</span>
                            <strong>24</strong>
                            <small>Pending review</small>
                        </article>
                        <article class="pt-preview-card pt-preview-card--wide">
                            <span>Workflow Status</span>
                            <strong>PR routed to BAC Secretariat</strong>
                            <div class="pt-preview-progress">
                                <i></i>
                                <i></i>
                                <i></i>
                                <i></i>
                            </div>
                        </article>
                        <article class="pt-preview-card pt-preview-card--gold">
                            <span>AI Insights</span>
                            <strong>Completeness checks ready</strong>
                            <small>Rules-based guidance</small>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pt-saas-section" id="features">
        <div class="pt-saas-section-heading">
            <p class="pt-eyebrow">Enterprise Procurement Tools</p>
            <h2>Everything needed to manage LGU procurement records</h2>
            <p>Focused modules for document movement, review, signatures, audit visibility, and AI-assisted guidance.</p>
        </div>

        <div class="pt-saas-feature-grid">
            @foreach ([
                ['Document Tracking', 'Monitor PPMP, APP, PR, RFQ, Abstract, PO, and inspection records from creation to completion.', 'DT'],
                ['Workflow Routing', 'Guide documents through office review, approval, return, and release stages.', 'WR'],
                ['AI-Assisted Processing', 'Use configured rules for completeness, route validation, metadata, and delay insights.', 'AI'],
                ['Electronic Signature', 'Support controlled signing steps for authorized document approvers.', 'ES'],
                ['Audit Trail', 'Record account activity and document movement for accountability review.', 'AT'],
                ['Smart Procurement Chatbot', 'Ask workflow questions and get guided procurement system help.', 'SC'],
            ] as [$title, $copy, $icon])
                <article class="pt-saas-feature-card">
                    <span>{{ $icon }}</span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $copy }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pt-saas-section pt-saas-workflow" id="workflow">
        <div>
            <p class="pt-eyebrow">Workflow</p>
            <h2>From planning to inspection, each document keeps its place.</h2>
            <p>PaperTrail gives offices a shared view of where a procurement record is, who currently holds it, and what action should happen next.</p>
        </div>

        <div class="pt-workflow-steps">
            @foreach (['Create', 'Submit', 'Review', 'Approve', 'Complete'] as $step)
                <span>{{ $step }}</span>
            @endforeach
        </div>
    </section>

    <section class="pt-saas-section pt-ai-section" id="ai-assistance">
        <div class="pt-ai-panel">
            <p class="pt-eyebrow">AI Assistance</p>
            <h2>Built to support decisions, not replace approvals.</h2>
            <p>AI tools assist with completeness checks, routing guidance, metadata classification, delay risk, and procurement summaries while official actions remain controlled by authorized users.</p>
        </div>
    </section>

    <section class="pt-saas-section pt-about-section" id="about">
        <div>
            <p class="pt-eyebrow">About</p>
            <h2>Professional procurement management for LGU Tomas Oppus.</h2>
            <p>PaperTrail centralizes procurement document tracking, routing, review, approval support, e-signature, notifications, and audit records in one secure portal.</p>
        </div>
    </section>
@endsection
