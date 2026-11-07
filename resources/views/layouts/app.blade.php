<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sumatera Heritage & Trails</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Library parser Markdown ringan untuk Chatbot -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    @stack('styles')
</head>
<body class="bg-brand-light text-brand-text font-sans antialiased min-h-screen flex flex-col">

    <!-- NAVBAR -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <!-- Logo -->
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-brand-primary font-serif font-bold text-xl">
                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                Sumatera Heritage & Trails
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

                <!-- Dropdown Jenis Wisata (Categories) -->
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
                
                <!-- Tombol Chatbot (Bagian profil di sebelahnya sudah dihapus) -->
                <button id="btn-chatbot" type="button" class="bg-brand-accent hover:bg-orange-800 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    Chatbot
                </button>

            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-grow">
        @yield('content')
    </main>

    <!-- FOOTER ASLI -->
    <footer class="bg-brand-dark text-white pt-16 pb-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-12 border-b border-gray-700 pb-12">
            <!-- Kolom 1 -->
            <div>
                <div class="flex items-center gap-2 font-serif font-bold text-xl mb-4 text-[#f0dfc8]">
                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 4.5l6.5 13.5h-13L12 6.5z"/></svg>
                    Sumatera Heritage
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
            <p>© 2026 Dinas Kebudayaan & Pariwisata Sumatera Utara. Wonderful Indonesia Partner.</p>
            <div class="flex gap-6 mt-4 md:mt-0">
                <a href="#" class="hover:text-white">Legal Terms</a>
                <a href="#" class="hover:text-white">Ethical Expedition Code</a>
                <a href="#" class="hover:text-white">Portal Accessibility</a>
            </div>
        </div>
    </footer>

    <!-- MODAL CHATBOT TAILWIND (REVISI FINAL: KONSISTENSI, ANIMASI & TOMBOL) -->
    <div id="wayfinder-chatbot" class="hidden fixed z-[9999] bg-white border border-gray-200 shadow-2xl flex-col transition-all duration-300 ease-in-out" style="bottom: 20px; right: 20px; width: 400px; height: 500px; border-radius: 1rem; overflow: hidden;">
        
        <!-- HEADER -->
        <div id="chatbot-header" class="bg-[#92400e] text-white px-4 py-3 flex justify-between items-center cursor-move select-none shrink-0 z-10">
            <div class="flex items-center gap-2 font-bold text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <span>WayFinder AI</span>
            </div>
            <div class="flex items-center gap-4">
                <button id="btn-resize-chatbot" type="button" class="text-white hover:text-gray-300 transition flex items-center justify-center" title="Toggle Fullscreen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
                <button id="btn-close-chatbot" type="button" class="text-white hover:text-gray-300 transition flex items-center justify-center" title="Tutup">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- KONTEN UTAMA -->
        <div id="chatbot-body-container" class="flex-1 flex flex-col min-h-0 bg-[#f8faf9] relative">
            
            <!-- Sapaan Awal (Hanya Muncul di Fullscreen saat Kosong) -->
            <div id="gemini-hero-greeting" class="hidden absolute inset-0 flex-col items-center justify-center p-6 text-center bg-white z-0">
                <!-- Tempat animasi teks mengetik -->
                <h2 id="typing-text" class="text-3xl md:text-4xl font-semibold text-gray-800 mb-8 tracking-tight h-12"></h2>
                <!-- Tempat placeholder untuk form input saat di tengah -->
                <div id="hero-input-portal" class="w-full max-w-2xl"></div>
            </div>

            <!-- Area Bubble Chat -->
            <div id="chat-messages" class="flex-1 overflow-y-auto p-5 text-sm flex flex-col gap-4 z-10 w-full mx-auto max-w-5xl">
                <div id="initial-bot-bubble" class="bg-white text-gray-800 p-4 rounded-2xl rounded-tl-sm border border-gray-200 self-start max-w-[85%] shadow-sm prose prose-sm prose-orange">
                    <strong>Halo! Saya WayFinder AI.</strong><br>Ada yang bisa saya bantu terkait destinasi wisata di Sumatera Utara?
                </div>
                <!-- Riwayat chat akan masuk ke sini -->
            </div>

            <!-- Area Input -->
            <div id="chat-input-area" class="shrink-0 p-4 border-t border-gray-200 bg-white z-20 transition-all duration-300">
                <div id="input-wrapper" class="flex gap-3 items-center w-full mx-auto max-w-5xl">
                    <input type="text" id="chat-input" placeholder="Tanya tentang destinasi wisata..." class="flex-1 px-4 py-3 bg-white border border-[#92400e] rounded-full text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-[#92400e] transition" autocomplete="off">
                    <button id="btn-send-chat" type="button" disabled class="px-6 py-3 bg-[#92400e] text-white font-bold rounded-full text-sm transition hover:bg-[#7a350c] disabled:opacity-50 disabled:cursor-not-allowed">
                        Kirim
                    </button>
                </div>
            </div>
            
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnChatbotNavbar = document.getElementById('btn-chatbot');
        const chatbotModal = document.getElementById('wayfinder-chatbot');
        const chatbotHeader = document.getElementById('chatbot-header');
        const btnResize = document.getElementById('btn-resize-chatbot');
        const btnClose = document.getElementById('btn-close-chatbot');
        const btnSend = document.getElementById('btn-send-chat');
        const chatInput = document.getElementById('chat-input');
        const chatMessages = document.getElementById('chat-messages');
        
        const bodyContainer = document.getElementById('chatbot-body-container');
        const inputArea = document.getElementById('chat-input-area');
        const inputWrapper = document.getElementById('input-wrapper');
        const heroGreeting = document.getElementById('gemini-hero-greeting');
        const heroInputPortal = document.getElementById('hero-input-portal');
        const initialBotBubble = document.getElementById('initial-bot-bubble');
        const typingTextElement = document.getElementById('typing-text');

        let isThinking = false;
        let isFullscreen = false;
        let hasChatStarted = false; 

        const SESSION_KEY_MESSAGES = 'wayfinder_chat_history';
        const SESSION_KEY_STATE = 'wayfinder_chat_state';
        const SESSION_KEY_FULLSCREEN = 'wayfinder_chat_fullscreen';
        const SESSION_KEY_STARTED = 'wayfinder_chat_started';

        // Animasi Mengetik Teks Sapaan
        const textToType = "Sebaiknya kita mulai dari mana?";
        let typeIndex = 0;
        let typeInterval;

        function startTypingAnimation() {
            typingTextElement.textContent = "";
            typeIndex = 0;
            clearInterval(typeInterval);
            typeInterval = setInterval(() => {
                if (typeIndex < textToType.length) {
                    typingTextElement.textContent += textToType.charAt(typeIndex);
                    typeIndex++;
                } else {
                    clearInterval(typeInterval);
                }
            }, 80); 
        }

        function renderLayout() {
            if (isFullscreen) {
                chatbotModal.style.width = '100vw';
                chatbotModal.style.height = '100vh';
                chatbotModal.style.top = '0';
                chatbotModal.style.left = '0';
                chatbotModal.style.borderRadius = '0';
                chatbotHeader.classList.remove('cursor-move');
                
                bodyContainer.classList.remove('bg-[#f8faf9]');
                bodyContainer.classList.add('bg-white'); 

                if (!hasChatStarted) {
                    chatMessages.classList.add('hidden');
                    heroGreeting.classList.remove('hidden');
                    heroGreeting.classList.add('flex');
                    
                    inputArea.classList.remove('p-4', 'border-t');
                    inputArea.classList.add('bg-transparent', 'border-none');
                    heroInputPortal.appendChild(inputWrapper);
                    
                    chatInput.classList.remove('bg-gray-50');
                    chatInput.classList.add('bg-white', 'text-base');

                    startTypingAnimation();
                } else {
                    chatMessages.classList.remove('hidden');
                    heroGreeting.classList.add('hidden');
                    heroGreeting.classList.remove('flex');
                    initialBotBubble.classList.add('hidden'); 
                    
                    inputArea.classList.add('p-4', 'border-t', 'border-gray-200');
                    inputArea.classList.remove('bg-transparent', 'border-none');
                    inputArea.appendChild(inputWrapper);
                    
                    chatInput.classList.remove('bg-gray-50', 'text-base');
                    chatInput.classList.add('bg-white');
                }
            } else {
                chatbotModal.style.width = '400px';
                chatbotModal.style.height = '500px';
                chatbotModal.style.top = 'auto';
                chatbotModal.style.left = 'auto';
                chatbotModal.style.bottom = '20px';
                chatbotModal.style.right = '20px';
                chatbotModal.style.borderRadius = '1rem';
                chatbotHeader.classList.add('cursor-move');

                bodyContainer.classList.add('bg-[#f8faf9]');
                bodyContainer.classList.remove('bg-white');

                chatMessages.classList.remove('hidden');
                heroGreeting.classList.add('hidden');
                heroGreeting.classList.remove('flex');
                
                if (!hasChatStarted) initialBotBubble.classList.remove('hidden');

                inputArea.classList.add('p-4', 'border-t', 'border-gray-200');
                inputArea.classList.remove('bg-transparent', 'border-none');
                inputArea.appendChild(inputWrapper);

                chatInput.classList.add('bg-white', 'text-gray-800');
                chatInput.classList.remove('bg-gray-50', 'text-base');
                clearInterval(typeInterval); 
            }
        }

        btnResize.addEventListener('click', () => {
            isFullscreen = !isFullscreen;
            sessionStorage.setItem(SESSION_KEY_FULLSCREEN, isFullscreen);
            renderLayout();
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        function validateInput() {
            const textLength = chatInput.value.trim().length;
            if (textLength > 0 && !isThinking) {
                btnSend.disabled = false;
                btnSend.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btnSend.disabled = true;
                btnSend.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        chatInput.addEventListener('input', validateInput);

        let isDragging = false, offsetX = 0, offsetY = 0;
        chatbotHeader.addEventListener('mousedown', (e) => {
            if (isFullscreen || e.target.closest('button')) return;
            isDragging = true;
            const rect = chatbotModal.getBoundingClientRect();
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
            chatbotModal.style.bottom = 'auto';
            chatbotModal.style.right = 'auto';
            chatbotModal.style.left = rect.left + 'px';
            chatbotModal.style.top = rect.top + 'px';
        });
        document.addEventListener('mousemove', (e) => {
            if (!isDragging || isFullscreen) return;
            chatbotModal.style.left = (e.clientX - offsetX) + 'px';
            chatbotModal.style.top = (e.clientY - offsetY) + 'px';
        });
        document.addEventListener('mouseup', () => { isDragging = false; });

        function saveState() {
            const messagesToSave = Array.from(chatMessages.children).filter(el => 
                el.id !== 'initial-bot-bubble' && el.id !== 'ai-loading-indicator'
            );
            const htmlToSave = messagesToSave.map(el => el.outerHTML).join('');
            sessionStorage.setItem(SESSION_KEY_MESSAGES, htmlToSave);
            sessionStorage.setItem(SESSION_KEY_STARTED, hasChatStarted);
        }

        function appendMessage(sender, htmlContent, classes) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `p-4 rounded-2xl shadow-sm ${classes}`;
            msgDiv.innerHTML = htmlContent;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            saveState();
        }

        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text || isThinking) return;

            hasChatStarted = true;
            renderLayout(); 

            appendMessage('Anda', text, 'bg-[#92400e] text-white self-end max-w-[85%] rounded-tr-sm');
            
            chatInput.value = '';
            isThinking = true;
            validateInput(); 
            chatInput.disabled = true;

            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'ai-loading-indicator';
            loadingDiv.className = 'p-4 rounded-2xl rounded-tl-sm self-start text-xs italic shadow-sm animate-pulse bg-white text-gray-500 border border-gray-200';
            loadingDiv.innerText = 'WayFinder AI sedang berpikir...';
            chatMessages.appendChild(loadingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;

            fetch('{{ route("chatbot.send") }}', { 
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: text })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('ai-loading-indicator').remove();
                isThinking = false;
                
                if (data.reply) {
                    const formatted = typeof marked !== 'undefined' ? marked.parse(data.reply) : data.reply;
                    appendMessage('WayFinder AI', formatted, 'self-start max-w-[85%] rounded-tl-sm prose prose-sm prose-orange bg-white border border-gray-200 text-gray-800');
                } else {
                    appendMessage('Sistem', 'Format respons tidak sesuai.', 'bg-red-100 text-red-800 self-center');
                }
            })
            .catch(error => {
                if(document.getElementById('ai-loading-indicator')) document.getElementById('ai-loading-indicator').remove();
                isThinking = false;
                appendMessage('Sistem', 'Kesalahan koneksi ke server.', 'bg-red-100 text-red-800 self-center');
            })
            .finally(() => {
                chatInput.disabled = false;
                validateInput();
                chatInput.focus();
            });
        }

        btnSend.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !btnSend.disabled) sendMessage();
        });

        btnChatbotNavbar.addEventListener('click', () => {
            chatbotModal.classList.remove('hidden');
            chatbotModal.classList.add('flex');
            sessionStorage.setItem(SESSION_KEY_STATE, 'true');
            chatInput.focus();
            if (isFullscreen && !hasChatStarted) startTypingAnimation();
        });

        btnClose.addEventListener('click', () => {
            chatbotModal.classList.add('hidden');
            chatbotModal.classList.remove('flex');
            sessionStorage.setItem(SESSION_KEY_STATE, 'false');
        });

        function init() {
            hasChatStarted = sessionStorage.getItem(SESSION_KEY_STARTED) === 'true';
            isFullscreen = sessionStorage.getItem(SESSION_KEY_FULLSCREEN) === 'true';
            
            const history = sessionStorage.getItem(SESSION_KEY_MESSAGES);
            if (history && hasChatStarted) {
                chatMessages.insertAdjacentHTML('beforeend', history);
            }

            const isOpen = sessionStorage.getItem(SESSION_KEY_STATE) === 'true';
            if (isOpen) {
                chatbotModal.classList.remove('hidden');
                chatbotModal.classList.add('flex');
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            renderLayout();
            validateInput();
        }
        
        init();
    });
    </script>
    @stack('scripts')
</body>
</html>