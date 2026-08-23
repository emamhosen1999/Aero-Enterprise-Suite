import React, { useEffect, useRef } from 'react';
import { MapContainer, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-fullscreen/dist/Leaflet.fullscreen.js';
import 'leaflet-fullscreen/dist/leaflet.fullscreen.css';
import {
    TILE_LAYERS,
    DEFAULT_MAP_CENTER,
    DEFAULT_ZOOM,
    MIN_ZOOM,
    MAX_ZOOM,
    MAP_INJECTED_STYLES
} from './mapConstants';

// Helper component to handle imperative map operations (fitBounds, flyTo)
const MapController = React.memo(({
    fitBoundsTrigger,
    users,
    flyToCoords,
    attendanceTypeConfigs
}) => {
    const map = useMap();

    // Handle Fit Bounds
    useEffect(() => {
        if (!map || fitBoundsTrigger === 0) return;

        const bounds = L.latLngBounds([]);

        // Add user markers to bounds
        (users || []).forEach(u => {
            const inLoc = u.punchin_location || u.location;
            const outLoc = u.punchout_location;

            if (inLoc && inLoc.lat && inLoc.lng) {
                bounds.extend([parseFloat(inLoc.lat), parseFloat(inLoc.lng)]);
            }
            if (outLoc && outLoc.lat && outLoc.lng) {
                bounds.extend([parseFloat(outLoc.lat), parseFloat(outLoc.lng)]);
            }
        });

        // Add geofence polygons / routes to bounds if available
        (attendanceTypeConfigs || []).forEach(cfg => {
            if (cfg.config?.polygon) {
                cfg.config.polygon.forEach(p => {
                    if (p.lat && p.lng) bounds.extend([parseFloat(p.lat), parseFloat(p.lng)]);
                });
            }
            if (cfg.config?.waypoints) {
                cfg.config.waypoints.forEach(w => {
                    if (w.lat && w.lng) bounds.extend([parseFloat(w.lat), parseFloat(w.lng)]);
                });
            }
        });

        if (bounds.isValid()) {
            map.fitBounds(bounds, {
                padding: [60, 60],
                maxZoom: 15,
                animate: true,
                duration: 0.8
            });
        }
    }, [map, fitBoundsTrigger, users, attendanceTypeConfigs]);

    // Handle FlyTo specific coordinate
    useEffect(() => {
        if (!map || !flyToCoords) return;
        map.flyTo(flyToCoords, 16, {
            animate: true,
            duration: 1.2
        });
    }, [map, flyToCoords]);

    return null;
});

MapController.displayName = 'MapController';

export const MapContainerView = React.memo(({
    currentTileId = 'voyager',
    users = [],
    attendanceTypeConfigs = [],
    fitBoundsTrigger = 0,
    flyToCoords = null,
    children
}) => {
    const tileConfig = TILE_LAYERS[currentTileId] || TILE_LAYERS.voyager;

    // Inject custom CSS styles for radar pulse and glassmorphism once
    useEffect(() => {
        const styleId = 'team-map-injected-styles';
        if (!document.getElementById(styleId)) {
            const styleEl = document.createElement('style');
            styleEl.id = styleId;
            styleEl.innerHTML = MAP_INJECTED_STYLES;
            document.head.appendChild(styleEl);
        }
    }, []);

    return (
        <div style={{ position: 'relative', width: '100%', height: '100%' }}>
            <MapContainer
                center={DEFAULT_MAP_CENTER}
                zoom={DEFAULT_ZOOM}
                minZoom={MIN_ZOOM}
                maxZoom={MAX_ZOOM}
                style={{ width: '100%', height: '100%', background: '#0f172a' }}
                scrollWheelZoom={true}
                doubleClickZoom={true}
                dragging={true}
                touchZoom={true}
                zoomControl={false}
                attributionControl={false}
            >
                {/* Active Tile Layer */}
                <TileLayer
                    key={tileConfig.id}
                    url={tileConfig.url}
                    subdomains={tileConfig.subdomains}
                    maxZoom={tileConfig.maxZoom}
                    attribution={tileConfig.attribution}
                />

                {/* Map Imperative Controller */}
                <MapController
                    fitBoundsTrigger={fitBoundsTrigger}
                    users={users}
                    flyToCoords={flyToCoords}
                    attendanceTypeConfigs={attendanceTypeConfigs}
                />

                {/* Children layers: Geofences, Living Markers */}
                {children}
            </MapContainer>
        </div>
    );
});

MapContainerView.displayName = 'MapContainerView';
export default MapContainerView;
