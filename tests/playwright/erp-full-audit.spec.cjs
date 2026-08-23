// @ts-check
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE = 'https://erp.dhakabypass.com';
const EMAIL = 'emam@dhakabypass.com';
const PASS  = 'jarvisMJ@321';
const AUTH_STATE = path.join(__dirname, '.auth-state.json');
const REPORT_DIR = __dirname;

/* ─── Web pages (GET, no route params) ─── */
const WEB_PAGES = [
  '/dashboard',
  '/employees',
  '/users',
  '/users/paginate',
  '/users/stats',
  '/departments',
  '/designations',
  '/roles',
  '/roles-permissions',
  '/organization',
  '/holidays',
  '/leaves',
  '/leaves-employee',
  '/leaves-paginate',
  '/leaves-stats',
  '/leaves/analytics',
  '/leaves/pending-approvals',
  '/leaves/bulk/calendar-data',
  '/leaves/bulk/leave-types',
  '/leave-summary',
  '/attendance',
  '/attendance-employee',
  '/attendance/admin/dashboard',
  '/attendance/admin/records',
  '/attendance/admin/settings',
  '/attendance/admin/shifts',
  '/attendance/admin/roster',
  '/attendance/admin/shift-swaps',
  '/attendance/admin/regularizations',
  '/attendance/admin/overtime',
  '/attendance/admin/comp-off',
  '/attendance/daily-timesheet',
  '/timesheet',
  '/daily-works',
  '/daily-works-json',
  '/daily-works-se',
  '/daily-works-se-json',
  '/daily-works-summary',
  '/reports',
  '/reports-json',
  '/stats',
  '/tasks-all',
  '/tasks-all-se',
  '/tasks/daily-summary-json',
  '/tasks/daily-summary-se',
  '/tasks/se',
  '/work-location',
  '/work-location_json',
  '/profiles/search',
  '/quality/ncr',
  '/petty-cash',
  '/petty-cash/transactions',
  '/petty-cash/history',
  '/petty-cash/categories',
  '/petty-cash/analytics',
  '/petty-cash/admin/overview',
  '/petty-cash/audit-log',
  '/my-devices',
  '/letters',
  '/letters-paginate',
  '/notifications',
  '/notifications/list',
  '/notifications/unread-count',
  '/workspace/objections',
  '/settings/biometric-devices',
  '/settings/biometric-devices/active',
  '/settings/biometric-devices/attlogs',
  '/settings/biometric-devices/download-history',
  '/settings/biometric-devices/health',
  '/settings/biometric-devices/logs',
  '/settings/biometric-devices/operlogs',
  '/settings/biometric-devices/templates',
  '/settings/notifications',
  '/settings/notifications/list',
  '/settings/request-logs',
  '/settings/request-logs/list',
  '/security/dashboard',
  '/om/dashboard',
  '/om/equipment',
  '/om/incidents',
  '/om/shift-logs',
  '/om/toll-operations',
  '/om/traffic-monitoring',
  '/om/work-orders',
  '/updates',
];

/* ─── API v1 GET endpoints ─── */
const API_ENDPOINTS = [
  '/api/v1/auth/me',
  '/api/v1/profile',
  '/api/v1/config',
  '/api/v1/sync/bootstrap',
  '/api/v1/daily-works',
  '/api/v1/daily-works/selectable-dates',
  '/api/v1/daily-works/objections/metadata',
  '/api/v1/daily-works/objections/my',
  '/api/v1/daily-works/objections/queue',
  '/api/v1/leaves',
  '/api/v1/leaves/summary',
  '/api/v1/leaves/analytics',
  '/api/v1/leaves/calendar',
  '/api/v1/leaves/pending-approvals',
  '/api/v1/leaves/decided-approvals',
  '/api/v1/leave-types',
  '/api/v1/attendance/today',
  '/api/v1/attendance/history',
  '/api/v1/attendance/monthly-summary',
  '/api/v1/attendance/daily-timesheet',
  '/api/v1/attendance/my-roster',
  '/api/v1/attendance/roster',
  '/api/v1/attendance/shifts',
  '/api/v1/attendance/present-users',
  '/api/v1/attendance/absent-users',
  '/api/v1/attendance/team-day',
  '/api/v1/attendance/team-locations',
  '/api/v1/attendance/locations-today',
  '/api/v1/attendance/comp-off/mine',
  '/api/v1/attendance/overtime/mine',
  '/api/v1/attendance/overtime/pending',
  '/api/v1/attendance/overtime/decided',
  '/api/v1/attendance/regularizations/mine',
  '/api/v1/attendance/regularizations/pending',
  '/api/v1/attendance/regularizations/decided',
  '/api/v1/attendance/swaps/mine',
  '/api/v1/attendance/swaps/pending',
  '/api/v1/attendance/swaps/awaiting-me',
  '/api/v1/attendance/swaps/eligible',
  '/api/v1/attendance/swaps/pickup',
  '/api/v1/attendance/swaps/team-decided',
  '/api/v1/manager/dashboard-summary',
  '/api/v1/manager/team-members',
  '/api/v1/account/devices',
  '/api/v1/om/dashboard',
  '/api/v1/om/equipment',
  '/api/v1/om/incidents',
  '/api/v1/om/shift-logs',
  '/api/v1/om/toll-operations',
  '/api/v1/om/traffic-monitoring',
  '/api/v1/om/work-orders',
  '/api/departments',
  '/api/departments/list',
  '/api/designations/list',
  '/api/roles',
  '/api/permissions',
  '/api/permissions/grouped/modules',
  '/api/users/managers/list',
  '/api/notifications',
  '/api/notifications/unread-count',
  '/api/version',
];

