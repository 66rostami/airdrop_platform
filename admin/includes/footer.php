<?php
// admin/includes/footer.php
if (!defined('ADMIN_ACCESS')) {
    die('Direct access not permitted');
}
?>
            </main>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirmation Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmButton">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 mb-0" id="loadingMessage">Please wait...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="toastTemplate" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2"></i>
                <strong class="me-auto" id="toastTitle">Notification</strong>
                <small id="toastTime">just now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastMessage"></div>
        </div>
    </div>

    <!-- Core JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/web3@1.7.0/dist/web3.min.js"></script>
    
    <!-- Admin Core JS -->
    <script src="assets/js/admin.js"></script>
    
    <!-- Custom JavaScript -->
    <?php if (isset($customJS)): ?>
    <script><?php echo $customJS; ?></script>
    <?php endif; ?>

    <script>
    // Utility Functions
    const AdminUtils = {
        showToast: function(message, title = 'Notification', type = 'info') {
            const toast = document.getElementById('toastTemplate').cloneNode(true);
            toast.id = 'toast-' + Date.now();
            
            toast.querySelector('#toastTitle').textContent = title;
            toast.querySelector('#toastMessage').textContent = message;
            toast.querySelector('#toastTime').textContent = 'just now';
            
            // Set appropriate background color
            toast.classList.add(`bg-${type === 'error' ? 'danger' : type}`);
            
            document.querySelector('.toast-container').appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        },

        showAlert: function(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.getElementById('alertContainer').appendChild(alertDiv);
            
            setTimeout(() => alertDiv.remove(), 5000);
        },

        confirm: function(message, callback) {
            const modal = document.getElementById('confirmModal');
            const confirmBtn = modal.querySelector('#confirmButton');
            
            modal.querySelector('#confirmMessage').textContent = message;
            
            // Remove existing event listener
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            // Add new event listener
            newConfirmBtn.addEventListener('click', () => {
                bootstrap.Modal.getInstance(modal).hide();
                callback();
            });
            
            new bootstrap.Modal(modal).show();
        },

        loading: {
            show: function(message = 'Please wait...') {
                const modal = document.getElementById('loadingModal');
                modal.querySelector('#loadingMessage').textContent = message;
                new bootstrap.Modal(modal).show();
            },
            hide: function() {
                const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
                if (modal) modal.hide();
            }
        },

        formatDate: function(date) {
            return new Intl.DateTimeFormat('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(new Date(date));
        },

        copyToClipboard: async function(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.showToast('Copied to clipboard!', 'Success', 'success');
            } catch (err) {
                this.showToast('Failed to copy text', 'Error', 'error');
            }
        }
    };

    // Session Management
    const SessionManager = {
        checkInterval: 60000, // Check every minute
        warningThreshold: 300000, // Show warning 5 minutes before expiry
        
        init: function() {
            this.startChecking();
            this.initializeSessionTimer();
        },
        
        startChecking: function() {
            setInterval(() => this.checkSession(), this.checkInterval);
        },
        
        async checkSession() {
            try {
                const response = await fetch('ajax/check_session.php');
                const data = await response.json();
                
                if (!data.loggedIn) {
                    window.location.href = 'login.php?session=expired';
                    return;
                }
                
                if (data.timeRemaining < this.warningThreshold) {
                    AdminUtils.showToast(
                        `Your session will expire in ${Math.ceil(data.timeRemaining / 60000)} minutes`,
                        'Session Warning',
                        'warning'
                    );
                }
            } catch (error) {
                console.error('Session check failed:', error);
            }
        },
        
        initializeSessionTimer: function() {
            const timerElement = document.getElementById('sessionTimer');
            if (!timerElement) return;
            
            setInterval(() => {
                const now = new Date();
                timerElement.textContent = now.toLocaleTimeString();
            }, 1000);
        }
    };

    // Global Search
    document.getElementById('globalSearch').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const searchTerm = this.value.trim();
            if (searchTerm) {
                AdminUtils.loading.show('Searching...');
                window.location.href = `search.php?q=${encodeURIComponent(searchTerm)}`;
            }
        }
    });

    // Initialize Session Manager
    SessionManager.init();

    // Logout confirmation
    function confirmLogout() {
        AdminUtils.confirm('Are you sure you want to logout?', () => {
            AdminUtils.loading.show('Logging out...');
            window.location.href = 'logout.php';
        });
    }
    </script>
</body>
</html>