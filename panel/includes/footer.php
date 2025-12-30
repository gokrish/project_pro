<?php
/**
 * Footer Component
 * Closes content wrapper and loads JavaScript
 * 
 * @version 5.0
 */
?>
                    </div>
                    <!-- / Content -->
                    
                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme mt-auto">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                &copy; <?= date('Y') ?> <a href="/" target="_blank" class="footer-link fw-semibold">ProConsultancy</a>. 
                                All rights reserved.
                            </div>
                            <div>
                                <span class="footer-text">Version 5.0</span>
                                <span class="mx-2">|</span>
                                <a href="/docs" class="footer-link me-3">Documentation</a>
                                <a href="/support" class="footer-link">Support</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
                    
                    <div class="content-backdrop fade"></div>
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>
        
        <!-- Overlay for mobile -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->
    
    <!-- Core JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" 
            integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" 
            crossorigin="anonymous"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
            crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
    <script src="/panel/assets/js/helpers.js"></script>
    <script src="/panel/assets/js/logger.js"></script>
    <script src="/panel/assets/js/api.js"></script>
    <script src="/panel/assets/js/utils.js"></script>
    <script src="/panel/assets/js/validation.js"></script>
    <script src="/panel/assets/js/upload.js"></script>
    <script src="/panel/assets/js/notifications.js"></script>
    <script src="/panel/assets/js/app.js"></script>
    
    <!-- Module-specific JavaScript -->
    <?php if (!empty($customJS)): ?>
        <?php foreach ($customJS as $js): ?>
            <script src="<?= htmlspecialchars($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // Initialize application
        $(document).ready(function() {
            if (window.APP_CONFIG.debug) {
                console.log('%c✅ Application Initialized', 'color: #48bb78; font-weight: bold;');
            }
            
            // Initialize layout
            App.init();
            
            // Load notifications
            Notifications.load();
        });
    </script>
</body>
</html>