/* ──────────────────────────────────────────────────────────────
 * TEST 1: Login via browser — use 'load' event, not networkidle
 * ────────────────────────────────────────────────────────────── */
test('01 — Login', async ({ page }) => {
  test.setTimeout(90000);

  // Navigate to login with retry
  let loaded = false;
  for (let attempt = 1; attempt <= 3; attempt++) {
    try {
      console.log(`Navigating to ${BASE}/login (attempt ${attempt})...`);
      await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
      loaded = true;
      break;
    } catch (e) {
      console.log(`Navigation attempt ${attempt} failed: ${e.message}`);
      if (attempt < 3) await page.waitForTimeout(2000);
    }
  }

  if (!loaded) {
    throw new Error('Failed to navigate to login page after 3 attempts');
  }

  const CANDIDATE_EMAILS = [
    'emam@dhakabypass.com',
    'emamhsajeeb@gmail.com',
    'emam.hosen@dhakabypass.com',
    'admin@dhakabypass.com',
  ];

  let loggedIn = false;

  for (const email of CANDIDATE_EMAILS) {
    console.log(`Attempting login with ${email}...`);
    await page.fill('input[type="email"], input[name="email"]', email);
    await page.fill('input[type="password"], input[name="password"]', PASS);
    await page.click('button[type="submit"]');

    try {
      await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 8000 });
      console.log(`✅ Logged in successfully with ${email}, URL:`, page.url());
      loggedIn = true;
      break;
    } catch (e) {
      console.log(`❌ Login failed for ${email}`);
      // Wait a moment before next attempt
      await page.waitForTimeout(1000);
    }
  }

  if (!loggedIn) {
    throw new Error('Could not log in with any candidate email');
  }

  // Save auth state
  await page.context().storageState({ path: AUTH_STATE });
  console.log('✅ Auth state saved');
});

/* ──────────────────────────────────────────────────────────────
 * TEST 2: Audit all web pages
 * ────────────────────────────────────────────────────────────── */
