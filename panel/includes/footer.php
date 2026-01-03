<?php
/**
 * Footer Component - Closes all tags properly
 * @version 5.1 FINAL
 */
?>
                    </div>
                    <!-- / Content Container -->
                    
                    <!-- Footer -->
                    <footer class="content-footer">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="mb-2 mb-md-0">
                                &copy; <?= date('Y') ?> <a href="/" class="footer-link fw-semibold">ProConsultancy</a>. 
                                All rights reserved.
                            </div>
                            <div>
                                <span style="color: #a0aec0;">Version 2.0</span>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>
    </div>
    <!-- / Layout wrapper -->
    
    <!-- Core JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" 
            crossorigin="anonymous"></script>
    
    <!-- Custom JavaScript -->
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
        console.log('ProConsultancy ATS v5.0 - Loaded');
        
        // Global search functionality
        $('#globalSearch').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                var query = $(this).val();
                if (query.length > 0) {
                    window.location.href = '/panel/search.php?q=' + encodeURIComponent(query);
                }
            }
        });
    });
    </script>
</body>
</html>