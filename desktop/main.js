const { app, BrowserWindow, Menu, Tray, ipcMain, Notification, shell } = require('electron');
const path = require('path');
const http = require('http');
const https = require('https');
const fs = require('fs');

const { encryptData, decryptData } = require('./secure-storage');
const { registerGlobalHotkeys, unregisterGlobalHotkeys } = require('./hotkeys');
const {
  setupWindowsJumpList,
  setupNativeContextMenu,
  setupThemeSync,
  showNativeSaveDialog,
  showNativeOpenDialog,
  setAutoLaunch,
  getAutoLaunch
} = require('./windows-native');

// Enable Chromium V8 Bytecode Caching for instant script parsing & 0ms page loads
app.commandLine.appendSwitch('enable-features', 'V8CodeCache');
app.commandLine.appendSwitch('disk-cache-size', '524288000');

const isDevMode = process.argv.includes('--dev') || process.env.NODE_ENV === 'development';

// Ensure single instance (in production mode)
if (!isDevMode) {
  const gotTheLock = app.requestSingleInstanceLock();
  if (!gotTheLock) {
    app.exit(0);
  }
}

let mainWindow = null;
let settingsWindow = null;
let tray = null;
let isQuitting = false;
let isLoadingFallback = false;
let heartbeatInterval = null;

// Protocol registration for deep linking (dbedc://)
if (process.defaultApp) {
  if (process.argv.length >= 2) {
    app.setAsDefaultProtocolClient('dbedc', process.execPath, [path.resolve(process.argv[1])]);
  }
} else {
  app.setAsDefaultProtocolClient('dbedc');
}

// Stored configuration handling
const configPath = path.join(app.getPath('userData'), 'desktop-config.json');

function loadConfig() {
  try {
    if (fs.existsSync(configPath)) {
      const data = fs.readFileSync(configPath, 'utf8');
      return JSON.parse(data);
    }
  } catch (err) {
    console.error('Failed to load desktop config:', err);
  }
  return {};
}

function saveConfig(config) {
  try {
    fs.writeFileSync(configPath, JSON.stringify(config, null, 2), 'utf8');
  } catch (err) {
    console.error('Failed to save desktop config:', err);
  }
}

let config = loadConfig();
if (!config.secureStore) {
  config.secureStore = {};
}

function getEffectiveServerUrl() {
  if (config.serverUrl) return config.serverUrl;
  return 'http://erp.dhakabypass.com';
}

function getIconPath() {
  const iconPath = path.join(__dirname, '..', 'public', 'favicon.ico');
  if (fs.existsSync(iconPath)) return iconPath;
  const pngPath = path.join(__dirname, '..', 'public', 'windows11', 'Square44x44Logo.targetsize-256.png');
  if (fs.existsSync(pngPath)) return pngPath;
  return null;
}

function navigateToRoute(routePath) {
  if (!mainWindow || mainWindow.isDestroyed()) return;
  const baseUrl = getEffectiveServerUrl();
  const cleanPath = routePath.startsWith('/') ? routePath : '/' + routePath;
  const fullUrl = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) + cleanPath : baseUrl + cleanPath;
  mainWindow.loadURL(fullUrl).catch((err) => {
    console.error(`Failed to navigate to ${routePath}:`, err);
  });
}

function createMainWindow() {
  const appIcon = getIconPath();

  mainWindow = new BrowserWindow({
    width: 1280,
    height: 830,
    minWidth: 900,
    minHeight: 600,
    title: `DBEDC Guardian Enterprise Suite ${isDevMode ? '(Dev Mode)' : ''}`,
    icon: appIcon || undefined,
    backgroundColor: '#0f172a',
    show: true,
    autoHideMenuBar: false,
    titleBarStyle: 'default',
    titleBarOverlay: false,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: false
    }
  });

  // Display dark splash screen immediately on app launch (eliminates blank white screen)
  mainWindow.loadFile(path.join(__dirname, 'assets', 'loading.html')).catch(() => {});

  mainWindow.center();
  mainWindow.show();
  mainWindow.focus();

  setupAppMenu();
  setupTray(appIcon);
  setupWindowsJumpList();
  setupNativeContextMenu(mainWindow);
  setupThemeSync(mainWindow);
  registerGlobalHotkeys(mainWindow);
  startSessionHeartbeat();

  mainWindow.webContents.on('did-fail-load', (_event, errorCode, errorDescription, validatedURL) => {
    console.error(`did-fail-load: ${errorCode} (${errorDescription}) for URL: ${validatedURL}`);
    if (errorCode === -3 || (validatedURL && validatedURL.startsWith('file://')) || isLoadingFallback) {
      return;
    }

    isLoadingFallback = true;
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.loadFile(path.join(__dirname, 'assets', 'offline.html'))
        .finally(() => { isLoadingFallback = false; });
    }
  });

  mainWindow.on('close', (event) => {
    if (!isQuitting) {
      event.preventDefault();
      mainWindow.hide();
      return false;
    }
  });

  // Smoothly transition from splash loader to server URL
  setTimeout(() => {
    loadTargetUrl();
  }, 400);
}

