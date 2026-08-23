import React, { useEffect, useRef } from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';
import { THEME_COLORS } from './mapConstants';

export const MapGeofenceLayers = React.memo(({
    attendanceTypeConfigs = [],
    users = [],
    layerVisibility = { geofences: true, waypoints: true, trajectories: true }
}) => {
    const map = useMap();
    const layersRef = useRef([]);

    useEffect(() => {
        if (!map) return;

        // Clean up previous layers
        layersRef.current.forEach(layer => {
            try {
                map.removeLayer(layer);
            } catch (e) {
                // Ignore removal errors
            }
        });
        layersRef.current = [];

        if (!attendanceTypeConfigs || attendanceTypeConfigs.length === 0) return;

        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

        attendanceTypeConfigs.forEach((typeConfig, index) => {
            const { base_slug, config, name } = typeConfig;
            const zoneColor = colors[index % colors.length];

            // 1. Polygon Geofence Zones
            if (base_slug === 'geo_polygon' && config && layerVisibility.geofences) {
                const polygonPoints = config.polygon || [];
                const polygons = config.polygons || [];

                const processPoly = (pts, zoneTitle) => {
                    const validPts = pts.filter(p => p && p.lat && p.lng);
                    if (validPts.length < 3) return;

                    const coords = validPts.map(p => [parseFloat(p.lat), parseFloat(p.lng)]);

                    // Create polygon
                    const polygon = L.polygon(coords, {
                        color: zoneColor,
                        fillColor: zoneColor,
                        fillOpacity: 0.16,
                        weight: 2.5,
                        opacity: 0.85,
                        dashArray: '6, 6',
                    }).addTo(map);

                    // Calculate centroid
                    const bounds = polygon.getBounds();
                    const center = bounds.getCenter();

                    // Calculate how many users are inside or near this polygon
                    const insideCount = users.filter(u => {
                        const loc = u.punchin_location || u.punchout_location || u.location;
                        if (!loc || !loc.lat || !loc.lng) return false;
                        const userLatLng = L.latLng(parseFloat(loc.lat), parseFloat(loc.lng));
                        return bounds.contains(userLatLng);
                    }).length;

                    // Centroid Label Badge
                    const labelHtml = `
                        <div class="geofence-centroid-badge" style="border-color: ${zoneColor}88;">
                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:${zoneColor};"></span>
                            <span>${zoneTitle || name}</span>
                            ${insideCount > 0 ? `<span style="background:${zoneColor}; color:white; border-radius:10px; padding:0 6px; font-size:10px;">${insideCount} Officers</span>` : ''}
                        </div>
                    `;

                    const labelMarker = L.marker(center, {
                        icon: L.divIcon({
                            html: labelHtml,
                            className: 'geofence-label-marker',
                            iconSize: [120, 26],
                            iconAnchor: [60, 13]
                        }),
                        interactive: false
                    }).addTo(map);

                    // Popup
                    polygon.bindPopup(`
                        <div style="font-family: inherit; padding: 6px; min-width: 140px; color: var(--gray-12, #1e293b);">
                            <div style="font-weight: 700; color: ${zoneColor}; font-size: 13px; margin-bottom: 2px;">
                                🛡️ ${zoneTitle || name}
                            </div>
                            <div style="font-size: 11px; color: var(--gray-10, #64748b);">Geofence Zone Perimeter</div>
                            <div style="font-size: 11px; margin-top: 4px; font-weight: 600;">
                                Verified Officers: <span style="color:${zoneColor};">${insideCount}</span>
                            </div>
                        </div>
                    `);

                    layersRef.current.push(polygon);
                    layersRef.current.push(labelMarker);
                };

                if (polygonPoints.length >= 3) {
                    processPoly(polygonPoints, name);
                }

                polygons.forEach((poly, polyIdx) => {
                    if (poly.points && poly.points.length >= 3) {
                        processPoly(poly.points, poly.name || `${name} Zone ${polyIdx + 1}`);
                    }
                });
            }

            // 2. Route Waypoints & Corridors
            if (base_slug === 'route_waypoint' && config && layerVisibility.waypoints) {
                const rawWaypoints = config.waypoints || [];
                const routes = config.routes || [];

                const processRoute = (wps, routeTitle) => {
                    const validWaypoints = (wps || []).filter(w => w && w.lat && w.lng);
                    if (validWaypoints.length < 2) return;

                    const latLngs = validWaypoints.map(w => [parseFloat(w.lat), parseFloat(w.lng)]);

                    // Draw Corridor Polyline
                    const routeLine = L.polyline(latLngs, {
                        color: zoneColor,
                        weight: 3.5,
                        opacity: 0.75,
                        dashArray: '8, 6',
                    }).addTo(map);

                    layersRef.current.push(routeLine);

                    // Add Waypoint Markers
                    validWaypoints.forEach((wp, wpIdx) => {
                        const isStart = wpIdx === 0;
                        const isEnd = wpIdx === validWaypoints.length - 1;
                        const bgColor = isStart ? '#10b981' : isEnd ? '#ef4444' : zoneColor;

                        const markerHtml = `
                            <div style="
                                width: 26px;
                                height: 26px;
                                border-radius: 50%;
                                background: ${bgColor};
                                border: 2px solid #ffffff;
                                box-shadow: 0 3px 8px rgba(0,0,0,0.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: 800;
                                font-size: 11px;
                            ">
                                ${isStart ? 'S' : isEnd ? 'E' : (wpIdx + 1)}
                            </div>
                        `;

                        const wpMarker = L.marker([parseFloat(wp.lat), parseFloat(wp.lng)], {
                            icon: L.divIcon({
                                html: markerHtml,
                                className: 'waypoint-marker',
                                iconSize: [26, 26],
                                iconAnchor: [13, 13]
                            })
                        }).addTo(map);

                        wpMarker.bindPopup(`
                            <div style="font-family: inherit; padding: 4px; color: var(--gray-12, #1e293b);">
                                <strong style="color: ${zoneColor};">${routeTitle || name}</strong><br>
                                <span style="font-size: 11px; color: var(--gray-10, #64748b);">
                                    ${isStart ? '🚀 Route Start Point' : isEnd ? '🏁 Route End Point' : `Waypoint #${wpIdx + 1}`}
                                </span>
                            </div>
                        `);

                        layersRef.current.push(wpMarker);
                    });
                };

                if (rawWaypoints.length >= 2) {
                    processRoute(rawWaypoints, name);
                }

                routes.forEach((route, rIdx) => {
                    if (route.waypoints && route.waypoints.length >= 2) {
                        processRoute(route.waypoints, route.name || `${name} Route ${rIdx + 1}`);
                    }
                });
            }
        });

        return () => {
            layersRef.current.forEach(layer => {
                try {
                    map.removeLayer(layer);
                } catch (e) {}
            });
            layersRef.current = [];
        };
    }, [map, attendanceTypeConfigs, users, layerVisibility]);

    return null;
});

MapGeofenceLayers.displayName = 'MapGeofenceLayers';
export default MapGeofenceLayers;
