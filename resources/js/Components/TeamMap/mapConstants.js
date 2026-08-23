/**
 * Team Locations GIS Map Constants & Styling Utilities
 * DBEDC Guardian 100/100 GIS Command Center
 */

export const TILE_LAYERS = {
    voyager: {
        id: 'voyager',
        name: 'Voyager (Crisp)',
        icon: 'Compass',
        url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 20,
        attribution: '&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
    },
    darkMatter: {
        id: 'darkMatter',
        name: 'Dark Matter (Midnight)',
        icon: 'Moon',
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 20,
        attribution: '&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
    },
    positron: {
        id: 'positron',
        name: 'Positron (Minimal)',
        icon: 'Sun',
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        subdomains: 'abcd',
        maxZoom: 20,
        attribution: '&copy; <a href="https://carto.com/">CARTO</a>, &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
    },
    satellite: {
        id: 'satellite',
        name: 'Satellite (Aerial HD)',
        icon: 'Globe',
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        subdomains: '',
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    },
    osm: {
        id: 'osm',
        name: 'OpenStreetMap',
        icon: 'Map',
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        subdomains: 'abc',
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }
};

export const DEFAULT_MAP_CENTER = [23.8103, 90.4125]; // Dhaka Expressway Central Coordinates
export const DEFAULT_ZOOM = 12;
export const MIN_ZOOM = 7;
export const MAX_ZOOM = 19;
export const AUTO_POLL_INTERVAL_SEC = 15;

export const THEME_COLORS = {
    active: '#10b981',      // Emerald green
    completed: '#3b82f6',   // Blue
    punchin: '#10b981',     // Green
    punchout: '#ef4444',    // Red / Crimson
    geofence: '#8b5cf6',    // Purple / Indigo
    route: '#06b6d4',       // Cyan
    flagged: '#f59e0b',     // Amber
    darkBg: '#0f172a',      // Slate 900
    cardGlass: 'rgba(15, 23, 42, 0.82)',
    lightGlass: 'rgba(255, 255, 255, 0.88)'
};

/**
 * Injected CSS for animated radar pulses, living markers, and glassmorphism styling
 */
export const MAP_INJECTED_STYLES = `
/* Living Radar Pulse Keyframes */
@keyframes radarPing {
    0% {
        transform: scale(0.7);
        opacity: 0.9;
    }
    50% {
        opacity: 0.5;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

@keyframes beaconGlow {
    0%, 100% {
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.6), 0 0 20px rgba(16, 185, 129, 0.3);
    }
    50% {
        box-shadow: 0 0 18px rgba(16, 185, 129, 0.9), 0 0 32px rgba(16, 185, 129, 0.5);
    }
}

@keyframes dashFlow {
    to {
        stroke-dashoffset: -24;
    }
}

/* Custom Marker Classes */
.living-marker-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.living-marker-wrapper:hover {
    transform: scale(1.15) translateY(-3px);
    z-index: 9999 !important;
}

.living-marker-radar-ring {
    position: absolute;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.25);
    border: 1.5px solid rgba(16, 185, 129, 0.85);
    animation: radarPing 2.2s cubic-bezier(0, 0.2, 0.8, 1) infinite;
    pointer-events: none;
}

.living-marker-core {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 2.5px solid #ffffff;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #ffffff;
    font-weight: 700;
    font-size: 13px;
    z-index: 2;
}

.living-marker-core.is-active {
    border-color: #10b981;
    animation: beaconGlow 2.5s ease-in-out infinite;
}

.living-marker-core.is-completed {
    border-color: #64748b;
}

.living-marker-core.is-punchin {
    border-color: #10b981;
}

.living-marker-core.is-punchout {
    border-color: #ef4444;
}

.living-marker-badge {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: #ffffff;
    z-index: 3;
}

/* Glassmorphism Leaflet Popup */
.leaflet-popup-content-wrapper {
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
    border-radius: 12px !important;
}

.leaflet-popup-content {
    margin: 0 !important;
    line-height: normal !important;
}

.leaflet-popup-tip {
    background: var(--color-panel-solid, #1e293b) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25) !important;
}

/* Centroid Labels for Polygons */
.geofence-centroid-badge {
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 3px 10px;
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Trajectory flowing dashes */
.patrol-trajectory-path {
    stroke-dasharray: 8 6;
    animation: dashFlow 1.2s linear infinite;
}
`;
