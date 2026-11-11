@inject('navigationRepository', 'App\Services\Ontology\DestinationRepository')
@php
    $navFacets = $navigationRepository->facets();
    $navTypeLabels = [
        'Alam' => 'Nature',
        'Budaya' => 'Heritage',
        'Religi' => 'Religious',
        'Bahari' => 'Marine',
        'Kuliner' => 'Culinary',
        'Buatan' => 'Man-made',
    ];
    $activeJenis = request()->routeIs('destinations') ? request('jenis') : null;
    $availableTypes = collect($navTypeLabels)
        ->filter(fn (string $label, string $jenis): bool => ($navFacets['types'][$jenis] ?? 0) > 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sumatera Heritage & Trails')</title>
    <meta name="description" content="@yield('meta_description', 'Explore curated destinations across North Sumatra through the WayFinder tourism ontology.')">
    <meta property="og:title" content="@yield('title', 'Sumatera Heritage & Trails')">
    <meta property="og:description" content="@yield('meta_description', 'Explore curated destinations across North Sumatra through the WayFinder tourism ontology.')">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <script>
        (() => {
            const root = document.documentElement;
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            try {
                const preference = localStorage.getItem('wayfinder.theme');
                const isDark = preference === 'dark' || (preference !== 'light' && systemPrefersDark);

                root.classList.toggle('dark', isDark);
                root.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (error) {
                root.classList.toggle('dark', systemPrefersDark);
                root.style.colorScheme = systemPrefersDark ? 'dark' : 'light';
                console.warn('WayFinder: saved theme preference could not be restored.', error);
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fonts
    @stack('styles')
</head>
<body class="bg-brand-light text-brand-text font-sans antialiased min-h-screen flex flex-col">

    <div id="global-progress-bar" aria-hidden="true"><div id="global-progress-bar-fill"></div></div>

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[10000] focus:rounded-lg focus:bg-brand-dark focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-white">Skip to content</a>

    <!-- NAVBAR -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-3">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-brand-primary font-serif font-bold text-base sm:text-xl min-w-0">
                <svg class="w-8 h-8 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                <span class="hidden sm:inline truncate">Sumatera Heritage &amp; Trails</span>
                <span class="sm:hidden truncate">WayFinder</span>
            </a>

            <!-- Menu Tengah -->
            <nav class="hidden lg:flex items-center gap-8 text-sm font-medium text-gray-600" aria-label="Main navigation">
                <a href="{{ route('home') }}" class="hover:text-brand-primary {{ request()->routeIs('home') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Dashboard</a>

                <a href="{{ route('destinations') }}" class="hover:text-brand-primary {{ request()->routeIs('destinations', 'destination.detail') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Destinations</a>

                <!-- Dropdown Jenis Wisata (Categories) -->
                <div class="relative group">
                    <button type="button" aria-haspopup="true" class="flex items-center gap-1 hover:text-brand-primary focus:outline-none {{ in_array($activeJenis, $availableTypes->keys()->all(), true) ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">
                        Categories
                        <svg class="w-4 h-4 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div class="absolute left-0 top-full pt-3 hidden group-hover:block group-focus-within:block">
                        <div class="w-56 bg-white border border-gray-200 rounded-xl shadow-lg py-2">
                            @foreach ($availableTypes as $jenis => $label)
                                <a href="{{ route('destinations', ['jenis' => $jenis]) }}" class="flex items-center justify-between px-4 py-2 hover:bg-brand-light hover:text-brand-primary {{ $activeJenis === $jenis ? 'bg-brand-light text-brand-primary font-semibold' : '' }}">
                                    {{ $label }}
                                    <span class="text-xs text-brand-muted">{{ number_format($navFacets['types'][$jenis]) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <a href="{{ route('map') }}" class="hover:text-brand-primary {{ request()->routeIs('map') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Map View</a>
            </nav>

            <!-- Tombol Kanan -->
            <div class="flex items-center gap-2 sm:gap-4 flex-shrink-0">
                <button id="btn-theme-toggle" type="button" class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-primary" aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
                    <svg data-theme-icon="moon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg data-theme-icon="sun" class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                </button>
                <button id="btn-chatbot" type="button" class="bg-brand-accent hover:bg-orange-800 text-white px-3 sm:px-5 py-2.5 rounded-full text-sm font-semibold transition flex items-center gap-2" aria-haspopup="dialog" aria-expanded="false" aria-controls="wayfinder-chatbot">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    <span class="hidden sm:inline">Chatbot</span>
                </button>

                <button id="btn-mobile-menu" type="button" class="lg:hidden w-10 h-10 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 flex items-center justify-center" aria-controls="mobile-menu" aria-expanded="false" aria-label="Open navigation menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <!-- MOBILE MENU -->
        <div id="mobile-menu" class="hidden lg:hidden border-t border-gray-200 bg-white" aria-hidden="true">
            <nav class="max-w-7xl mx-auto px-4 sm:px-6 py-4 space-y-1 text-sm font-medium text-gray-700" aria-label="Mobile navigation">
                <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2 hover:bg-brand-light {{ request()->routeIs('home') ? 'bg-brand-light text-brand-primary' : '' }}">Dashboard</a>
                <a href="{{ route('destinations') }}" class="block rounded-lg px-3 py-2 hover:bg-brand-light {{ request()->routeIs('destinations', 'destination.detail') ? 'bg-brand-light text-brand-primary' : '' }}">Destinations</a>
                <a href="{{ route('map') }}" class="block rounded-lg px-3 py-2 hover:bg-brand-light {{ request()->routeIs('map') ? 'bg-brand-light text-brand-primary' : '' }}">Map View</a>

                <p class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-400">Categories</p>
                @foreach ($availableTypes as $jenis => $label)
                    <a href="{{ route('destinations', ['jenis' => $jenis]) }}" class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-brand-light {{ $activeJenis === $jenis ? 'bg-brand-light text-brand-primary' : '' }}">
                        <span>{{ $label }}</span>
                        <span class="text-xs text-gray-400">{{ number_format($navFacets['types'][$jenis]) }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main id="main-content" class="flex-grow">
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="bg-brand-dark text-white pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-12 border-b border-gray-700 pb-12">
            <!-- Kolom 1 -->
            <div>
                <div class="flex items-center gap-2 font-serif font-bold text-xl mb-4 text-[#f0dfc8]">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                    Sumatera Heritage
                </div>
                <p class="text-sm text-gray-400 leading-relaxed">
                    Preserving Batak heritage, rainforest ecosystems, and the volcanic landscapes of North Sumatra through regenerative eco-travel.
                </p>
            </div>
            <!-- Kolom 2 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Destinations</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ route('destinations', ['q' => 'Toba']) }}" class="hover:text-white">Lake Toba Caldera</a></li>
                    <li><a href="{{ route('destinations', ['q' => 'Bukit Lawang']) }}" class="hover:text-white">Bukit Lawang Leuser</a></li>
                    <li><a href="{{ route('destinations', ['q' => 'Samosir']) }}" class="hover:text-white">Samosir Heritage</a></li>
                    @if (($navFacets['types']['Kuliner'] ?? 0) > 0)
                        <li><a href="{{ route('destinations', ['jenis' => 'Kuliner']) }}" class="hover:text-white">Culinary Trails</a></li>
                    @endif
                </ul>
            </div>
            <!-- Kolom 3 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Advisories & Protocols</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ route('destinations', ['provinsi' => 'Sumatera Utara']) }}" class="hover:text-white">North Sumatra Destination Guide</a></li>
                    <li><a href="{{ route('destinations', ['jenis' => 'Alam']) }}" class="hover:text-white">Responsible Ecotourism</a></li>
                    <li><a href="{{ route('map') }}" class="hover:text-white">Interactive Map Atlas</a></li>
                    <li><a href="{{ route('home') }}" class="hover:text-white">Ontology Dashboard</a></li>
                </ul>
            </div>
            <!-- Kolom 4 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Emergency & Assistance</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="tel:112" class="text-orange-400 hover:text-orange-300 underline underline-offset-4">National Emergency (112)</a></li>
                    <li><a href="https://basarnas.go.id/" target="_blank" rel="noopener" class="hover:text-white">Basarnas Search & Rescue ↗</a></li>
                    <li>Gunung Leuser Ranger Post</li>
                    <li><a href="{{ route('destinations') }}" class="hover:text-white">Medan Visitor Information</a></li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500 gap-4">
            <p>© {{ date('Y') }} Sumatera Heritage &amp; Trails. Tourism ontology project.</p>
            <div class="flex flex-wrap justify-center gap-6">
                <a href="{{ route('destinations') }}" class="hover:text-white">Destinations</a>
                <a href="{{ route('map') }}" class="hover:text-white">Map Atlas</a>
                <a href="{{ route('home') }}" class="hover:text-white">Dashboard</a>
            </div>
        </div>
    </footer>

    <!-- CHATBOT DIALOG -->
    <div
        id="wayfinder-chatbot"
        role="dialog"
        aria-modal="true"
        aria-labelledby="chatbot-title"
        aria-hidden="true"
        data-chat-url="{{ route('chatbot.send') }}"
        class="fixed inset-0 z-[9999] hidden h-[100dvh] w-screen flex-col overflow-hidden bg-white text-gray-900 shadow-2xl"
    >
        <!-- HEADER -->
        <header id="chatbot-header" class="flex shrink-0 items-center justify-between border-b border-gray-200 bg-white px-4 py-3 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#92400e] text-white" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 id="chatbot-title" class="truncate text-sm font-bold text-gray-900">WayFinder AI</h2>
                    <p class="text-xs text-gray-500">Pemandu wisata berdasarkan dataset Sumatera Utara</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button id="btn-resize-chatbot" type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#92400e]" aria-label="Minimize chatbot" title="Minimize chatbot">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>
                </button>
                <button id="btn-close-chatbot" type="button" class="flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#92400e]" aria-label="Close chatbot" title="Close chatbot">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </header>

        <!-- KONTEN UTAMA -->
        <main id="chatbot-body-container" class="relative flex min-h-0 flex-1 flex-col bg-white">
            <section id="chatbot-empty-state" class="flex min-h-0 flex-1 flex-col items-center justify-center px-4 py-8 sm:px-8" aria-labelledby="chatbot-greeting">
                <div class="w-full max-w-3xl">
                    <h3 id="chatbot-greeting" data-chatbot-greeting class="mb-8 min-h-10 text-center text-2xl font-semibold tracking-tight text-gray-800 sm:text-3xl"></h3>
                    <form id="chat-form" class="flex w-full items-end gap-3 rounded-3xl border border-gray-200 bg-white p-3 shadow-sm transition focus-within:border-[#92400e] focus-within:ring-2 focus-within:ring-[#92400e]/15">
                        <textarea id="chat-input" maxlength="800" rows="1" placeholder="Tanyakan tentang destinasi Sumatera Utara..." class="max-h-40 min-h-12 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm text-gray-800 outline-none placeholder:text-gray-400 focus:ring-0" autocomplete="off" aria-label="Pesan untuk WayFinder AI" aria-describedby="chatbot-status"></textarea>
                        <button id="btn-send-chat" type="submit" disabled class="flex h-11 shrink-0 items-center justify-center rounded-full bg-[#92400e] px-5 text-sm font-bold text-white transition hover:bg-[#7a350c] focus:outline-none focus:ring-2 focus:ring-[#92400e] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-35" aria-label="Kirim pesan">
                            Kirim
                        </button>
                    </form>
                </div>
            </section>

            <section id="chatbot-conversation" class="hidden min-h-0 flex-1 flex-col">
                <div id="chat-messages" class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-4 py-6 sm:px-8" role="log" aria-live="polite" aria-relevant="additions">
                    <div id="chat-message-list" class="mx-auto flex w-full max-w-3xl flex-col gap-5"></div>
                </div>
                <div id="chatbot-loading" class="mx-auto hidden w-full max-w-3xl px-4 pb-4 text-sm text-gray-500" aria-hidden="true">
                    <span class="inline-flex animate-pulse items-center gap-2 rounded-full bg-gray-100 px-4 py-2">
                        <span class="h-2 w-2 rounded-full bg-[#92400e]"></span>
                        WayFinder AI sedang memproses data...
                    </span>
                </div>
                <div id="chat-input-area" class="shrink-0 border-t border-gray-200 bg-white px-4 py-4 sm:px-8">
                    <div id="chat-input-dock" class="mx-auto w-full max-w-3xl"></div>
                    <p class="mx-auto mt-2 max-w-3xl text-center text-[11px] text-gray-400">Jawaban AI merujuk pada informasi yang tersedia di dataset destinasi.</p>
                </div>
            </section>
        </main>
        <p id="chatbot-status" class="sr-only" role="status" aria-live="polite"></p>
    </div>

    @stack('scripts')
</body>
</html>
