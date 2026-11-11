import { initChatbotWidget } from './chatbot';
import { initMaps } from './map';

const debounce = (callback, delay = 250) => {
    let timeoutId;

    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), delay);
    };
};

const numberFormatter = new Intl.NumberFormat('en-US');

const initThemeToggle = () => {
    const button = document.getElementById('btn-theme-toggle');

    if (! button) {
        return;
    }

    const root = document.documentElement;
    const moonIcon = button.querySelector('[data-theme-icon="moon"]');
    const sunIcon = button.querySelector('[data-theme-icon="sun"]');

    const syncThemeControl = () => {
        const isDark = root.classList.contains('dark');
        const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';

        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(isDark));
        moonIcon?.classList.toggle('hidden', isDark);
        sunIcon?.classList.toggle('hidden', ! isDark);
    };

    button.addEventListener('click', () => {
        const isDark = ! root.classList.contains('dark');

        root.classList.toggle('dark', isDark);
        root.style.colorScheme = isDark ? 'dark' : 'light';
        syncThemeControl();

        try {
            localStorage.setItem('wayfinder.theme', isDark ? 'dark' : 'light');
        } catch (error) {
            console.warn('WayFinder: theme preference could not be saved.', error);
        }
    });

    syncThemeControl();
};

const urlWithParams = (base, params) => {
    const url = new URL(base, window.location.origin);
    url.search = params.toString();

    return url;
};

const requestJson = async (url, signal) => {
    const response = await fetch(url, {
        signal,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (! response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
    }

    return response.json();
};

const searchParamsFromForm = (form) => {
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        const text = String(value).trim();

        if (text !== '') {
            params.set(key, text);
        }
    });

    return params;
};

const setText = (id, text) => {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = text;
    }
};

const announce = (id, message) => {
    setText(id, message);
};

const cardSkeleton = () => `
    <div class="rounded-3xl border border-gray-100 bg-white shadow-sm overflow-hidden flex flex-col" aria-hidden="true">
        <div class="relative h-56 skeleton-shimmer">
            <div class="absolute top-3 left-3 flex gap-1">
                <div class="h-4 w-14 rounded bg-gray-300/80"></div>
                <div class="h-4 w-12 rounded bg-gray-300/60"></div>
            </div>
            <div class="absolute bottom-3 left-3 right-3 h-4 w-2/3 rounded bg-gray-300/70"></div>
        </div>
        <div class="p-5 flex-grow flex flex-col space-y-3">
            <div class="h-4 w-16 rounded bg-gray-200 skeleton-shimmer"></div>
            <div class="h-5 w-4/5 rounded bg-gray-200 skeleton-shimmer"></div>
            <div class="space-y-1.5 py-1">
                <div class="h-3 w-full rounded bg-gray-100 skeleton-shimmer"></div>
                <div class="h-3 w-3/4 rounded bg-gray-100 skeleton-shimmer"></div>
            </div>
            <div class="h-3 w-1/2 rounded bg-gray-100 skeleton-shimmer"></div>
            <div class="flex justify-between items-center border-t border-gray-100 pt-3 mt-auto">
                <div class="space-y-1">
                    <div class="h-2.5 w-12 rounded bg-gray-100 skeleton-shimmer"></div>
                    <div class="h-4 w-16 rounded bg-gray-200 skeleton-shimmer"></div>
                </div>
                <div class="h-8 w-28 rounded-full bg-gray-200 skeleton-shimmer"></div>
            </div>
        </div>
    </div>
`;

const renderCardSkeleton = (container, count = 6) => {
    container.innerHTML = `
        <div class="mb-10 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
            ${Array.from({ length: count }, cardSkeleton).join('')}
        </div>
    `;
};

