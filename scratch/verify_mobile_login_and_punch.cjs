const http = require('http');

function postJson(urlStr, data, headers = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const bodyStr = JSON.stringify(data);
    const req = http.request({
      hostname: url.hostname,
      port: url.port || 80,
      path: url.pathname + url.search,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Content-Length': Buffer.byteLength(bodyStr),
        ...headers,
      },
    }, (res) => {
      let raw = '';
      res.on('data', chunk => raw += chunk);
      res.on('end', () => {
        let json;
        try { json = JSON.parse(raw); } catch { json = raw; }
        resolve({ status: res.statusCode, data: json });
      });
    });
    req.on('error', reject);
    req.write(bodyStr);
    req.end();
  });
}

function getJson(urlStr, headers = {}) {
  return new Promise((resolve, reject) => {
    const url = new URL(urlStr);
    const req = http.request({
      hostname: url.hostname,
      port: url.port || 80,
      path: url.pathname + url.search,
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        ...headers,
      },
    }, (res) => {
      let raw = '';
      res.on('data', chunk => raw += chunk);
      res.on('end', () => {
        let json;
        try { json = JSON.parse(raw); } catch { json = raw; }
        resolve({ status: res.statusCode, data: json });
      });
    });
    req.on('error', reject);
    req.end();
  });
}

async function main() {
  console.log('=== TESTING MOBILE API ON LOCALHOST:8000 ===');
  const candidates = [
    { email: 'admin@dhakabypass.com', pass: 'password' },
    { email: 'emam@dhakabypass.com', pass: 'jarvisMJ@321' },
    { email: 'emamhsajeeb@gmail.com', pass: 'jarvisMJ@321' },
    { email: 'admin@admin.com', pass: 'password' },
  ];

  let token = null;
  let user = null;
  const deviceId = 'a1b2c3d4-e5f6-47a8-b9c0-d1e2f3a4b5c6';

  for (const c of candidates) {
    console.log(`\nTrying login: ${c.email} ...`);
    const resp = await postJson('http://127.0.0.1:8000/api/v1/auth/login', {
      email: c.email,
      password: c.pass,
      device_id: deviceId,
      device_name: 'Test Node Client',
      device_type: 'android',
      device_signature: {
        platform: 'android',
        os_version: '14.0',
        model: 'Pixel 8',
        brand: 'Google',
        app_version: '1.1.4',
      },
    });

    console.log(`Status: [${resp.status}]`);
    if (resp.status === 200 && resp.data?.data?.token) {
      token = resp.data.data.token;
      user = resp.data.data.user;
      console.log('🎉 Login Success! User:', user);
      break;
    } else {
      console.log('Response:', JSON.stringify(resp.data));
    }
  }

  if (!token) {
    console.log('\nCould not log in with candidate passwords. Let us check.');
    return;
  }

  console.log('\n=== TESTING TODAY ATTENDANCE ===');
  const today = await getJson('http://127.0.0.1:8000/api/v1/attendance/today', {
    'Authorization': `Bearer ${token}`,
    'X-Device-Id': deviceId,
  });
  console.log('Today Status:', today.status, JSON.stringify(today.data, null, 2));

  console.log('\n=== TESTING ATTENDANCE PUNCH ===');
  const punch = await postJson('http://127.0.0.1:8000/api/v1/attendance/punch', {
    lat: 23.8103,
    lng: 90.4125,
    location: 'Dhaka Bypass Toll Plaza',
    photo: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  }, {
    'Authorization': `Bearer ${token}`,
    'X-Device-Id': deviceId,
  });
  console.log('Punch Status:', punch.status, JSON.stringify(punch.data, null, 2));
}

main().catch(console.error);
