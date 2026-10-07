@extends('layouts.dashboard')

@section('title', $group['title'] . ' | PaperTrail')

@section('content')
    @include('admin.settings.partials.console')
@endsection
