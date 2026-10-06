    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content border-0 shadow-lg logout-modal-content">
                <div class="modal-header border-0 pb-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold" id="logoutModalLabel">Logout</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center px-4 pt-1 pb-4">
                    <div class="logout-icon-box my-3 d-flex justify-content-center align-items-center">
                        <svg class="logout-icon-svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#e03131" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </div>
                    <h5 class="fw-bold mb-2 logout-prompt-title">Are you sure you want to logout?</h5>
                    <p class="text-muted mb-4 logout-prompt-desc">You will be signed out of your account and returned to the login page.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-logout-cancel px-4 py-2" data-bs-dismiss="modal">Cancel</button>
                        <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-logout-confirm px-4 py-2">Yes, Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>

</body>
</html>
