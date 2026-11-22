import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

const tileLayer = () => L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
});

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
}[character]));

<<<<<<< HEAD
const markerInfoContent = (destination) => `
    <article style="box-sizing:border-box;width:min(320px,calc(100vw - 72px));min-width:250px;max-width:320px;padding:2px;font-family:Arial,sans-serif;font-size:13px;line-height:1.45;color:#111827;opacity:1">
        ${destination.image ? `<img src="${escapeHtml(destination.image)}" alt="${escapeHtml(destination.name)}" style="display:block;width:100%;height:140px;object-fit:cover;border-radius:8px;margin:0 0 9px">` : ''}
        ${destination.image_attribution ? `<span style="display:block;margin-bottom:5px;font-size:10px;line-height:1.35;color:#4b5563">Foto: ${escapeHtml(destination.image_attribution.artist)} · ${escapeHtml(destination.image_attribution.license)} — <a href="${escapeHtml(destination.image_attribution.source_url)}" target="_blank" rel="noopener noreferrer" style="color:#7c2d12;font-weight:600">sumber</a> · <a href="${escapeHtml(destination.image_attribution.license_url)}" target="_blank" rel="noopener noreferrer" style="color:#7c2d12;font-weight:600">lisensi</a></span>` : ''}
        <strong style="display:block;margin:0 0 3px;font-size:16px;line-height:1.3;font-weight:700;letter-spacing:normal;color:#111827;opacity:1">${escapeHtml(destination.name)}</strong>
        <span style="display:block;font-size:12px;color:#374151">${escapeHtml(destination.province ?? destination.location)}${destination.type ? ` · ${escapeHtml(destination.type)}` : ''}</span>
        ${destination.rating_label || destination.price ? `<span style="display:block;margin-top:3px;font-size:12px;color:#374151">${destination.rating_label ? `⭐ ${escapeHtml(destination.rating_label)}` : ''}${destination.rating_label && destination.price ? ' · ' : ''}${escapeHtml(destination.price ?? '')}</span>` : ''}
        <nav aria-label="Tautan destinasi" style="display:flex;flex-wrap:wrap;gap:12px;margin-top:9px;font-size:12px;font-weight:700">
            ${destination.url ? `<a href="${escapeHtml(destination.url)}" style="color:#7c2d12">Lihat detail</a>` : ''}
            ${destination.google_maps_url ? `<a href="${escapeHtml(destination.google_maps_url)}" target="_blank" rel="noopener noreferrer" style="color:#7c2d12">Buka Google Maps</a>` : ''}
            ${destination.directions_url ? `<a href="${escapeHtml(destination.directions_url)}" target="_blank" rel="noopener noreferrer" data-route-destination="${escapeHtml(destination.id)}" data-route-name="${escapeHtml(destination.name)}" data-route-location="${escapeHtml(destination.location)}" data-route-latitude="${escapeHtml(destination.latitude)}" data-route-longitude="${escapeHtml(destination.longitude)}" style="color:#7c2d12">Petunjuk arah</a>` : ''}
        </nav>
    </article>
`;

let googleMapsPromise = null;

const loadGoogleMapsApi = (apiKey) => {
    if (window.google?.maps?.Map) {
        return Promise.resolve(window.google.maps);
    }

    if (googleMapsPromise) {
        return googleMapsPromise;
    }

    googleMapsPromise = new Promise((resolve, reject) => {
        const callbackName = '__wayfinderGoogleMapsReady';
        const script = document.createElement('script');
        const url = new URL('https://maps.googleapis.com/maps/api/js');

        window[callbackName] = () => {
            delete window[callbackName];

            if (! window.google?.maps?.Map) {
                reject(new Error('Google Maps JavaScript API tidak tersedia.'));

                return;
            }

            resolve(window.google.maps);
        };
        script.onerror = () => {
            delete window[callbackName];
            googleMapsPromise = null;
            reject(new Error('Google Maps JavaScript API gagal dimuat.'));
        };

        url.searchParams.set('key', apiKey);
        url.searchParams.set('loading', 'async');
        url.searchParams.set('callback', callbackName);
        url.searchParams.set('language', 'id');
        url.searchParams.set('region', 'ID');
        url.searchParams.set('libraries', 'marker,geometry');
        script.src = url.toString();
        script.async = true;
        document.head.appendChild(script);
    });

    return googleMapsPromise;
};

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
const toggleSidebar = (show) => {
    const sidebar = document.getElementById('map-sidebar');

    if (! sidebar) {
        return;
    }

    sidebar.classList.toggle('hidden', ! show);
    sidebar.classList.toggle('flex', show);
<<<<<<< HEAD
    document.querySelector('[data-toggle-sidebar="true"]')?.setAttribute('aria-expanded', String(show));
};

