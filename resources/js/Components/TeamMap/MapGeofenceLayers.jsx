import React, { useEffect, useRef } from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';
import { THEME_COLORS } from './mapConstants';
import { normalizeCoord, fetchRoadRouteGeometry } from './roadRoutingService';

export const MapGeofenceLayers = React.memo(({
    attendanceTypeConfigs = [],
    users = [],
    layerVisibility = { geofences: true, waypoints: true, trajectories: true }
}) => {
    const map = useMap();
    const layersRef = useRef([]);

    useEffect(() => {
        if (!map) return;

        let isCancelled = false;

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

        const colors = ['#0284c7', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#14b8a6', '#f97316'];

        attendanceTypeConfigs.forEach((typeConfig, index) => {
            const { base_slug, slug, config, name } = typeConfig;
            const zoneColor = colors[index % colors.length];
            if (!config) return;

            // 1. Polygon Geofence Zones (Render if geo_polygon slug OR config contains polygon data)
            const isPolygonType = base_slug === 'geo_polygon' ||
                                  slug?.includes('polygon') ||
                                  slug?.includes('geofence') ||
                                  Boolean(config.polygon?.length || config.polygons?.length);

            if (isPolygonType && layerVisibility.geofences !== false) {
                const rawPolygon = config.polygon || [];
                const rawPolygons = config.polygons || [];

                const processPoly = (pts, zoneTitle) => {
                    const validPts = (pts || []).map(normalizeCoord).filter(Boolean);
                    if (validPts.length < 3) return;

                    const coords = validPts.map(p => [p.lat, p.lng]);

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

                    // Calculate active user headcount inside this polygon
                    const insideCount = users.filter(u => {
                        const loc = normalizeCoord(u.punchin_location || u.punchout_location || u.location);
                        if (!loc) return false;
                        return bounds.contains(L.latLng(loc.lat, loc.lng));
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

                if (rawPolygon.length >= 3) {
                    processPoly(rawPolygon, name);
                }

                rawPolygons.forEach((poly, polyIdx) => {
                    const pts = poly.points || poly.coordinates || poly;
                    if (Array.isArray(pts) && pts.length >= 3) {
                        processPoly(pts, poly.name || `${name} Zone ${polyIdx + 1}`);
                    }
                });
            }

            // 2. Route Waypoints & Corridors (Render if route_waypoint slug OR config contains waypoints / routes)
            const isRouteType = base_slug === 'route_waypoint' ||
                                slug?.includes('route') ||
                                slug?.includes('waypoint') ||
                                slug?.includes('patrol') ||
                                Boolean(config.waypoints?.length || config.routes?.length);

            if (isRouteType && layerVisibility.waypoints !== false) {
                const rawWaypoints = config.waypoints || [];
                const rawRoutes = config.routes || [];

                const processRoute = (wps, routeTitle, toleranceMeters) => {
                    const validWaypoints = (wps || []).map(normalizeCoord).filter(Boolean);
                    if (validWaypoints.length === 0) return;

                    const initialLatLngs = validWaypoints.map(w => [w.lat, w.lng]);

                    let routeLineGlow = null;
                    let routeLineMain = null;
                    let routeLineAnim = null;

                    // If 2 or more waypoints, create polyline group and snap to actual road network
                    if (initialLatLngs.length >= 2) {
                        // 1. Outer Glow Corridor (Corridor envelope)
                        routeLineGlow = L.polyline(initialLatLngs, {
                            color: zoneColor,
                            weight: 12,
                            opacity: 0.2,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(map);

                        // 2. Main Highway Solid Track
                        routeLineMain = L.polyline(initialLatLngs, {
                            color: zoneColor,
                            weight: 4.5,
                            opacity: 0.85,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(map);

                        // 3. Inner White Animated Patrol Flow
                        routeLineAnim = L.polyline(initialLatLngs, {
                            color: '#ffffff',
                            weight: 2,
                            opacity: 0.9,
                            dashArray: '8, 8',
                            className: 'patrol-trajectory-path',
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(map);

                        layersRef.current.push(routeLineGlow);
                        layersRef.current.push(routeLineMain);
                        layersRef.current.push(routeLineAnim);

                        // Asynchronously fetch exact road highway geometry from OSRM
                        fetchRoadRouteGeometry(validWaypoints).then(roadResult => {
                            if (isCancelled || !roadResult || !roadResult.latLngs) return;
                            const roadCoords = roadResult.latLngs;

                            if (routeLineGlow && map.hasLayer(routeLineGlow)) {
                                routeLineGlow.setLatLngs(roadCoords);
                            }
                            if (routeLineMain && map.hasLayer(routeLineMain)) {
                                routeLineMain.setLatLngs(roadCoords);
                            }
                            if (routeLineAnim && map.hasLayer(routeLineAnim)) {
                                routeLineAnim.setLatLngs(roadCoords);
                            }
                        }).catch(err => {
                            console.warn('Road snapping fallback active:', err);
                        });
                    }

                    // Add Waypoint Markers & Tolerance Rings
                    validWaypoints.forEach((wp, wpIdx) => {
                        const isStart = wpIdx === 0;
                        const isEnd = wpIdx === validWaypoints.length - 1 && validWaypoints.length > 1;
                        const bgColor = isStart ? '#10b981' : isEnd ? '#ef4444' : zoneColor;

                        // Tolerance Ring
                        if (toleranceMeters && toleranceMeters > 0) {
                            const circle = L.circle([wp.lat, wp.lng], {
                                radius: toleranceMeters,
                                color: bgColor,
                                fillColor: bgColor,
                                fillOpacity: 0.08,
                                weight: 1.5,
                                dashArray: '4, 4'
                            }).addTo(map);
                            layersRef.current.push(circle);
                        }

                        const markerHtml = `
                            <div style="
                                width: 28px;
                                height: 28px;
                                border-radius: 50%;
                                background: ${bgColor};
                                border: 2.5px solid var(--color-surface, #ffffff);
                                box-shadow: 0 4px 10px rgba(0,0,0,0.4);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-weight: 800;
                                font-size: 11px;
                            ">
                                ${validWaypoints.length === 1 ? '📍' : isStart ? 'S' : isEnd ? 'E' : (wpIdx + 1)}
                            </div>
                        `;

                        const wpMarker = L.marker([wp.lat, wp.lng], {
                            icon: L.divIcon({
                                html: markerHtml,
                                className: 'waypoint-marker',
                                iconSize: [28, 28],
                                iconAnchor: [14, 14]
                            })
                        }).addTo(map);

                        wpMarker.bindPopup(`
                            <div style="font-family: inherit; padding: 4px; color: var(--gray-12, #1e293b);">
                                <strong style="color: ${zoneColor};">${routeTitle || name}</strong><br>
                                <span style="font-size: 11px; color: var(--gray-10, #64748b);">
                                    ${validWaypoints.length === 1 ? '🎯 Patrol Checkpoint' : isStart ? '🚀 Expressway Route Start' : isEnd ? '🏁 Expressway Route End' : `Waypoint #${wpIdx + 1}`}
                                </span>
                                ${toleranceMeters ? `<div style="font-size: 10px; color: var(--gray-9); margin-top: 2px;">Highway Attendance Tolerance: ${toleranceMeters}m</div>` : ''}
                            </div>
                        `);

                        layersRef.current.push(wpMarker);
                    });
                };

                const defaultTolerance = config.tolerance || 150;

                if (rawWaypoints.length > 0) {
                    processRoute(rawWaypoints, name, defaultTolerance);
                }

                rawRoutes.forEach((route, rIdx) => {
                    const wps = route.waypoints || route.points || route.coords;
                    if (Array.isArray(wps) && wps.length > 0) {
                        processRoute(wps, route.name || `${name} Route ${rIdx + 1}`, route.tolerance || defaultTolerance);
                    }
                });
            }
        });

        return () => {
            isCancelled = true;
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
