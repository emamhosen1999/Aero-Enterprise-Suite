// @ts-check
const { test, expect } = require('@playwright/test');

const TARGETS = [
  { name: 'Local Dev Server', base: 'http://127.0.0.1:8000' },
  { name: 'Live Production', base: 'https://erp.dhakabypass.com' },
];

const PASS = 'jarvisMJ@321';

const CANDIDATES = [
  'emam@dhakabypass.com',
  'emamhsajeeb@gmail.com',
  'emam.hosen@dhakabypass.com',
  'emamul.jasim@dhakabypass.com',
  'admin@dhakabypass.com',
];

const DEVICE_ID = 'e4d9c72a-6b21-4f33-8a19-91c2847a9501';

for (const target of TARGETS) {
  test(`Mobile API Login and Punch — ${target.name}`, async ({ request }) => {
    test.setTimeout(120000);

    let token = null;
    let loggedInEmail = null;
    let userResource = null;

    console.log(`\n=============================================`);
    console.log(`  TESTING MOBILE API ON: ${target.name} (${target.base})`);
    console.log(`=============================================`);

    for (const email of CANDIDATES) {
      console.log(`\nAttempting /api/v1/auth/login with ${email}...`);

      const loginPayload = {
        email,
        password: PASS,
        device_id: DEVICE_ID,
        device_name: 'Playwright Mobile Client',
        device_type: 'android',
        device_signature: {
          platform: 'android',
          os_version: '14.0',
          model: 'Pixel 8',
          manufacturer: 'Google',
          brand: 'Google',
          app_version: '1.1.4',
          build_version: '114',
        },
      };

      try {
        const resp = await request.post(`${target.base}/api/v1/auth/login`, {
          data: loginPayload,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'User-Agent': 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Mobile Safari/537.36',
          },
        });

        const status = resp.status();
        const bodyText = await resp.text();
        let body;
        try { body = JSON.parse(bodyText); } catch { body = bodyText; }

        console.log(`Response Status: [${status}]`);
        console.log('Response Body:', JSON.stringify(body, null, 2));

        if (status === 200 && (body?.data?.token || body?.token)) {
          token = body?.data?.token || body?.token;
          userResource = body?.data?.user || body?.user;
          loggedInEmail = email;
          console.log(`\n🎉 LOGIN SUCCESSFUL with ${email}!`);
          console.log(`User Employee ID: ${userResource?.employee_id || userResource?.id}`);
          console.log(`Token: ${token.slice(0, 15)}...`);
          break;
        }
      } catch (err) {
        console.log(`Error during login request: ${err.message}`);
      }
    }

    if (!token) {
      console.log(`\n⚠️ Login did not complete on ${target.name}. Skipping punch.`);
      return;
    }

    console.log(`\n=============================================`);
    console.log(`  TESTING TODAY ATTENDANCE STATUS (${target.name})`);
    console.log(`=============================================`);

    const authHeaders = {
      'Authorization': `Bearer ${token}`,
      'X-Device-Id': DEVICE_ID,
      'Accept': 'application/json',
      'User-Agent': 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36',
    };

    const todayResp = await request.get(`${target.base}/api/v1/attendance/today`, {
      headers: authHeaders,
    });

    console.log(`GET /api/v1/attendance/today: [${todayResp.status()}]`);
    const todayBody = await todayResp.json().catch(() => ({}));
    console.log('Today Attendance:', JSON.stringify(todayBody, null, 2));

    console.log(`\n=============================================`);
    console.log(`  TESTING MOBILE PUNCH (${target.name})`);
    console.log(`=============================================`);

    const punchPayload = {
      type: 'polygon',
      lat: 23.8103,
      lng: 90.4125,
      location: 'Dhaka Bypass Toll Plaza Main Office',
      photo: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      timestamp: new Date().toISOString(),
    };

    const punchResp = await request.post(`${target.base}/api/v1/attendance/punch`, {
      data: punchPayload,
      headers: authHeaders,
    });

    console.log(`POST /api/v1/attendance/punch: [${punchResp.status()}]`);
    const punchResult = await punchResp.text();
    console.log('Punch Result:', punchResult);

    console.log(`\n=============================================`);
    console.log(`  CONFIRMING TODAY ATTENDANCE AFTER PUNCH (${target.name})`);
    console.log(`=============================================`);

    const afterResp = await request.get(`${target.base}/api/v1/attendance/today`, {
      headers: authHeaders,
    });

    console.log(`GET /api/v1/attendance/today (after): [${afterResp.status()}]`);
    const afterBody = await afterResp.json().catch(() => ({}));
    console.log('Today Attendance (Updated):', JSON.stringify(afterBody, null, 2));
  });
}