const mapRowSkeleton = () => `
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm" aria-hidden="true">
        <div class="flex gap-3 mb-3">
            <div class="h-20 w-20 flex-shrink-0 rounded-xl skeleton-shimmer"></div>
            <div class="flex-grow space-y-2 py-0.5">
                <div class="flex justify-between items-start gap-2">
                    <div class="h-4 w-3/5 rounded bg-gray-200 skeleton-shimmer"></div>
                    <div class="h-3 w-10 rounded bg-gray-200 skeleton-shimmer"></div>
                </div>
                <div class="h-3 w-1/2 rounded bg-gray-100 skeleton-shimmer"></div>
                <div class="h-3 w-2/5 rounded bg-gray-100 skeleton-shimmer"></div>
                <div class="h-3 w-16 rounded bg-gray-100 skeleton-shimmer"></div>
            </div>
        </div>
        <div class="flex gap-2">
            <div class="h-8 flex-1 rounded-lg bg-gray-200 skeleton-shimmer"></div>
            <div class="h-8 flex-1 rounded-lg bg-gray-100 skeleton-shimmer"></div>
        </div>
    </div>
`;

const renderMapSkeleton = (container, count = 4) => {
    container.innerHTML = `
        <div class="space-y-4" aria-hidden="true">
            ${Array.from({ length: count }, mapRowSkeleton).join('')}
        </div>
    `;
};

const suggestionSkeleton = () => `
    <div class="divide-y divide-gray-100" aria-hidden="true">
        ${Array.from({ length: 4 }).map(() => `
            <div class="flex items-center gap-3 px-4 py-3">
                <div class="h-10 w-10 flex-shrink-0 rounded-lg skeleton-shimmer"></div>
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="h-3.5 w-3/4 rounded bg-gray-200 skeleton-shimmer"></div>
                    <div class="h-2.5 w-1/2 rounded bg-gray-100 skeleton-shimmer"></div>
                </div>
                <div class="h-3 w-12 rounded bg-gray-100 skeleton-shimmer"></div>
            </div>
        `).join('')}
    </div>
`;

const renderLiveError = (container, message, retry) => {
    container.innerHTML = '';

    const panel = document.createElement('div');
    panel.className = 'rounded-2xl border border-red-200 bg-red-50 p-6 text-center';
    panel.setAttribute('role', 'alert');

    const heading = document.createElement('p');
    heading.className = 'mb-2 text-sm font-bold text-red-800';
    heading.textContent = 'We could not load these results.';

    const detail = document.createElement('p');
    detail.className = 'mb-4 text-xs text-red-700';
    detail.textContent = message;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'rounded-full bg-brand-accent px-5 py-2 text-xs font-bold text-white transition hover:bg-orange-800';
    button.textContent = 'Try again';
    button.addEventListener('click', retry);

    panel.append(heading, detail, button);
    container.appendChild(panel);
};