test('02 — Audit all web pages', async ({ browser }) => {
  test.setTimeout(600000); // 10 min for ~90 pages

  const contextOptions = fs.existsSync(AUTH_STATE) ? { storageState: AUTH_STATE } : {};
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();

  const failures = [];
  const pageErrors = [];
  const consoleErrors = [];

  page.on('pageerror', (err) => {
    pageErrors.push({ url: page.url(), error: err.message });
  });
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      consoleErrors.push({ url: page.url(), text: msg.text() });
    }
  });

  for (const routePath of WEB_PAGES) {
    const url = `${BASE}${routePath}`;
    let status = 0;
    let errorText = '';
    const networkFiveXX = [];

    // Catch 5xx on XHR/fetch fired by the page
    const respHandler = (resp) => {
      if (resp.status() >= 500) {
        networkFiveXX.push({ url: resp.url(), status: resp.status() });
      }
    };
    page.on('response', respHandler);

    try {
      const resp = await page.goto(url, { waitUntil: 'load', timeout: 30000 });
      status = resp?.status() || 0;

      // Wait for async fetches
      await page.waitForTimeout(1500);

      // Scan body for server-error text
      const body = await page.textContent('body').catch(() => '');
      if (/SQLSTATE|Unknown column|Column not found|Integrity constraint|server error|500 /i.test(body)) {
        errorText = body.replace(/\s+/g, ' ').slice(0, 400);
      }
    } catch (e) {
      errorText = e.message.slice(0, 300);
    }

    page.off('response', respHandler);

    const bad = status >= 400 || errorText || networkFiveXX.length > 0;
    if (bad) {
      failures.push({ page: routePath, status, error: errorText || null, networkErrors: networkFiveXX.length ? networkFiveXX : null });
    }
    console.log(`${bad ? '❌' : '✅'} [${status}] ${routePath}${errorText ? ' → ' + errorText.slice(0, 100) : ''}`);
    for (const ne of networkFiveXX) {
      console.log(`     ↳ 5xx [${ne.status}] ${ne.url}`);
    }
  }

  // Filter meaningful console errors
  const importantConsole = consoleErrors.filter(e =>
    /500|SQLSTATE|column|error|exception|undefined/i.test(e.text)
  );

  // Summary
  console.log('\n══════════════════════════════════════');
  console.log('WEB PAGES AUDIT');
  console.log('══════════════════════════════════════');
  console.log(`Total:         ${WEB_PAGES.length}`);
  console.log(`Failed:        ${failures.length}`);
  console.log(`Console errs:  ${importantConsole.length}`);
  console.log(`Page errors:   ${pageErrors.length}`);

  if (failures.length) {
    console.log('\n--- FAILED ---');
    for (const f of failures) {
      console.log(`  ${f.page} [${f.status}]${f.error ? ' ' + f.error.slice(0, 200) : ''}`);
    }
  }
  if (pageErrors.length) {
    console.log('\n--- PAGE EXCEPTIONS ---');
    for (const e of pageErrors.slice(0, 20)) {
      console.log(`  ${e.url} → ${e.error.slice(0, 200)}`);
    }
  }

  fs.writeFileSync(path.join(REPORT_DIR, 'web-audit-report.json'),
    JSON.stringify({ failures, pageErrors, consoleErrors: importantConsole }, null, 2));

  await context.close();
});

/* ──────────────────────────────────────────────────────────────
 * TEST 3: Audit API endpoints
 * ────────────────────────────────────────────────────────────── */
