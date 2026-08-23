# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: erp-full-audit.spec.cjs >> 01 — Login
- Location: tests\playwright\erp-full-audit.spec.cjs:169:1

# Error details

```
Error: Failed to navigate to login page after 3 attempts
```

# Test source

```ts
  87  |   '/settings/notifications/list',
  88  |   '/settings/request-logs',
  89  |   '/settings/request-logs/list',
  90  |   '/security/dashboard',
  91  |   '/om/dashboard',
  92  |   '/om/equipment',
  93  |   '/om/incidents',
  94  |   '/om/shift-logs',
  95  |   '/om/toll-operations',
  96  |   '/om/traffic-monitoring',
  97  |   '/om/work-orders',
  98  |   '/updates',
  99  | ];
  100 | 
  101 | /* ─── API v1 GET endpoints ─── */
  102 | const API_ENDPOINTS = [
  103 |   '/api/v1/auth/me',
  104 |   '/api/v1/profile',
  105 |   '/api/v1/config',
  106 |   '/api/v1/sync/bootstrap',
  107 |   '/api/v1/daily-works',
  108 |   '/api/v1/daily-works/selectable-dates',
  109 |   '/api/v1/daily-works/objections/metadata',
  110 |   '/api/v1/daily-works/objections/my',
  111 |   '/api/v1/daily-works/objections/queue',
  112 |   '/api/v1/leaves',
  113 |   '/api/v1/leaves/summary',
  114 |   '/api/v1/leaves/analytics',
  115 |   '/api/v1/leaves/calendar',
  116 |   '/api/v1/leaves/pending-approvals',
  117 |   '/api/v1/leaves/decided-approvals',
  118 |   '/api/v1/leave-types',
  119 |   '/api/v1/attendance/today',
  120 |   '/api/v1/attendance/history',
  121 |   '/api/v1/attendance/monthly-summary',
  122 |   '/api/v1/attendance/daily-timesheet',
  123 |   '/api/v1/attendance/my-roster',
  124 |   '/api/v1/attendance/roster',
  125 |   '/api/v1/attendance/shifts',
  126 |   '/api/v1/attendance/present-users',
  127 |   '/api/v1/attendance/absent-users',
  128 |   '/api/v1/attendance/team-day',
  129 |   '/api/v1/attendance/team-locations',
  130 |   '/api/v1/attendance/locations-today',
  131 |   '/api/v1/attendance/comp-off/mine',
  132 |   '/api/v1/attendance/overtime/mine',
  133 |   '/api/v1/attendance/overtime/pending',
  134 |   '/api/v1/attendance/overtime/decided',
  135 |   '/api/v1/attendance/regularizations/mine',
  136 |   '/api/v1/attendance/regularizations/pending',
  137 |   '/api/v1/attendance/regularizations/decided',
  138 |   '/api/v1/attendance/swaps/mine',
  139 |   '/api/v1/attendance/swaps/pending',
  140 |   '/api/v1/attendance/swaps/awaiting-me',
  141 |   '/api/v1/attendance/swaps/eligible',
  142 |   '/api/v1/attendance/swaps/pickup',
  143 |   '/api/v1/attendance/swaps/team-decided',
  144 |   '/api/v1/manager/dashboard-summary',
  145 |   '/api/v1/manager/team-members',
  146 |   '/api/v1/account/devices',
  147 |   '/api/v1/om/dashboard',
  148 |   '/api/v1/om/equipment',
  149 |   '/api/v1/om/incidents',
  150 |   '/api/v1/om/shift-logs',
  151 |   '/api/v1/om/toll-operations',
  152 |   '/api/v1/om/traffic-monitoring',
  153 |   '/api/v1/om/work-orders',
  154 |   '/api/departments',
  155 |   '/api/departments/list',
  156 |   '/api/designations/list',
  157 |   '/api/roles',
  158 |   '/api/permissions',
  159 |   '/api/permissions/grouped/modules',
  160 |   '/api/users/managers/list',
  161 |   '/api/notifications',
  162 |   '/api/notifications/unread-count',
  163 |   '/api/version',
  164 | ];
  165 | 
  166 | /* ──────────────────────────────────────────────────────────────
  167 |  * TEST 1: Login via browser — use 'load' event, not networkidle
  168 |  * ────────────────────────────────────────────────────────────── */
  169 | test('01 — Login', async ({ page }) => {
  170 |   test.setTimeout(90000);
  171 | 
  172 |   // Navigate to login with retry
  173 |   let loaded = false;
  174 |   for (let attempt = 1; attempt <= 3; attempt++) {
  175 |     try {
  176 |       console.log(`Navigating to ${BASE}/login (attempt ${attempt})...`);
  177 |       await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  178 |       loaded = true;
  179 |       break;
  180 |     } catch (e) {
  181 |       console.log(`Navigation attempt ${attempt} failed: ${e.message}`);
  182 |       if (attempt < 3) await page.waitForTimeout(2000);
  183 |     }
  184 |   }
  185 | 
  186 |   if (!loaded) {
> 187 |     throw new Error('Failed to navigate to login page after 3 attempts');
      |           ^ Error: Failed to navigate to login page after 3 attempts
  188 |   }
  189 | 
  190 |   const CANDIDATE_EMAILS = [
  191 |     'emam@dhakabypass.com',
  192 |     'emamhsajeeb@gmail.com',
  193 |     'emam.hosen@dhakabypass.com',
  194 |     'admin@dhakabypass.com',
  195 |   ];
  196 | 
  197 |   let loggedIn = false;
  198 | 
  199 |   for (const email of CANDIDATE_EMAILS) {
  200 |     console.log(`Attempting login with ${email}...`);
  201 |     await page.fill('input[type="email"], input[name="email"]', email);
  202 |     await page.fill('input[type="password"], input[name="password"]', PASS);
  203 |     await page.click('button[type="submit"]');
  204 | 
  205 |     try {
  206 |       await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 8000 });
  207 |       console.log(`✅ Logged in successfully with ${email}, URL:`, page.url());
  208 |       loggedIn = true;
  209 |       break;
  210 |     } catch (e) {
  211 |       console.log(`❌ Login failed for ${email}`);
  212 |       // Wait a moment before next attempt
  213 |       await page.waitForTimeout(1000);
  214 |     }
  215 |   }
  216 | 
  217 |   if (!loggedIn) {
  218 |     throw new Error('Could not log in with any candidate email');
  219 |   }
  220 | 
  221 |   // Save auth state
  222 |   await page.context().storageState({ path: AUTH_STATE });
  223 |   console.log('✅ Auth state saved');
  224 | });
  225 | 
  226 | /* ──────────────────────────────────────────────────────────────
  227 |  * TEST 2: Audit all web pages
  228 |  * ────────────────────────────────────────────────────────────── */
  229 | test('02 — Audit all web pages', async ({ browser }) => {
  230 |   test.setTimeout(600000); // 10 min for ~90 pages
  231 | 
  232 |   const contextOptions = fs.existsSync(AUTH_STATE) ? { storageState: AUTH_STATE } : {};
  233 |   const context = await browser.newContext(contextOptions);
  234 |   const page = await context.newPage();
  235 | 
  236 |   const failures = [];
  237 |   const pageErrors = [];
  238 |   const consoleErrors = [];
  239 | 
  240 |   page.on('pageerror', (err) => {
  241 |     pageErrors.push({ url: page.url(), error: err.message });
  242 |   });
  243 |   page.on('console', (msg) => {
  244 |     if (msg.type() === 'error') {
  245 |       consoleErrors.push({ url: page.url(), text: msg.text() });
  246 |     }
  247 |   });
  248 | 
  249 |   for (const routePath of WEB_PAGES) {
  250 |     const url = `${BASE}${routePath}`;
  251 |     let status = 0;
  252 |     let errorText = '';
  253 |     const networkFiveXX = [];
  254 | 
  255 |     // Catch 5xx on XHR/fetch fired by the page
  256 |     const respHandler = (resp) => {
  257 |       if (resp.status() >= 500) {
  258 |         networkFiveXX.push({ url: resp.url(), status: resp.status() });
  259 |       }
  260 |     };
  261 |     page.on('response', respHandler);
  262 | 
  263 |     try {
  264 |       const resp = await page.goto(url, { waitUntil: 'load', timeout: 30000 });
  265 |       status = resp?.status() || 0;
  266 | 
  267 |       // Wait for async fetches
  268 |       await page.waitForTimeout(1500);
  269 | 
  270 |       // Scan body for server-error text
  271 |       const body = await page.textContent('body').catch(() => '');
  272 |       if (/SQLSTATE|Unknown column|Column not found|Integrity constraint|server error|500 /i.test(body)) {
  273 |         errorText = body.replace(/\s+/g, ' ').slice(0, 400);
  274 |       }
  275 |     } catch (e) {
  276 |       errorText = e.message.slice(0, 300);
  277 |     }
  278 | 
  279 |     page.off('response', respHandler);
  280 | 
  281 |     const bad = status >= 400 || errorText || networkFiveXX.length > 0;
  282 |     if (bad) {
  283 |       failures.push({ page: routePath, status, error: errorText || null, networkErrors: networkFiveXX.length ? networkFiveXX : null });
  284 |     }
  285 |     console.log(`${bad ? '❌' : '✅'} [${status}] ${routePath}${errorText ? ' → ' + errorText.slice(0, 100) : ''}`);
  286 |     for (const ne of networkFiveXX) {
  287 |       console.log(`     ↳ 5xx [${ne.status}] ${ne.url}`);
```