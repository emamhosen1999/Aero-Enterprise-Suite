const { app, Menu, MenuItem, dialog, nativeTheme } = require('electron');
const fs = require('fs');

function setupWindowsJumpList() {
  if (process.platform !== 'win32') return;

  try {
    app.setJumpList([
      {
        type: 'tasks',
        items: [
          {
            type: 'task',
            title: 'Quick Punch / Attendance',
            description: 'Open attendance quick punch dialog',
            program: process.execPath,
            args: '--action=quick-punch',
            iconPath: process.execPath,
            iconIndex: 0
          },
          {
            type: 'task',
            title: 'Log Daily Work',
            description: 'Record daily progress and work entries',
            program: process.execPath,
            args: '--action=quick-work',
            iconPath: process.execPath,
            iconIndex: 0
          },
          {
            type: 'task',
            title: 'Quality NCR Log',
            description: 'File or inspect open NCR objections',
            program: process.execPath,
            args: '--action=quick-ncr',
            iconPath: process.execPath,
            iconIndex: 0
          }
        ]
      }
    ]);
  } catch (err) {
    console.error('Failed to setup Windows JumpList:', err);
  }
}

function setupNativeContextMenu(mainWindow) {
  if (!mainWindow) return;

  mainWindow.webContents.on('context-menu', (_event, params) => {
    const menu = new Menu();

    if (params.isEditable) {
      menu.append(new MenuItem({ role: 'undo' }));
      menu.append(new MenuItem({ role: 'redo' }));
      menu.append(new MenuItem({ type: 'separator' }));
      menu.append(new MenuItem({ role: 'cut' }));
      menu.append(new MenuItem({ role: 'copy' }));
      menu.append(new MenuItem({ role: 'paste' }));
      menu.append(new MenuItem({ role: 'selectAll' }));
    } else {
      if (params.selectionText && params.selectionText.trim().length > 0) {
        menu.append(new MenuItem({ role: 'copy' }));
        menu.append(new MenuItem({ type: 'separator' }));
      }
      menu.append(new MenuItem({ role: 'reload' }));
      menu.append(new MenuItem({
        label: 'Print Document',
        click: () => mainWindow.webContents.print({ silent: false, printBackground: true })
      }));
    }

    menu.popup({ window: mainWindow, x: params.x, y: params.y });
  });
}

function setupThemeSync(mainWindow) {
  if (!mainWindow) return;

  nativeTheme.on('updated', () => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('app:theme-changed', {
        isDarkMode: nativeTheme.shouldUseDarkColors
      });
    }
  });
}

async function showNativeSaveDialog(mainWindow, options) {
  if (!mainWindow || mainWindow.isDestroyed()) return null;
  const result = await dialog.showSaveDialog(mainWindow, {
    title: options.title || 'Save File - DBEDC Guardian',
    defaultPath: options.defaultPath || 'export.xlsx',
    filters: options.filters || [{ name: 'Documents', extensions: ['xlsx', 'pdf', 'csv', 'png'] }]
  });
  return result.canceled ? null : result.filePath;
}

async function showNativeOpenDialog(mainWindow, options) {
  if (!mainWindow || mainWindow.isDestroyed()) return null;
  const result = await dialog.showOpenDialog(mainWindow, {
    title: options.title || 'Select File - DBEDC Guardian',
    properties: options.properties || ['openFile'],
    filters: options.filters || [{ name: 'All Files', extensions: ['*'] }]
  });
  return result.canceled ? null : result.filePaths;
}

function setAutoLaunch(enabled) {
  try {
    app.setLoginItemSettings({
      openAtLogin: enabled,
      name: 'DBEDC Guardian'
    });
    return true;
  } catch (err) {
    console.error('Failed to update Auto-Launch setting:', err);
    return false;
  }
}

function getAutoLaunch() {
  try {
    const settings = app.getLoginItemSettings();
    return settings.openAtLogin;
  } catch (err) {
    return false;
  }
}

module.exports = {
  setupWindowsJumpList,
  setupNativeContextMenu,
  setupThemeSync,
  showNativeSaveDialog,
  showNativeOpenDialog,
  setAutoLaunch,
  getAutoLaunch
};