async function loadTargetUrl() {
  if (!mainWindow || mainWindow.isDestroyed()) return;

  const targetUrl = getEffectiveServerUrl();
  try {
    await mainWindow.loadURL(targetUrl);
  } catch (err) {
    console.error('loadURL failed:', err.message || err);
    if (!isLoadingFallback && mainWindow && !mainWindow.isDestroyed()) {
      isLoadingFallback = true;
      mainWindow.loadFile(path.join(__dirname, 'assets', 'offline.html'))
        .finally(() => { isLoadingFallback = false; });
    }
  }
}

function startSessionHeartbeat() {
  if (heartbeatInterval) clearInterval(heartbeatInterval);

  heartbeatInterval = setInterval(() => {
    if (!mainWindow || mainWindow.isDestroyed()) return;
    const targetUrl = getEffectiveServerUrl();

    try {
      const parsed = new URL(targetUrl);
      const reqModule = parsed.protocol === 'https:' ? https : http;
      const req = reqModule.request(
        {
          hostname: parsed.hostname,
          port: parsed.port || (parsed.protocol === 'https:' ? 443 : 80),
          path: '/session-check',
          method: 'GET',
          timeout: 5000
        },
        (res) => {
          const isOnline = res.statusCode >= 200 && res.statusCode < 500;
          if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send('app:network-status', isOnline);
          }
        }
      );
      req.on('error', () => {
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.webContents.send('app:network-status', false);
        }
      });
      req.end();
    } catch (e) {
      // Ignore heartbeat parsing errors
    }
  }, 60000);
}