const initLiveDestinations = () => {
    const form = document.querySelector('[data-live-destinations]');

    if (! form) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const results = document.getElementById('destination-results');
    const showing = document.getElementById('destination-showing');
    const reset = document.getElementById('destination-reset');
    const pillContainer = document.querySelector('[data-category-pills]');
    const liveUrl = form.dataset.liveUrl;

    if (! results || ! liveUrl) {
        return;
    }

    let controller = null;
    let requestId = 0;

    const update = async (overrideParams = null, scrollToTop = false) => {
        const params = overrideParams instanceof URLSearchParams ? overrideParams : searchParamsFromForm(form);
        const currentRequest = ++requestId;

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');
        renderCardSkeleton(results);
        announce('destination-live-status', 'Loading destinations.');

        if (scrollToTop) {
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        try {
            const payload = await requestJson(urlWithParams(liveUrl, params), controller.signal);

            if (currentRequest !== requestId) {
                return;
            }

            results.innerHTML = payload.html;

            if (showing) {
                showing.textContent = `Showing ${payload.first_item}–${payload.last_item} of ${numberFormatter.format(payload.total)}`;
            }

            if (reset) {
                reset.classList.toggle('hidden', ! payload.has_filters);
            }

            announce(
                'destination-live-status',
                payload.total === 0
                    ? 'No destinations match the current filters.'
                    : `Showing ${payload.first_item} to ${payload.last_item} of ${payload.total} destinations.`,
            );

            window.history.replaceState({}, '', urlWithParams(form.action, params).toString());
        } catch (error) {
            if (error.name === 'AbortError' || currentRequest !== requestId) {
                return;
            }

            renderLiveError(
                results,
                'Check your connection and try the filters again.',
                () => update(overrideParams, scrollToTop),
            );
            announce('destination-live-status', 'Destination results failed to load.');
        } finally {
            if (currentRequest === requestId) {
                results.removeAttribute('aria-busy');
            }
        }
    };

    const debouncedUpdate = debounce(() => update());

    input?.addEventListener('input', debouncedUpdate);
    form.querySelectorAll('select').forEach((select) => select.addEventListener('change', () => update()));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        update();
    });

    results.addEventListener('click', (event) => {
        const link = event.target.closest('a');

        if (! link || ! link.href) {
            return;
        }

        try {
            const url = new URL(link.href, window.location.origin);

            if (url.searchParams.has('page')) {
                event.preventDefault();
                const page = url.searchParams.get('page');
                const params = searchParamsFromForm(form);
                params.set('page', page);
                update(params, true);
            }
        } catch {}
    });

    pillContainer?.addEventListener('click', (event) => {
        const pill = event.target.closest('a[data-jenis]');

        if (! pill) {
            return;
        }

        event.preventDefault();
        const jenis = pill.dataset.jenis;
        let hiddenInput = form.querySelector('input[name="jenis"]');

        if (! hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'jenis';
            form.appendChild(hiddenInput);
        }

        hiddenInput.value = jenis;

        pillContainer.querySelectorAll('a[data-jenis]').forEach((element) => {
            const isMatch = element.dataset.jenis === jenis;

            if (isMatch) {
                element.className = jenis === ''
                    ? 'text-xs font-bold px-3 py-1.5 rounded-full transition bg-brand-dark text-white'
                    : 'text-xs font-bold px-3 py-1.5 rounded-full transition bg-brand-primary text-white';
            } else {
                element.className = 'text-xs font-medium px-3 py-1.5 rounded-full transition bg-white border border-gray-200 text-gray-600 hover:border-gray-300';
            }
        });

        update(null, true);
    });
};

const initLiveMap = () => {
    const form = document.querySelector('[data-live-map]');

    if (! form) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const results = document.getElementById('map-results');
    const mapPillContainer = document.querySelector('[data-map-category-pills]');
    const liveUrl = form.dataset.liveUrl;

    if (! results || ! liveUrl) {
        return;
    }

    let controller = null;
    let requestId = 0;

    const update = async () => {
        const params = searchParamsFromForm(form);
        const currentRequest = ++requestId;

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        results.setAttribute('aria-busy', 'true');
        renderMapSkeleton(results);
        announce('map-live-status', 'Loading mapped destinations.');

        try {
            const payload = await requestJson(urlWithParams(liveUrl, params), controller.signal);

            if (currentRequest !== requestId) {
                return;
            }

            results.innerHTML = payload.html;

            setText('map-sidebar-count', numberFormatter.format(payload.matchCount));
            setText('map-marker-count', numberFormatter.format(payload.markerCount));
            setText('map-footer-match-count', numberFormatter.format(payload.matchCount));
            setText('map-footer-marker-count', numberFormatter.format(payload.markerCount));

            if (window.LiveMap && typeof window.LiveMap.update === 'function') {
                window.LiveMap.update(payload.markers);
            }

            announce('map-live-status', `${payload.matchCount} destinations match; ${payload.markerCount} have coordinates.`);

            window.history.replaceState({}, '', urlWithParams(form.action, params).toString());
        } catch (error) {
            if (error.name === 'AbortError' || currentRequest !== requestId) {
                return;
            }

            renderLiveError(
                results,
                'The map list could not be refreshed. Check your connection and try again.',
                update,
            );
            announce('map-live-status', 'Mapped destinations failed to load.');
        } finally {
            if (currentRequest === requestId) {
                results.removeAttribute('aria-busy');
            }
        }
    };

    const debouncedUpdate = debounce(update);

    input?.addEventListener('input', debouncedUpdate);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        update();
    });

    mapPillContainer?.addEventListener('click', (event) => {
        const pill = event.target.closest('a[data-jenis]');

        if (! pill) {
            return;
        }

        event.preventDefault();
        const jenis = pill.dataset.jenis;
        let hiddenInput = form.querySelector('input[name="jenis"]');

        if (! hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'jenis';
            form.appendChild(hiddenInput);
        }

        hiddenInput.value = jenis;

        mapPillContainer.querySelectorAll('a[data-jenis]').forEach((element) => {
            const isMatch = element.dataset.jenis === jenis;

            if (isMatch) {
                element.className = 'text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap transition bg-brand-dark text-white';
            } else {
                element.className = 'text-xs font-bold px-4 py-1.5 rounded-full whitespace-nowrap transition bg-white border border-gray-200 text-gray-700 hover:border-gray-300';
            }
        });

        update();
    });
};

