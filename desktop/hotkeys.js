const { globalShortcut } = require('electron');

function registerGlobalHotkeys(mainWindow) {
  try {
    globalShortcut.unregisterAll();

    // Ctrl+Alt+P -> Quick Punch
    globalShortcut.register('CommandOrControl+Alt+P', () => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.show();
        mainWindow.focus();
        mainWindow.webContents.send('app:hotkey-action', { action: 'quick-punch' });
      }
    });

    // Ctrl+Alt+W -> Quick Daily Work
    globalShortcut.register('CommandOrControl+Alt+W', () => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.show();
        mainWindow.focus();
        mainWindow.webContents.send('app:hotkey-action', { action: 'quick-work' });
      }
    });

    // Ctrl+Alt+N -> Quick Quality NCR
    globalShortcut.register('CommandOrControl+Alt+N', () => {
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.show();
        mainWindow.focus();
        mainWindow.webContents.send('app:hotkey-action', { action: 'quick-ncr' });
      }
    });

    console.log('Global hotkeys registered (Ctrl+Alt+P, Ctrl+Alt+W, Ctrl+Alt+N)');
  } catch (err) {
    console.error('Failed to register global hotkeys:', err);
  }
}

function unregisterGlobalHotkeys() {
  try {
    globalShortcut.unregisterAll();
  } catch (err) {
    console.error('Failed to unregister hotkeys:', err);
  }
}

module.exports = {
  registerGlobalHotkeys,
  unregisterGlobalHotkeys
};
