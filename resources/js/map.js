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

const toggleSidebar = (show) => {
    const sidebar = document.getElementById('map-sidebar');

    if (! sidebar) {
        return;
    }

    sidebar.classList.toggle('hidden', ! show);
    sidebar.classList.toggle('flex', show);
};

const initMapPage = () => {
    const container = document.getElementById('map');

    if (! container) {
        return;
    }

    const config = window.WayFinderMapConfig ?? {};
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
        button.setAttribute('aria-label', showAllIndividually ? 'Show marker clusters' : 'Show markers individually');
        button.setAttribute('title', showAllIndividually ? 'Show marker clusters' : 'Show markers individually');
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

        if (event.target.closest('[data-map-fit]')) {
            fitMap();

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

const initDetailMap = () => {
    const container = document.getElementById('detail-map');

    if (! container) {
        return;
    }

    const latitude = Number(container.dataset.latitude);
    const longitude = Number(container.dataset.longitude);

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
    initDetailMap();
};
