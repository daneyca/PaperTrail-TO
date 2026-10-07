<header class="pt-public-navbar pt-saas-navbar" id="home">
    <div class="pt-public-navbar-inner">
        <x-logo />

        <button class="pt-public-nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-public-nav-toggle>
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="pt-public-nav" aria-label="Public navigation" data-public-nav>
            <a href="{{ url('/') }}#home">Home</a>
            <a href="{{ url('/') }}#features">Features</a>
            <a href="{{ url('/') }}#workflow">Workflow</a>
            <a href="{{ url('/') }}#ai-assistance">AI Assistance</a>
            <a href="{{ url('/') }}#about">About</a>

            @guest
                <a class="pt-public-cta" href="{{ route('login') }}">Login</a>
            @endguest

            @auth
                <a class="pt-public-cta" href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/dashboard') }}">Dashboard</a>
            @endauth
        </nav>
    </div>
</header>
