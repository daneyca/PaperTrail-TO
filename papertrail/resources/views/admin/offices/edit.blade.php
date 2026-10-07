@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Edit Office | PaperTrail')

@section('content')
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Edit Office</h1>
            <p>Update office details for {{ $office->code }}.</p>
        </div>
    </section>

    <section class="form-panel">
        <form method="POST" action="{{ route('admin.offices.update', $office) }}">
            @method('PUT')
            @include('admin.offices._form', ['mode' => 'edit'])
        </form>
    </section>
@endsection
