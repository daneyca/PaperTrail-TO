@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Add Office | PaperTrail')

@section('content')
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Add Office</h1>
            <p>Create an LGU office or department for user assignment and future procurement routing.</p>
        </div>
    </section>

    <section class="form-panel">
        <form method="POST" action="{{ route('admin.offices.store') }}">
            @include('admin.offices._form', ['mode' => 'create'])
        </form>
    </section>
@endsection
