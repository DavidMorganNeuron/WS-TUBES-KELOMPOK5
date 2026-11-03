@extends('layouts.app')

@section('content')
<div class="flex h-[calc(100vh-80px)] overflow-hidden">
    
    <!-- SIDEBAR KIRI -->
    <div class="w-[400px] bg-white border-r border-gray-200 flex flex-col z-10 shadow-lg relative hidden md:flex">
        <div class="p-5 border-b border-gray-100 space-y-4 bg-[#f8faf9]">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                <input type="text" value="Lake Toba & Karo Plateau" class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-medium outline-none focus:border-brand-primary">
            </div>
            
            <div class="flex gap-2 overflow-x-auto pb-1">
                <button class="bg-brand-dark text-white text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap">All (42)</button>
                <button class="bg-white border border-gray-200 text-gray-700 text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Nature (18)</button>
                <button class="bg-white border border-gray-200 text-gray-700 text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-400"></span> Culture (12)</button>
            </div>

            <div class="pt-2">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-xs font-bold text-gray-700 flex items-center gap-1">📍 Sumatra Utara & Aceh</span>
                    <label class="flex items-center gap-2 text-xs font-bold text-gray-600">
                        <input type="checkbox" checked class="rounded text-brand-primary focus:ring-brand-primary"> Boundaries
                    </label>
                </div>
                <div class="flex justify-between text-[10px] font-bold text-gray-400 mb-1">
                    <span>Radius: 35 km</span>
                    <span>Karo Region</span>
                </div>
                <input type="range" class="w-full h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-brand-primary">
            </div>
            
            <div class="flex border-b border-gray-200 mt-2">
                <button class="flex-1 text-center py-2 text-xs font-bold text-brand-primary border-b-2 border-brand-primary">📍 Nearby POIs (4)</button>
                <button class="flex-1 text-center py-2 text-xs font-bold text-gray-500 hover:text-gray-700">🗺️ Route Planner <span class="bg-orange-100 text-orange-600 px-1.5 rounded text-[10px]">2</span></button>
            </div>
        </div>

        <div class="flex-grow overflow-y-auto p-4 space-y-4 bg-[#f8faf9]">
            <div class="bg-white rounded-2xl p-4 border-2 border-brand-dark shadow-md">
                <div class="flex gap-3 mb-3">
                    <div class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                        <img src="https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 bg-brand-primary text-white text-[8px] font-bold px-1.5 py-0.5 rounded">Nature</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-sm text-gray-900">Sipiso-Piso Waterfall</h3>
                            <button class="text-red-500">♥</button>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">⭐ 4.9 (1,248) • 4.2 km away</p>
                        <p class="text-[10px] text-gray-500">Tongging, Merek, Karo Regency</p>
                        <p class="text-[10px] font-bold text-green-600 mt-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Open Now • Closes 18:00</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="flex-1 bg-brand-dark text-white text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-800">◈ Directions</button>
                    <button class="flex-1 bg-brand-light border border-brand-primary/20 text-brand-dark text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">⊕ Add to Route</button>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm hover:shadow-md transition cursor-pointer">
                <div class="flex gap-3 mb-3">
                    <div class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                        <img src="https://images.unsplash.com/photo-1628151015968-3a4429e9ef04?auto=format&fit=crop&q=80" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 bg-brand-accent text-white text-[8px] font-bold px-1.5 py-0.5 rounded">Culture</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-sm text-gray-900">Tuk Tuk Siadong Samosir</h3>
                            <button class="text-gray-400 hover:text-red-500">♡</button>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">⭐ 4.8 (3,820) • 12.0 km away</p>
                        <p class="text-[10px] text-gray-500">Simanindo, Samosir Island</p>
                        <p class="text-[10px] font-bold text-green-600 mt-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Open 24 Hours • Ferry Accessible</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">◈ Directions</button>
                    <button class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">⊕ Add to Route</button>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-4 border border-gray-200 shadow-sm hover:shadow-md transition cursor-pointer">
                <div class="flex gap-3 mb-3">
                    <div class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0">
                        <img src="https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&q=80" class="w-full h-full object-cover">
                        <span class="absolute bottom-1 left-1 bg-brand-accent text-white text-[8px] font-bold px-1.5 py-0.5 rounded">Heritage</span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-sm text-gray-900">Baiturrahman Grand Mosque</h3>
                            <button class="text-gray-400 hover:text-red-500">♡</button>
                        </div>
                        <p class="text-[10px] text-gray-500 mb-1">⭐ 5.0 (8,914) • Banda Aceh</p>
                        <p class="text-[10px] text-gray-500">Kp. Baru, Baiturrahman, Banda Aceh</p>
                        <p class="text-[10px] font-bold text-green-600 mt-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Open Now • Cultural Etiquette</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">◈ Directions</button>
                    <button class="flex-1 bg-gray-50 border border-gray-200 text-gray-700 text-xs font-bold py-2 rounded-lg flex items-center justify-center gap-1 hover:bg-gray-100">⊕ Add to Route</button>
                </div>
            </div>
        </div>

        <div class="p-3 bg-white border-t border-gray-200 flex justify-between items-center text-xs font-bold">
            <span class="text-green-600 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Live GPS active</span>
            <button onclick="alert('Filter dibersihkan')" class="text-gray-500 hover:text-gray-900">Clear Filters</button>
        </div>
    </div>

    <!-- AREA PETA (KANAN) -->
    <div class="flex-grow relative bg-[#e5e9e2] overflow-hidden">
        <!-- Tombol Toggle Peta -->
        <div class="absolute top-6 left-6 z-[1000] flex bg-white rounded-lg shadow-md overflow-hidden border border-gray-200">
            <button class="bg-brand-dark text-white px-4 py-2 text-xs font-bold flex items-center gap-2">🗺️ Terrain Map</button>
            <button onclick="alert('Fitur Satellite belum tersedia di versi gratis')" class="bg-white text-gray-600 hover:bg-gray-50 px-4 py-2 text-xs font-bold flex items-center gap-2">🛰️ Satellite</button>
        </div>

        <!-- Curated Layers Dropdown -->
        <div class="absolute top-6 right-6 z-[1000] bg-white rounded-xl shadow-lg border border-gray-100 p-4 w-64 hidden lg:block">
            <div class="flex justify-between items-center mb-4">
                <h4 class="font-bold text-xs text-gray-800 flex items-center gap-1">◈ CURATED LAYERS</h4>
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
            </div>
            <div class="space-y-3">
                <label class="flex items-center justify-between text-xs font-medium text-gray-600 cursor-pointer">
                    <span class="flex items-center gap-2">⛴️ Ferry Routes (Toba & Sabang)</span>
                    <input type="checkbox" checked class="rounded text-brand-dark focus:ring-brand-dark">
                </label>
                <label class="flex items-center justify-between text-xs font-medium text-gray-600 cursor-pointer">
                    <span class="flex items-center gap-2">✈️ Airport Hubs (KNO & BTJ)</span>
                    <input type="checkbox" checked class="rounded text-brand-dark focus:ring-brand-dark">
                </label>
                <label class="flex items-center justify-between text-xs font-medium text-gray-600 cursor-pointer">
                    <span class="flex items-center gap-2">⛰️ Top Scenic Drives</span>
                    <input type="checkbox" class="rounded text-brand-dark focus:ring-brand-dark">
                </label>
            </div>
        </div>

        <!-- PETA LEAFLET -->
        <div class="w-full h-full z-0" id="map"></div>

        <!-- Peta Kontrol Kanan Bawah -->
        <div class="absolute bottom-20 right-6 z-[1000] flex flex-col gap-2">
            <button onclick="alert('Lokasi Anda terdeteksi')" class="w-10 h-10 bg-white rounded-lg shadow-md flex items-center justify-center text-gray-600 hover:bg-gray-50">🎯</button>
            <button class="w-10 h-10 bg-brand-accent text-white rounded-lg shadow-md flex items-center justify-center hover:bg-orange-800">👥</button>
        </div>

        <!-- Footer Peta Bawah -->
        <div class="absolute bottom-0 left-0 w-full bg-white/90 backdrop-blur border-t border-gray-200 px-6 py-2 flex justify-between items-center text-xs text-gray-600 z-[1000]">
            <span>Caldera Toba Geopark & Serambi Mekkah</span>
            <div class="flex gap-4 items-center">
                <span class="flex items-center gap-2">Scale <span class="w-16 h-0.5 bg-gray-400 block"></span> 10 km</span>
                <span>Elevation: 905m MSL</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Inisialisasi peta
    var map = L.map('map').setView([2.684, 98.875], 8);

    // Tambahkan tile layer (tampilan peta)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Marker Sipiso-Piso
    var marker1 = L.marker([2.927, 98.522]).addTo(map);
    marker1.bindPopup("<b>Sipiso-Piso Waterfall</b><br>Tongging, Karo Regency.<br><a href='#'>Lihat Detail</a>").openPopup();
    
    // Marker Tuk Tuk
    L.marker([2.657, 98.815]).addTo(map).bindPopup("<b>Tuk Tuk Siadong</b><br>Samosir Island");

    // Marker Banda Aceh
    L.marker([5.552, 95.318]).addTo(map).bindPopup("<b>Baiturrahman Grand Mosque</b><br>Banda Aceh");
</script>
@endpush