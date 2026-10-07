@extends('layouts.dashboard')

@section('title', 'Create PPMP | PaperTrail')

@section('content')
    <form
        method="POST"
        action="{{ route('head-office.ppmp.store') }}"
        class="ppmp-builder-form"
    >
        @include('head-office.ppmp._form', ['useDocumentCreateFlow' => false])
    </form>
@endsection
