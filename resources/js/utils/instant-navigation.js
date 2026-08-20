/**
 * Ultra-Fast Instant Page Navigation Engine for Inertia + React + Electron
 * Zero loading delays via Stale-While-Revalidate prop caching & aggressive link prefetching.
 */

import { router } from '@inertiajs/react';

const pageCache = new Map();
const pendingRequests = new Set();

/**
 * Prefetch an Inertia page URL on hover or focus
 */
export function prefetchUrl(url) {
  if (!url || typeof url !== 'string') return;
  if (url.startsWith('#') || url.startsWith('javascript:') || url.includes('/logout')) return;

  // Normalize URL
  try {
    const targetUrl = new URL(url, window.location.origin);
    if (targetUrl.origin !== window.location.origin) return;

    const pathKey = targetUrl.pathname + targetUrl.search;
    if (pageCache.has(pathKey) || pendingRequests.has(pathKey)) return;

    pendingRequests.add(pathKey);

    fetch(pathKey, {
      headers: {
        'X-Inertia': 'true',
        'X-Inertia-Version': window.Laravel?.inertiaVersion || '',
        'Accept': 'text/html, application/xhtml+xml',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then((res) => {
        if (res.ok) return res.json();
        throw new Error('Prefetch failed');
      })
      .then((pageData) => {
        if (pageData && pageData.component) {
          pageCache.set(pathKey, {
            data: pageData,
            timestamp: Date.now()
          });
        }
      })
      .catch(() => {})
      .finally(() => {
        pendingRequests.delete(pathKey);
      });
  } catch (e) {
    // Ignore invalid URLs
  }
}

/**
 * Initialize Instant Navigation & Cache Interceptors
 */
export function initInstantNavigation() {
  if (typeof window === 'undefined') return;

  // 1. Mouseover and Touchstart preloader (captures 150-300ms of user intent prior to click)
  document.addEventListener(
    'mouseover',
    (e) => {
      const anchor = e.target.closest('a');
      if (anchor && anchor.href) {
        prefetchUrl(anchor.href);
      }
    },
    { passive: true }
  );

  document.addEventListener(
    'touchstart',
    (e) => {
      const anchor = e.target.closest('a');
      if (anchor && anchor.href) {
        prefetchUrl(anchor.href);
      }
    },
    { passive: true }
  );

  // 2. Cache current page on navigate success
  router.on('success', (event) => {
    if (event.detail?.page) {
      const pathKey = window.location.pathname + window.location.search;
      pageCache.set(pathKey, {
        data: event.detail.page,
        timestamp: Date.now()
      });
    }
  });

  // 3. Intercept router start for Instant Rendering (Stale-While-Revalidate)
  router.on('start', (event) => {
    const visitUrl = event.detail?.visit?.url;
    if (!visitUrl) return;

    try {
      const targetUrl = new URL(visitUrl, window.location.origin);
      const pathKey = targetUrl.pathname + targetUrl.search;

      const cached = pageCache.get(pathKey);
      if (cached && (Date.now() - cached.timestamp < 300000)) { // 5 min fresh cache
        // Instantly set page state without waiting for server response
        if (window.Inertia?.setPage) {
          window.Inertia.setPage(cached.data);
        }
      }
    } catch (e) {
      // Fallback to normal navigation
    }
  });

  console.log('⚡ DBEDC Guardian Instant Navigation Engine active (0ms page load target)');
}
