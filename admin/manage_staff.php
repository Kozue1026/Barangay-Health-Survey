<?php
$pageTitle = "Manage Staff";
require_once '../config/session.php';
requireAdmin();

$success_msg = '';
$error_msg = '';

$current_admin_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'admin');
        $status = trim($_POST['status'] ?? 'active');
        $password = trim($_POST['password'] ?? '');
        
        // Strict Validation (all fields except email)
        if (!preg_match('/^[a-zA-Z\s\-\'\.]+$/', $full_name)) {
            $error_msg = "Full Name must contain letters, spaces, hyphens, and apostrophes only.";
        } elseif (!preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
            $error_msg = "Username must contain letters, numbers, hyphens, or underscores only.";
        } elseif (!in_array($role, ['admin', 'super_admin'])) {
            $error_msg = "Invalid role selected.";
        } elseif (!in_array($status, ['active', 'inactive'])) {
            $error_msg = "Invalid status selected.";
        } elseif ($action === 'create' && (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password))) {
            $error_msg = "Password must be at least 8 characters long and contain both letters and numbers.";
        } elseif ($action === 'edit' && !empty($password) && (strlen($password) < 8 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password))) {
            $error_msg = "New password must be at least 8 characters long and contain both letters and numbers.";
        } else {
            try {
            if ($action === 'create') {
                $exists = false;
                foreach (fb_all('admins') as $a) {
                    if (($a['username'] ?? '') === $username || ($email !== '' && ($a['email'] ?? '') === $email)) { $exists = true; break; }
                }
                if ($exists) {
                    $error_msg = "Username or Email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $new_id = fb_next_id('admins');
                    fb_set_rec('admins', $new_id, [
                        'id' => $new_id, 'username' => $username, 'password' => $hashed_password,
                        'full_name' => $full_name, 'email' => $email, 'role' => $role, 'status' => $status,
                        'created_at' => fb_now(), 'updated_at' => fb_now(),
                    ]);

                    fb_log($current_admin_id, 'admin', 'create_staff', "Created staff account: $username ($full_name)");

                    $success_msg = "Staff member created successfully.";
                }
            } elseif ($action === 'edit') {
                $staff_id = (string)($_POST['staff_id'] ?? '');

                $exists = false;
                foreach (fb_all('admins') as $aid => $a) {
                    if ((string)$aid === $staff_id) continue;
                    if (($a['username'] ?? '') === $username || ($email !== '' && ($a['email'] ?? '') === $email)) { $exists = true; break; }
                }
                if ($exists) {
                    $error_msg = "Username or Email already exists.";
                } else {
                    $existing = fb_get_rec('admins', $staff_id);
                    if (!$existing) {
                        $error_msg = "Staff record not found.";
                    } else {
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            fb_update_rec('admins', $staff_id, [
                                'username' => $username, 'password' => $hashed_password, 'full_name' => $full_name,
                                'email' => $email, 'role' => $role, 'status' => $status, 'updated_at' => fb_now(),
                            ]);
                        } else {
                            fb_update_rec('admins', $staff_id, [
                                'username' => $username, 'full_name' => $full_name, 'email' => $email,
                                'role' => $role, 'status' => $status, 'updated_at' => fb_now(),
                            ]);
                        }

                        fb_log($current_admin_id, 'admin', 'update_staff', "Updated staff account: $username");

                        $success_msg = "Staff member updated successfully.";
                    }
                }
            }
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $staff_id = (string)($_POST['staff_id'] ?? '');
        
        if ($staff_id == $current_admin_id) {
            $error_msg = "You cannot delete your own account.";
        } else {
            try {
                $staff = fb_get_rec('admins', $staff_id);

                if ($staff) {
                    fb_delete_rec('admins', $staff_id);

                    fb_log($current_admin_id, 'admin', 'delete_staff', "Deleted staff account: " . ($staff['username'] ?? '') . " (" . ($staff['full_name'] ?? '') . ")");

                    $success_msg = "Staff member deleted successfully.";
                }
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Search and Pagination
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $allStaff = [];
    foreach (fb_all('admins') as $aid => $a) {
        if (!is_array($a)) continue;
        $a['id'] = (string)$aid;
        unset($a['password'], $a['password_hash']);
        if ($search !== '') {
            $hay = strtolower(($a['username'] ?? '') . ' ' . ($a['full_name'] ?? '') . ' ' . ($a['email'] ?? ''));
            if (stripos($hay, $search) === false) continue;
        }
        $allStaff[] = $a;
    }
    usort($allStaff, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
    $total_records = count($allStaff);
} catch (Exception $e) {
    $allStaff = [];
    $total_records = 0;
}
$total_pages = (int)ceil($total_records / $limit);

// Get records
$staffs = array_slice($allStaff, $offset, $limit);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Staff</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openCreateModal()">
                <i class="bi bi-person-plus me-1"></i> Add New Staff
            </button>
        </div>

        <!-- Filter / Search Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" id="search_input" class="form-control" placeholder="Search by username, full name, email... (Live filtering as you type)" value="<?= htmlspecialchars($search) ?>" onkeyup="realtimeSearchStaff(this.value)" oninput="realtimeSearchStaff(this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Staff List Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="staff_table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">#</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-person-x fs-3 d-block mb-2 text-muted"></i>
                                        No staff members found matching criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffs as $i => $staff): ?>
                                    <?php 
                                        $staffName = htmlspecialchars($staff['full_name']);
                                        $staffUser = htmlspecialchars($staff['username']);
                                    ?>
                                    <tr>
                                        <td class="ps-4"><?= $offset + $i + 1 ?></td>
                                        <td><strong><?= $staffUser ?></strong></td>
                                        <td><?= $staffName ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($staff['email']) ?></small></td>
                                        <td>
                                            <?php if ($staff['role'] == 'super_admin'): ?>
                                                <span class="badge bg-danger">Super Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Admin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($staff['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group">
                                                <!-- Edit Staff Button -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Staff" onclick='openEditModal(<?= json_encode($staff) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <?php if ($staff['id'] != $current_admin_id): ?>
                                                    <!-- Delete Staff Form with System Modal -->
                                                    <form method="POST" id="deleteStaffForm_<?= $staff['id'] ?>" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Staff" onclick="confirmDeleteStaff(<?= $staff['id'] ?>, '<?= addslashes($staffName) ?>', '<?= addslashes($staffUser) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Cannot delete your own account">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white pt-3 pb-0">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add / Edit Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="staffModalLabel">Add New Staff</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="staffForm" onsubmit="return validateStaffForm(event)">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="staff_id" id="staff_id" value="">
        
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="full_name" id="full_name" required oninput="sanitizeNameInput(this)">
                <div class="form-text">Letters, spaces, hyphens, and apostrophes only.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="username" id="username" required oninput="sanitizeUsernameInput(this)">
                <div class="form-text">Alphanumeric characters, underscores, and hyphens only.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" id="email" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger" id="pwdAsterisk">*</span> <small id="pwdHelp" class="text-muted"></small></label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="password" required onkeyup="checkStaffPassStrength()">
                    <span class="pw-toggle-btn" data-pw-toggle="password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-slash"></i>
                    </span>
                </div>
                <div class="progress mt-2" style="height: 5px;">
                    <div id="staff_meter" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                </div>
                <small id="staff_pass_text" class="text-muted d-block mt-1"></small>
            </div>
            <div class="mb-3">
                <label class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select" name="role" id="role" required>
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" name="status" id="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveBtn">Save Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Live Client Input Sanitizers
function sanitizeNameInput(input) {
    input.value = input.value.replace(/[^a-zA-Z\s\-\'\.]/g, '');
}

function sanitizeUsernameInput(input) {
    input.value = input.value.replace(/[^a-zA-Z0-9_\-]/g, '');
}

function validateStaffForm(e) {
    const fullName = document.getElementById('full_name').value;
    const username = document.getElementById('username').value;
    const pass = document.getElementById('password').value;
    const isCreate = document.getElementById('formAction').value === 'create';
    
    const nameRegex = /^[a-zA-Z\s\-\'\.]+$/;
    if (!nameRegex.test(fullName)) {
        alert("Full Name must contain letters only (no numbers or special characters).");
        e.preventDefault();
        return false;
    }
    
    const userRegex = /^[a-zA-Z0-9_\-]+$/;
    if (!userRegex.test(username)) {
        alert("Username must contain letters, numbers, underscores, or hyphens only.");
        e.preventDefault();
        return false;
    }
    
    if ((isCreate || pass.length > 0) && (pass.length < 8 || !/[a-zA-Z]/.test(pass) || !/[0-9]/.test(pass))) {
        alert("Password must be at least 8 characters long and contain both letters and numbers.");
        e.preventDefault();
        return false;
    }
    return true;
}

function checkStaffPassStrength() {
    let val = document.getElementById('password').value;
    let meter = document.getElementById('staff_meter');
    let text = document.getElementById('staff_pass_text');
    
    if (val.length === 0) {
        meter.style.width = '0%';
        text.textContent = '';
        return;
    }
    
    let hasLetters = /[a-zA-Z]/.test(val);
    let hasNumbers = /[0-9]/.test(val);
    let hasLength = val.length >= 8;
    
    if (!hasLength) {
        meter.style.width = '25%';
        meter.className = 'progress-bar bg-danger';
        text.textContent = 'Weak (Must be at least 8 characters)';
        text.className = 'text-danger small fw-medium mt-1 d-block';
    } else if (!hasLetters || !hasNumbers) {
        meter.style.width = '40%';
        meter.className = 'progress-bar bg-danger';
        text.textContent = 'Weak (Must contain both letters and numbers)';
        text.className = 'text-danger small fw-medium mt-1 d-block';
    } else {
        meter.style.width = '100%';
        meter.className = 'progress-bar bg-success';
        text.textContent = 'Strong (Meets security requirements)';
        text.className = 'text-success small fw-medium mt-1 d-block';
    }
}

// Real-Time Live Search
function realtimeSearchStaff(query) {
    const filter = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#staff_table tbody tr');
    rows.forEach(row => {
        if (row.cells.length < 2) return;
        const text = row.innerText.toLowerCase();
        row.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
    });
}

// Modal Handlers & Actions
function openCreateModal() {
    document.getElementById('staffModalLabel').innerText = 'Add New Staff';
    document.getElementById('formAction').value = 'create';
    document.getElementById('staffForm').reset();
    document.getElementById('password').required = true;
    document.getElementById('pwdAsterisk').style.display = 'inline';
    document.getElementById('pwdHelp').innerText = '(Required - 8+ chars, letters & numbers)';
    document.getElementById('saveBtn').innerText = 'Save Staff';
    document.getElementById('staff_meter').style.width = '0%';
    document.getElementById('staff_pass_text').textContent = '';
}

function openEditModal(staff) {
    document.getElementById('staffModalLabel').innerText = 'Edit Staff';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('staff_id').value = staff.id;
    
    document.getElementById('full_name').value = staff.full_name;
    document.getElementById('email').value = staff.email;
    document.getElementById('username').value = staff.username;
    document.getElementById('role').value = staff.role;
    document.getElementById('status').value = staff.status;
    
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('pwdAsterisk').style.display = 'none';
    document.getElementById('pwdHelp').innerText = '(Leave blank to keep current password)';
    document.getElementById('saveBtn').innerText = 'Update Staff';
    document.getElementById('staff_meter').style.width = '0%';
    document.getElementById('staff_pass_text').textContent = '';
    
    var myModal = new bootstrap.Modal(document.getElementById('staffModal'));
    myModal.show();
}

function confirmDeleteStaff(id, name, username) {
    App.confirmAction(`Are you sure you want to delete staff account "${name}" (@${username})? This action cannot be undone.`, function() {
        document.getElementById('deleteStaffForm_' + id).submit();
    }, { title: 'Delete Staff Account', heading: 'Confirm Account Deletion', confirmText: 'Yes, Delete Staff', confirmClass: 'btn-danger', iconClass: 'bi bi-trash text-danger fs-1' });
}
</script>

<?php require_once '../includes/footer.php'; ?>
