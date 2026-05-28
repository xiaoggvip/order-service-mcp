import fs from 'fs';
import path from 'path';

function getEnv() {
  return {
    debug: process.env.DEBUG === 'true',
    logToFile: process.env.LOG_TO_FILE === 'true',
    logDir: process.env.LOG_DIR || './logs',
    logLevel: process.env.LOG_LEVEL || 'info'
  };
}

const logLevels = ['debug', 'info', 'warn', 'error'];

function ensureLogDir(logDir) {
  try {
    if (!fs.existsSync(logDir)) {
      fs.mkdirSync(logDir, { recursive: true });
    }
    return true;
  } catch (err) {
    return false;
  }
}

function log(level, message, data = null) {
  const env = getEnv();
  const levelIndex = logLevels.indexOf(level);
  const currentLogLevelIndex = logLevels.indexOf(env.logLevel);
  
  if (levelIndex < currentLogLevelIndex) return;
  
  if (!env.debug && !env.logToFile) return;
  
  const timestamp = new Date().toISOString();
  const logMessage = data 
    ? `[${timestamp}] [${level.toUpperCase()}] ${message}\n${JSON.stringify(data, null, 2)}`
    : `[${timestamp}] [${level.toUpperCase()}] ${message}`;
  
  if (env.logToFile) {
    const logDirReady = ensureLogDir(env.logDir);
    if (logDirReady) {
      try {
        const logFile = path.join(env.logDir, `mcp-${new Date().toISOString().split('T')[0]}.log`);
        fs.appendFileSync(logFile, logMessage + '\n\n', { encoding: 'utf8' });
      } catch (err) {
        if (env.debug) {
          console.error('[Logger] 写入日志文件失败:', err.message);
        }
      }
    }
  }
  
  if (env.debug) {
    console.error(logMessage);
  }
}

export const logger = {
  debug: (message, data) => log('debug', message, data),
  info: (message, data) => log('info', message, data),
  warn: (message, data) => log('warn', message, data),
  error: (message, data) => log('error', message, data)
};

export default logger;
