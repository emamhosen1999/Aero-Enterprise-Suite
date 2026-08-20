const { safeStorage } = require('electron');

function encryptData(text) {
  if (!text) return '';
  try {
    if (safeStorage && safeStorage.isEncryptionAvailable()) {
      const buffer = safeStorage.encryptString(text);
      return 'enc:' + buffer.toString('hex');
    }
  } catch (err) {
    console.error('Encryption failed, falling back to base64:', err);
  }
  return 'b64:' + Buffer.from(text, 'utf8').toString('base64');
}

function decryptData(encryptedStr) {
  if (!encryptedStr) return '';
  try {
    if (encryptedStr.startsWith('enc:') && safeStorage && safeStorage.isEncryptionAvailable()) {
      const hexStr = encryptedStr.substring(4);
      const buffer = Buffer.from(hexStr, 'hex');
      return safeStorage.decryptString(buffer);
    } else if (encryptedStr.startsWith('b64:')) {
      const b64Str = encryptedStr.substring(4);
      return Buffer.from(b64Str, 'base64').toString('utf8');
    }
  } catch (err) {
    console.error('Decryption failed:', err);
  }
  return encryptedStr;
}

module.exports = {
  encryptData,
  decryptData
};