const initDashboardSuggestions = () => {
    const form = document.querySelector('[data-live-suggest]');

    if (! form) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const panel = form.querySelector('[data-suggest-panel]');
    const status = form.querySelector('[data-suggest-status]');
    const suggestUrl = form.dataset.suggestUrl;

    if (! input || ! panel || ! suggestUrl) {
        return;
    }

    let controller = null;
    let requestId = 0;
    let items = [];
    let activeIndex = -1;

    const setSuggestionStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const clearActive = () => {
        items.forEach((item) => {
            item.classList.remove('bg-brand-light');
            item.setAttribute('aria-selected', 'false');
        });
        input.removeAttribute('aria-activedescendant');
    };

    const closePanel = () => {
        panel.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        clearActive();
        activeIndex = -1;
    };

    const setActive = (index) => {
        clearActive();
        activeIndex = index;

        if (activeIndex >= 0 && items[activeIndex]) {
            items[activeIndex].classList.add('bg-brand-light');
            items[activeIndex].setAttribute('aria-selected', 'true');
            input.setAttribute('aria-activedescendant', items[activeIndex].id);
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    };

    const update = async () => {
        const query = input.value.trim();

        if (query === '') {
            requestId++;

            if (controller) {
                controller.abort();
            }

            panel.innerHTML = '';
            items = [];
            closePanel();
            setSuggestionStatus('');

            return;
        }

        const currentRequest = ++requestId;

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();
        setSuggestionStatus('Loading suggestions.');
        panel.innerHTML = suggestionSkeleton();
        panel.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');

        try {
            const payload = await requestJson(
                urlWithParams(suggestUrl, new URLSearchParams({ q: query })),
                controller.signal,
            );

            if (currentRequest !== requestId) {
                return;
            }

            panel.innerHTML = payload.html ?? '';
            items = Array.from(panel.querySelectorAll('[data-suggestion]'));
            activeIndex = -1;

            items.forEach((item, index) => {
                item.id = `dashboard-suggestion-${index}`;
                item.setAttribute('aria-selected', 'false');
            });

            if (items.length === 0) {
                closePanel();
                setSuggestionStatus('No suggestions found.');
            } else {
                panel.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');
                setSuggestionStatus(`${items.length} suggestions available. Use the arrow keys to browse.`);
            }
        } catch (error) {
            if (error.name === 'AbortError' || currentRequest !== requestId) {
                return;
            }

            closePanel();
            setSuggestionStatus('Suggestions could not be loaded.');
        }
    };

    const debouncedUpdate = debounce(update);

    input.addEventListener('input', debouncedUpdate);
    input.addEventListener('focus', () => {
        if (items.length > 0 && input.value.trim() !== '') {
            panel.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();

            return;
        }

        if (panel.classList.contains('hidden') || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((activeIndex + 1) % items.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((activeIndex - 1 + items.length) % items.length);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            items[activeIndex].click();
        }
    });
    document.addEventListener('click', (event) => {
        if (! form.contains(event.target)) {
            closePanel();
        }
    });
};

const initMobileNavigation = () => {
    const button = document.getElementById('btn-mobile-menu');
    const menu = document.getElementById('mobile-menu');

    if (! button || ! menu) {
        return;
    }

    const setOpen = (open) => {
        menu.classList.toggle('hidden', ! open);
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    };

    button.addEventListener('click', () => {
        setOpen(menu.classList.contains('hidden'));
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! menu.classList.contains('hidden')) {
            setOpen(false);
            button.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (menu.classList.contains('hidden')) {
            return;
        }

        if (! menu.contains(event.target) && ! button.contains(event.target)) {
            setOpen(false);
        }
    });

    const desktopQuery = window.matchMedia('(min-width: 1024px)');

    const closeOnDesktop = (event) => {
        if (event.matches) {
            setOpen(false);
        }
    };

    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', closeOnDesktop);
    }
};

const initShareButton = () => {
    const button = document.getElementById('btn-share-destination');

    if (! button) {
        return;
    }

    const status = document.getElementById('share-status');

    button.addEventListener('click', async () => {
        const shareData = {
            title: button.dataset.shareTitle ?? document.title,
            text: button.dataset.shareText ?? '',
            url: window.location.href,
        };

        const announceShare = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
                announceShare('Destination shared.');
            } catch {
                announceShare('Sharing was cancelled.');
            }

            return;
        }

        try {
            await navigator.clipboard.writeText(window.location.href);
            announceShare('Link copied to clipboard.');
        } catch {
            announceShare('Copy the address bar URL to share this destination.');
        }
    });
};