const initMapLegendDrag = (mapContainer) => {
    const panel = mapContainer.querySelector('[data-map-legend]');
    const handle = panel?.querySelector('[data-map-legend-handle]');

    if (! panel || ! handle) {
        return;
    }

    let activePointerId = null;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;
    let startTranslateX = 0;
    let startTranslateY = 0;
    let translateX = 0;
    let translateY = 0;
    let hasMoved = false;
    let animationFrame = 0;
    let pendingPosition = null;

    const clampPosition = (left, top) => ({
        left: Math.max(0, Math.min(mapContainer.clientWidth - panel.offsetWidth, left)),
        top: Math.max(0, Math.min(mapContainer.clientHeight - panel.offsetHeight, top)),
    });

    const applyTranslation = (x, y) => {
        translateX = x;
        translateY = y;
        panel.style.transform = `translate3d(${translateX}px, ${translateY}px, 0)`;
    };

    const clampPanel = () => {
        if (window.getComputedStyle(panel).display === 'none') {
            return;
        }

        const containerRect = mapContainer.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        const left = panelRect.left - containerRect.left;
        const top = panelRect.top - containerRect.top;
        const position = clampPosition(left, top);

        applyTranslation(
            translateX + position.left - left,
            translateY + position.top - top,
        );
    };

    const scheduleClamp = () => {
        window.requestAnimationFrame(clampPanel);
    };

    const finishDrag = (event) => {
        if (event.pointerId !== activePointerId) {
            return;
        }

        event.stopPropagation();

        if (animationFrame !== 0) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = 0;
        }

        if (pendingPosition !== null) {
            applyTranslation(pendingPosition.x, pendingPosition.y);
            pendingPosition = null;
        }

        const pointerId = activePointerId;
        activePointerId = null;

        if (handle.hasPointerCapture(pointerId)) {
            handle.releasePointerCapture(pointerId);
        }
    };

    handle.addEventListener('pointerdown', (event) => {
        if (! event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)
            || event.target.closest('a, button')) {
            return;
        }

        event.stopPropagation();
        clampPanel();
        activePointerId = event.pointerId;
        const containerRect = mapContainer.getBoundingClientRect();
        const panelRect = panel.getBoundingClientRect();
        startX = event.clientX;
        startY = event.clientY;
        startLeft = panelRect.left - containerRect.left;
        startTop = panelRect.top - containerRect.top;
        startTranslateX = translateX;
        startTranslateY = translateY;
        hasMoved = false;
        pendingPosition = null;
        handle.setPointerCapture(activePointerId);
    });

    handle.addEventListener('pointermove', (event) => {
        if (event.pointerId !== activePointerId) {
            return;
        }

        event.stopPropagation();

        const deltaX = event.clientX - startX;
        const deltaY = event.clientY - startY;

        if (! hasMoved && Math.hypot(deltaX, deltaY) <= 4) {
            return;
        }

        hasMoved = true;
        event.preventDefault();
        const position = clampPosition(startLeft + deltaX, startTop + deltaY);
        const baseLeft = startLeft - startTranslateX;
        const baseTop = startTop - startTranslateY;
        pendingPosition = {
            x: position.left - baseLeft,
            y: position.top - baseTop,
        };

        if (animationFrame === 0) {
            animationFrame = window.requestAnimationFrame(() => {
                if (pendingPosition !== null) {
                    applyTranslation(pendingPosition.x, pendingPosition.y);
                    pendingPosition = null;
                }

                animationFrame = 0;
            });
        }
    });

    handle.addEventListener('pointerup', finishDrag);
    handle.addEventListener('pointercancel', finishDrag);
    handle.addEventListener('lostpointercapture', finishDrag);
    window.addEventListener('resize', scheduleClamp);
    document.addEventListener('fullscreenchange', scheduleClamp);
    document.addEventListener('webkitfullscreenchange', scheduleClamp);
    clampPanel();
};

const initLeafletMapPage = (container, config) => {
=======
};

