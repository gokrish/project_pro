/**
 * Logger Utility
 * Centralized logging with error tracking
 * 
 * @version 5.0
 */

const Logger = {
    /**
     * Log levels
     */
    LEVELS: {
        DEBUG: 'debug',
        INFO: 'info',
        WARN: 'warn',
        ERROR: 'error',
        CRITICAL: 'critical'
    },
    
    /**
     * Enable/disable logging based on debug mode
     */
    enabled: window.APP_CONFIG?.debug || false,
    
    /**
     * Store logs for debugging
     */
    logs: [],
    
    /**
     * Log debug message
     */
    debug(message, data = null) {
        this._log(this.LEVELS.DEBUG, message, data, 'color: #718096');
    },
    
    /**
     * Log info message
     */
    info(message, data = null) {
        this._log(this.LEVELS.INFO, message, data, 'color: #4299e1');
    },
    
    /**
     * Log warning message
     */
    warn(message, data = null) {
        this._log(this.LEVELS.WARN, message, data, 'color: #f6ad55');
    },
    
    /**
     * Log error message
     */
    error(message, data = null) {
        this._log(this.LEVELS.ERROR, message, data, 'color: #f56565');
        
        // Send critical errors to server
        if (this.enabled) {
            this._sendToServer(this.LEVELS.ERROR, message, data);
        }
    },
    
    /**
     * Log critical error (always sent to server)
     */
    critical(message, data = null) {
        this._log(this.LEVELS.CRITICAL, message, data, 'color: #c53030; font-weight: bold');
        this._sendToServer(this.LEVELS.CRITICAL, message, data);
    },
    
    /**
     * Internal logging method
     */
    _log(level, message, data, style) {
        const timestamp = new Date().toISOString();
        const logEntry = {
            timestamp,
            level,
            message,
            data,
            url: window.location.href,
            user: window.APP_CONFIG?.user?.code || 'guest'
        };
        
        // Store log
        this.logs.push(logEntry);
        
        // Keep only last 100 logs
        if (this.logs.length > 100) {
            this.logs.shift();
        }
        
        // Console output in debug mode
        if (this.enabled) {
            const prefix = `%c[${level.toUpperCase()}] ${timestamp}`;
            
            if (data) {
                console.log(prefix, style, message, data);
            } else {
                console.log(prefix, style, message);
            }
        }
    },
    
    /**
     * Send error to server
     */
    _sendToServer(level, message, data) {
        try {
            fetch('/panel/api/log-error.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.APP_CONFIG?.csrfToken || ''
                },
                body: JSON.stringify({
                    level,
                    message,
                    data,
                    url: window.location.href,
                    user_agent: navigator.userAgent,
                    stack: new Error().stack
                })
            }).catch(err => {
                console.error('Failed to send error to server:', err);
            });
        } catch (err) {
            console.error('Error in _sendToServer:', err);
        }
    },
    
    /**
     * Get all logs
     */
    getAllLogs() {
        return this.logs;
    },
    
    /**
     * Export logs as JSON
     */
    export() {
        const dataStr = JSON.stringify(this.logs, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
        
        const exportFileDefaultName = `logs_${new Date().toISOString()}.json`;
        
        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        linkElement.click();
    },
    
    /**
     * Clear all logs
     */
    clear() {
        this.logs = [];
        if (this.enabled) {
            console.clear();
            console.log('%c🗑️ Logs cleared', 'color: #718096');
        }
    }
};

// Global error handler
window.addEventListener('error', function(event) {
    Logger.error('Uncaught JavaScript Error', {
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error?.stack
    });
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(event) {
    Logger.error('Unhandled Promise Rejection', {
        reason: event.reason,
        promise: event.promise
    });
});

// Export to window
window.Logger = Logger;