const initGlobalProgressBar = () => {
    const bar = document.getElementById('global-progress-bar');
    const fill = document.getElementById('global-progress-bar-fill');

    if (! bar || ! fill) {
        return;
    }

    let timer = null;

    const start = () => {
        bar.classList.add('active');
        fill.style.width = '0%';
        window.clearTimeout(timer);

        timer = window.setTimeout(() => {
            fill.style.width = '30%';
            timer = window.setTimeout(() => {
                fill.style.width = '70%';
                timer = window.setTimeout(() => {
                    fill.style.width = '88%';
                }, 800);
            }, 300);
        }, 50);
    };

    const finish = () => {
        window.clearTimeout(timer);
        fill.style.width = '100%';
        window.setTimeout(() => {
            bar.classList.remove('active');
            window.setTimeout(() => {
                fill.style.width = '0%';
            }, 200);
        }, 150);
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');

        if (! link || ! link.href) {
            return;
        }

        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
            return;
        }

        const href = link.getAttribute('href') ?? '';

        if (href.startsWith('#') || href.startsWith('tel:') || href.startsWith('mailto:') || href.startsWith('javascript:')) {
            return;
        }

        try {
            const url = new URL(link.href, window.location.origin);

            if (url.origin !== window.location.origin) {
                return;
            }

            if (url.pathname === window.location.pathname && url.search === window.location.search) {
                return;
            }

            // Do not trigger global bar if link is an ajax category pill or pagination link handled locally
            if (link.hasAttribute('data-jenis') || link.closest('#destination-results')) {
                return;
            }

            start();
        } catch {}
    });

    window.addEventListener('pageshow', finish);
};

const init = () => {
    initThemeToggle();
    initGlobalProgressBar();
    initMobileNavigation();
    initLiveDestinations();
    initLiveMap();
    initDashboardSuggestions();
    initShareButton();
    initChatbotWidget();
    initMaps();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