function buildNativeMenuFromPages(pages) {
  if (!Array.isArray(pages) || pages.length === 0) return;

  const mainItems = [];
  const workforceItems = [];
  const qualityItems = [];
  const adminItems = [];

  const convertPageToMenuItem = (page) => {
    if (page.subMenu && Array.isArray(page.subMenu) && page.subMenu.length > 0) {
      const validSub = page.subMenu.map(convertPageToMenuItem).filter(Boolean);
      if (validSub.length === 0) return null;
      return {
        label: page.name,
        submenu: validSub
      };
    }
    if (page.route) {
      return {
        label: page.name,
        click: () => navigateToRoute(page.route)
      };
    }
    return null;
  };

  pages.forEach((page) => {
    const item = convertPageToMenuItem(page);
    if (!item) return;

    const category = (page.category || '').toLowerCase();
    const name = (page.name || '').toLowerCase();

    if (category === 'settings' || category === 'admin' || name.includes('admin')) {
      adminItems.push(item);
    } else if (name.includes('workforce') || name.includes('employee') || name.includes('attendance') || name.includes('holiday') || name.includes('leave')) {
      workforceItems.push(item);
    } else if (name.includes('quality') || name.includes('ncr')) {
      qualityItems.push(item);
    } else {
      mainItems.push(item);
    }
  });

  const customTemplate = [
    {
      label: 'File',
      submenu: [
        { label: 'Server Endpoint Settings...', click: () => openSettingsWindow() },
        { type: 'separator' },
        { label: 'Reload Application', accelerator: 'CmdOrCtrl+R', click: () => loadTargetUrl() },
        { label: 'Exit DBEDC Guardian', accelerator: 'CmdOrCtrl+Q', click: () => { isQuitting = true; app.quit(); } }
      ]
    }
  ];

  if (mainItems.length > 0) {
    customTemplate.push({ label: 'Main', submenu: mainItems });
  }
  if (workforceItems.length > 0) {
    customTemplate.push({ label: 'Workforce', submenu: workforceItems });
  }
  if (qualityItems.length > 0) {
    customTemplate.push({ label: 'Quality', submenu: qualityItems });
  }
  if (adminItems.length > 0) {
    customTemplate.push({ label: 'Admin', submenu: adminItems });
  }

  customTemplate.push(
    {
      label: 'Quick Actions',
      submenu: [
        {
          label: 'Quick Punch Attendance',
          accelerator: 'Ctrl+Alt+P',
          click: () => mainWindow && !mainWindow.isDestroyed() && mainWindow.webContents.send('app:hotkey-action', { action: 'quick-punch' })
        },
        {
          label: 'Log Daily Work Entry',
          accelerator: 'Ctrl+Alt+W',
          click: () => mainWindow && !mainWindow.isDestroyed() && mainWindow.webContents.send('app:hotkey-action', { action: 'quick-work' })
        },
        {
          label: 'File Quality NCR Objection',
          accelerator: 'Ctrl+Alt+N',
          click: () => mainWindow && !mainWindow.isDestroyed() && mainWindow.webContents.send('app:hotkey-action', { action: 'quick-ncr' })
        }
      ]
    },
    {
      label: 'Edit',
      submenu: [{ role: 'undo' }, { role: 'redo' }, { type: 'separator' }, { role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { role: 'selectAll' }]
    },
    {
      label: 'View',
      submenu: [{ role: 'reload' }, { role: 'forceReload' }, { role: 'toggleDevTools' }, { type: 'separator' }, { role: 'resetZoom' }, { role: 'zoomIn' }, { role: 'zoomOut' }, { type: 'separator' }, { role: 'togglefullscreen' }]
    },
    {
      label: 'Help',
      submenu: [
        { label: 'Visit Web Portal', click: () => shell.openExternal('http://erp.dhakabypass.com') },
        { label: 'About DBEDC Guardian Desktop', click: () => { if (Notification.isSupported()) new Notification({ title: 'DBEDC Guardian Desktop', body: `Version ${app.getVersion()}` }).show(); } }
      ]
    }
  );

  const menu = Menu.buildFromTemplate(customTemplate);
  Menu.setApplicationMenu(menu);
}

function setupAppMenu() {
  const defaultPages = [
    { name: 'Dashboard', route: '/dashboard', category: 'main' },
    { name: 'Employee Dashboard', route: '/employee-dashboard', category: 'main' },
    { name: 'Daily Works', route: '/daily-works', category: 'main' },
    { name: 'My Attendance', route: '/attendance/my-attendance', category: 'main' },
    { name: 'My Leaves', route: '/leaves', category: 'main' },
    { name: 'Petty Cash Management', route: '/petty-cash', category: 'main' },
    {
      name: 'Workforce',
      category: 'workforce',
      subMenu: [
        { name: 'Employees Directory', route: '/employees' },
        {
          name: 'Time & Attendance',
          subMenu: [
            { name: 'Attendance Register', route: '/attendances' },
            { name: 'Holidays Schedule', route: '/holidays' },
            { name: 'Leave Management', route: '/leaves/management' }
          ]
        }
      ]
    },
    { name: 'Quality NCR Objections', route: '/ncrs', category: 'quality' },
    {
      name: 'Admin',
      category: 'admin',
      subMenu: [
        { name: 'Users Management', route: '/users' },
        { name: 'Roles & Permissions', route: '/roles' },
        { name: 'Application Settings', route: '/settings' },
        {
          name: 'System Management',
          subMenu: [
            { name: 'Active Device Sessions', route: '/admin/device-sessions' },
            { name: 'Feature Flags', route: '/admin/feature-flags' },
            { name: 'Notification Settings', route: '/admin/notification-settings' }
          ]
        }
      ]
    }
  ];

  buildNativeMenuFromPages(defaultPages);
}

function setupTray(iconPath) {
  if (tray) return;
  if (!iconPath) return;

  tray = new Tray(iconPath);
  tray.setToolTip('DBEDC Guardian Desktop');

  const trayMenu = Menu.buildFromTemplate([
    {
      label: 'Open DBEDC Guardian',
      click: () => {
        if (mainWindow && !mainWindow.isDestroyed()) {
          mainWindow.show();
          mainWindow.focus();
        }
      }
    },
    {
      label: 'Server Endpoint Settings',
      click: () => openSettingsWindow()
    },
    {
      label: 'Reload',
      click: () => loadTargetUrl()
    },
    { type: 'separator' },
    {
      label: 'Exit DBEDC Guardian',
      click: () => {
        isQuitting = true;
        app.quit();
      }
    }
  ]);

  tray.setContextMenu(trayMenu);
  tray.on('double-click', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.show();
      mainWindow.focus();
    }
  });
}

