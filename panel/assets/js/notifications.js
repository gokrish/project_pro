/**
 * Notifications System
 * Real-time notifications with polling
 * 
 * @version 5.0
 */

const Notifications = {
    /**
     * Configuration
     */
    config: {
        pollInterval: 60000, // 1 minute
        maxVisible: 5
    },
    
    /**
     * Notification count
     */
    count: 0,
    
    /**
     * Poll timer
     */
    pollTimer: null,
    
    /**
     * Load notifications
     */
    async load() {
        Logger.debug('Loading notifications');
        
        try {
            const response = await API.get('notifications/unread.php');
            
            if (response.success) {
                this.count = response.data.count || 0;
                this.render(response.data.notifications || []);
                this.updateBadge();
                
                Logger.info('Notifications loaded', { count: this.count });
            }
            
        } catch (error) {
            Logger.error('Failed to load notifications', error);
        }
    },
    
    /**
     * Render notifications
     */
    render(notifications) {
        const $list = $('#notificationList ul');
        $list.empty();
        
        if (notifications.length === 0) {
            $list.html(`
                <li class="list-group-item list-group-item-action text-center">
                    <small class="text-muted">No new notifications</small>
                </li>
            `);
            return;
        }
        
        notifications.slice(0, this.config.maxVisible).forEach(notification => {
            const html = `
                <li class="list-group-item list-group-item-action dropdown-notifications-item" 
                    data-id="${notification.id}">
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar">
                                <span class="avatar-initial rounded-circle bg-label-${notification.type}">
                                    <i class="bx ${this._getIcon(notification.type)}"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${Helpers.escapeHtml(notification.title)}</h6>
                            <p class="mb-0">${Helpers.escapeHtml(notification.message)}</p>
                            <small class="text-muted">${Helpers.formatDate(notification.created_at, 'Y-m-d H:i')}</small>
                        </div>
                    </div>
                </li>
            `;
            
            $list.append(html);
        });
        
        // Bind click events
        $list.find('.dropdown-notifications-item').on('click', function() {
            const id = $(this).data('id');
            Notifications.markAsRead(id);
        });
    },
    
    /**
     * Update badge count
     */
    updateBadge() {
        const $badge = $('#notificationBadge');
        
        if (this.count > 0) {
            $badge.text(this.count > 99 ? '99+' : this.count).show();
        } else {
            $badge.hide();
        }
        
        Logger.debug('Notification badge updated', { count: this.count });
    },
    
    /**
     * Mark notification as read
     */
    async markAsRead(id) {
        Logger.debug('Marking notification as read', { id });
        
        try {
            const response = await API.post('notifications/mark-read.php', { id });
            
            if (response.success) {
                this.count = Math.max(0, this.count - 1);
                this.updateBadge();
                this.load();
                
                Logger.info('Notification marked as read', { id });
            }
            
        } catch (error) {
            Logger.error('Failed to mark notification as read', error);
        }
    },
    
    /**
     * Mark all as read
     */
    async markAllAsRead() {
        Logger.info('Marking all notifications as read');
        
        try {
            const response = await API.post('notifications/mark-all-read.php');
            
            if (response.success) {
                this.count = 0;
                this.updateBadge();
                this.load();
                
                Helpers.showToast('All notifications marked as read', 'success');
            }
            
        } catch (error) {
            Logger.error('Failed to mark all as read', error);
        }
    },
    
    /**
     * Start polling
     */
    startPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
        }
        
        this.pollTimer = setInterval(() => {
            this.load();
        }, this.config.pollInterval);
        
        Logger.info('Notification polling started', { interval: this.config.pollInterval });
    },
    
    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
            Logger.info('Notification polling stopped');
        }
    },
    
    /**
     * Get icon for notification type
     */
    _getIcon(type) {
        const icons = {
            success: 'bx-check-circle',
            info: 'bx-info-circle',
            warning: 'bx-error',
            error: 'bx-error-circle'
        };
        
        return icons[type] || icons.info;
    },
    
    /**
     * Initialize
     */
    init() {
        Logger.info('Initializing notifications');
        
        // Load initial notifications
        this.load();
        
        // Start polling
        this.startPolling();
        
        // Mark all as read button
        $('#markAllRead').on('click', () => {
            this.markAllAsRead();
        });
    }
};

// Export to window
window.Notifications = Notifications;