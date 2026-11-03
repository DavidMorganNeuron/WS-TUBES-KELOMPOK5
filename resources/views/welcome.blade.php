<!-- resources/views/home.blade.php -->
@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <!-- Header Section -->
    <div class="mb-10">
        <span class="inline-block bg-brand-light border border-brand-primary/20 text-brand-primary text-xs font-bold px-3 py-1 rounded-full mb-4">
            <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            Personal Journey Hub
        </span>
        <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">Welcome back, Budi!</h1>
        <p class="text-gray-600 text-lg max-w-2xl">Your customized expedition ledger across North Sumatra's volcanic heights and Aceh's sacred coastlines.</p>
    </div>

    <!-- 4 Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Active Plans</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">3 Trips Planned</h3>
                <p class="text-sm text-gray-500">Next departure in 8 days</p>
            </div>
            <div class="bg-brand-light p-3 rounded-xl text-brand-primary">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
        </div>
        <!-- Ulangi untuk 3 card lainnya (Saved Archive, Vouchers, Live Micro-Climate) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Saved Archive</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">14 Saved Attractions</h3>
                <p class="text-sm text-gray-500">8 Highlands • 6 Coastal</p>
            </div>
            <div class="bg-orange-50 p-3 rounded-xl text-orange-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Vouchers & Permits</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-1">2 Active Booking Vouchers</h3>
                <p class="text-sm text-gray-500">1 Lake Ferry • 1 Leuser Permit</p>
            </div>
            <div class="bg-yellow-50 p-3 rounded-xl text-yellow-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
            </div>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex justify-between items-start">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Live Micro-Climate</p>
                <h3 class="text-xl font-bold text-gray-900 mb-1">Clear Caldera & Coast</h3>
                <p class="text-sm text-gray-500">Lake Toba 23°C • Sabang 29°C</p>
            </div>
            <div class="bg-brand-light p-3 rounded-xl text-brand-primary">
                ☀️
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mb-8 overflow-x-auto">
        <nav class="flex space-x-8 min-w-max">
            <a href="#" class="border-b-2 border-brand-accent py-4 text-brand-primary font-semibold flex items-center gap-2">
                🗺️ My Itineraries (Active & Upcoming)
            </a>
            <a href="#" class="border-b-2 border-transparent py-4 text-gray-500 hover:text-brand-primary font-medium flex items-center gap-2">
                ♡ Saved Attractions & Wishlist
            </a>
            <a href="#" class="border-b-2 border-transparent py-4 text-gray-500 hover:text-brand-primary font-medium flex items-center gap-2">
                🎫 Bookings & Permits
            </a>
        </nav>
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- KOLOM KIRI (2/3) -->
        <div class="lg:col-span-2 space-y-8">
            
            <!-- Kartu Itinerary Utama -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Gambar Header -->
                <div class="relative h-64 bg-gray-300">
                    <img src="https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?auto=format&fit=crop&q=80" alt="Lake Toba" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                    <div class="absolute top-4 left-4 flex gap-2">
                        <span class="bg-white/20 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full">SUMATERA UTARA</span>
                        <span class="bg-white text-brand-primary text-xs font-bold px-3 py-1 rounded-full">Active Itinerary</span>
                    </div>
                    <div class="absolute bottom-6 left-6 right-6 text-white">
                        <p class="text-sm font-medium mb-2 opacity-90">OCT 18 – 22, 2025 • 5 DAYS / 4 NIGHTS</p>
                        <h2 class="text-3xl font-serif font-bold leading-tight">5-Day Lake Toba & Samosir Cultural Immersion</h2>
                    </div>
                    <button class="absolute bottom-6 right-6 bg-white text-gray-800 text-sm font-bold px-4 py-2 rounded-full flex items-center gap-2 hover:bg-gray-50">
                        ✏️ Edit Itinerary
                    </button>
                </div>

                <!-- Konten Itinerary -->
                <div class="p-8">
                    <!-- Progress -->
                    <div class="mb-8">
                        <div class="flex justify-between text-sm font-bold mb-2">
                            <span class="text-brand-primary">PREPARATION PROGRESS</span>
                            <span class="text-orange-600">Day 1 of 5 (Starting in 6 Days)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-brand-accent h-2 rounded-full" style="width: 20%"></div>
                        </div>
                    </div>

                    <!-- Daily Schedule -->
                    <h3 class="text-xl font-serif font-bold mb-4">Daily Schedule</h3>
                    
                    <div class="space-y-4">
                        <!-- Hari 1 -->
                        <div class="bg-brand-light rounded-2xl p-5 border border-brand-primary/10 flex gap-5">
                            <div class="bg-brand-primary text-white font-bold text-sm px-3 py-1 rounded-lg h-fit whitespace-nowrap">DAY 01</div>
                            <div>
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-gray-900">Medan to Parapat & Lake Crossing</h4>
                                    <span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded">En Route</span>
                                </div>
                                <p class="text-sm text-gray-600">Morning transfer via Tebing Tinggi highway to Ajibata Port. Board the traditional wooden car ferry to Tomok Village, Samosir Island.</p>
                            </div>
                        </div>
                        <!-- Hari 2 -->
                        <div class="bg-white rounded-2xl p-5 border border-gray-100 flex gap-5">
                            <div class="bg-brand-light text-brand-primary font-bold text-sm px-3 py-1 rounded-lg h-fit whitespace-nowrap border border-brand-primary/20">DAY 02</div>
                            <div>
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-gray-900">Tomok & Simanindo Batak Ancestral Rituals</h4>
                                    <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded">Scheduled</span>
                                </div>
                                <p class="text-sm text-gray-600">Visit the megalithic King Sidabutar stone sarcophagus, attend Sigale-gale marionette performance, and explore Huta Bolon museum.</p>
                            </div>
                        </div>
                        <!-- Hari 3 -->
                        <div class="bg-white rounded-2xl p-5 border border-gray-100 flex gap-5">
                            <div class="bg-brand-light text-brand-primary font-bold text-sm px-3 py-1 rounded-lg h-fit whitespace-nowrap border border-brand-primary/20">DAY 03</div>
                            <div>
                                <div class="flex justify-between items-start mb-1">
                                    <h4 class="font-bold text-gray-900">Lumban Suhi-Suhi Ulos Weaving Guild</h4>
                                    <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded">Scheduled</span>
                                </div>
                                <p class="text-sm text-gray-600">Hands-on weaving workshop with master Batak artisans, natural indigo dye demonstration, and Samosir volcanic crater trail stroll.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Boxes -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                        <div class="bg-red-50 rounded-xl p-4 border border-red-100">
                            <p class="font-bold text-red-800 text-sm flex items-center gap-2 mb-1">⛴️ Ferry Alert: Ajibata to Tomok</p>
                            <p class="text-xs text-red-700">Confirmed departure: 14:30 WIB. Please arrive at Pier 2 at least 35 minutes prior for vehicle boarding.</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                            <p class="font-bold text-gray-800 text-sm flex items-center gap-2 mb-1">🌤️ Caldera Weather Forecast</p>
                            <p class="text-xs text-gray-600">Crisp mornings (19°C). High altitude UV index 7 at midday. Light afternoon rain showers expected around Tuk-tuk peninsula.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regional Customs (Bawah) -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-serif font-bold flex items-center gap-2">📖 Regional Customs & Etiquette Protocols</h3>
                        <p class="text-sm text-gray-500 mt-1">Essential compliance for respectful travels across Aceh and North Sumatra</p>
                    </div>
                    <span class="bg-brand-light text-brand-primary text-xs font-bold px-3 py-1 rounded-full border border-brand-primary/20">Mandatory Guide</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-brand-light/50 p-6 rounded-2xl">
                        <h4 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-primary"></span> Aceh (Veranda of Mecca)</h4>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <li class="flex gap-2"><span>🛡️</span> <span><strong>Modest Dress Code:</strong> Both men and women must cover shoulders and knees.</span></li>
                            <li class="flex gap-2"><span>🕌</span> <span><strong>Friday Observance:</strong> All public businesses pause between 11:30 and 14:00.</span></li>
                            <li class="flex gap-2"><span>🚫</span> <span><strong>Alcohol Prohibition:</strong> Sale and consumption of alcohol is strictly illegal.</span></li>
                        </ul>
                    </div>
                    <div class="bg-orange-50/50 p-6 rounded-2xl">
                        <h4 class="font-bold text-gray-900 flex items-center gap-2 mb-4"><span class="w-2 h-2 rounded-full bg-brand-accent"></span> Sumatera Utara & Batak Highlands</h4>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <li class="flex gap-2"><span>🪦</span> <span><strong>Sacred Megaliths:</strong> Remove hats and sunglasses when standing near stone sarcophagi.</span></li>
                            <li class="flex gap-2"><span>🧶</span> <span><strong>Ulos Gifting Protocol:</strong> Treat handed Ulos textiles with two hands as a symbol of cosmic warmth.</span></li>
                            <li class="flex gap-2"><span>🏠</span> <span><strong>Traditional House Courtesies:</strong> Always state 'Horas' upon entering Batak Bolon houses.</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN (1/3 Sidebar) -->
        <div class="space-y-8">
            
            <!-- Curated Wishlist -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Curated Wishlist</p>
                    <a href="#" class="text-xs font-bold text-orange-600 hover:underline">View All (14)</a>
                </div>
                <h3 class="text-xl font-serif font-bold mb-6">North Sumatra & Aceh Highlights</h3>

                <div class="space-y-4">
                    <!-- Item 1 -->
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-gray-50 transition border border-transparent hover:border-gray-100">
                        <img src="https://images.unsplash.com/photo-1596422846543-75c6fc197f07?auto=format&fit=crop&q=80" class="w-20 h-20 rounded-lg object-cover" alt="Sipiso">
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <h4 class="font-bold text-sm text-gray-900">Karo Regency</h4>
                                <span class="text-red-500 text-xs">🔖</span>
                            </div>
                            <p class="text-xs font-bold text-gray-700 mb-1">Air Terjun Sipiso-Piso</p>
                            <p class="text-xs text-gray-500 mb-2 italic">"Visit sunrise at Sipiso-Piso"</p>
                            <div class="flex gap-2 text-[10px] text-gray-500 font-medium">
                                <span>120m Drop</span> • <span>Early Trek</span>
                            </div>
                        </div>
                    </div>
                    <!-- Item 2 -->
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-gray-50 transition border border-transparent hover:border-gray-100">
                        <img src="https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&q=80" class="w-20 h-20 rounded-lg object-cover" alt="Pulau Weh">
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <h4 class="font-bold text-sm text-gray-900">Pulau Weh, Aceh</h4>
                                <span class="text-red-500 text-xs">🔖</span>
                            </div>
                            <p class="text-xs font-bold text-gray-700 mb-1">Iboih Marine Reserve & Rubiah</p>
                            <p class="text-xs text-gray-500 mb-2 italic">"Snorkel at Iboih Beach Sabang"</p>
                            <div class="flex gap-2 text-[10px] text-gray-500 font-medium">
                                <span>Coral Nursery</span> • <span>Zero Km Reef</span>
                            </div>
                        </div>
                    </div>
                    <!-- Item 3 -->
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-gray-50 transition border border-transparent hover:border-gray-100">
                        <img src="https://images.unsplash.com/photo-1628151015968-3a4429e9ef04?auto=format&fit=crop&q=80" class="w-20 h-20 rounded-lg object-cover" alt="Tsunami Museum">
                        <div class="flex-1">
                            <div class="flex justify-between">
                                <h4 class="font-bold text-sm text-gray-900">Banda Aceh</h4>
                                <span class="text-red-500 text-xs">🔖</span>
                            </div>
                            <p class="text-xs font-bold text-gray-700 mb-1">Aceh Tsunami Memorial Museum</p>
                            <p class="text-xs text-gray-500 mb-2 italic">"Visit Tsunami museum morning session"</p>
                            <div class="flex gap-2 text-[10px] text-gray-500 font-medium">
                                <span>Historical Memorial</span> • <span>Audio Tour</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recommended Expeditions -->
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Tailored for Nature & Coffee Aficionado</p>
                <h3 class="text-xl font-serif font-bold mb-6">Recommended Expeditions</h3>

                <div class="space-y-4">
                    <!-- Card 1 -->
                    <div class="border border-gray-100 rounded-2xl p-4 bg-[#fafaf9]">
                        <div class="flex justify-between items-start mb-2">
                            <span class="bg-orange-100 text-orange-800 text-[10px] font-bold px-2 py-1 rounded">Highland Agro-tour</span>
                            <span class="text-brand-primary">☕</span>
                        </div>
                        <h4 class="font-bold text-sm text-gray-900 mb-2">Takengon Gayo Highlands 2-Day Agro-tour</h4>
                        <p class="text-xs text-gray-600 mb-4 leading-relaxed">Direct harvest experience with Gayo organic coffee co-ops, cupping sessions with Q-graders, and lakeside glamping.</p>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-200">
                            <span class="text-xs font-bold text-gray-900">IDR 1,450,000 / pax</span>
                            <button class="bg-brand-dark text-white text-xs font-bold px-3 py-1.5 rounded-md hover:bg-gray-800">Add to Trip</button>
                        </div>
                    </div>

                    <!-- Card 2 -->
                    <div class="border border-gray-100 rounded-2xl p-4 bg-[#fafaf9]">
                        <div class="flex justify-between items-start mb-2">
                            <span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-1 rounded">National Park Permit</span>
                            <span class="text-brand-primary">🌿</span>
                        </div>
                        <p class="text-[10px] font-bold text-red-600 mb-1">• Permit Slot Open</p>
                        <h4 class="font-bold text-sm text-gray-900 mb-2">Bukit Lawang Jungle Trekking Permit</h4>
                        <p class="text-xs text-gray-600 mb-4 leading-relaxed">Official Gunung Leuser UNESCO sanctuary entry for semi-wild Sumatran Orangutan encounter and Bahorok river tubing.</p>
                        <div class="flex justify-between items-center mt-2 pt-2 border-t border-gray-200">
                            <span class="text-xs text-gray-500 flex items-center gap-1">✓ Verified Ranger Escort</span>
                            <button class="bg-brand-accent text-white text-xs font-bold px-3 py-1.5 rounded-md hover:bg-orange-800">Apply Permit</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Digital Companion Card -->
            <div class="bg-brand-dark text-white rounded-3xl p-6 shadow-lg">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Digital Companion</p>
                <h3 class="text-xl font-serif font-bold mb-2">Sumatra-Aceh Offline Pass</h3>
                <p class="text-xs text-gray-300 mb-6 leading-relaxed">Equipped for remote highlands with zero cellular signal. Includes topographic vector trail maps, offline Batak phrasebook, and PDF booking permits.</p>
                
                <div class="space-y-3 mb-6">
                    <button class="w-full flex items-center justify-between bg-white/10 hover:bg-white/20 px-4 py-3 rounded-xl transition text-sm">
                        <span class="flex items-center gap-2">📱 Offline Wallet Pass (Apple / Google)</span>
                        <span>⬇️</span>
                    </button>
                    <button class="w-full flex items-center justify-between bg-white/10 hover:bg-white/20 px-4 py-3 rounded-xl transition text-sm">
                        <span class="flex items-center gap-2">🗺️ Highlands Topo Vector Cache (.gpx)</span>
                        <span>⬇️</span>
                    </button>
                </div>

                <div>
                    <p class="text-[10px] font-bold text-gray-400 mb-2 uppercase tracking-wider">Instant Emergency SOS Dispatch</p>
                    <div class="flex gap-2">
                        <button class="flex-1 bg-brand-accent hover:bg-orp