function openSettingsWindow() {
  if (settingsWindow && !settingsWindow.isDestroyed()) {
    settingsWindow.focus();
    return;
  }

  const appIcon = getIconPath();

  settingsWindow = new BrowserWindow({
    width: 480,
    height: 340,
    resizable: false,
    title: 'Server Settings - DBEDC Guardian',
    icon: appIcon || undefined,
    parent: (mainWindow && !mainWindow.isDestroyed()) ? mainWindow : undefined,
    modal: true,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true
    }
  });

  settingsWindow.loadFile(path.join(__dirname, 'assets', 'settings.html'));

  settingsWindow.on('closed', () => {
    settingsWindow = null;
  });
}

// Handle Deep Link protocols
app.on('second-instance', (event, commandLine) => {
  if (mainWindow && !mainWindow.isDestroyed()) {
    if (mainWindow.isMinimized()) mainWindow.restore();
    mainWindow.show();
    mainWindow.focus();

    const deepLinkUrl = commandLine.find((arg) => arg.startsWith('dbedc://'));
    if (deepLinkUrl) {
      mainWindow.webContents.send('app:deep-link', deepLinkUrl);
    }
  }
});

// IPC listeners
ipcMain.handle('app:get-version', () => app.getVersion());

ipcMain.handle('app:get-server-url', () => getEffectiveServerUrl());

ipcMain.handle('app:set-server-url', (_event, newUrl) => {
  config.serverUrl = newUrl;
  saveConfig(config);
  return true;
});

ipcMain.handle('app:set-secure-data', (_event, { key, value }) => {
  if (!config.secureStore) config.secureStore = {};
  config.secureStore[key] = encryptData(value);
  saveConfig(config);
  return true;
});

ipcMain.handle('app:get-secure-data', (_event, key) => {
  if (config.secureStore && config.secureStore[key]) {
    return decryptData(config.secureStore[key]);
  }
  return null;
});

ipcMain.handle('app:show-save-dialog', (_event, options) => {
  return showNativeSaveDialog(mainWindow, options);
});

ipcMain.handle('app:show-open-dialog', (_event, options) => {
  return showNativeOpenDialog(mainWindow, options);
});

ipcMain.handle('app:set-auto-launch', (_event, enabled) => {
  return setAutoLaunch(enabled);
});

ipcMain.handle('app:get-auto-launch', () => {
  return getAutoLaunch();
});

ipcMain.on('app:update-native-menu', (_event, pages) => {
  try {
    buildNativeMenuFromPages(pages);
  } catch (err) {
    console.error('Failed to update native menu from pages:', err);
  }
});

ipcMain.on('app:send-notification', (_event, options) => {
  if (Notification.isSupported()) {
    new Notification({
      title: options.title || 'DBEDC Guardian',
      body: options.body || '',
      icon: getIconPath() || undefined
    }).show();
  }
});

ipcMain.on('app:set-badge-count', (_event, count) => {
  if (app.setBadgeCount) {
    app.setBadgeCount(count || 0);
  }
});

ipcMain.on('app:set-progress-bar', (_event, progress) => {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.setProgressBar(typeof progress === 'number' ? progress : -1);
  }
});

ipcMain.on('app:flash-frame', (_event, flag) => {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.flashFrame(flag !== false);
  }
});

ipcMain.on('app:print-window', () => {
  if (mainWindow && !mainWindow.isDestroyed()) {
    mainWindow.webContents.print({ silent: false, printBackground: true });
  }
});

ipcMain.on('app:open-settings', () => openSettingsWindow());

ipcMain.on('app:reload', () => loadTargetUrl());

ipcMain.on('app:minimize', () => mainWindow && !mainWindow.isDestroyed() && mainWindow.minimize());
ipcMain.on('app:maximize', () => {
  if (!mainWindow || mainWindow.isDestroyed()) return;
  if (mainWindow.isMaximized()) {
    mainWindow.unmaximize();
  } else {
    mainWindow.maximize();
  }
});
ipcMain.on('app:close', () => mainWindow && !mainWindow.isDestroyed() && mainWindow.close());

// Lifecycle handlers
app.whenReady().then(() => {
  createMainWindow();

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createMainWindow();
    }
  });
});

app.on('will-quit', () => {
  unregisterGlobalHotkeys();
  if (heartbeatInterval) clearInterval(heartbeatInterval);
});

app.on('before-quit', () => {
  isQuitting = true;
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
