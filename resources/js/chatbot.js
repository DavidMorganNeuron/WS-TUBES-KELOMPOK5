import DOMPurify from 'dompurify';
import { marked } from 'marked';

const STORAGE_KEY = 'wayfinder.chatbot.v1';
const MAX_HISTORY = 10;
const MAX_MESSAGE_LENGTH = 800;
const MAX_STORED_TURNS = 60;
const GREETING = 'Sebaiknya kita mulai dari mana?';

marked.setOptions({
    breaks: true,
    gfm: true,
});

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const renderMarkdown = (text) => DOMPurify.sanitize(marked.parse(String(text ?? ''), { async: false }), {
    USE_PROFILES: { html: true },
    FORBID_TAGS: ['button', 'embed', 'form', 'iframe', 'input', 'object', 'script', 'style'],
    FORBID_ATTR: ['onerror', 'onload', 'onclick', 'style'],
});

const initialState = () => ({
    isOpen: false,
    mode: 'fullscreen',
    position: null,
    turns: [],
    pending: null,
});

const readState = () => {
    try {
        const stored = sessionStorage.getItem(STORAGE_KEY);

        if (! stored) {
            return initialState();
        }

        const parsed = JSON.parse(stored);

        if (! parsed || typeof parsed !== 'object') {
            return initialState();
        }

        const turns = Array.isArray(parsed.turns)
            ? parsed.turns
                .filter((turn) => turn && ['user', 'model', 'error'].includes(turn.role) && typeof turn.text === 'string')
                .slice(-MAX_STORED_TURNS)
                .map((turn) => ({
                    role: turn.role,
                    text: turn.text.slice(0, MAX_MESSAGE_LENGTH * 4),
                    status: turn.role === 'user' && ['pending', 'failed'].includes(turn.status)
                        ? turn.status
                        : 'complete',
                }))
            : [];
        const pending = parsed.pending
            && typeof parsed.pending.message === 'string'
            && Array.isArray(parsed.pending.history)
            ? {
                message: parsed.pending.message.slice(0, MAX_MESSAGE_LENGTH),
                history: parsed.pending.history
                    .filter((turn) => turn && ['user', 'model'].includes(turn.role) && typeof turn.text === 'string')
                    .slice(-MAX_HISTORY)
                    .map((turn) => ({ role: turn.role, text: turn.text.slice(0, MAX_MESSAGE_LENGTH) })),
            }
            : null;
        const position = parsed.position
            && Number.isFinite(parsed.position.left)
            && Number.isFinite(parsed.position.top)
            ? { left: parsed.position.left, top: parsed.position.top }
            : null;

        return {
            isOpen: parsed.isOpen === true,
            mode: parsed.mode === 'popup' ? 'popup' : 'fullscreen',
            position,
            turns,
            pending,
        };
    } catch (error) {
        console.warn('WayFinder AI: saved chat state could not be restored.', error);

        return initialState();
    }
};

const createBubble = (turn) => {
    const wrapper = document.createElement('article');
    const isUser = turn.role === 'user';
    const isError = turn.role === 'error';

    wrapper.className = isUser ? 'flex justify-end' : 'flex justify-start';
    wrapper.setAttribute('aria-label', isUser ? 'Pesan Anda' : isError ? 'Pesan kesalahan' : 'Balasan WayFinder AI');

    const bubble = document.createElement('div');

    if (isUser) {
        bubble.className = 'max-w-[90%] whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm bg-[#92400e] px-4 py-3 text-sm text-white shadow-sm sm:max-w-[80%]';
        bubble.textContent = turn.text;
    } else if (isError) {
        bubble.className = 'max-w-[90%] break-words rounded-2xl rounded-tl-sm border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm sm:max-w-[80%]';
        bubble.textContent = turn.text;
    } else {
        bubble.className = 'prose prose-sm max-w-[90%] break-words rounded-2xl rounded-tl-sm border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm sm:max-w-[80%]';
        bubble.innerHTML = renderMarkdown(turn.text);
    }

    wrapper.appendChild(bubble);

    return wrapper;
};

