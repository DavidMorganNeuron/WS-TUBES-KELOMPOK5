const debounce = (callback, delay = 250) => {
    let timeoutId;

    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), delay);
    };
};

const numberFormatter = new Intl.NumberFormat('en-US');

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

const initLiveDestinations = () => {
    const form = document.querySelector('[data-live-destinations]');

    if (! form) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const results = document.getElementById('destination-results');
    const showing = document.getElementById('destination-showing');
    const reset = document.getElementById('destination-reset');
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
        results.classList.add('opacity-60');

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

            window.history.replaceState({}, '', urlWithParams(form.action, params).toString());
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
        } finally {
            if (currentRequest === requestId) {
                results.classList.remove('opacity-60');
            }
        }
    };

    const debouncedUpdate = debounce(update);

    input?.addEventListener('input', debouncedUpdate);
    form.querySelectorAll('select').forEach((select) => select.addEventListener('change', update));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        update();
    });
};

const initLiveMap = () => {
    const form = document.querySelector('[data-live-map]');

    if (! form) {
        return;
    }

    const input = form.querySelector('input[name="q"]');
    const results = document.getElementById('map-results');
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
        results.classList.add('opacity-60');

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

            window.history.replaceState({}, '', urlWithParams(form.action, params).toString());
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
        } finally {
            if (currentRequest === requestId) {
                results.classList.remove('opacity-60');
            }
        }
    };

    const debouncedUpdate = debounce(update);

    input?.addEventListener('input', debouncedUpdate);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
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
    const suggestUrl = form.dataset.suggestUrl;

    if (! input || ! panel || ! suggestUrl) {
        return;
    }

    let controller = null;
    let requestId = 0;
    let items = [];
    let activeIndex = -1;

    const closePanel = () => {
        panel.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        items.forEach((item) => item.classList.remove('bg-brand-light'));
        activeIndex = -1;
    };

    const setActive = (index) => {
        items.forEach((item) => item.classList.remove('bg-brand-light'));
        activeIndex = index;

        if (activeIndex >= 0 && items[activeIndex]) {
            items[activeIndex].classList.add('bg-brand-light');
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

            return;
        }

        const currentRequest = ++requestId;

        if (controller) {
            controller.abort();
        }

        controller = new AbortController();

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

            if (items.length === 0) {
                closePanel();
            } else {
                panel.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error(error);
            }
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

const initLiveSearch = () => {
    initLiveDestinations();
    initLiveMap();
    initDashboardSuggestions();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLiveSearch);
} else {
    initLiveSearch();
}
