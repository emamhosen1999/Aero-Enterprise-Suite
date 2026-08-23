/**
 * Road Routing & Highway Snapping Service using OSRM
 * Fetches true road driving geometry for patrol corridors & waypoints
 */

const memoryCache = new Map();

/**
 * Normalizes coordinate object or array to { lat, lng }
 */
export const normalizeCoord = (pt) => {
    if (!pt) return null;
    if (Array.isArray(pt) && pt.length >= 2) {
        const lat = parseFloat(pt[0]);
        const lng = parseFloat(pt[1]);
        if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
    }
    if (typeof pt === 'object') {
        const latVal = pt.lat ?? pt.latitude;
        const lngVal = pt.lng ?? pt.longitude;
        if (latVal !== undefined && lngVal !== undefined) {
            const lat = parseFloat(latVal);
            const lng = parseFloat(lngVal);
            if (!isNaN(lat) && !isNaN(lng)) return { lat, lng };
        }
    }
    return null;
};

/**
 * Fetches the road-snapped driving polyline between sequential waypoints
 * @param {Array<{lat: number|string, lng: number|string}>} waypoints
 * @returns {Promise<{latLngs: Array<[number, number]>, distance: number, duration: number}|null>}
 */
export const fetchRoadRouteGeometry = async (waypoints) => {
    const validPts = (waypoints || []).map(normalizeCoord).filter(Boolean);
    if (validPts.length < 2) return null;

    // Build OSRM query string: lng,lat;lng,lat...
    const coordQuery = validPts.map(p => `${p.lng},${p.lat}`).join(';');
    const cacheKey = `road_route_${coordQuery}`;

    // 1. In-Memory Cache check
    if (memoryCache.has(cacheKey)) {
        return memoryCache.get(cacheKey);
    }

    // 2. SessionStorage Cache check
    try {
        const cached = sessionStorage.getItem(cacheKey);
        if (cached) {
            const parsed = JSON.parse(cached);
            memoryCache.set(cacheKey, parsed);
            return parsed;
        }
    } catch (e) {
        // Ignore storage errors
    }

    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${coordQuery}?overview=full&geometries=geojson&steps=false&annotations=false`;
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`OSRM HTTP error: ${response.status}`);
        }

        const data = await response.json();
        if (data.code === 'Ok' && data.routes && data.routes[0]?.geometry?.coordinates) {
            // GeoJSON coordinates are [lng, lat] -> convert to Leaflet [lat, lng]
            const rawCoords = data.routes[0].geometry.coordinates;
            const latLngs = rawCoords.map(([lng, lat]) => [lat, lng]);

            const result = {
                latLngs,
                distance: data.routes[0].distance,
                duration: data.routes[0].duration,
                isSnappedToRoad: true
            };

            memoryCache.set(cacheKey, result);
            try {
                sessionStorage.setItem(cacheKey, JSON.stringify(result));
            } catch (e) {}

            return result;
        }
    } catch (error) {
        console.warn('OSRM road route fetch fallback to direct polyline:', error.message);
    }

    // Fallback: Return straight direct segments if OSRM is unreachable
    const fallback = {
        latLngs: validPts.map(p => [p.lat, p.lng]),
        distance: 0,
        duration: 0,
        isSnappedToRoad: false
    };
    return fallback;
};