const initChatbot = () => {
    const dialog = document.getElementById('wayfinder-chatbot');
    const openButton = document.getElementById('btn-chatbot');
    const closeButton = document.getElementById('btn-close-chatbot');
    const resizeButton = document.getElementById('btn-resize-chatbot');
    const header = document.getElementById('chatbot-header');
    const emptyState = document.getElementById('chatbot-empty-state');
    const conversation = document.getElementById('chatbot-conversation');
    const messages = document.getElementById('chat-messages');
    const messageList = document.getElementById('chat-message-list');
    const loading = document.getElementById('chatbot-loading');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const inputDock = document.getElementById('chat-input-dock');
    const sendButton = document.getElementById('btn-send-chat');
    const status = document.getElementById('chatbot-status');
    const greeting = document.querySelector('[data-chatbot-greeting]');
    const chatUrl = dialog?.dataset.chatUrl;

    if (! dialog || ! openButton || ! closeButton || ! resizeButton || ! header || ! emptyState || ! conversation
        || ! messages || ! messageList || ! loading || ! form || ! input || ! inputDock || ! sendButton || ! chatUrl) {
        return;
    }

    const state = readState();
    let activeRequest = false;
    let lastFocusedElement = null;
    let dragState = null;
    let greetingTimer = null;

    const persist = () => {
        try {
            state.turns = state.turns.slice(-MAX_STORED_TURNS);
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (error) {
            console.warn('WayFinder AI: chat state could not be saved for this tab.', error);
        }
    };

    const completedHistory = () => {
        const completedTurns = [];
        let pendingUser = null;

        state.turns.forEach((turn) => {
            if (turn.role === 'user' && turn.status === 'complete') {
                pendingUser = turn;

                return;
            }

            if (turn.role === 'model' && pendingUser) {
                completedTurns.push(
                    { role: 'user', text: pendingUser.text },
                    { role: 'model', text: turn.text },
                );
                pendingUser = null;

                return;
            }

            if (turn.role === 'error') {
                pendingUser = null;
            }
        });

        const history = completedTurns.slice(-MAX_HISTORY);

        while (history[0]?.role === 'model') {
            history.shift();
        }

        return history;
    };

    const scrollToLatest = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const renderTurns = () => {
        messageList.replaceChildren(...state.turns.map(createBubble));
        scrollToLatest();
    };

    const updateShell = () => {
        const isPopup = state.mode === 'popup';

        dialog.classList.toggle('chatbot--popup', isPopup);
        dialog.classList.toggle('inset-0', ! isPopup);
        dialog.classList.toggle('w-screen', ! isPopup);
        dialog.classList.toggle('h-[100dvh]', ! isPopup);
        dialog.classList.toggle('bottom-4', isPopup);
        dialog.classList.toggle('right-4', isPopup);
        dialog.classList.toggle('sm:bottom-5', isPopup);
        dialog.classList.toggle('sm:right-5', isPopup);
        dialog.classList.toggle('w-[calc(100vw-2rem)]', isPopup);
        dialog.classList.toggle('sm:w-[min(420px,calc(100vw-2rem))]', isPopup);
        dialog.classList.toggle('h-[min(650px,calc(100dvh-2rem))]', isPopup);
        dialog.classList.toggle('rounded-2xl', isPopup);
        dialog.classList.toggle('border', isPopup);
        dialog.classList.toggle('border-gray-200', isPopup);
        document.body.classList.toggle('overflow-hidden', state.isOpen);
        header.classList.toggle('cursor-move', isPopup);
        header.classList.toggle('touch-none', isPopup);
        dialog.classList.toggle('hidden', ! state.isOpen);
        dialog.classList.toggle('flex', state.isOpen);
        dialog.setAttribute('aria-hidden', String(! state.isOpen));
        openButton.setAttribute('aria-expanded', String(state.isOpen));
        resizeButton.setAttribute('aria-label', isPopup ? 'Expand chatbot' : 'Minimize chatbot');
        resizeButton.setAttribute('title', isPopup ? 'Expand chatbot' : 'Minimize chatbot');
        resizeButton.innerHTML = isPopup
            ? '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3H5a2 2 0 00-2 2v3m13-5h3a2 2 0 012 2v3M3 16v3a2 2 0 002 2h3m8 0h3a2 2 0 002-2v-3"/></svg>'
            : '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/></svg>';

        if (isPopup && state.position) {
            dialog.style.left = `${state.position.left}px`;
            dialog.style.top = `${state.position.top}px`;
            dialog.style.right = 'auto';
            dialog.style.bottom = 'auto';
        } else if (!isPopup) {
            dialog.style.left = '';
            dialog.style.top = '';
            dialog.style.right = '';
            dialog.style.bottom = '';
        }
    };

    const updateLayout = () => {
        const hasConversation = state.turns.length > 0;

        emptyState.classList.toggle('hidden', hasConversation);
        conversation.classList.toggle('hidden', ! hasConversation);
        conversation.classList.toggle('flex', hasConversation);

        if (hasConversation && form.parentElement !== inputDock) {
            inputDock.appendChild(form);
        } else if (! hasConversation && form.parentElement !== emptyState.firstElementChild) {
            emptyState.firstElementChild.appendChild(form);
        }

        loading.classList.toggle('hidden', ! state.pending);
        loading.setAttribute('aria-hidden', String(! state.pending));
        renderTurns();
        syncSendState();
    };

    const syncSendState = () => {
        sendButton.disabled = activeRequest || input.value.trim() === '';
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
        input.style.overflowY = input.scrollHeight > 160 ? 'auto' : 'hidden';
    };

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const openDialog = () => {
        lastFocusedElement = document.activeElement;
        state.isOpen = true;
        state.mode = 'fullscreen';
        state.position = null;
        updateShell();
        updateLayout();
        persist();

        if (! state.turns.length) {
            startGreeting();
        }

        window.setTimeout(() => input.focus(), 30);
    };

    const closeDialog = () => {
        state.isOpen = false;
        updateShell();
        persist();

        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    };

    const toggleMode = () => {
        state.mode = state.mode === 'fullscreen' ? 'popup' : 'fullscreen';

        if (state.mode === 'fullscreen') {
            state.position = null;
        }

        updateShell();
        persist();
    };

    const handleRequestError = (error) => {
        const message = error instanceof TypeError
            ? 'Koneksi jaringan terputus. Periksa koneksi Anda, lalu coba lagi.'
            : error.message;

        state.turns.push({ role: 'error', text: message });
        setStatus('WayFinder AI belum dapat membalas.');
    };

    const sendPendingMessage = async (pending, userTurn) => {
        if (activeRequest) {
            return;
        }

        activeRequest = true;
        state.pending = pending;
        loading.classList.remove('hidden');
        loading.setAttribute('aria-hidden', 'false');
        syncSendState();
        persist();
        setStatus('WayFinder AI sedang memproses data.');
        scrollToLatest();

        try {
            const response = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: pending.message,
                    history: pending.history,
                }),
            });

            let payload;

            try {
                payload = await response.json();
            } catch (error) {
                throw new Error('Respons WayFinder AI tidak dapat dibaca. Silakan coba lagi.', { cause: error });
            }

            if (! response.ok) {
                const errorMessage = typeof payload.reply === 'string' && payload.reply !== ''
                    ? payload.reply
                    : 'WayFinder AI sedang tidak dapat menjawab. Silakan coba lagi.';

                throw new Error(errorMessage);
            }

            if (typeof payload.reply !== 'string' || payload.reply.trim() === '') {
                throw new Error('WayFinder AI belum memberikan jawaban. Silakan coba lagi.');
            }

            userTurn.status = 'complete';
            state.turns.push({ role: 'model', text: payload.reply });
            setStatus('WayFinder AI telah membalas.');
        } catch (error) {
            userTurn.status = 'failed';
            handleRequestError(error);
        } finally {
            state.pending = null;
            activeRequest = false;
            persist();
            updateLayout();
            syncSendState();
            input.focus();
        }
    };

    const sendMessage = async () => {
        const message = input.value.trim();

        if (message === '' || activeRequest) {
            return;
        }

        if (message.length > MAX_MESSAGE_LENGTH) {
            state.turns.push({
                role: 'error',
                text: `Pesan maksimal ${MAX_MESSAGE_LENGTH} karakter.`,
            });
            updateLayout();

            return;
        }

        const pending = {
            message,
            history: completedHistory(),
        };
        const userTurn = { role: 'user', text: message, status: 'pending' };

        window.clearInterval(greetingTimer);
        state.turns.push(userTurn);
        state.pending = pending;
        input.value = '';
        updateLayout();
        persist();
        await sendPendingMessage(pending, userTurn);
    };

    const focusableElements = () => Array.from(
        dialog.querySelectorAll('button:not(:disabled), textarea, [href], [tabindex]:not([tabindex="-1"])'),
    ).filter((element) => element.offsetParent !== null);

    const startGreeting = () => {
        if (! greeting) {
            return;
        }

        window.clearInterval(greetingTimer);
        greeting.textContent = '';

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            greeting.textContent = GREETING;

            return;
        }

        let index = 0;
        greetingTimer = window.setInterval(() => {
            greeting.textContent = GREETING.slice(0, index + 1);
            index += 1;

            if (index >= GREETING.length) {
                window.clearInterval(greetingTimer);
            }
        }, 42);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        void sendMessage();
    });

    input.addEventListener('input', syncSendState);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && ! event.shiftKey) {
            event.preventDefault();
            void sendMessage();
        }
    });

    openButton.addEventListener('click', () => {
        if (state.isOpen) {
            closeDialog();

            return;
        }

        openDialog();
    });
    closeButton.addEventListener('click', closeDialog);
    resizeButton.addEventListener('click', toggleMode);

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = focusableElements();

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    header.addEventListener('pointerdown', (event) => {
        if (state.mode !== 'popup' || event.button !== 0 || event.target.closest('button')) {
            return;
        }

        const rect = dialog.getBoundingClientRect();
        dragState = {
            pointerId: event.pointerId,
            offsetX: event.clientX - rect.left,
            offsetY: event.clientY - rect.top,
        };
        header.setPointerCapture(event.pointerId);
    });

    header.addEventListener('pointermove', (event) => {
        if (! dragState || event.pointerId !== dragState.pointerId) {
            return;
        }

        const left = Math.min(Math.max(0, event.clientX - dragState.offsetX), Math.max(0, window.innerWidth - dialog.offsetWidth));
        const top = Math.min(Math.max(0, event.clientY - dragState.offsetY), Math.max(0, window.innerHeight - dialog.offsetHeight));

        dialog.style.left = `${left}px`;
        dialog.style.top = `${top}px`;
        dialog.style.right = 'auto';
        dialog.style.bottom = 'auto';
        state.position = { left, top };
    });

    const endDrag = (event) => {
        if (! dragState || event.pointerId !== dragState.pointerId) {
            return;
        }

        dragState = null;
        persist();
        header.releasePointerCapture?.(event.pointerId);
    };

    header.addEventListener('pointerup', endDrag);
    header.addEventListener('pointercancel', endDrag);

    window.addEventListener('resize', () => {
        if (state.mode !== 'popup' || ! state.position) {
            return;
        }

        state.position = {
            left: Math.min(state.position.left, Math.max(0, window.innerWidth - dialog.offsetWidth)),
            top: Math.min(state.position.top, Math.max(0, window.innerHeight - dialog.offsetHeight)),
        };
        dialog.style.left = `${state.position.left}px`;
        dialog.style.top = `${state.position.top}px`;
        persist();
    });

    updateShell();
    updateLayout();

    if (state.isOpen && ! state.turns.length) {
        startGreeting();
    }

    if (state.pending) {
        const pendingTurn = [...state.turns].reverse().find(
            (turn) => turn.role === 'user' && turn.status === 'pending' && turn.text === state.pending.message,
        );

        if (pendingTurn) {
            void sendPendingMessage(state.pending, pendingTurn);
        } else {
            state.pending = null;
            persist();
        }
    }
};

export const initChatbotWidget = () => {
    initChatbot();
};
