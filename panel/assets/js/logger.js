/**
 * Logger Utility
 * Handles application logging with different levels
 * 
 * @version 2.0
 */

const Logger = {
    /**
     * Log levels
     */
    levels: {
        DEBUG: 0,
        INFO: 1,
        WARNING: 2,
        ERROR: 3,
        CRITICAL: 4
    },
    
    /**
     * Current log level (can be changed via settings)
     */
    currentLevel: 1, // INFO by default
    
    /**
     * Enable/disable console logging
     */
    enabled: true,
    
    /**
     * Log a debug message
     */
    debug(message, data = null) {
        this.log('DEBUG', message, data);
    },
    
    /**
     * Log an info message
     */
    info(message, data = null) {
        this.log('INFO', message, data);
    },
    
    /**
     * Log a warning message
     */
    warning(message, data = null) {
        this.log('WARNING', message, data);
    },
    
    /**
     * Log an error message
     */
    error(message, data = null) {
        this.log('ERROR', message, data);
    },
    
    /**
     * Log a critical message
     */
    critical(message, data = null) {
        this.log('CRITICAL', message, data);
    },
    
    /**
     * Internal log method
     */
    log(level, message, data = null) {
        if (!this.enabled) {
            return;
        }
        
        const levelValue = this.levels[level] || 0;
        
        if (levelValue < this.currentLevel) {
            return;
        }
        
        const timestamp = new Date().toISOString();
        const prefix = `[${timestamp}] [${level}]`;
        
        // Choose console method
        const consoleMethod = {
            'DEBUG': 'log',
            'INFO': 'info',
            'WARNING': 'warn',
            'ERROR': 'error',
            'CRITICAL': 'error'
        }[level] || 'log';
        
        // Log to console
        if (data) {
            console[consoleMethod](prefix, message, data);
        } else {
            console[consoleMethod](prefix, message);
        }
        
        // Send critical errors to server (optional)
        if (level === 'CRITICAL' || level === 'ERROR') {
            this.sendToServer(level, message, data);
        }
    },
    
    /**
     * Send log to server (for error tracking)
     */
    sendToServer(level, message, data) {
        try {
            // Only send if API is available
            if (typeof API === 'undefined') {
                return;
            }
            
            API.post('logs/client-error.php', {
                level: level,
                message: message,
                data: data,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            }).catch(() => {
                // Silently fail - don't want to create infinite loop
            });
        } catch (error) {
            // Silently fail
        }
    },
    
    /**
     * Set log level
     */
    setLevel(level) {
        if (this.levels.hasOwnProperty(level)) {
            this.currentLevel = this.levels[level];
        }
    },
    
    /**
     * Enable/disable logging
     */
    setEnabled(enabled) {
        this.enabled = enabled;
    }
};

// Export to window
window.Logger = Logger;

// Log that logger is loaded
console.log('✅ Logger initialized');