const initMapPage = () => {
    const container = document.getElementById('map');

    if (! container) {
        return;
    }

    const config = window.WayFinderMapConfig ?? {};
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    const markerColors = config.colors ?? {};
    const map = L.map(container).setView([2.8, 98.6], 8);
    const clusterGroup = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 50,
    });
    const individualGroup = L.layerGroup();
    const markers = new Map();
    let bounds = [];
    let showAllIndividually = false;

    tileLayer().addTo(map);
    map.addLayer(clusterGroup);

    const clearMarkers = () => {
        clusterGroup.clearLayers();
        individualGroup.clearLayers();
        markers.clear();
        bounds = [];
    };

    const renderMarkers = (destinations) => {
        clearMarkers();

        (Array.isArray(destinations) ? destinations : []).forEach((destination) => {
            const latitude = Number(destination.latitude);
            const longitude = Number(destination.longitude);

            if (! Number.isFinite(latitude) || ! Number.isFinite(longitude)) {
                return;
            }

            const color = markerColors[destination.type] ?? '#0f5132';
            const icon = L.divIcon({
                className: '',
                html: `<div style="width:18px;height:18px;border-radius:9999px;background:${escapeHtml(color)};border:2px solid #ffffff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9],
            });

<<<<<<< HEAD
            const marker = L.marker([latitude, longitude], { icon })
                .bindPopup(markerInfoContent(destination));
=======
            const marker = L.marker([latitude, longitude], { icon }).bindPopup(`
                <div style="min-width:180px">
                    <img src="${escapeHtml(destination.image)}" alt="${escapeHtml(destination.name)}" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px">
                    ${destination.image_attribution ? `<span style="display:block;font-size:9px;color:#4b5563">Photo: ${escapeHtml(destination.image_attribution.artist)} · ${escapeHtml(destination.image_attribution.license)} — <a href="${escapeHtml(destination.image_attribution.source_url)}" target="_blank" rel="noopener noreferrer">source</a> · <a href="${escapeHtml(destination.image_attribution.license_url)}" target="_blank" rel="noopener noreferrer">license</a></span>` : ''}
                    <strong style="display:block;font-size:13px">${escapeHtml(destination.name)}</strong>
                    <span style="font-size:11px;color:#4b5563">${escapeHtml(destination.province)} • ${escapeHtml(destination.type)}</span><br>
                    <span style="font-size:11px;color:#4b5563">⭐ ${escapeHtml(destination.rating_label)} • ${escapeHtml(destination.price)}</span><br>
                    <a href="${escapeHtml(destination.url)}" style="display:inline-block;margin-top:6px;font-size:11px;font-weight:700;color:#b74a1c">View details</a>
                </div>
            `);
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

            (showAllIndividually ? individualGroup : clusterGroup).addLayer(marker);
            markers.set(destination.id, marker);
            bounds.push([latitude, longitude]);
        });
    };

    const toggleAllMarkers = () => {
        const button = document.querySelector('[data-map-all]');

        if (! button) {
            return;
        }

        const currentGroup = showAllIndividually ? individualGroup : clusterGroup;
        const nextGroup = showAllIndividually ? clusterGroup : individualGroup;

        map.removeLayer(currentGroup);
        currentGroup.clearLayers();
        showAllIndividually = ! showAllIndividually;
        markers.forEach((marker) => nextGroup.addLayer(marker));
        map.addLayer(nextGroup);

        button.setAttribute('aria-pressed', String(showAllIndividually));
<<<<<<< HEAD
        const label = showAllIndividually ? 'Tampilkan penanda berkelompok' : 'Tampilkan penanda satu per satu';
        button.textContent = showAllIndividually ? 'KELOMPOK' : 'SATUAN';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
=======
        button.setAttribute('aria-label', showAllIndividually ? 'Show marker clusters' : 'Show markers individually');
        button.setAttribute('title', showAllIndividually ? 'Show marker clusters' : 'Show markers individually');
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
        button.classList.toggle('bg-brand-accent', showAllIndividually);
        button.classList.toggle('text-white', showAllIndividually);
        button.classList.toggle('bg-white', ! showAllIndividually);
        button.classList.toggle('text-gray-700', ! showAllIndividually);

        fitMap();
    };

    const fitMap = () => {
        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
        } else {
            map.setView([2.8, 98.6], 7);
        }
    };

    const focusMarker = (id) => {
        const marker = markers.get(id);

        if (! marker) {
            return;
        }

        const openMarker = () => {
            map.flyTo(marker.getLatLng(), 13, { duration: 0.8 });
            marker.openPopup();
        };

        if (showAllIndividually) {
            openMarker();
        } else {
            clusterGroup.zoomToShowLayer(marker, openMarker);
        }

        if (window.innerWidth < 768) {
            toggleSidebar(false);
        }
    };

    window.fitMap = fitMap;
    window.focusMarker = focusMarker;
    window.toggleSidebar = toggleSidebar;
    window.LiveMap = {
        update(destinations) {
            renderMarkers(destinations);
            fitMap();
        },
    };

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-toggle-sidebar]');

        if (toggle) {
            toggleSidebar(toggle.dataset.toggleSidebar === 'true');

            return;
        }

<<<<<<< HEAD
        if (event.target.closest('[data-map-all]')) {
            toggleAllMarkers();

            return;
        }

        const card = event.target.closest('[data-marker-focus]');

        if (card && ! event.target.closest('a, button')) {
            focusMarker(card.dataset.markerFocus);
        }
    });

    document.addEventListener('keydown', (event) => {
        const card = event.target.closest('[data-marker-focus]');

        if (! card || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        focusMarker(card.dataset.markerFocus);
    });

    renderMarkers(config.markers ?? []);
    fitMap();
};

const initGoogleMapPage = (container, config, maps) => {
    const markerColors = config.colors ?? {};
    const legend = container.querySelector('[data-map-legend]');
    legend?.remove();
    const map = new maps.Map(container, {
        center: { lat: 2.8, lng: 98.6 },
        zoom: 8,
        maxZoom: 19,
        mapTypeControl: false,
        zoomControl: true,
        zoomControlOptions: {
            position: maps.ControlPosition.TOP_RIGHT,
        },
        fullscreenControl: true,
        fullscreenControlOptions: {
            position: maps.ControlPosition.TOP_RIGHT,
        },
    });
    if (legend) {
        legend.classList.remove('absolute', 'top-4', 'left-4', 'z-[1000]');
        legend.style.margin = '16px';
        map.controls[maps.ControlPosition.TOP_LEFT].push(legend);
    }

    const infoWindow = new maps.InfoWindow({ maxWidth: 360 });
    const markers = new Map();
    let clusterMarkers = [];
    let destinations = [];
    let showAllIndividually = false;
    let destinationMarkersHidden = false;
    let routeFitGeneration = 0;
    let routeFitPending = false;
    const routePanel = document.getElementById('map-route-panel');
    const routeOptions = document.getElementById('route-options');
    const routeStatus = document.getElementById('route-planner-status');
    const routeSteps = document.getElementById('route-steps');
    const routeStepsList = document.getElementById('route-steps-list');
    const routeOriginSelect = document.getElementById('route-origin-select');
    const routeDestinationName = document.getElementById('route-destination-name');
    const routeDestinationLocation = document.getElementById('route-destination-location');
    const routeState = {
        mode: 'DRIVE',
        origin: null,
        destination: null,
        routes: [],
        routeLines: [],
        selectedRouteIndex: null,
        originMarker: null,
        destinationMarker: null,
        labelOverlay: null,
        previousView: null,
        requestId: 0,
    };

    const openMarkerInfo = (destination, marker) => {
        infoWindow.setContent(markerInfoContent(destination));
        infoWindow.open({ map, anchor: marker });
    };

    const clearMarkers = () => {
        markers.forEach((marker) => marker.setMap(null));
        clusterMarkers.forEach((marker) => marker.setMap(null));
        markers.clear();
        clusterMarkers = [];
    };

    const syncMarkerModeButton = () => {
        const button = document.querySelector('[data-map-all]');

        if (! button) {
            return;
        }

        const label = showAllIndividually ? 'Tampilkan penanda berkelompok' : 'Tampilkan penanda satu per satu';
        button.textContent = showAllIndividually ? 'KELOMPOK' : 'SATUAN';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', String(showAllIndividually));
        button.classList.toggle('bg-brand-accent', showAllIndividually);
        button.classList.toggle('text-white', showAllIndividually);
        button.classList.toggle('bg-white', ! showAllIndividually);
        button.classList.toggle('text-gray-700', ! showAllIndividually);
    };

    const centerOf = (group) => ({
        latitude: group.reduce((total, item) => total + item.latitude, 0) / group.length,
        longitude: group.reduce((total, item) => total + item.longitude, 0) / group.length,
    });

    const distanceMeters = (first, second) => {
        const radians = Math.PI / 180;
        const latitudeDelta = (second.latitude - first.latitude) * radians;
        const longitudeDelta = (second.longitude - first.longitude) * radians;
        const latitude = first.latitude * radians;
        const secondLatitude = second.latitude * radians;
        const haversine = Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(latitude) * Math.cos(secondLatitude) * Math.sin(longitudeDelta / 2) ** 2;

        return 6371000 * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
    };

    const renderMarkers = (items) => {
        clearMarkers();
        destinations = (Array.isArray(items) ? items : []).filter((destination) => {
            const latitude = Number(destination.latitude);
            const longitude = Number(destination.longitude);

            return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
                && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
        }).map((destination) => ({
            ...destination,
            latitude: Number(destination.latitude),
            longitude: Number(destination.longitude),
        }));

        destinations.forEach((destination) => {
            const color = markerColors[destination.type] ?? '#0f5132';
            const marker = new maps.Marker({
                map,
                position: { lat: destination.latitude, lng: destination.longitude },
                title: destination.name,
                icon: {
                    path: maps.SymbolPath.CIRCLE,
                    scale: 9,
                    fillColor: color,
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                },
            });

            marker.addListener('click', () => {
                openMarkerInfo(destination, marker);
            });
            markers.set(destination.id, marker);
        });

        if (! showAllIndividually) {
            const zoom = map.getZoom() ?? 8;
            const clusterDistance = 48 * 156543.03392 * Math.cos(2.8 * Math.PI / 180) / (2 ** zoom);
            const groups = [];

            destinations.forEach((destination) => {
                const group = groups.find((candidate) => (
                    distanceMeters(destination, centerOf(candidate)) < clusterDistance
                ));

                if (group) {
                    group.push(destination);
                } else {
                    groups.push([destination]);
                }
            });

            groups.filter((group) => group.length > 1).forEach((group) => {
                const center = centerOf(group);
                const position = { lat: center.latitude, lng: center.longitude };
                const marker = new maps.Marker({
                    map,
                    position,
                    title: `${group.length} destinasi`,
                    icon: {
                        path: maps.SymbolPath.CIRCLE,
                        scale: 17,
                        fillColor: '#0f5132',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                    },
                    label: {
                        text: String(group.length),
                        color: '#ffffff',
                        fontSize: '11px',
                        fontWeight: '700',
                    },
                });

                group.forEach((destination) => markers.get(destination.id)?.setMap(null));
                marker.addListener('click', () => {
                    map.panTo(position);
                    map.setZoom(Math.min((map.getZoom() ?? 8) + 2, 16));
                });
                clusterMarkers.push(marker);
            });
        }

        syncMarkerModeButton();

        if (destinationMarkersHidden) {
            markers.forEach((marker) => marker.setMap(null));
            clusterMarkers.forEach((marker) => marker.setMap(null));
        }
    };

    const fitMap = () => {
        if (destinations.length > 0) {
            const bounds = new maps.LatLngBounds();

            destinations.forEach((destination) => {
                bounds.extend({ lat: destination.latitude, lng: destination.longitude });
            });

            if (destinations.length === 1) {
                map.setCenter(bounds.getCenter());
                map.setZoom(12);

                return;
            }

            map.fitBounds(bounds, 40);
            maps.event.addListenerOnce(map, 'idle', () => {
                if ((map.getZoom() ?? 0) > 12) {
                    map.setZoom(12);
                }
            });
        } else {
            map.setCenter({ lat: 2.8, lng: 98.6 });
            map.setZoom(7);
        }
    };

    const isValidRoutePoint = (point) => point
        && Number.isFinite(point.latitude) && point.latitude >= -90 && point.latitude <= 90
        && Number.isFinite(point.longitude) && point.longitude >= -180 && point.longitude <= 180;

    const setRoutePanelVisible = (visible) => {
        routePanel?.classList.toggle('hidden', ! visible);
        routePanel?.classList.toggle('flex', visible);
        document.querySelector('[data-map-browser-header]')?.classList.toggle('hidden', visible);
        document.getElementById('map-results')?.classList.toggle('hidden', visible);
        document.querySelector('[data-map-browser-footer]')?.classList.toggle('hidden', visible);

        if (visible && window.innerWidth < 768) {
            toggleSidebar(true);
        }
    };

    const clearRouteVisuals = () => {
        setDestinationMarkersHidden(false);
        routeState.routeLines.forEach(({ polyline, outline }) => {
            polyline.setMap(null);
            outline?.setMap(null);
        });
        routeState.routeLines = [];
        routeState.routes = [];
        routeState.selectedRouteIndex = null;
        routeState.originMarker?.setMap(null);
        routeState.destinationMarker?.setMap(null);
        routeState.originMarker = null;
        routeState.destinationMarker = null;
        routeState.labelOverlay?.setMap(null);
        routeState.labelOverlay = null;
        routeOptions?.replaceChildren();
        routeStepsList?.replaceChildren();

        if (routeSteps) {
            routeSteps.open = false;
            routeSteps.classList.add('hidden');
        }
    };

    const captureMapView = () => {
        const center = map.getCenter();
        const zoom = map.getZoom();

        if (! center || typeof zoom !== 'number') {
            return null;
        }

        return {
            center: center.toJSON(),
            zoom,
        };
    };

    const setDestinationMarkersHidden = (hidden) => {
        if (destinationMarkersHidden === hidden) {
            return;
        }

        destinationMarkersHidden = hidden;

        if (hidden) {
            markers.forEach((marker) => marker.setMap(null));
            clusterMarkers.forEach((marker) => marker.setMap(null));

            return;
        }

        renderMarkers(destinations);
    };

    const syncRouteOriginSelect = (origin) => {
        if (! routeOriginSelect || ! origin) {
            return;
        }

        const value = origin.id ?? origin.source;
        let option = Array.from(routeOriginSelect.options).find((candidate) => candidate.value === String(value));

        if (! option) {
            option = document.createElement('option');
            option.value = String(value);
            option.textContent = `${origin.name} — titik asal`;
            routeOriginSelect.insertBefore(option, routeOriginSelect.querySelector('[value="__current"]'));
        }

        routeOriginSelect.value = String(value);
    };

    const updateRouteDestination = (destination) => {
        routeDestinationName.textContent = destination?.name ?? 'Pilih destinasi';
        routeDestinationLocation.textContent = destination?.location ?? '—';
    };

    const createRouteLabel = (position, label) => {
        const overlay = new maps.OverlayView();
        const element = document.createElement('div');
        element.textContent = label;
        Object.assign(element.style, {
            position: 'absolute',
            transform: 'translate(-50%, -100%)',
            padding: '7px 10px',
            border: '1px solid #1e3a8a',
            borderRadius: '9999px',
            background: '#ffffff',
            color: '#111827',
            boxShadow: '0 2px 8px rgba(15, 23, 42, .25)',
            font: '700 12px Arial, sans-serif',
            whiteSpace: 'nowrap',
        });
        overlay.onAdd = function () {
            this.getPanes()?.floatPane?.appendChild(element);
        };
        overlay.draw = function () {
            const pixel = this.getProjection()?.fromLatLngToDivPixel(position);

            if (pixel) {
                element.style.left = `${pixel.x}px`;
                element.style.top = `${pixel.y}px`;
            }
        };
        overlay.onRemove = function () {
            element.remove();
        };
        overlay.setMap(map);

        return overlay;
    };

    const renderRouteSteps = (route) => {
        routeStepsList?.replaceChildren();
        const steps = Array.isArray(route.steps) ? route.steps : [];

        steps.forEach((step) => {
            if (typeof step?.instruction !== 'string' || step.instruction.trim() === '') {
                return;
            }

            const item = document.createElement('li');
            item.append(document.createTextNode(step.instruction));

            if (typeof step.distanceText === 'string' && step.distanceText !== '') {
                const distance = document.createElement('span');
                distance.className = 'ml-1 font-semibold text-gray-500';
                distance.textContent = `(${step.distanceText})`;
                item.append(distance);
            }

            routeStepsList?.append(item);
        });

        routeSteps?.classList.toggle('hidden', steps.length === 0);
    };

    const selectRoute = (routeIndex) => {
        const selected = routeState.routeLines.find((item) => item.route.index === routeIndex);

        if (! selected) {
            return;
        }

        routeState.selectedRouteIndex = routeIndex;
        routeState.routeLines.forEach((item) => {
            const isSelected = item.route.index === routeIndex;

            item.polyline.setOptions(isSelected
                ? { strokeColor: '#2563eb', strokeOpacity: 1, strokeWeight: 7, zIndex: 20 }
                : { strokeColor: '#94a3b8', strokeOpacity: 0.85, strokeWeight: 5, zIndex: 10 });

            if (isSelected) {
                if (! item.outline) {
                    item.outline = new maps.Polyline({
                        map,
                        path: item.path,
                        clickable: false,
                        strokeColor: '#1e3a8a',
                        strokeOpacity: 1,
                        strokeWeight: 11,
                        zIndex: 19,
                    });
                } else {
                    item.outline.setMap(map);
                }
            } else {
                item.outline?.setMap(null);
            }
        });

        routeOptions?.querySelectorAll('[data-route-option]').forEach((button) => {
            const isSelected = Number(button.dataset.routeOption) === routeIndex;
            button.classList.toggle('border-blue-600', isSelected);
            button.classList.toggle('bg-blue-50', isSelected);
            button.classList.toggle('border-gray-200', ! isSelected);
            button.setAttribute('aria-pressed', String(isSelected));
        });

        renderRouteSteps(selected.route);
        routeState.labelOverlay?.setMap(null);
        const middlePoint = selected.path[Math.floor(selected.path.length / 2)];
        const modeIcon = { DRIVE: '🚗', TWO_WHEELER: '🏍️', WALK: '🚶' }[routeState.mode] ?? '🚗';
        routeState.labelOverlay = createRouteLabel(
            middlePoint,
            `${modeIcon} ${selected.route.durationText} • ${selected.route.distanceText}`,
        );

        const bounds = new maps.LatLngBounds();
        selected.path.forEach((point) => bounds.extend(point));
        const fitGeneration = ++routeFitGeneration;
        routeFitPending = true;
        map.fitBounds(bounds, 56);
        maps.event.addListenerOnce(map, 'idle', () => {
            if (fitGeneration === routeFitGeneration) {
                routeFitPending = false;
            }
        });
    };

    const renderRouteOptions = () => {
        if (! routeOptions) {
            return;
        }

        routeOptions.replaceChildren();
        routeState.routes.forEach((route) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.routeOption = String(route.index);
            button.setAttribute('aria-pressed', 'false');
            button.className = 'w-full rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-blue-300';

            const summary = document.createElement('span');
            summary.className = 'block text-sm font-bold text-gray-900';
            summary.textContent = route.summary;
            const detail = document.createElement('span');
            detail.className = 'mt-1 block text-xs text-gray-600';
            detail.textContent = `${route.durationText} · ${route.distanceText}`;
            button.append(summary, detail);
            button.addEventListener('click', () => selectRoute(route.index));
            routeOptions.append(button);
        });
    };

    const drawRouteEndpoints = () => {
        const origin = routeState.origin;
        const destination = routeState.destination;

        if (! origin || ! destination) {
            return;
        }

        routeState.originMarker = new maps.Marker({
            map,
            position: { lat: origin.latitude, lng: origin.longitude },
            title: `Asal: ${origin.name}`,
            zIndex: 1000,
            icon: {
                path: maps.SymbolPath.CIRCLE,
                scale: 9,
                fillColor: '#ffffff',
                fillOpacity: 1,
                strokeColor: '#1e3a8a',
                strokeWeight: 4,
            },
        });
        routeState.destinationMarker = new maps.Marker({
            map,
            position: { lat: destination.latitude, lng: destination.longitude },
            title: `Tujuan: ${destination.name}`,
            zIndex: 1001,
        });
    };

    const requestRoutes = async () => {
        if (! isValidRoutePoint(routeState.origin) || ! isValidRoutePoint(routeState.destination)) {
            if (routeStatus) {
                routeStatus.textContent = 'Pilih titik asal atau gunakan lokasi Anda untuk menghitung rute.';
            }

            return;
        }

        const requestId = ++routeState.requestId;
        clearRouteVisuals();

        if (typeof config.routeComputeUrl !== 'string' || config.routeComputeUrl === '') {
            if (routeStatus) {
                routeStatus.textContent = 'Layanan rute belum dikonfigurasi.';
            }

            return;
        }

        if (! maps.geometry?.encoding?.decodePath) {
            if (routeStatus) {
                routeStatus.textContent = 'Pustaka Google Maps untuk menggambar rute tidak tersedia.';
            }

            return;
        }

        if (routeStatus) {
            routeStatus.textContent = 'Sedang menghitung rute...';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch(config.routeComputeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    origin_lat: routeState.origin.latitude,
                    origin_lng: routeState.origin.longitude,
                    destination_lat: routeState.destination.latitude,
                    destination_lng: routeState.destination.longitude,
                    travel_mode: routeState.mode,
                }),
            });
            const payload = await response.json();

            if (requestId !== routeState.requestId) {
                return;
            }

            if (! response.ok || payload?.available !== true || ! Array.isArray(payload.routes)) {
                if (routeStatus) {
                    routeStatus.textContent = payload?.message ?? 'Rute belum dapat dihitung. Periksa koneksi atau coba lagi.';
                }

                return;
            }

            routeState.routes = payload.routes;
            routeState.routes.forEach((route) => {
                if (! Number.isInteger(route.index) || typeof route.encodedPolyline !== 'string') {
                    return;
                }

                try {
                    const path = maps.geometry.encoding.decodePath(route.encodedPolyline);

                    if (path.length < 2) {
                        return;
                    }

                    const polyline = new maps.Polyline({
                        map,
                        path,
                        clickable: true,
                        strokeColor: '#94a3b8',
                        strokeOpacity: 0.85,
                        strokeWeight: 5,
                        zIndex: 10,
                    });
                    const line = { route, path, polyline, outline: null };
                    polyline.addListener('click', () => selectRoute(route.index));
                    routeState.routeLines.push(line);
                } catch (error) {
                    console.warn('WayFinder: geometri rute tidak dapat dibaca.', error);
                }
            });

            routeState.routes = routeState.routeLines.map((item) => item.route);

            if (routeState.routeLines.length === 0) {
                if (routeStatus) {
                    routeStatus.textContent = 'Rute belum dapat ditampilkan. Silakan coba lagi.';
                }

                return;
            }

            renderRouteOptions();
            setDestinationMarkersHidden(true);
            drawRouteEndpoints();
            const defaultRoute = routeState.routes.find((route) => route.isDefault === true) ?? routeState.routes[0];
            selectRoute(defaultRoute.index);

            if (routeStatus) {
                routeStatus.textContent = `Ditemukan ${routeState.routes.length} pilihan rute menuju ${routeState.destination.name}.`;
            }
        } catch (error) {
            if (requestId !== routeState.requestId) {
                return;
            }

            console.warn('WayFinder: permintaan rute tidak berhasil.', error);

            if (routeStatus) {
                routeStatus.textContent = 'Rute belum dapat dihitung. Periksa koneksi atau coba lagi.';
            }
        }
    };

    const planRoute = (destination) => {
        const normalized = {
            id: destination?.id ?? null,
            name: String(destination?.name ?? '').trim(),
            location: String(destination?.location ?? '').trim(),
            latitude: Number(destination?.latitude),
            longitude: Number(destination?.longitude),
        };

        if (! normalized.name || ! isValidRoutePoint(normalized)) {
            return false;
        }

        routeState.requestId += 1;
        if (routeState.previousView === null) {
            routeState.previousView = captureMapView();
        }
        clearRouteVisuals();
        routeState.destination = normalized;
        updateRouteDestination(normalized);
        infoWindow.close();
        setRoutePanelVisible(true);

        if (routeStatus) {
            routeStatus.textContent = routeState.origin
                ? 'Tujuan dipilih. Sedang menyiapkan rute...'
                : 'Pilih titik asal atau gunakan lokasi Anda untuk menghitung rute.';
        }

        if (routeState.origin) {
            requestRoutes();
        }

        return true;
    };

    const setRouteOrigin = (origin) => {
        routeState.origin = origin;
        syncRouteOriginSelect(origin);

        if (routeState.destination) {
            requestRoutes();
        } else if (routeStatus) {
            routeStatus.textContent = 'Titik asal dipilih. Pilih destinasi untuk menghitung rute.';
        }
    };

    const useCurrentLocation = () => {
        if (! navigator.geolocation) {
            setRouteOrigin({
                id: null,
                source: '__medan',
                name: 'Pusat Kota Medan',
                latitude: 3.5952,
                longitude: 98.6722,
                location: 'Sumatera Utara',
            });

            if (routeStatus) {
                routeStatus.textContent = 'Lokasi tidak tersedia; titik asal menggunakan pusat Kota Medan.';
            }

            return;
        }

        if (routeStatus) {
            routeStatus.textContent = 'Meminta akses lokasi perangkat...';
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                setRouteOrigin({
                    id: null,
                    source: '__current',
                    name: 'Lokasi Anda',
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    location: 'Lokasi perangkat',
                });
            },
            () => {
                setRouteOrigin({
                    id: null,
                    source: '__medan',
                    name: 'Pusat Kota Medan',
                    latitude: 3.5952,
                    longitude: 98.6722,
                    location: 'Sumatera Utara',
                });

                if (routeStatus) {
                    routeStatus.textContent = 'Izin lokasi tidak tersedia; titik asal menggunakan pusat Kota Medan.';
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
        );
    };

    const closeRoutePlanner = () => {
        routeState.requestId += 1;
        clearRouteVisuals();
        const previousView = routeState.previousView;
        routeState.previousView = null;
        routeState.origin = null;
        routeState.destination = null;
        routeOriginSelect && (routeOriginSelect.value = '');
        updateRouteDestination(null);
        setRoutePanelVisible(false);

        if (previousView) {
            const restorePreviousView = () => {
                map.setCenter(previousView.center);
                map.setZoom(previousView.zoom);
            };

            if (routeFitPending) {
                const fitGeneration = routeFitGeneration;
                maps.event.addListenerOnce(map, 'idle', () => {
                    if (fitGeneration === routeFitGeneration) {
                        routeFitPending = false;
                        restorePreviousView();
                    }
                });
            } else {
                restorePreviousView();
            }
        }

        if (routeStatus) {
            routeStatus.textContent = 'Pilih titik asal atau gunakan lokasi Anda untuk menghitung rute.';
        }
    };

    const populateRouteOriginOptions = () => {
        if (! routeOriginSelect) {
            return;
        }

        const currentOption = routeOriginSelect.querySelector('[value="__current"]');
        const originOptions = (Array.isArray(config.markers) ? config.markers : [])
            .filter((destination) => {
                const latitude = Number(destination.latitude);
                const longitude = Number(destination.longitude);

                return destination.id && typeof destination.name === 'string'
                    && Number.isFinite(latitude) && latitude >= -90 && latitude <= 90
                    && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180;
            })
            .sort((first, second) => first.name.localeCompare(second.name, 'id'));

        originOptions.forEach((destination) => {
            const option = document.createElement('option');
            option.value = String(destination.id);
            option.textContent = `${destination.name} — ${destination.location ?? 'Sumatera Utara'}`;
            option.dataset.destinationOption = 'true';
            routeOriginSelect.insertBefore(option, currentOption);
        });
    };

    const swapRouteDirection = () => {
        if (! routeState.origin || ! routeState.destination) {
            if (routeStatus) {
                routeStatus.textContent = 'Pilih titik asal dan tujuan sebelum menukar arah.';
            }

            return;
        }

        const previousOrigin = routeState.origin;
        routeState.origin = {
            ...routeState.destination,
            source: routeState.destination.source ?? '__current',
        };
        routeState.destination = previousOrigin;
        syncRouteOriginSelect(routeState.origin);
        updateRouteDestination(routeState.destination);
        requestRoutes();
    };

    const toggleAllMarkers = () => {
        showAllIndividually = ! showAllIndividually;
        renderMarkers(destinations);
        fitMap();
    };

    const focusMarker = (id) => {
        const destination = destinations.find((item) => item.id === id);

        if (! destination) {
            return;
        }

        showAllIndividually = true;
        renderMarkers(destinations);
        map.panTo({ lat: destination.latitude, lng: destination.longitude });
        map.setZoom(13);
        const marker = markers.get(id);

        if (marker) {
            openMarkerInfo(destination, marker);
        }

        if (window.innerWidth < 768) {
            toggleSidebar(false);
        }
    };

    map.addListener('zoom_changed', () => {
        if (! showAllIndividually) {
            renderMarkers(destinations);
        }
    });

    window.fitMap = fitMap;
    window.focusMarker = focusMarker;
    window.toggleSidebar = toggleSidebar;
    window.LiveMap = {
        update(items) {
            renderMarkers(items);
            fitMap();
        },
        planRoute,
    };

    populateRouteOriginOptions();
    routeOriginSelect?.addEventListener('change', () => {
        const selectedValue = routeOriginSelect.value;

        if (selectedValue === '__current') {
            useCurrentLocation();

            return;
        }

        if (selectedValue === '__medan') {
            setRouteOrigin({
                id: null,
                source: '__medan',
                name: 'Pusat Kota Medan',
                latitude: 3.5952,
                longitude: 98.6722,
                location: 'Sumatera Utara',
            });

            return;
        }

        const destination = destinations.find((item) => String(item.id) === selectedValue)
            ?? (Array.isArray(config.markers) ? config.markers.find((item) => String(item.id) === selectedValue) : null);

        if (! destination) {
            routeState.origin = null;
            routeState.requestId += 1;
            clearRouteVisuals();

            if (routeStatus) {
                routeStatus.textContent = 'Pilih titik asal atau gunakan lokasi Anda untuk menghitung rute.';
            }

            return;
        }

        setRouteOrigin({
            id: destination.id,
            name: String(destination.name ?? 'Destinasi WayFinder'),
            location: String(destination.location ?? ''),
            latitude: Number(destination.latitude),
            longitude: Number(destination.longitude),
        });
    });

    document.querySelectorAll('[data-route-mode]').forEach((button) => {
        button.addEventListener('click', () => {
            routeState.mode = button.dataset.routeMode ?? 'DRIVE';
            document.querySelectorAll('[data-route-mode]').forEach((modeButton) => {
                const isSelected = modeButton === button;
                modeButton.setAttribute('aria-pressed', String(isSelected));
                modeButton.classList.toggle('border-blue-600', isSelected);
                modeButton.classList.toggle('bg-blue-50', isSelected);
                modeButton.classList.toggle('text-blue-800', isSelected);
                modeButton.classList.toggle('border-gray-200', ! isSelected);
                modeButton.classList.toggle('bg-white', ! isSelected);
                modeButton.classList.toggle('text-gray-700', ! isSelected);
            });

            if (routeState.origin && routeState.destination) {
                requestRoutes();
            }
        });
    });

    document.querySelector('[data-route-current-location]')?.addEventListener('click', useCurrentLocation);
    document.querySelector('[data-route-swap]')?.addEventListener('click', swapRouteDirection);
    document.querySelectorAll('[data-route-close], [data-route-reset]').forEach((button) => {
        button.addEventListener('click', closeRoutePlanner);
    });

    document.addEventListener('click', (event) => {
        const routeTrigger = event.target.closest('[data-route-destination]');

        if (routeTrigger && typeof window.LiveMap?.planRoute === 'function') {
            const opened = window.LiveMap.planRoute({
                id: routeTrigger.dataset.routeDestination,
                name: routeTrigger.dataset.routeName,
                location: routeTrigger.dataset.routeLocation,
                latitude: routeTrigger.dataset.routeLatitude,
                longitude: routeTrigger.dataset.routeLongitude,
            });

            if (opened) {
                event.preventDefault();

                return;
            }
        }

        const toggle = event.target.closest('[data-toggle-sidebar]');

        if (toggle) {
            toggleSidebar(toggle.dataset.toggleSidebar === 'true');
=======
        if (event.target.closest('[data-map-fit]')) {
            fitMap();
>>>>>>> ee7c4b41627891394572865c2960230988e32b45

            return;
        }

        if (event.target.closest('[data-map-all]')) {
            toggleAllMarkers();

            return;
        }

        const card = event.target.closest('[data-marker-focus]');

        if (card && ! event.target.closest('a, button')) {
            focusMarker(card.dataset.markerFocus);
        }
    });

    document.addEventListener('keydown', (event) => {
        const card = event.target.closest('[data-marker-focus]');

        if (! card || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        focusMarker(card.dataset.markerFocus);
    });

    renderMarkers(config.markers ?? []);
    fitMap();
};

<<<<<<< HEAD
const initGoogleDetailMap = (container, maps, latitude, longitude) => {
    const position = { lat: latitude, lng: longitude };
    const map = new maps.Map(container, {
        center: position,
        zoom: 13,
        mapTypeControl: false,
        zoomControl: true,
        zoomControlOptions: {
            position: maps.ControlPosition.TOP_RIGHT,
        },
        fullscreenControl: true,
        fullscreenControlOptions: {
            position: maps.ControlPosition.TOP_RIGHT,
        },
    });
    const marker = new maps.Marker({
        map,
        position,
        title: container.dataset.name ?? '',
    });
    const destination = {
        id: container.dataset.id,
        name: container.dataset.name,
        location: container.dataset.location,
        province: container.dataset.location,
        url: container.dataset.detailUrl,
        google_maps_url: container.dataset.googleMapsUrl,
        directions_url: container.dataset.directionsUrl,
        latitude,
        longitude,
    };
    const infoWindow = new maps.InfoWindow({ maxWidth: 360 });

    marker.addListener('click', () => {
        infoWindow.setContent(markerInfoContent(destination));
        infoWindow.open({ map, anchor: marker });
    });
};

const initLeafletDetailMap = (container, latitude, longitude) => {
    const map = L.map(container, { scrollWheelZoom: false }).setView([latitude, longitude], 13);
    tileLayer().addTo(map);

    const icon = L.divIcon({
        className: '',
        html: '<div style="width:22px;height:22px;border-radius:9999px;background:#b74a1c;border:3px solid #ffffff;box-shadow:0 1px 5px rgba(0,0,0,.4)"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });
    const destination = {
        id: container.dataset.id,
        name: container.dataset.name,
        location: container.dataset.location,
        province: container.dataset.location,
        url: container.dataset.detailUrl,
        google_maps_url: container.dataset.googleMapsUrl,
        directions_url: container.dataset.directionsUrl,
        latitude,
        longitude,
    };
    const marker = L.marker([latitude, longitude], { icon })
        .addTo(map)
        .bindPopup(markerInfoContent(destination));

    marker.openPopup();
};

=======
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
const initDetailMap = () => {
    const container = document.getElementById('detail-map');

    if (! container) {
        return;
    }

    const latitude = Number(container.dataset.latitude);
    const longitude = Number(container.dataset.longitude);

<<<<<<< HEAD
    if (! Number.isFinite(latitude) || latitude < -90 || latitude > 90
        || ! Number.isFinite(longitude) || longitude < -180 || longitude > 180) {
        return;
    }

    const config = window.WayFinderMapConfig ?? {};

    if (typeof config.googleMapsKey === 'string' && config.googleMapsKey !== '') {
        loadGoogleMapsApi(config.googleMapsKey)
            .then((maps) => initGoogleDetailMap(container, maps, latitude, longitude))
            .catch((error) => {
                console.error('WayFinder: Google Maps gagal dimuat di halaman destinasi; menggunakan peta OpenStreetMap.', error);
                initLeafletDetailMap(container, latitude, longitude);
            });

        return;
    }

    initLeafletDetailMap(container, latitude, longitude);
};

export const initMaps = () => {
    const container = document.getElementById('map');
    const config = window.WayFinderMapConfig ?? {};

    if (container && typeof config.googleMapsKey === 'string' && config.googleMapsKey !== '') {
        initMapLegendDrag(container);
        loadGoogleMapsApi(config.googleMapsKey)
            .then((maps) => {
                initGoogleMapPage(container, config, maps);
            })
            .catch((error) => {
                console.error('WayFinder: Google Maps gagal dimuat; menggunakan peta Leaflet.', error);
                const status = document.getElementById('map-live-status');

                if (status) {
                    status.textContent = 'Google Maps tidak tersedia; peta OpenStreetMap ditampilkan sebagai pengganti.';
                }

                initLeafletMapPage(container, config);
            });
    } else if (container) {
        initMapLegendDrag(container);
        initLeafletMapPage(container, config);
    }

=======
    if (! Number.isFinite(latitude) || ! Number.isFinite(longitude)) {
        return;
    }

    const map = L.map(container, { scrollWheelZoom: false }).setView([latitude, longitude], 13);
    tileLayer().addTo(map);

    const icon = L.divIcon({
        className: '',
        html: '<div style="width:22px;height:22px;border-radius:9999px;background:#b74a1c;border:3px solid #ffffff;box-shadow:0 1px 5px rgba(0,0,0,.4)"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });

    L.marker([latitude, longitude], { icon })
        .addTo(map)
        .bindPopup(`<strong>${escapeHtml(container.dataset.name)}</strong><br>${escapeHtml(container.dataset.location)}`)
        .openPopup();
};

export const initMaps = () => {
    initMapPage();
>>>>>>> ee7c4b41627891394572865c2960230988e32b45
    initDetailMap();
};
