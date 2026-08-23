import React, { useEffect, useRef, useCallback } from 'react';
import { useMap } from 'react-leaflet';
import L from 'leaflet';
import { THEME_COLORS } from './mapConstants';

export const MapLivingMarkers = React.memo(({
    users = [],
    selectedUserId,
    onSelectOfficer,
    onOpenTelemetry,
    onOpenPhoto,
    layerVisibility = { trajectories: true }
}) => {
    const map = useMap();
    const markersRef = useRef([]);
    const linesRef = useRef([]);

    // Location parsing helper
    const parseLocation = useCallback((loc) => {
        if (!loc) return null;
        if (typeof loc === 'object' && loc.lat && loc.lng) {
            const lat = parseFloat(loc.lat);
            const lng = parseFloat(loc.lng);
            if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
        }
        if (typeof loc === 'string') {
            try {
                const parsed = JSON.parse(loc);
                if (parsed.lat && parsed.lng) {
                    const lat = parseFloat(parsed.lat);
                    const lng = parseFloat(parsed.lng);
                    if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
                }
            } catch (e) {
                const parts = loc.split(',');
                if (parts.length >= 2) {
                    const lat = parseFloat(parts[0].trim());
                    const lng = parseFloat(parts[1].trim());
                    if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
                }
            }
        }
        return null;
    }, []);

    // Create Living DivIcon HTML
    const createLivingIcon = useCallback((user, type = 'active', isSelected = false) => {
        const isActive = user.status === 'active' || type === 'punchin';
        const isPunchOut = type === 'punchout';
        const avatarUrl = user.profile_image_url;
        const initial = user.name?.charAt(0)?.toUpperCase() || '?';

        const radarPulse = isActive ? '<div class="living-marker-radar-ring"></div>' : '';
        const coreClass = `living-marker-core ${isActive ? 'is-active' : isPunchOut ? 'is-punchout' : 'is-completed'}`;
        const badgeBg = isActive ? THEME_COLORS.active : isPunchOut ? THEME_COLORS.punchout : THEME_COLORS.completed;
        const badgeIcon = isActive ? '▶' : isPunchOut ? '◼' : '✓';

        const html = `
            <div class="living-marker-wrapper" style="${isSelected ? 'transform: scale(1.25); z-index: 9999;' : ''}">
                ${radarPulse}
                <div class="${coreClass}" style="${isSelected ? 'border-color: #38bdf8; box-shadow: 0 0 16px #38bdf8;' : ''}">
                    ${avatarUrl ?
                        `<img src="${avatarUrl}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.parentElement.innerText='${initial}';" />` :
                        initial
                    }
                </div>
                <div class="living-marker-badge" style="background: ${badgeBg};">
                    ${badgeIcon}
                </div>
            </div>
        `;

        return L.divIcon({
            html,
            className: 'custom-living-marker',
            iconSize: [44, 44],
            iconAnchor: [22, 22],
            popupAnchor: [0, -22]
        });
    }, []);

    // Build Glassmorphic Popup HTML
    const createPopupHtml = useCallback((user, cycleData, type = 'current') => {
        const isActive = user.status === 'active';
        const inTime = cycleData?.punchin_time || user.punchin_time || '--';
        const outTime = cycleData?.punchout_time || user.punchout_time;
        const photo = cycleData?.punchin_photo_url || user.punchin_photo_url;
        const outPhoto = cycleData?.punchout_photo_url || user.punchout_photo_url;
        const targetPhoto = type === 'punchout' ? (outPhoto || photo) : photo;

        const photoHtml = targetPhoto ? `
            <div style="margin: 8px 0; border-radius: 6px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); max-height: 90px; cursor: pointer; position: relative;"
                 onclick="window.__openMapPhoto && window.__openMapPhoto('${targetPhoto}', '${user.name.replace(/'/g, "\\'")}', '${inTime}', '${type}')">
                <img src="${targetPhoto}" style="width: 100%; height: 85px; object-fit: cover;" alt="Selfie" />
                <div style="position: absolute; bottom: 2px; right: 4px; background: rgba(0,0,0,0.65); padding: 1px 6px; border-radius: 4px; font-size: 9px; color: #fff;">
                    🔍 Zoom
                </div>
            </div>
        ` : '';

        return `
            <div style="
                min-width: 210px;
                max-width: 250px;
                background: var(--color-panel-solid, var(--color-surface, #ffffff));
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border: 1px solid var(--gray-a6, #cbd5e1);
                border-radius: 12px;
                padding: 12px;
                color: var(--gray-12, #1e293b);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.18);
                font-family: inherit;
            ">
                <!-- Header -->
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <div style="
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        overflow: hidden;
                        border: 2px solid ${isActive ? THEME_COLORS.active : 'var(--gray-8, #94a3b8)'};
                        background: var(--gray-a4, #e2e8f0);
                        color: var(--gray-12, #1e293b);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        font-size: 12px;
                        flex-shrink: 0;
                    ">
                        ${user.profile_image_url ?
                            `<img src="${user.profile_image_url}" style="width:100%; height:100%; object-fit:cover;" />` :
                            (user.name?.charAt(0)?.toUpperCase() || '?')
                        }
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--gray-12, #0f172a);">
                            ${user.name}
                        </div>
                        <div style="font-size: 10px; color: var(--gray-10, #64748b); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${user.designation || 'Officer'}
                        </div>
                    </div>
                    <div style="
                        font-size: 9px;
                        font-weight: 600;
                        padding: 2px 6px;
                        border-radius: 10px;
                        background: ${isActive ? 'var(--green-a3, rgba(16, 185, 129, 0.15))' : 'var(--blue-a3, rgba(59, 130, 246, 0.15))'};
                        color: ${isActive ? 'var(--green-11, #059669)' : 'var(--blue-11, #2563eb)'};
                        border: 1px solid ${isActive ? 'var(--green-a5, rgba(16, 185, 129, 0.4))' : 'var(--blue-a5, rgba(59, 130, 246, 0.4))'};
                    ">
                        ${isActive ? '🟢 ACTIVE' : '✅ DONE'}
                    </div>
                </div>

                <!-- Timestamps -->
                <div style="background: var(--gray-a3, rgba(0, 0, 0, 0.04)); border-radius: 6px; padding: 6px; margin-bottom: 6px; font-size: 11px; border: 1px solid var(--gray-a4);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                        <span style="color: var(--gray-10, #64748b);">Check In:</span>
                        <span style="font-weight: 600; color: var(--green-11, #059669);">${inTime}</span>
                    </div>
                    ${outTime ? `
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: var(--gray-10, #64748b);">Check Out:</span>
                        <span style="font-weight: 600; color: var(--red-11, #dc2626);">${outTime}</span>
                    </div>` : ''}
                </div>

                ${photoHtml}

                <!-- Inspect Button -->
                <button
                    onclick="window.__inspectOfficer && window.__inspectOfficer(${user.user_id})"
                    style="
                        width: 100%;
                        background: linear-gradient(135deg, #0284c7, #2563eb);
                        border: none;
                        border-radius: 6px;
                        color: #ffffff;
                        padding: 6px 10px;
                        font-size: 11px;
                        font-weight: 600;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 4px;
                        margin-top: 6px;
                        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.4);
                    "
                >
                    🔍 Inspect Telemetry
                </button>
            </div>
        `;
    }, []);

    // Attach global popup callbacks for buttons inside Leaflet HTML popups
    useEffect(() => {
        window.__inspectOfficer = (userId) => {
            const officer = users.find(u => u.user_id === userId);
            if (officer && onOpenTelemetry) {
                onOpenTelemetry(officer);
            }
        };

        window.__openMapPhoto = (url, officerName, timestamp, type) => {
            if (onOpenPhoto) {
                onOpenPhoto({ url, officerName, timestamp, type });
            }
        };

        return () => {
            delete window.__inspectOfficer;
            delete window.__openMapPhoto;
        };
    }, [users, onOpenTelemetry, onOpenPhoto]);

    // Render Markers with intelligent spiderfy anti-overlap
    useEffect(() => {
        if (!map) return;

        // Clear existing markers and trajectories
        markersRef.current.forEach(m => {
            try { map.removeLayer(m); } catch (e) {}
        });
        markersRef.current = [];

        linesRef.current.forEach(l => {
            try { map.removeLayer(l); } catch (e) {}
        });
        linesRef.current = [];

        if (!users || users.length === 0) return;

        const occupiedPositions = [];
        const THRESHOLD = 0.00015; // roughly 15 meters

        // Offset calculation if multiple users are at the exact same location
        const getAdjustedPosition = (coords) => {
            let lat = coords.lat;
            let lng = coords.lng;

            const existingCount = occupiedPositions.filter(p =>
                Math.abs(p.lat - lat) < THRESHOLD && Math.abs(p.lng - lng) < THRESHOLD
            ).length;

            if (existingCount > 0) {
                // Golden spiral / radial fan-out
                const angle = existingCount * 1.25;
                const distance = 0.00018 * Math.sqrt(existingCount);
                lat += Math.cos(angle) * distance;
                lng += Math.sin(angle) * distance;
            }

            occupiedPositions.push({ lat, lng });
            return { lat, lng };
        };

        users.forEach((user) => {
            const cycles = user.cycles && user.cycles.length > 0 ? user.cycles : null;
            const isSelected = selectedUserId === user.user_id;

            if (cycles) {
                // Render cycles
                cycles.forEach((cycle, cycleIdx) => {
                    const inLoc = parseLocation(cycle.punchin_location);
                    const outLoc = parseLocation(cycle.punchout_location);

                    if (inLoc && outLoc && cycle.is_complete) {
                        // Complete cycle: Show Punch-In marker, Punch-Out marker, and Trajectory Line
                        const adjIn = getAdjustedPosition(inLoc);
                        const adjOut = getAdjustedPosition(outLoc);

                        // 1. Punch-in marker
                        const inMarker = L.marker([adjIn.lat, adjIn.lng], {
                            icon: createLivingIcon(user, 'punchin', isSelected),
                            zIndexOffset: isSelected ? 1000 : 100
                        }).addTo(map);

                        inMarker.bindPopup(createPopupHtml(user, cycle, 'punchin'));
                        inMarker.on('click', () => onSelectOfficer && onSelectOfficer(user));
                        markersRef.current.push(inMarker);

                        // 2. Punch-out marker
                        const outMarker = L.marker([adjOut.lat, adjOut.lng], {
                            icon: createLivingIcon(user, 'punchout', isSelected),
                            zIndexOffset: isSelected ? 1000 : 90
                        }).addTo(map);

                        outMarker.bindPopup(createPopupHtml(user, cycle, 'punchout'));
                        outMarker.on('click', () => onSelectOfficer && onSelectOfficer(user));
                        markersRef.current.push(outMarker);

                        // 3. Patrol Trajectory Line
                        if (layerVisibility.trajectories) {
                            const trajectoryLine = L.polyline([
                                [adjIn.lat, adjIn.lng],
                                [adjOut.lat, adjOut.lng]
                            ], {
                                color: '#06b6d4',
                                weight: 3.5,
                                opacity: 0.8,
                                className: 'patrol-trajectory-path'
                            }).addTo(map);

                            linesRef.current.push(trajectoryLine);
                        }
                    } else {
                        // Incomplete / active cycle
                        const activeLoc = inLoc || outLoc;
                        if (activeLoc) {
                            const adjPos = getAdjustedPosition(activeLoc);
                            const marker = L.marker([adjPos.lat, adjPos.lng], {
                                icon: createLivingIcon(user, user.status, isSelected),
                                zIndexOffset: isSelected ? 1000 : 150
                            }).addTo(map);

                            marker.bindPopup(createPopupHtml(user, cycle, 'punchin'));
                            marker.on('click', () => onSelectOfficer && onSelectOfficer(user));
                            markersRef.current.push(marker);
                        }
                    }
                });
            } else {
                // Fallback single position
                const inLoc = parseLocation(user.punchin_location || user.location);
                const outLoc = parseLocation(user.punchout_location);
                const primaryLoc = inLoc || outLoc;

                if (primaryLoc) {
                    const adjPos = getAdjustedPosition(primaryLoc);
                    const marker = L.marker([adjPos.lat, adjPos.lng], {
                        icon: createLivingIcon(user, user.status, isSelected),
                        zIndexOffset: isSelected ? 1000 : 100
                    }).addTo(map);

                    marker.bindPopup(createPopupHtml(user, user, user.status));
                    marker.on('click', () => onSelectOfficer && onSelectOfficer(user));
                    markersRef.current.push(marker);

                    if (inLoc && outLoc && user.punchout_time && layerVisibility.trajectories) {
                        const adjOut = getAdjustedPosition(outLoc);
                        const line = L.polyline([
                            [adjPos.lat, adjPos.lng],
                            [adjOut.lat, adjOut.lng]
                        ], {
                            color: '#06b6d4',
                            weight: 3.5,
                            opacity: 0.8,
                            className: 'patrol-trajectory-path'
                        }).addTo(map);

                        linesRef.current.push(line);
                    }
                }
            }
        });

        return () => {
            markersRef.current.forEach(m => {
                try { map.removeLayer(m); } catch (e) {}
            });
            markersRef.current = [];

            linesRef.current.forEach(l => {
                try { map.removeLayer(l); } catch (e) {}
            });
            linesRef.current = [];
        };
    }, [map, users, selectedUserId, layerVisibility, createLivingIcon, createPopupHtml, parseLocation, onSelectOfficer]);

    return null;
});

MapLivingMarkers.displayName = 'MapLivingMarkers';
export default MapLivingMarkers;