test('03 — Audit API endpoints', async ({ browser }) => {
  test.setTimeout(300000);

  const contextOptions = fs.existsSync(AUTH_STATE) ? { storageState: AUTH_STATE } : {};
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();

  // Get API token
  let token = '';
  try {
    const res = await page.request.post(`${BASE}/api/v1/auth/login`, {
      data: { email: EMAIL, password: PASS },
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    });
    if (res.ok()) {
      const body = await res.json();
      token = body?.token || body?.data?.token || body?.access_token || '';
      console.log('✅ Got API token');
    } else {
      console.log(`⚠️  API login returned ${res.status()}, will use session cookies`);
    }
  } catch (e) {
    console.log('⚠️  API login failed:', e.message, '— using session cookies');
  }

  const headers = { 'Accept': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const apiFailures = [];

  for (const ep of API_ENDPOINTS) {
    const url = `${BASE}${ep}`;
    let status = 0;
    let errBody = '';

    for (let attempt = 1; attempt <= 3; attempt++) {
      try {
        const res = await page.request.get(url, { headers, timeout: 15000 });
        status = res.status();
        if (status >= 400) {
          errBody = (await res.text().catch(() => '')).slice(0, 500);
        }
        break;
      } catch (e) {
        errBody = e.message.slice(0, 300);
        if (attempt < 3) await page.waitForTimeout(500);
      }
    }

    await page.waitForTimeout(150);

    const bad = status >= 500 || (status === 0 && errBody);
    if (bad) {
      apiFailures.push({ endpoint: ep, status, error: errBody });
    }
    console.log(`${bad ? '❌' : '✅'} [${status}] ${ep}${errBody && status >= 500 ? ' → ' + errBody.slice(0, 80) : ''}`);
  }

  console.log('\n══════════════════════════════════════');
  console.log('API ENDPOINTS AUDIT');
  console.log('══════════════════════════════════════');
  console.log(`Total:  ${API_ENDPOINTS.length}`);
  console.log(`Failed: ${apiFailures.length}`);

  if (apiFailures.length) {
    console.log('\n--- FAILED ---');
    for (const f of apiFailures) {
      console.log(`  ${f.endpoint} [${f.status}] ${(f.error || '').slice(0, 200)}`);
    }
  }

  fs.writeFileSync(path.join(REPORT_DIR, 'api-audit-report.json'),
    JSON.stringify(apiFailures, null, 2));

  await context.close();
});

/* ──────────────────────────────────────────────────────────────
 * TEST 4: CRUD sanity checks
 * ────────────────────────────────────────────────────────────── */
test('04 — CRUD operations sanity check', async ({ browser }) => {
  test.setTimeout(120000);

  if (!fs.existsSync(AUTH_STATE)) {
    test.skip();
    return;
  }

  const context = await browser.newContext({ storageState: AUTH_STATE });
  const page = await context.newPage();

  let token = '';
  try {
    const res = await page.request.post(`${BASE}/api/v1/auth/login`, {
      data: { email: EMAIL, password: PASS },
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    });
    if (res.ok()) {
      const body = await res.json();
      token = body?.token || body?.data?.token || body?.access_token || '';
    }
  } catch (e) {}

  const headers = { 'Accept': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const crudResults = [];

  async function checkGet(label, url) {
    try {
      const res = await page.request.get(url, { headers, timeout: 20000 });
      const ok = res.status() < 400;
      console.log(`${ok ? '✅' : '❌'} ${label} [${res.status()}]`);
      if (!ok) {
        const body = (await res.text().catch(() => '')).slice(0, 300);
        crudResults.push({ op: label, status: res.status(), error: body });
      }
      return ok ? await res.json().catch(() => null) : null;
    } catch (e) {
      console.log(`❌ ${label} → ${e.message.slice(0, 100)}`);
      crudResults.push({ op: label, error: e.message });
      return null;
    }
  }

  console.log('\n--- CRUD Sanity ---');
  const profile = await checkGet('Profile', `${BASE}/api/v1/profile`);
  if (profile) {
    const u = profile?.data || profile;
    console.log(`   employee_id=${u?.employee_id}, id=${u?.id}`);
    if (u?.id && u?.employee_id && String(u.id) !== String(u.employee_id)) {
      crudResults.push({ op: 'Profile id mismatch', error: `id=${u.id} != employee_id=${u.employee_id}` });
    }
  }

  await checkGet('Users paginate', `${BASE}/users/paginate`);
  await checkGet('Departments', `${BASE}/api/departments`);
  await checkGet('Designations', `${BASE}/api/designations/list`);
  await checkGet('Roles', `${BASE}/api/roles`);
  await checkGet('Leaves paginate', `${BASE}/leaves-paginate`);
  await checkGet('Daily works JSON', `${BASE}/daily-works-json`);
  await checkGet('Attendance records', `${BASE}/attendance/admin/records`);
  await checkGet('Sync bootstrap', `${BASE}/api/v1/sync/bootstrap`);
  await checkGet('Sync pull', `${BASE}/api/v1/sync/pull?cursor=0&epoch=1`);
  await checkGet('Attendance today', `${BASE}/api/v1/attendance/today`);
  await checkGet('Attendance history', `${BASE}/api/v1/attendance/history`);
  await checkGet('Manager dashboard', `${BASE}/api/v1/manager/dashboard-summary`);
  await checkGet('Manager team', `${BASE}/api/v1/manager/team-members`);
  await checkGet('Leave summary', `${BASE}/api/v1/leaves/summary`);
  await checkGet('Leave types', `${BASE}/api/v1/leave-types`);
  await checkGet('Account devices', `${BASE}/api/v1/account/devices`);
  await checkGet('Employee directory', `${BASE}/profiles/search?q=`);
  await checkGet('Petty cash', `${BASE}/petty-cash/transactions`);
  await checkGet('Objections', `${BASE}/workspace/objections`);
  await checkGet('Biometric devices', `${BASE}/settings/biometric-devices/active`);

  console.log('\n══════════════════════════════════════');
  console.log('CRUD RESULTS');
  console.log('══════════════════════════════════════');
  console.log(`Failed: ${crudResults.length}`);
  if (crudResults.length) {
    for (const r of crudResults) {
      console.log(`  ${r.op} [${r.status || 'ERR'}] ${(r.error || '').slice(0, 200)}`);
    }
  }

  fs.writeFileSync(path.join(REPORT_DIR, 'crud-audit-report.json'),
    JSON.stringify(crudResults, null, 2));

  await context.close();
});
