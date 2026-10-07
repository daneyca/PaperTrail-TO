@extends(request()->boolean('modal') ? 'layouts.modal' : 'layouts.dashboard')

@section('title', 'Edit Account | PaperTrail')

@section('content')
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Edit Account</h1>
            <p>Update account details for {{ $user->user_id }}.</p>
        </div>
    </section>

    <section class="form-panel">
        <form
            method="POST"
            action="{{ route('admin.users.update', $user) }}"
            data-confirm-status-change="true"
            data-current-status="{{ $user->status }}"
        >
            @method('PUT')
            @include('admin.users._form', ['mode' => 'edit'])
        </form>
    </section>
@endsection
