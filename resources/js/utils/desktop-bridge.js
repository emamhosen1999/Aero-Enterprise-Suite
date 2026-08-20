/**
 * Utility helper for DBEDC Guardian Desktop Client integration (Electron)
 */

export const isDesktop = () => {
  return typeof window !== 'undefined' && window.electronAPI && window.electronAPI.isElectron === true;
};

export const getDesktopVersion = async () => {
  if (isDesktop()) {
    return await window.electronAPI.getAppVersion();
  }
  return null;
};

export const getDesktopServerUrl = async () => {
  if (isDesktop()) {
    return await window.electronAPI.getServerUrl();
  }
  return null;
};

export const setDesktopServerUrl = async (url) => {
  if (isDesktop()) {
    return await window.electronAPI.setServerUrl(url);
  }
  return false;
};

export const sendDesktopNotification = (title, body) => {
  if (isDesktop()) {
    window.electronAPI.sendNotification({ title, body });
  } else if (typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'granted') {
    new Notification(title, { body });
  }
};

export const setDesktopBadgeCount = (count) => {
  if (isDesktop()) {
    window.electronAPI.setBadgeCount(count);
  }
};

export const openDesktopSettings = () => {
  if (isDesktop()) {
    window.electronAPI.openSettings();
  }
};

export const subscribeDesktopDeepLink = (callback) => {
  if (isDesktop()) {
    window.electronAPI.onDeepLink(callback);
  }
};

export const subscribeDesktopHotkey = (callback) => {
  if (isDesktop()) {
    window.electronAPI.onHotkeyAction(callback);
  }
};

export const subscribeDesktopNavigation = (callback) => {
  if (isDesktop() && window.electronAPI.onNavigate) {
    window.electronAPI.onNavigate(callback);
  }
};

export const subscribeDesktopThemeChange = (callback) => {
  if (isDesktop()) {
    window.electronAPI.onThemeChanged(callback);
  }
};

export const setSecureToken = async (key, val) => {
  if (isDesktop()) {
    return await window.electronAPI.setSecureData(key, val);
  }
  localStorage.setItem(key, val);
  return true;
};

export const getSecureToken = async (key) => {
  if (isDesktop()) {
    return await window.electronAPI.getSecureData(key);
  }
  return localStorage.getItem(key);
};

export const triggerNativePrint = () => {
  if (isDesktop()) {
    window.electronAPI.printWindow();
  } else if (typeof window !== 'undefined') {
    window.print();
  }
};

export const showNativeSaveDialog = async (options = {}) => {
  if (isDesktop()) {
    return await window.electronAPI.showSaveDialog(options);
  }
  return null;
};

export const showNativeOpenDialog = async (options = {}) => {
  if (isDesktop()) {
    return await window.electronAPI.showOpenDialog(options);
  }
  return null;
};

export const setAutoLaunchOnLogin = async (enabled) => {
  if (isDesktop()) {
    return await window.electronAPI.setAutoLaunch(enabled);
  }
  return false;
};

export const getAutoLaunchOnLogin = async () => {
  if (isDesktop()) {
    return await window.electronAPI.getAutoLaunch();
  }
  return false;
};

export const setTaskbarProgress = (progress) => {
  if (isDesktop()) {
    window.electronAPI.setProgressBar(progress);
  }
};

export const flashTaskbarFrame = (flag = true) => {
  if (isDesktop()) {
    window.electronAPI.flashFrame(flag);
  }
};
