const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
  isElectron: true,
  getAppVersion: () => ipcRenderer.invoke('app:get-version'),
  getServerUrl: () => ipcRenderer.invoke('app:get-server-url'),
  setServerUrl: (url) => ipcRenderer.invoke('app:set-server-url', url),
  sendNotification: (options) => ipcRenderer.send('app:send-notification', options),
  setBadgeCount: (count) => ipcRenderer.send('app:set-badge-count', count),
  openSettings: () => ipcRenderer.send('app:open-settings'),
  reloadApp: () => ipcRenderer.send('app:reload'),
  minimize: () => ipcRenderer.send('app:minimize'),
  maximize: () => ipcRenderer.send('app:maximize'),
  close: () => ipcRenderer.send('app:close'),
  printWindow: () => ipcRenderer.send('app:print-window'),
  setSecureData: (key, value) => ipcRenderer.invoke('app:set-secure-data', { key, value }),
  getSecureData: (key) => ipcRenderer.invoke('app:get-secure-data', key),
  showSaveDialog: (options) => ipcRenderer.invoke('app:show-save-dialog', options),
  showOpenDialog: (options) => ipcRenderer.invoke('app:show-open-dialog', options),
  setAutoLaunch: (enabled) => ipcRenderer.invoke('app:set-auto-launch', enabled),
  getAutoLaunch: () => ipcRenderer.invoke('app:get-auto-launch'),
  setProgressBar: (progress) => ipcRenderer.send('app:set-progress-bar', progress),
  flashFrame: (flag) => ipcRenderer.send('app:flash-frame', flag),
  onDeepLink: (callback) => {
    ipcRenderer.on('app:deep-link', (_event, url) => callback(url));
  },
  onHotkeyAction: (callback) => {
    ipcRenderer.on('app:hotkey-action', (_event, data) => callback(data));
  },
  onThemeChanged: (callback) => {
    ipcRenderer.on('app:theme-changed', (_event, data) => callback(data));
  },
  onNetworkStatusChanged: (callback) => {
    ipcRenderer.on('app:network-status', (_event, status) => callback(status));
  },
  onNavigate: (callback) => {
    ipcRenderer.on('app:navigate', (_event, data) => callback(data));
  },
  updateNativeMenu: (pages) => ipcRenderer.send('app:update-native-menu', pages)
});
