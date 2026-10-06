<?php
$pageTitle = "Manage Residents";
require_once '../config/session.php';
requireAdmin();

$success_msg = '';
$error_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $resident_number = trim($_POST['resident_number'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $extension_name = trim($_POST['extension_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $civil_status = trim($_POST['civil_status'] ?? 'Single');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        
        $occupation = trim($_POST['occupation'] ?? '');
        $employer = trim($_POST['employer'] ?? '');
        $employer_address = trim($_POST['employer_address'] ?? '');
        
        $spouse_name = trim($_POST['spouse_name'] ?? '');
        $spouse_occupation = trim($_POST['spouse_occupation'] ?? '');
        $spouse_employer = trim($_POST['spouse_employer'] ?? '');
        
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        
        $reference1_name = trim($_POST['reference1_name'] ?? '');
        $reference1_contact = trim($_POST['reference1_contact'] ?? '');
        $reference2_name = trim($_POST['reference2_name'] ?? '');
        $reference2_contact = trim($_POST['reference2_contact'] ?? '');
        $signature = trim($_POST['signature'] ?? '');
        
        // Strict Input Validation
        $nameRegex = '/^[\p{L}\s\-\'\.]+$/u';
        if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
            $error_msg = "First Name is required and must contain letters only (no numbers or symbols).";
        } elseif (empty($last_name) || !preg_match($nameRegex, $last_name)) {
            $error_msg = "Last Name is required and must contain letters only (no numbers or symbols).";
        } elseif (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
            $error_msg = "Middle Name must contain letters only (no numbers or symbols).";
        } elseif (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
            $error_msg = "Phone number is required and must be exactly 11 digits (e.g. 09171234567).";
        } elseif (!empty($birthdate) && strtotime($birthdate) > time()) {
            $error_msg = "Birthdate cannot be in the future.";
        } elseif ($action === 'create') {
            $exists = false;
            try {
                foreach (fb_all('residents') as $r) {
                    if (($r['resident_number'] ?? '') === $resident_number) { $exists = true; break; }
                }
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
                $exists = true;
            }
            if ($exists) {
                if ($error_msg === '') $error_msg = "Resident number already exists.";
            } else {
                // Handle Profile Photo
                $profile_picture = null;
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                    $fileName = $_FILES['profile_pic']['name'];
                    $fileSize = $_FILES['profile_pic']['size'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $imageInfo = @getimagesize($fileTmpPath);
                    if (in_array($fileExtension, $allowedExtensions) && $imageInfo && $fileSize < 2 * 1024 * 1024) {
                        $uploadFileDir = '../uploads/profile_pics/';
                        if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
                        $profile_picture = 'res_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                        move_uploaded_file($fileTmpPath, $uploadFileDir . $profile_picture);
                    }
                }

                $password = password_hash($resident_number, PASSWORD_DEFAULT);

                try {
                    $new_resident_id = fb_next_id('residents');

                    fb_set_rec('residents', $new_resident_id, [
                        'id' => $new_resident_id,
                        'resident_number' => $resident_number, 'password' => $password,
                        'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name, 'extension_name' => $extension_name,
                        'email' => $email, 'phone' => $phone, 'address' => $address, 'gender' => $gender, 'civil_status' => $civil_status,
                        'birthdate' => ($birthdate ?: null), 'occupation' => $occupation, 'employer' => $employer, 'employer_address' => $employer_address,
                        'spouse_name' => $spouse_name, 'spouse_occupation' => $spouse_occupation, 'spouse_employer' => $spouse_employer,
                        'father_name' => $father_name, 'mother_name' => $mother_name,
                        'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                        'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact, 'signature' => $signature,
                        'profile_picture' => $profile_picture, 'status' => $status, 'first_login' => 1,
                        'created_at' => fb_now(), 'updated_at' => fb_now(),
                    ]);

                    // Save Children
                    if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                        foreach ($_POST['child_name'] as $idx => $cName) {
                            $cName = trim($cName);
                            if ($cName !== '') {
                                $cAge = isset($_POST['child_age'][$idx]) && $_POST['child_age'][$idx] !== '' ? (int)$_POST['child_age'][$idx] : null;
                                $child_id = fb_next_id('resident_children');
                                fb_set_rec('resident_children', $child_id, [
                                    'id' => $child_id, 'resident_id' => $new_resident_id,
                                    'child_name' => $cName, 'age' => $cAge, 'created_at' => fb_now(),
                                ]);
                            }
                        }
                    }

                    // Initialize notifications for the new resident (Welcome + active surveys)
                    initializeResidentNotifications(null, $new_resident_id, $first_name);

                    fb_log($_SESSION['user_id'], 'admin', 'create_resident', "Created resident: $resident_number ($first_name $last_name)");

                    $success_msg = "Resident created successfully.";
                } catch (Exception $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            }
        } elseif ($action === 'edit') {
            $resident_id = (string)($_POST['resident_id'] ?? '');

            try {
                $oldResident = fb_get_rec('residents', $resident_id);
            } catch (Exception $e) {
                $oldResident = null;
                $error_msg = "Database error: " . $e->getMessage();
            }

            if (!empty($oldResident)) {
                // Handle Profile Photo replacement
                $profile_picture = $oldResident['profile_picture'];
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                    $fileName = $_FILES['profile_pic']['name'];
                    $fileSize = $_FILES['profile_pic']['size'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $imageInfo = @getimagesize($fileTmpPath);
                    if (in_array($fileExtension, $allowedExtensions) && $imageInfo && $fileSize < 2 * 1024 * 1024) {
                        $uploadFileDir = '../uploads/profile_pics/';
                        if (!is_dir($uploadFileDir)) mkdir($uploadFileDir, 0755, true);
                        $newPicName = 'res_' . time() . '_' . rand(100, 999) . '.' . $fileExtension;
                        if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newPicName)) {
                            if (!empty($profile_picture) && file_exists($uploadFileDir . $profile_picture)) {
                                @unlink($uploadFileDir . $profile_picture);
                            }
                            $profile_picture = $newPicName;
                        }
                    }
                }

                $newData = [
                    'first_name'         => $first_name,
                    'last_name'          => $last_name,
                    'middle_name'        => $middle_name,
                    'extension_name'     => $extension_name,
                    'email'              => $email,
                    'phone'              => $phone,
                    'address'            => $address,
                    'gender'             => $gender,
                    'civil_status'       => $civil_status,
                    'birthdate'          => $birthdate,
                    'status'             => $status,
                    'occupation'         => $occupation,
                    'employer'           => $employer,
                    'employer_address'   => $employer_address,
                    'spouse_name'        => $spouse_name,
                    'spouse_occupation'  => $spouse_occupation,
                    'spouse_employer'    => $spouse_employer,
                    'father_name'        => $father_name,
                    'mother_name'        => $mother_name,
                    'reference1_name'    => $reference1_name,
                    'reference1_contact' => $reference1_contact,
                    'reference2_name'    => $reference2_name,
                    'reference2_contact' => $reference2_contact,
                    'signature'          => $signature
                ];
                $changes = getResidentChangesDescription($oldResident, $newData);

                try {
                    fb_update_rec('residents', $resident_id, [
                        'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name, 'extension_name' => $extension_name,
                        'email' => $email, 'phone' => $phone, 'address' => $address, 'gender' => $gender, 'civil_status' => $civil_status,
                        'birthdate' => ($birthdate ?: null), 'status' => $status, 'occupation' => $occupation, 'employer' => $employer,
                        'employer_address' => $employer_address, 'spouse_name' => $spouse_name, 'spouse_occupation' => $spouse_occupation,
                        'spouse_employer' => $spouse_employer, 'father_name' => $father_name, 'mother_name' => $mother_name,
                        'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                        'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact, 'signature' => $signature,
                        'profile_picture' => $profile_picture, 'updated_at' => fb_now(),
                    ]);

                    // Update children
                    foreach (fb_all('resident_children') as $cid => $c) {
                        if (is_array($c) && (string)($c['resident_id'] ?? '') === (string)$resident_id) {
                            fb_delete_rec('resident_children', (string)$cid);
                        }
                    }
                    if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                        foreach ($_POST['child_name'] as $idx => $cName) {
                            $cName = trim($cName);
                            if ($cName !== '') {
                                $cAge = isset($_POST['child_age'][$idx]) && $_POST['child_age'][$idx] !== '' ? (int)$_POST['child_age'][$idx] : null;
                                $child_id = fb_next_id('resident_children');
                                fb_set_rec('resident_children', $child_id, [
                                    'id' => $child_id, 'resident_id' => (string)$resident_id,
                                    'child_name' => $cName, 'age' => $cAge, 'created_at' => fb_now(),
                                ]);
                            }
                        }
                    }

                    fb_log($_SESSION['user_id'], 'admin', 'update_resident', "Updated resident details for: " . ($oldResident['resident_number'] ?? $resident_id));

                    if (!empty($changes)) {
                        $changeDetails = implode('; ', $changes);
                    }

                    $success_msg = "Resident updated successfully.";
                } catch (Exception $e) {
                    $error_msg = "Database error: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'archive' || $action === 'delete') { // Archive Resident (Soft Delete)
        $resident_id = (string)($_POST['resident_id'] ?? '');
        try {
            $resident = fb_get_rec('residents', $resident_id);
        } catch (Exception $e) {
            $resident = null;
            $error_msg = "Database error: " . $e->getMessage();
        }

        if (!empty($resident)) {
            try {
                fb_update_rec('residents', $resident_id, ['status' => 'archived', 'updated_at' => fb_now()]);

                fb_log($_SESSION['user_id'], 'admin', 'archive_resident', "Archived resident: " . ($resident['resident_number'] ?? $resident_id) . " (" . ($resident['first_name'] ?? '') . " " . ($resident['last_name'] ?? '') . ")");

                $success_msg = "Resident \"" . htmlspecialchars($resident['resident_number'] ?? $resident_id) . "\" has been archived successfully. All records are safely preserved.";
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'restore') { // Restore Resident from Archive
        $resident_id = (string)($_POST['resident_id'] ?? '');
        try {
            $resident = fb_get_rec('residents', $resident_id);
        } catch (Exception $e) {
            $resident = null;
            $error_msg = "Database error: " . $e->getMessage();
        }

        if (!empty($resident)) {
            try {
                fb_update_rec('residents', $resident_id, ['status' => 'active', 'updated_at' => fb_now()]);

                fb_log($_SESSION['user_id'], 'admin', 'restore_resident', "Restored resident: " . ($resident['resident_number'] ?? $resident_id) . " (" . ($resident['first_name'] ?? '') . " " . ($resident['last_name'] ?? '') . ") to active status");

                $success_msg = "Resident \"" . htmlspecialchars($resident['resident_number'] ?? $resident_id) . "\" has been restored to active status successfully.";
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'permanent_delete') { // Permanent Delete
        $resident_id = (string)($_POST['resident_id'] ?? '');
        try {
            $resident = fb_get_rec('residents', $resident_id);
        } catch (Exception $e) {
            $resident = null;
            $error_msg = "Database error: " . $e->getMessage();
        }

        if (!empty($resident)) {
            // Delete profile picture file if exists
            if (!empty($resident['profile_picture'])) {
                $picPath = '../uploads/profile_pics/' . $resident['profile_picture'];
                if (file_exists($picPath)) {
                    @unlink($picPath);
                }
            }

            try {
                // Clean up children records
                foreach (fb_all('resident_children') as $cid => $c) {
                    if (is_array($c) && (string)($c['resident_id'] ?? '') === $resident_id) {
                        fb_delete_rec('resident_children', (string)$cid);
                    }
                }
                // Clean up responses + their response_answers
                $deletedResponseIds = [];
                foreach (fb_all('responses') as $rid => $r) {
                    if (is_array($r) && (string)($r['resident_id'] ?? '') === $resident_id) {
                        $deletedResponseIds[] = (string)$rid;
                        fb_delete_rec('responses', (string)$rid);
                    }
                }
                if (!empty($deletedResponseIds)) {
                    foreach (fb_all('response_answers') as $aid => $a) {
                        if (is_array($a) && in_array((string)($a['response_id'] ?? ''), $deletedResponseIds, true)) {
                            fb_delete_rec('response_answers', (string)$aid);
                        }
                    }
                }
                // Clean up notifications, activity logs, login history for that resident
                foreach (fb_all('notifications') as $nid => $n) {
                    if (is_array($n) && (string)($n['user_id'] ?? '') === $resident_id && ($n['user_type'] ?? '') === 'resident') {
                        fb_delete_rec('notifications', (string)$nid);
                    }
                }
                foreach (fb_all('activity_logs') as $lid => $l) {
                    if (is_array($l) && (string)($l['user_id'] ?? '') === $resident_id && ($l['user_type'] ?? '') === 'resident') {
                        fb_delete_rec('activity_logs', (string)$lid);
                    }
                }
                try {
                    foreach (fb_all('login_history') as $hid => $h) {
                        if (is_array($h) && (string)($h['user_id'] ?? '') === $resident_id && ($h['user_type'] ?? '') === 'resident') {
                            fb_delete_rec('login_history', (string)$hid);
                        }
                    }
                } catch (Exception $e) { /* login_history node may not exist */ }
                fb_delete_rec('residents', $resident_id);

                fb_log($_SESSION['user_id'], 'admin', 'permanent_delete_resident', "Permanently deleted resident: " . ($resident['resident_number'] ?? $resident_id) . " (" . ($resident['first_name'] ?? '') . " " . ($resident['last_name'] ?? '') . ")");

                $success_msg = "Resident \"" . htmlspecialchars($resident['resident_number'] ?? $resident_id) . "\" has been permanently deleted.";
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $resident_id = (string)($_POST['resident_id'] ?? '');
        try {
            $resident = fb_get_rec('residents', $resident_id);
        } catch (Exception $e) {
            $resident = null;
            $error_msg = "Database error: " . $e->getMessage();
        }

        if (!empty($resident)) {
            $new_status = (($resident['status'] ?? '') === 'active') ? 'inactive' : 'active';
            try {
                fb_update_rec('residents', $resident_id, ['status' => $new_status, 'updated_at' => fb_now()]);

                fb_log($_SESSION['user_id'], 'admin', 'toggle_resident_status', "Changed status of " . ($resident['resident_number'] ?? $resident_id) . " to $new_status");

                $success_msg = "Resident status updated to " . ucfirst($new_status) . ".";
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reset_password') {
        $resident_id = (string)($_POST['resident_id'] ?? '');
        try {
            $resident = fb_get_rec('residents', $resident_id);
        } catch (Exception $e) {
            $resident = null;
            $error_msg = "Database error: " . $e->getMessage();
        }

        if (!empty($resident)) {
            $hashed = password_hash($resident['resident_number'] ?? $resident_id, PASSWORD_DEFAULT);
            try {
                fb_update_rec('residents', $resident_id, ['password' => $hashed, 'first_login' => 1, 'updated_at' => fb_now()]);

                fb_log($_SESSION['user_id'], 'admin', 'reset_resident_password', "Reset password for resident " . ($resident['resident_number'] ?? $resident_id) . " to default");

                $success_msg = "Resident password has been reset successfully.";
            } catch (Exception $e) {
                $error_msg = "Database error: " . $e->getMessage();
            }
        } else {
            $error_msg = "Resident record not found.";
        }
    }
}

// Fetch Search and Filter query (applied in PHP over Firebase rows)
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$residents = [];
$counts = ['total' => 0, 'active' => 0, 'inactive' => 0, 'archived' => 0, 'total_active_inactive' => 0];
try {
    $allResidents = fb_all('residents');
    $childCounts = [];
    foreach (fb_all('resident_children') as $c) {
        if (!is_array($c)) continue;
        $rid = (string)($c['resident_id'] ?? '');
        if ($rid === '') continue;
        $childCounts[$rid] = ($childCounts[$rid] ?? 0) + 1;
    }
    foreach ($allResidents as $rid => $r) {
        if (!is_array($r)) continue;
        $r['id'] = (string)$rid;
        $st = $r['status'] ?? '';
        if (isset($counts[$st])) $counts[$st]++;
        $counts['total']++;

        if ($status_filter === 'archived') {
            if ($st !== 'archived') continue;
        } elseif (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
            if ($st !== $status_filter) continue;
        } else {
            if ($st === 'archived') continue;
        }

        if ($search !== '') {
            $hay = strtolower(
                ($r['resident_number'] ?? '') . ' ' . ($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '') . ' ' .
                ($r['middle_name'] ?? '') . ' ' . ($r['extension_name'] ?? '') . ' ' . ($r['email'] ?? '') . ' ' .
                ($r['phone'] ?? '') . ' ' . ($r['address'] ?? '')
            );
            if (stripos($hay, strtolower($search)) === false) continue;
        }

        $r['children_count'] = $childCounts[(string)$rid] ?? 0;
        unset($r['password'], $r['password_hash']);
        $residents[] = $r;
    }
    usort($residents, function ($a, $b) { return (int)($b['id'] ?? 0) - (int)($a['id'] ?? 0); });
} catch (Exception $e) {
    $residents = [];
    $error_msg = "Database error: " . $e->getMessage();
}

// Calculate Next Sequential Resident Number (RES-XXXX) based on highest existing resident number
try {
    $next_res_num = fb_next_resident_number();
} catch (Exception $e) {
    $next_res_num = 'RES-0001';
}

// Count for filter badges
$counts['total_active_inactive'] = ($counts['active'] ?? 0) + ($counts['inactive'] ?? 0);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0 fw-bold"><i class="bi bi-people text-primary me-2"></i>Manage Residents</h2>
                <small class="text-muted">Register, search, view, edit, and manage barangay resident records</small>
            </div>
            <button class="btn btn-primary px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#residentModal" onclick="openCreateModal()">
                <i class="bi bi-person-plus me-1"></i> Add New Resident
            </button>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-center" id="searchFilterForm">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" id="search_input" class="form-control" placeholder="Search by Resident No., Name, Contact, Address... (Live filtering)" value="<?= htmlspecialchars($search) ?>" onkeyup="realtimeSearchResidents(this.value)" oninput="realtimeSearchResidents(this.value)">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="document.getElementById('searchFilterForm').submit()">
                            <option value="all" <?= empty($status_filter) || $status_filter === 'all' ? 'selected' : '' ?>>All Statuses (Active &amp; Inactive) (<?= $counts['total_active_inactive'] ?? 0 ?>)</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active (<?= $counts['active'] ?? 0 ?>)</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive (<?= $counts['inactive'] ?? 0 ?>)</option>
                            <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archived Residents (<?= $counts['archived'] ?? 0 ?>)</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-filter me-1"></i>Filter</button>
                        <?php if (!empty($search) || !empty($status_filter)): ?>
                            <a href="manage_residents.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Residents Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="residents_table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 140px;">Resident No.</th>
                                <th>Full Name</th>
                                <th>Civil Status</th>
                                <th>Contact Number</th>
                                <th>Address</th>
                                <th>Children</th>
                                <th>Status</th>
                                <th class="text-end" style="min-width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($residents)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                        No resident records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($residents as $i => $res): ?>
                                    <?php 
                                        $fullName = trim(($res['first_name'] ?? '') . ' ' . ($res['middle_name'] ?? '') . ' ' . ($res['last_name'] ?? '') . ' ' . ($res['extension_name'] ?? ''));
                                        $initials = strtoupper(substr($res['first_name'] ?? 'R', 0, 1) . substr($res['last_name'] ?? 'S', 0, 1));
                                    ?>
                                    <tr>
                                        <td class="text-muted small"><?= $i + 1 ?></td>
                                        <td>
                                            <span class="font-monospace fw-bold text-primary"><?= htmlspecialchars($res['resident_number']) ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($res['profile_picture']) && file_exists('../uploads/profile_pics/' . $res['profile_picture'])): ?>
                                                    <img src="../uploads/profile_pics/<?= htmlspecialchars($res['profile_picture']) ?>" class="rounded-circle me-2" style="width: 34px; height: 34px; object-fit: cover;" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2 small fw-bold" style="width: 34px; height: 34px; font-size: 0.75rem;">
                                                        <?= $initials ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($fullName) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars(($res['email'] ?? '') ?: 'No email') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars(($res['civil_status'] ?? '') ?: 'Single') ?></span>
                                        </td>
                                        <td>
                                            <i class="bi bi-telephone text-muted me-1 small"></i><?= htmlspecialchars($res['phone'] ?? '') ?>
                                        </td>
                                        <td>
                                            <small class="text-truncate d-inline-block" style="max-width: 180px;" title="<?= htmlspecialchars($res['address'] ?? '') ?>">
                                                <?= htmlspecialchars($res['address'] ?? '') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill"><?= (int)$res['children_count'] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($res['status'] === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>Active</span>
                                            <?php elseif ($res['status'] === 'archived'): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><i class="bi bi-archive me-1"></i>Archived</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <!-- View Resident Details Button -->
                                            <button type="button" class="btn btn-sm btn-outline-info" title="View Resident Details" onclick="fetchAndOpenViewModal(<?= $res['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <?php if ($res['status'] === 'archived'): ?>
                                                <!-- Restore Resident Form -->
                                                <form method="POST" id="restoreForm_<?= $res['id'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-success" title="Restore Resident" onclick="confirmRestoreResident(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </form>

                                                <!-- Permanently Delete Resident Form -->
                                                <form method="POST" id="permDeleteForm_<?= $res['id'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Permanently Delete Resident" onclick="confirmPermanentDeleteResident(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Edit Resident Button -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Resident" onclick="fetchAndOpenEditModal(<?= $res['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <!-- Toggle Status Form -->
                                                <form method="POST" id="toggleForm_<?= $res['id'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                    <button type="button" class="btn btn-sm <?= $res['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= $res['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" onclick="confirmToggleStatus(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= $res['status'] ?>')">
                                                        <i class="bi <?= $res['status'] === 'active' ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Reset Password Form -->
                                                <form method="POST" id="resetPassForm_<?= $res['id'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-warning" title="Reset Resident Password" onclick="confirmResetResidentPassword(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                        <i class="bi bi-key"></i>
                                                    </button>
                                                </form>

                                                <!-- Print Resident Information Button -->
                                                <a href="print_resident.php?id=<?= $res['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Resident Information">
                                                    <i class="bi bi-printer"></i>
                                                </a>

                                                <!-- Archive Resident Form -->
                                                <form method="POST" id="archiveForm_<?= $res['id'] ?>" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="archive">
                                                    <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Archive Resident" onclick="confirmArchiveResident(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Resident Details Modal -->
<div class="modal fade" id="viewResidentModal" tabindex="-1" aria-labelledby="viewResidentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title fw-bold" id="viewResidentModalLabel">
            <i class="bi bi-person-badge text-primary me-2"></i>Resident Details
          </h5>
          <div class="small text-muted" id="view_subtitle">Current database record</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <!-- Top Profile Banner -->
        <div class="d-flex align-items-center mb-4 p-3 bg-light rounded border">
            <div id="view_avatar_container" class="me-3"></div>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h5 class="fw-bold mb-0 text-primary" id="view_full_name"></h5>
                    <span id="view_status_badge"></span>
                </div>
                <div class="text-muted small font-monospace" id="view_res_number"></div>
                <div class="text-muted small mt-1" id="view_last_updated"></div>
            </div>
        </div>

        <!-- 1. Personal Information -->
        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>1. Personal Information</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Civil Status</small>
                <div class="fw-medium" id="view_civil_status">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Gender</small>
                <div class="fw-medium" id="view_gender">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Birthdate / Age</small>
                <div class="fw-medium" id="view_birthdate_age">-</div>
            </div>
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Contact Number</small>
                <div class="fw-medium" id="view_phone">-</div>
            </div>
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Email Address</small>
                <div class="fw-medium" id="view_email">-</div>
            </div>
            <div class="col-12">
                <small class="text-muted text-uppercase fw-semibold d-block">Residential Address</small>
                <div class="fw-medium" id="view_address">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Occupation</small>
                <div class="fw-medium" id="view_occupation">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Employer</small>
                <div class="fw-medium" id="view_employer">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Employer Address</small>
                <div class="fw-medium" id="view_employer_address">-</div>
            </div>
        </div>

        <!-- 2. Spouse Information -->
        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-heart me-2"></i>2. Spouse Information</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Spouse Name</small>
                <div class="fw-medium" id="view_spouse_name">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Spouse Occupation</small>
                <div class="fw-medium" id="view_spouse_occupation">-</div>
            </div>
            <div class="col-md-4">
                <small class="text-muted text-uppercase fw-semibold d-block">Spouse Employer</small>
                <div class="fw-medium" id="view_spouse_employer">-</div>
            </div>
        </div>

        <!-- 3. Children -->
        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-people me-2"></i>3. Children Information</h6>
        <div id="view_children_list" class="mb-4"></div>

        <!-- 4. Parents -->
        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-person-heart me-2"></i>4. Parents Information</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Father's Name</small>
                <div class="fw-medium" id="view_father_name">-</div>
            </div>
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Mother's Maiden Name</small>
                <div class="fw-medium" id="view_mother_name">-</div>
            </div>
        </div>

        <!-- 5. References & Signature -->
        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-card-checklist me-2"></i>5. Character References & Signature</h6>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Reference 1</small>
                <div class="fw-medium" id="view_ref1">-</div>
            </div>
            <div class="col-md-6">
                <small class="text-muted text-uppercase fw-semibold d-block">Reference 2</small>
                <div class="fw-medium" id="view_ref2">-</div>
            </div>
            <div class="col-12">
                <small class="text-muted text-uppercase fw-semibold d-block">Signature / Acknowledgement</small>
                <div class="fw-medium" id="view_signature">-</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <a id="viewPrintLink" href="#" target="_blank" class="btn btn-outline-info">
            <i class="bi bi-printer me-1"></i> Print Record
        </a>
        <button type="button" class="btn btn-primary" id="viewEditBtn">
            <i class="bi bi-pencil me-1"></i> Edit Resident
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add / Edit Resident Modal -->
<div class="modal fade" id="residentModal" tabindex="-1" aria-labelledby="residentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="residentModalLabel"><i class="bi bi-person-plus text-primary me-2"></i>Add New Resident</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="residentForm" enctype="multipart/form-data" onsubmit="return validateResidentForm(event)" class="d-flex flex-column overflow-hidden flex-grow-1" style="min-height: 0;">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="resident_id" id="resident_id" value="">
        
        <div class="modal-body p-4 overflow-y-auto">
            <!-- 1. PERSONAL INFORMATION -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-person me-2"></i>1. Personal Information</h6>
            
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Resident Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control font-monospace bg-light" name="resident_number" id="resident_number" value="<?= htmlspecialchars($next_res_num) ?>" required readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" id="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Profile Photo</label>
                    <input type="file" class="form-control" name="profile_pic" id="profile_pic" accept="image/*">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="first_name" id="first_name" required oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Middle Name</label>
                    <input type="text" class="form-control" name="middle_name" id="middle_name" oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="last_name" id="last_name" required oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Extension Name</label>
                    <input type="text" class="form-control" name="extension_name" id="extension_name" placeholder="Jr., Sr., III">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-medium">Civil Status</label>
                    <select class="form-select" name="civil_status" id="civil_status">
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Separated">Separated</option>
                        <option value="Divorced">Divorced</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender" id="gender" required>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Birthdate <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="birthdate" id="birthdate" required onchange="updateAgeDisplay()">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-medium">Age</label>
                    <input type="text" class="form-control bg-light" id="age" readonly placeholder="Auto-calculated">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Contact Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="phone" id="phone" pattern="[0-9]{11}" inputmode="numeric" placeholder="e.g. 09171234567" required maxlength="11" minlength="11" oninput="sanitizePhoneInput(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Email Address</label>
                    <input type="email" class="form-control" name="email" id="email" placeholder="name@example.com">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-medium">Residential Address <span class="text-danger">*</span></label>
                <textarea class="form-control" name="address" id="address" rows="2" placeholder="House No., Street, Purok, Barangay" required></textarea>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Occupation</label>
                    <input type="text" class="form-control" name="occupation" id="occupation" placeholder="e.g. Teacher, Engineer">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Employer / Business</label>
                    <input type="text" class="form-control" name="employer" id="employer" placeholder="Company Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Employer Address</label>
                    <input type="text" class="form-control" name="employer_address" id="employer_address" placeholder="Workplace Address">
                </div>
            </div>

            <!-- 2. SPOUSE INFORMATION -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-heart me-2"></i>2. Spouse Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-medium">Spouse Full Name</label>
                    <input type="text" class="form-control" name="spouse_name" id="spouse_name" placeholder="Spouse Full Name" oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Spouse Occupation</label>
                    <input type="text" class="form-control" name="spouse_occupation" id="spouse_occupation" placeholder="Spouse Occupation">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">Spouse Employer</label>
                    <input type="text" class="form-control" name="spouse_employer" id="spouse_employer" placeholder="Spouse Employer">
                </div>
            </div>

            <!-- 3. CHILDREN -->
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-people me-2"></i>3. Children Information</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addChildModalRow()">
                    <i class="bi bi-plus-circle me-1"></i> Add Child
                </button>
            </div>
            <div id="modalChildrenContainer" class="mb-4">
                <div id="noChildrenModalNotice" class="text-muted small py-1">No children recorded. Click "Add Child" to record children.</div>
            </div>

            <!-- 4. PARENTS -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-person-heart me-2"></i>4. Parents Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Father's Full Name</label>
                    <input type="text" class="form-control" name="father_name" id="father_name" placeholder="Father's Full Name" oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Mother's Maiden Name</label>
                    <input type="text" class="form-control" name="mother_name" id="mother_name" placeholder="Mother's Full Name" oninput="sanitizeNameInput(this)">
                </div>
            </div>

            <!-- 5. REFERENCES & SIGNATURE -->
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-card-checklist me-2"></i>5. Character References & Signature</h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Reference 1: Full Name</label>
                    <input type="text" class="form-control" name="reference1_name" id="reference1_name" placeholder="Reference 1 Name" oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Reference 1: Contact / Details</label>
                    <input type="text" class="form-control" name="reference1_contact" id="reference1_contact" placeholder="Reference 1 Contact">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Reference 2: Full Name</label>
                    <input type="text" class="form-control" name="reference2_name" id="reference2_name" placeholder="Reference 2 Name" oninput="sanitizeNameInput(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Reference 2: Contact / Details</label>
                    <input type="text" class="form-control" name="reference2_contact" id="reference2_contact" placeholder="Reference 2 Contact">
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label fw-medium">Signature (Optional)</label>
                    <input type="text" class="form-control" name="signature" id="signature" placeholder="Type full legal name as acknowledgement">
                </div>
            </div>

            <div class="alert alert-info py-2 px-3 mt-4 small mb-0">
                <i class="bi bi-info-circle me-1"></i>New resident will initially use their <strong>Resident Number</strong> as default password and will change it upon first login.
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary px-4 shadow-sm" id="saveBtn">Save Resident</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Password Reset / Change Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="passwordModalLabel"><i class="bi bi-key me-2 text-warning"></i>Change Resident Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="passForm">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="resident_id" id="pass_resident_id">
        
        <div class="modal-body">
            <p class="mb-3">Resident: <strong id="pass_resident_name"></strong> (<span id="pass_resident_num" class="font-monospace fw-bold"></span>)</p>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Password Reset Option</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="pass_option" id="opt_custom" value="custom" checked onclick="togglePassOption()">
                    <label class="form-check-label" for="opt_custom">Set Custom Password</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="pass_option" id="opt_default" value="default" onclick="togglePassOption()">
                    <label class="form-check-label" for="opt_default">Reset to Default Resident Number (<span id="default_pass_val"></span>)</label>
                </div>
            </div>

            <div id="custom_pass_section">
                <div class="mb-3">
                    <label class="form-label">New Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="custom_password" id="custom_password" onkeyup="checkModalPassStrength()">
                        <span class="pw-toggle-btn" data-pw-toggle="custom_password" title="Show/hide password" role="button" aria-label="Toggle password visibility">
                            <i class="bi bi-eye-slash"></i>
                        </span>
                    </div>
                    <div class="progress mt-2" style="height: 5px;">
                        <div id="modal_strength_meter" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                    </div>
                    <small id="modal_strength_text" class="text-muted d-block mt-1"></small>
                    <small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Must be at least 8 characters long and contain both letters and numbers.</small>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="savePassBtn" onclick="submitPasswordChange()">Update Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const nextResNum = "<?= htmlspecialchars($next_res_num) ?>";

function sanitizeNameInput(input) {
    input.value = input.value.replace(/[^a-zA-Z\s\-\'\.]/g, '');
}

function sanitizePhoneInput(input) {
    input.value = input.value.replace(/[^0-9]/g, '');
}

function updateAgeDisplay() {
    const birthdateVal = document.getElementById('birthdate').value;
    const ageInput = document.getElementById('age');
    if (!birthdateVal) {
        ageInput.value = '';
        return;
    }
    const birthDate = new Date(birthdateVal);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    ageInput.value = age >= 0 ? age + ' years old' : '';
}

function validateResidentForm(e) {
    const fname = document.getElementById('first_name').value.trim();
    const lname = document.getElementById('last_name').value.trim();
    const mname = document.getElementById('middle_name').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const bdate = document.getElementById('birthdate').value;
    
    if (!fname) {
        alert("Please enter First Name.");
        e.preventDefault();
        return false;
    }
    if (!lname) {
        alert("Please enter Last Name.");
        e.preventDefault();
        return false;
    }
    
    const nameRegex = /^[a-zA-Z\s\-\'\.]+$/;
    if (!nameRegex.test(fname) || !nameRegex.test(lname) || (mname && !nameRegex.test(mname))) {
        alert("First, Middle, and Last names must contain letters only.");
        e.preventDefault();
        return false;
    }
    
    if (phone.length !== 11 || !/^[0-9]{11}$/.test(phone)) {
        alert("Phone number must be exactly 11 digits (e.g. 09171234567).");
        e.preventDefault();
        return false;
    }

    if (bdate) {
        const bTime = new Date(bdate).getTime();
        const nowTime = new Date().getTime();
        if (bTime > nowTime) {
            alert("Birthdate cannot be in the future.");
            e.preventDefault();
            return false;
        }
    }
    return true;
}

function realtimeSearchResidents(query) {
    const filter = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#residents_table tbody tr');
    rows.forEach(row => {
        if (row.cells.length < 2) return;
        const text = row.innerText.toLowerCase();
        row.style.display = (filter === '' || text.includes(filter)) ? '' : 'none';
    });
}

function addChildModalRow(childName = '', childAge = '') {
    const container = document.getElementById('modalChildrenContainer');
    const notice = document.getElementById('noChildrenModalNotice');
    if (notice) notice.remove();

    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 child-modal-row';
    const escapedName = String(childName).replace(/"/g, '&quot;');
    const escapedAge = childAge !== '' && childAge !== null ? String(childAge).replace(/"/g, '&quot;') : '';

    row.innerHTML = `
        <div class="col-md-8">
            <input type="text" name="child_name[]" class="form-control form-control-sm" placeholder="Child Full Name" value="${escapedName}">
        </div>
        <div class="col-md-3">
            <input type="number" name="child_age[]" class="form-control form-control-sm" placeholder="Age" min="0" max="120" value="${escapedAge}">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeChildModalRow(this)" title="Remove child">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function removeChildModalRow(button) {
    const row = button.closest('.child-modal-row');
    if (row) row.remove();
    const container = document.getElementById('modalChildrenContainer');
    if (container.querySelectorAll('.child-modal-row').length === 0) {
        container.innerHTML = '<div id="noChildrenModalNotice" class="text-muted small py-1">No children recorded. Click "Add Child" to record children.</div>';
    }
}

function openCreateModal() {
    document.getElementById('residentModalLabel').innerHTML = '<i class="bi bi-person-plus text-primary me-2"></i>Add New Resident';
    document.getElementById('formAction').value = 'create';
    document.getElementById('resident_id').value = '';
    document.getElementById('residentForm').reset();
    document.getElementById('age').value = '';
    document.getElementById('resident_number').value = nextResNum;
    document.getElementById('resident_number').readOnly = true;
    document.getElementById('modalChildrenContainer').innerHTML = '<div id="noChildrenModalNotice" class="text-muted small py-1">No children recorded. Click "Add Child" to record children.</div>';
    document.getElementById('saveBtn').innerText = 'Save Resident';
}

function fetchAndOpenEditModal(residentId) {
    fetch(`../ajax/residents.php?action=get&id=${residentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                openEditModal(data.data);
            } else {
                alert('Could not fetch resident details.');
            }
        })
        .catch(() => alert('Network error occurred.'));
}

function openEditModal(resident) {
    document.getElementById('residentModalLabel').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Resident Information';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('resident_id').value = resident.id;
    
    document.getElementById('resident_number').value = resident.resident_number;
    document.getElementById('resident_number').readOnly = true;
    document.getElementById('status').value = resident.status || 'active';
    document.getElementById('first_name').value = resident.first_name || '';
    document.getElementById('last_name').value = resident.last_name || '';
    document.getElementById('middle_name').value = resident.middle_name || '';
    document.getElementById('extension_name').value = resident.extension_name || '';
    document.getElementById('civil_status').value = resident.civil_status || 'Single';
    document.getElementById('gender').value = resident.gender || 'Male';
    document.getElementById('birthdate').value = resident.birthdate || '';
    document.getElementById('email').value = resident.email || '';
    document.getElementById('phone').value = resident.phone || '';
    document.getElementById('address').value = resident.address || '';
    document.getElementById('occupation').value = resident.occupation || '';
    document.getElementById('employer').value = resident.employer || '';
    document.getElementById('employer_address').value = resident.employer_address || '';
    
    document.getElementById('spouse_name').value = resident.spouse_name || '';
    document.getElementById('spouse_occupation').value = resident.spouse_occupation || '';
    document.getElementById('spouse_employer').value = resident.spouse_employer || '';
    
    document.getElementById('father_name').value = resident.father_name || '';
    document.getElementById('mother_name').value = resident.mother_name || '';
    
    document.getElementById('reference1_name').value = resident.reference1_name || '';
    document.getElementById('reference1_contact').value = resident.reference1_contact || '';
    document.getElementById('reference2_name').value = resident.reference2_name || '';
    document.getElementById('reference2_contact').value = resident.reference2_contact || '';
    document.getElementById('signature').value = resident.signature || '';
    
    updateAgeDisplay();
    
    // Render children
    const container = document.getElementById('modalChildrenContainer');
    container.innerHTML = '';
    if (resident.children && resident.children.length > 0) {
        resident.children.forEach(c => {
            addChildModalRow(c.child_name, c.age);
        });
    } else {
        container.innerHTML = '<div id="noChildrenModalNotice" class="text-muted small py-1">No children recorded. Click "Add Child" to record children.</div>';
    }
    
    document.getElementById('saveBtn').innerText = 'Update Resident';
    
    var myModal = new bootstrap.Modal(document.getElementById('residentModal'));
    myModal.show();
}

function openPasswordModal(resident) {
    document.getElementById('pass_resident_id').value = resident.id;
    document.getElementById('pass_resident_name').innerText = resident.first_name + ' ' + resident.last_name;
    document.getElementById('pass_resident_num').innerText = resident.resident_number;
    document.getElementById('default_pass_val').innerText = resident.resident_number;
    document.getElementById('custom_password').value = '';
    document.getElementById('opt_custom').checked = true;
    togglePassOption();
    
    var myModal = new bootstrap.Modal(document.getElementById('passwordModal'));
    myModal.show();
}

function togglePassOption() {
    const isCustom = document.getElementById('opt_custom').checked;
    document.getElementById('custom_pass_section').style.display = isCustom ? 'block' : 'none';
    if (isCustom) {
        document.getElementById('custom_password').setAttribute('required', 'required');
    } else {
        document.getElementById('custom_password').removeAttribute('required');
    }
}

function checkModalPassStrength() {
    let val = document.getElementById('custom_password').value;
    let meter = document.getElementById('modal_strength_meter');
    let text = document.getElementById('modal_strength_text');
    
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

function submitPasswordChange() {
    const isCustom = document.getElementById('opt_custom').checked;
    const resName = document.getElementById('pass_resident_name').innerText;
    
    if (isCustom) {
        const val = document.getElementById('custom_password').value;
        if (val.length < 8 || !/[a-zA-Z]/.test(val) || !/[0-9]/.test(val)) {
            alert('Custom password must be at least 8 characters long and contain both letters and numbers.');
            return;
        }
    }
    
    const msg = isCustom 
        ? `Are you sure you want to update the password for resident "${resName}" to the specified custom password?`
        : `Are you sure you want to reset the password for resident "${resName}" to default (${document.getElementById('pass_resident_num').innerText})?`;
        
    App.confirmAction(msg, function() {
        document.getElementById('passForm').submit();
    }, { title: 'Confirm Password Update', heading: 'Update Password', confirmText: 'Yes, Update Password', confirmClass: 'btn-primary', iconClass: 'bi bi-key text-warning fs-1' });
}

function fetchAndOpenViewModal(residentId) {
    fetch(`../ajax/residents.php?action=get&id=${residentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                openViewModal(data.data);
            } else {
                alert('Could not fetch resident details.');
            }
        })
        .catch(() => alert('Network error occurred.'));
}

function openViewModal(r) {
    const fullName = [r.first_name, r.middle_name, r.last_name, r.extension_name].filter(Boolean).join(' ');
    document.getElementById('view_full_name').innerText = fullName || 'Resident Details';
    document.getElementById('view_res_number').innerText = 'Resident ID: ' + (r.resident_number || 'N/A');
    
    // Status Badge
    const statusHtml = r.status === 'active' 
        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Active</span>'
        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Inactive</span>';
    document.getElementById('view_status_badge').innerHTML = statusHtml;

    // Updated At timestamp
    const updatedDate = r.updated_at ? new Date(r.updated_at).toLocaleString() : 'Recently';
    document.getElementById('view_last_updated').innerHTML = `<i class="bi bi-clock-history me-1"></i>Last Updated: <strong>${updatedDate}</strong>`;

    // Avatar
    const initials = ((r.first_name ? r.first_name[0] : 'R') + (r.last_name ? r.last_name[0] : 'S')).toUpperCase();
    if (r.profile_picture) {
        document.getElementById('view_avatar_container').innerHTML = `<img src="../uploads/profile_pics/${r.profile_picture}" class="rounded-circle shadow-sm" style="width: 60px; height: 60px; object-fit: cover;" alt="Avatar">`;
    } else {
        document.getElementById('view_avatar_container').innerHTML = `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 60px; height: 60px; font-size: 1.25rem;">${initials}</div>`;
    }

    // Personal details
    document.getElementById('view_civil_status').innerText = r.civil_status || 'Single';
    document.getElementById('view_gender').innerText = r.gender || 'N/A';
    
    let ageStr = 'N/A';
    if (r.birthdate) {
        const bDate = new Date(r.birthdate);
        const today = new Date();
        let age = today.getFullYear() - bDate.getFullYear();
        const m = today.getMonth() - bDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < bDate.getDate())) age--;
        ageStr = `${r.birthdate} (${age >= 0 ? age + ' yrs old' : ''})`;
    }
    document.getElementById('view_birthdate_age').innerText = ageStr;
    document.getElementById('view_phone').innerText = r.phone || 'N/A';
    document.getElementById('view_email').innerText = r.email || 'None';
    document.getElementById('view_address').innerText = r.address || 'N/A';
    document.getElementById('view_occupation').innerText = r.occupation || 'None';
    document.getElementById('view_employer').innerText = r.employer || 'None';
    document.getElementById('view_employer_address').innerText = r.employer_address || 'None';

    // Spouse
    document.getElementById('view_spouse_name').innerText = r.spouse_name || 'N/A';
    document.getElementById('view_spouse_occupation').innerText = r.spouse_occupation || 'N/A';
    document.getElementById('view_spouse_employer').innerText = r.spouse_employer || 'N/A';

    // Children
    if (r.children && r.children.length > 0) {
        let childHtml = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0" style="font-size: 12px;"><thead class="table-light"><tr><th>#</th><th>Child Name</th><th>Age</th></tr></thead><tbody>';
        r.children.forEach((c, idx) => {
            childHtml += `<tr><td>${idx+1}</td><td>${c.child_name || ''}</td><td>${c.age !== null ? c.age + ' yrs old' : 'N/A'}</td></tr>`;
        });
        childHtml += '</tbody></table></div>';
        document.getElementById('view_children_list').innerHTML = childHtml;
    } else {
        document.getElementById('view_children_list').innerHTML = '<div class="text-muted small">No children recorded.</div>';
    }

    // Parents
    document.getElementById('view_father_name').innerText = r.father_name || 'N/A';
    document.getElementById('view_mother_name').innerText = r.mother_name || 'N/A';

    // References
    document.getElementById('view_ref1').innerText = (r.reference1_name || 'N/A') + (r.reference1_contact ? ' (' + r.reference1_contact + ')' : '');
    document.getElementById('view_ref2').innerText = (r.reference2_name || 'N/A') + (r.reference2_contact ? ' (' + r.reference2_contact + ')' : '');
    document.getElementById('view_signature').innerText = r.signature || 'N/A';

    // Print & Edit links
    document.getElementById('viewPrintLink').href = `print_resident.php?id=${r.id}`;
    document.getElementById('viewEditBtn').onclick = function() {
        const viewModalEl = document.getElementById('viewResidentModal');
        const modalInstance = bootstrap.Modal.getInstance(viewModalEl);
        if (modalInstance) modalInstance.hide();
        openEditModal(r);
    };

    const myModal = new bootstrap.Modal(document.getElementById('viewResidentModal'));
    myModal.show();
}

function confirmResetResidentPassword(id, name, resNum) {
    App.confirmAction(`Are you sure you want to reset the password for resident "${name}" (${resNum})?`, function() {
        document.getElementById('resetPassForm_' + id).submit();
    }, { title: 'Reset Resident Password', heading: 'Reset Password to Default?', confirmText: 'Yes, Reset Password', confirmClass: 'btn-warning', iconClass: 'bi bi-key text-warning fs-1' });
}

function confirmToggleStatus(id, name, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'Inactive' : 'Active';
    App.confirmAction(`Are you sure you want to change status for resident "${name}" to ${newStatus}?`, function() {
        document.getElementById('toggleForm_' + id).submit();
    }, { title: 'Toggle Resident Status', heading: `Set Status to ${newStatus}?`, confirmText: `Set to ${newStatus}`, confirmClass: 'btn-primary', iconClass: 'bi bi-arrow-repeat text-primary fs-1' });
}

function confirmArchiveResident(id, name, resNum) {
    App.confirmAction(`Are you sure you want to archive resident "${name}" (${resNum})? They will be moved to the archive and can be restored or permanently deleted later.`, function() {
        document.getElementById('archiveForm_' + id).submit();
    }, { title: 'Archive Resident', heading: 'Archive Resident?', confirmText: 'Yes, Move to Archive', confirmClass: 'btn-danger', iconClass: 'bi bi-archive text-warning fs-1' });
}

function confirmRestoreResident(id, name, resNum) {
    App.confirmAction(`Are you sure you want to restore resident "${name}" (${resNum}) back to active status?`, function() {
        document.getElementById('restoreForm_' + id).submit();
    }, { title: 'Restore Resident', heading: 'Restore Resident?', confirmText: 'Yes, Restore to Active', confirmClass: 'btn-success', iconClass: 'bi bi-arrow-counterclockwise text-success fs-1' });
}

function confirmPermanentDeleteResident(id, name, resNum) {
    App.confirmAction(`Are you sure you want to permanently delete resident "${name}" (${resNum})? This action cannot be undone and will permanently remove all resident data, history, and survey responses.`, function() {
        document.getElementById('permDeleteForm_' + id).submit();
    }, { title: 'Permanently Delete Resident', heading: 'Are you sure you want to permanently delete?', confirmText: 'Yes, Permanently Delete', confirmClass: 'btn-danger', iconClass: 'bi bi-exclamation-triangle text-danger fs-1' });
}
</script>

<?php require_once '../includes/footer.php'; ?>
