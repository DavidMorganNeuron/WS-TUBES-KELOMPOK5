<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sumatra Heritage & Trails</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-brand-light text-brand-text font-sans antialiased min-h-screen flex flex-col">

    <!-- NAVBAR -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-brand-primary font-serif font-bold text-xl">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                Sumatra Heritage & Trails
            </a>

            <!-- Menu Tengah -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
                <a href="{{ route('home') }}" class="hover:text-brand-primary {{ request()->routeIs('home') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Dashboard</a>

                <a href="{{ route('destinations') }}" class="hover:text-brand-primary {{ request()->routeIs('destinations', 'destination.detail') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Destinations</a>

                @php
                    $typeLabels = [
                        'Alam' => 'Nature',
                        'Budaya' => 'Heritage',
                        'Religi' => 'Religious',
                        'Bahari' => 'Marine',
                        'Kuliner' => 'Culinary',
                        'Buatan' => 'Man-made',
                    ];
                    $activeJenis = request()->routeIs('destinations') ? request('jenis') : null;
                @endphp

                <!-- Dropdown Jenis Wisata -->
                <div class="relative group">
                    <button type="button" aria-haspopup="true" class="flex items-center gap-1 hover:text-brand-primary focus:outline-none {{ in_array($activeJenis, array_keys($typeLabels), true) ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">
                        Categories
                        <svg class="w-4 h-4 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>

                    <div class="absolute left-0 top-full pt-3 hidden group-hover:block group-focus-within:block">
                        <div class="w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-2">
                            @foreach ($typeLabels as $jenis => $label)
                                <a href="{{ route('destinations', ['jenis' => $jenis]) }}" class="flex items-center justify-between px-4 py-2 hover:bg-brand-light hover:text-brand-primary {{ $activeJenis === $jenis ? 'bg-brand-light text-brand-primary font-semibold' : '' }}">
                                    {{ $label }}
                                    <span class="text-xs text-brand-muted">{{ $jenis }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>

                <a href="{{ route('map') }}" class="hover:text-brand-primary {{ request()->routeIs('map') ? 'border-b-2 border-brand-accent pb-1 text-brand-text' : '' }}">Map View</a>
            </nav>

            <!-- Tombol Kanan -->
            <div class="flex items-center gap-4">
                
                <a href="" class="bg-brand-accent hover:bg-orange-800 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition flex items-center gap-2">
                    Chatbot
                </a>

                <div class="flex items-center gap-3 border-l pl-4 border-gray-200">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-gray-800">Wisatawan</p>
                        <p class="text-xs text-brand-muted">Ontology Explorer</p>
                    </div>
                    <img src="https://i.pravatar.cc/150?img=11" alt="Explorer" class="w-10 h-10 rounded-full border-2 border-brand-light object-cover">
                </div>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- FOOTER -->
    <footer class="bg-brand-dark text-white pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-12 border-b border-gray-700 pb-12">
            <!-- Kolom 1 -->
            <div>
                <div class="flex items-center gap-2 font-serif font-bold text-xl mb-4 text-[#f0dfc8]">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                    Sumatra Heritage
                </div>
                <p class="text-sm text-gray-400 leading-relaxed">
                    Preserving Indigenous Batak wisdom, pristine rainforest ecosystems, and sacred Acehnese architectural heritage through regenerative eco-travel.
                </p>
            </div>
            <!-- Kolom 2 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Destinations</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ route('destinations', ['q' => 'Toba']) }}" class="hover:text-white">Lake Toba Caldera</a></li>
                    <li><a href="{{ route('destinations', ['q' => 'Iboih']) }}" class="hover:text-white">Sabang Marine Park</a></li>
                    <li><a href="{{ route('destinations', ['jenis' => 'Kuliner']) }}" class="hover:text-white">Culinary Trails</a></li>
                    <li><a href="{{ route('destinations', ['q' => 'Bukit Lawang']) }}" class="hover:text-white">Bukit Lawang Leuser</a></li>
                </ul>
            </div>
            <!-- Kolom 3 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Advisories & Protocols</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="{{ route('destinations', ['provinsi' => 'Aceh']) }}" class="hover:text-white">Aceh Destination Guide</a></li>
                    <li><a href="{{ route('destinations', ['jenis' => 'Alam']) }}" class="hover:text-white">Responsible Ecotourism</a></li>
                    <li><a href="{{ route('map') }}" class="hover:text-white">Interactive Map Atlas</a></li>
                    <li><a href="{{ route('home') }}" class="hover:text-white">Ontology Dashboard</a></li>
                </ul>
            </div>
            <!-- Kolom 4 -->
            <div>
                <h4 class="font-bold mb-4 uppercase tracking-wider text-sm text-gray-300">Emergency & Assistance</h4>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="tel:112" class="text-orange-400 hover:text-orange-300 underline underline-offset-4">Emergency Contacts (112)</a></li>
                    <li>Sumut Tourist Police (+62 611 0)</li>
                    <li>Banda Aceh SAR Center</li>
                    <li>Gunung Leuser Ranger Post</li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">
            <p>© 2025 Dinas Kebudayaan & Pariwisata Sumatera Utara & Aceh. Wonderful Indonesia Partner.</p>
            <div class="flex gap-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-white">Legal Terms</a>
                <a href="#" class="hover:text-white">Ethical Expedition Code</a>
                <a href="#" class="hover:text-white">Portal Accessibility</a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>