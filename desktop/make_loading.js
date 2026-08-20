const fs = require('fs');
const path = require('path');

const logoPath = path.join(__dirname, '..', 'public', 'assets', 'images', 'logo.png');
let logoDataUrl = '';

if (fs.existsSync(logoPath)) {
  const logoBuffer = fs.readFileSync(logoPath);
  logoDataUrl = `data:image/png;base64,${logoBuffer.toString('base64')}`;
}

const htmlContent = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DBEDC Guardian - Loading</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      background: #0f172a;
      color: #f8fafc;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      user-select: none;
    }
    .splash-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      animation: fadeIn 0.4s ease-out;
    }
    .logo-wrapper {
      position: relative;
      width: 84px;
      height: 84px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
      animation: pulse 2s ease-in-out infinite;
    }
    .logo-img {
      width: 54px;
      height: 54px;
      object-fit: contain;
    }
    .title-group {
      text-align: center;
    }
    .brand-title {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.02em;
      color: #ffffff;
      margin-bottom: 4px;
    }
    .brand-subtitle {
      font-size: 13px;
      color: #94a3b8;
      font-weight: 400;
    }
    .progress-bar-container {
      width: 220px;
      height: 4px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 4px;
      overflow: hidden;
      margin-top: 10px;
      position: relative;
    }
    .progress-bar-fill {
      width: 40%;
      height: 100%;
      background: linear-gradient(90deg, #3b82f6, #60a5fa);
      border-radius: 4px;
      position: absolute;
      animation: loadingSlide 1.5s ease-in-out infinite;
    }
    .status-text {
      font-size: 12px;
      color: #64748b;
      margin-top: 6px;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.96); }
      to { opacity: 1; transform: scale(1); }
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); }
      50% { transform: scale(1.03); box-shadow: 0 25px 50px rgba(59, 130, 246, 0.25); }
    }
    @keyframes loadingSlide {
      0% { left: -40%; }
      50% { left: 50%; }
      100% { left: 100%; }
    }
  </style>
</head>
<body>
  <div class="splash-container">
    <div class="logo-wrapper">
      ${logoDataUrl ? `<img src="${logoDataUrl}" alt="DBEDC Logo" class="logo-img">` : ''}
    </div>
    <div class="title-group">
      <h1 class="brand-title">DBEDC Guardian</h1>
      <p class="brand-subtitle">Dhaka Bypass Expressway Enterprise Suite</p>
    </div>
    <div class="progress-bar-container">
      <div class="progress-bar-fill"></div>
    </div>
    <p class="status-text">Initializing & Connecting to Enterprise Server...</p>
  </div>
</body>
</html>`;

const outputPath = path.join(__dirname, 'assets', 'loading.html');
fs.writeFileSync(outputPath, htmlContent, 'utf8');
console.log('Successfully generated loading.html with embedded base64 logo!');
