@if ($errors->any())
    <section class="session-alert session-alert-error no-print" role="alert" aria-label="Abstract validation errors">
        <strong>{{ old('save_action') === 'submit' ? 'Abstract was not submitted.' : 'Abstract was not saved.' }}</strong>
        <p>Correct the following and try again. Your entries have been kept.</p>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif
