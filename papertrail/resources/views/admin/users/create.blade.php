@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Add Account | PaperTrail')

@section('content')
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Add Account</h1>
            <p>Create a fixed internal User ID for authorized LGU procurement users.</p>
        </div>
    </section>

    <section class="form-panel">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @include('admin.users._form', ['mode' => 'create'])
        </form>
    </section>
@endsection
