/**
 * API Client
 * Centralized AJAX request handler with error handling
 * 
 * @version 5.0
 */

const API = {
    /**
     * Base API URL
     */
    baseUrl: window.APP_CONFIG?.apiUrl || '/panel/api',
    
    /**
     * Default headers
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.APP_CONFIG?.csrfToken || '',
            'X-Requested-With': 'XMLHttpRequest'
        };
    },
    
    /**
     * Make GET request
     */
    async get(endpoint, params = {}) {
        Logger.debug(`API GET: ${endpoint}`, params);
        
        try {
            const queryString = new URLSearchParams(params).toString();
            const url = `${this.baseUrl}/${endpoint}${queryString ? '?' + queryString : ''}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            return await this._handleResponse(response, 'GET', endpoint);
            
        } catch (error) {
            return this._handleError(error, 'GET', endpoint);
        }
    },
    
    /**
     * Make POST request
     */
    async post(endpoint, data = {}) {
        Logger.debug(`API POST: ${endpoint}`, data);
        
        try {
            const url = `${this.baseUrl}/${endpoint}`;
            
            const response = await fetch(url, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify(data)
            });
            
            return await this._handleResponse(response, 'POST', endpoint);
            
        } catch (error) {
            return this._handleError(error, 'POST', endpoint);
        }
    },
    
    /**
     * Make PUT request
     */
    async put(endpoint, data = {}) {
        Logger.debug(`API PUT: ${endpoint}`, data);
        
        try {
            const url = `${this.baseUrl}/${endpoint}`;
            
            const response = await fetch(url, {
                method: 'PUT',
                headers: this.getHeaders(),
                body: JSON.stringify(data)
            });
            
            return await this._handleResponse(response, 'PUT', endpoint);
            
        } catch (error) {
            return this._handleError(error, 'PUT', endpoint);
        }
    },
    
    /**
     * Make DELETE request
     */
    async delete(endpoint, data = {}) {
        Logger.debug(`API DELETE: ${endpoint}`, data);
        
        try {
            const url = `${this.baseUrl}/${endpoint}`;
            
            const response = await fetch(url, {
                method: 'DELETE',
                headers: this.getHeaders(),
                body: JSON.stringify(data)
            });
            
            return await this._handleResponse(response, 'DELETE', endpoint);
            
        } catch (error) {
            return this._handleError(error, 'DELETE', endpoint);
        }
    },
    
    /**
     * Upload file with progress tracking
     */
    async upload(endpoint, formData, onProgress = null) {
        Logger.debug(`API UPLOAD: ${endpoint}`);
        
        try {
            const url = `${this.baseUrl}/${endpoint}`;
            
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                // Track upload progress
                if (onProgress && typeof onProgress === 'function') {
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            onProgress(percentComplete);
                            Logger.debug(`Upload progress: ${percentComplete.toFixed(2)}%`);
                        }
                    });
                }
                
                // Handle completion
                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            Logger.info(`API UPLOAD SUCCESS: ${endpoint}`, response);
                            resolve(response);
                        } catch (error) {
                            Logger.error('Failed to parse upload response', error);
                            reject(new Error('Invalid response format'));
                        }
                    } else {
                        Logger.error(`API UPLOAD ERROR: ${endpoint}`, {
                            status: xhr.status,
                            response: xhr.responseText
                        });
                        reject(new Error(`Upload failed: ${xhr.status}`));
                    }
                });
                
                // Handle errors
                xhr.addEventListener('error', () => {
                    Logger.error(`API UPLOAD NETWORK ERROR: ${endpoint}`);
                    reject(new Error('Network error during upload'));
                });
                
                // Set headers (except Content-Type - let browser set it for multipart)
                xhr.open('POST', url);
                xhr.setRequestHeader('X-CSRF-Token', window.APP_CONFIG?.csrfToken || '');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                // Send request
                xhr.send(formData);
            });
            
        } catch (error) {
            return this._handleError(error, 'UPLOAD', endpoint);
        }
    },
    
    /**
     * Handle response
     */
    async _handleResponse(response, method, endpoint) {
        let data;
        
        try {
            data = await response.json();
        } catch (error) {
            Logger.error('Failed to parse API response', {
                method,
                endpoint,
                status: response.status,
                error: error.message
            });
            
            return {
                success: false,
                message: 'Invalid response from server',
                data: null
            };
        }
        
        if (!response.ok) {
            Logger.warn(`API ${method} ${response.status}: ${endpoint}`, data);
            
            // Handle specific status codes
            if (response.status === 401) {
                Helpers.showToast('Session expired. Please login again.', 'error');
                setTimeout(() => {
                    window.location.href = '/panel/login.php';
                }, 2000);
            } else if (response.status === 403) {
                Helpers.showToast('You don\'t have permission to perform this action', 'error');
            } else if (response.status === 404) {
                Helpers.showToast('Resource not found', 'error');
            } else if (response.status === 422) {
                // Validation errors - handled by caller
                Logger.info('Validation errors', data.errors);
            } else if (response.status >= 500) {
                Helpers.showToast('Server error. Please try again later.', 'error');
            }
            
            return data;
        }
        
        Logger.info(`API ${method} SUCCESS: ${endpoint}`, data);
        return data;
    },
    
    /**
     * Handle errors
     */
    _handleError(error, method, endpoint) {
        Logger.error(`API ${method} ERROR: ${endpoint}`, {
            message: error.message,
            stack: error.stack
        });
        
        Helpers.showToast('Network error. Please check your connection.', 'error');
        
        return {
            success: false,
            message: error.message || 'Network error occurred',
            data: null
        };
    }
};

// Export to window
window.API = API;