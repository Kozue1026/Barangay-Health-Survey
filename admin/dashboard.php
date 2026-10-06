<?php
$pageTitle = "Dashboard";
require_once '../config/session.php';
requireAdmin();

$success_msg = '';
$error_msg = '';

// Handle POST actions for Staff Resident Management from Dashboard
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
            $existing = fb_find_one('residents', 'resident_number', $resident_number);
            if ($existing) {
                $error_msg = "Resident number already exists.";
            } else {
                // Profile Photo Upload
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
                    'resident_number' => $resident_number, 'password' => $password,
                    'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name,
                    'extension_name' => $extension_name, 'email' => $email, 'phone' => $phone, 'address' => $address,
                    'gender' => $gender, 'civil_status' => $civil_status, 'birthdate' => ($birthdate ?: null),
                    'occupation' => $occupation, 'employer' => $employer, 'employer_address' => $employer_address,
                    'spouse_name' => $spouse_name, 'spouse_occupation' => $spouse_occupation, 'spouse_employer' => $spouse_employer,
                    'father_name' => $father_name, 'mother_name' => $mother_name,
                    'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                    'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact,
                    'signature' => $signature, 'profile_picture' => $profile_picture, 'status' => $status,
                    'first_login' => 1, 'created_at' => fb_now(), 'updated_at' => fb_now(),
                ]);

                // Save Children
                if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                    foreach ($_POST['child_name'] as $idx => $cName) {
                        $cName = trim($cName);
                        if ($cName !== '') {
                            $cAge = isset($_POST['child_age'][$idx]) && $_POST['child_age'][$idx] !== '' ? (int)$_POST['child_age'][$idx] : null;
                            $chid = fb_next_id('resident_children');
                            fb_set_rec('resident_children', $chid, [
                                'resident_id' => $new_resident_id, 'child_name' => $cName, 'age' => $cAge,
                            ]);
                        }
                    }
                }
                } catch (Exception $e) {
                    $error_msg = "Failed to create resident. Please try again.";
                    $new_resident_id = null;
                }

                if (!empty($new_resident_id) && empty($error_msg)) {
                // Initialize notifications for the new resident (Welcome + active surveys)
                initializeResidentNotifications(null, $new_resident_id, $first_name);

                fb_log($_SESSION['user_id'], 'admin', 'create_resident', "Created resident: $resident_number ($first_name $last_name)");

                $success_msg = "Resident created successfully.";
                }
            }
        } elseif ($action === 'edit') {
            $resident_id = (string)$_POST['resident_id'];

            $oldResident = fb_get_rec('residents', $resident_id);

            if ($oldResident) {
                // Profile Photo Upload
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
                fb_update_rec('residents', $resident_id, array_merge($newData, [
                    'birthdate' => ($birthdate ?: null),
                    'profile_picture' => $profile_picture, 'updated_at' => fb_now(),
                ]));

                // Update children
                foreach (fb_all('resident_children') as $chid => $ch) {
                    if ((string)($ch['resident_id'] ?? '') === $resident_id) fb_delete_rec('resident_children', $chid);
                }
                if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                    foreach ($_POST['child_name'] as $idx => $cName) {
                        $cName = trim($cName);
                        if ($cName !== '') {
                            $cAge = isset($_POST['child_age'][$idx]) && $_POST['child_age'][$idx] !== '' ? (int)$_POST['child_age'][$idx] : null;
                            $chid = fb_next_id('resident_children');
                            fb_set_rec('resident_children', $chid, [
                                'resident_id' => $resident_id, 'child_name' => $cName, 'age' => $cAge,
                            ]);
                        }
                    }
                }
                } catch (Exception $e) {
                    $error_msg = "Failed to update resident. Please try again.";
                }

                if (empty($error_msg)) {
                fb_log($_SESSION['user_id'], 'admin', 'update_resident', "Updated resident details for: " . ($oldResident['resident_number'] ?? ''));

                if (!empty($changes)) {
                    $changeDetails = implode('; ', $changes);
                }

                $success_msg = "Resident updated successfully.";
                }
            }
        }
    } elseif ($action === 'archive' || $action === 'delete') { // Archive Resident
        $resident_id = (string)$_POST['resident_id'];
        $resident = fb_get_rec('residents', $resident_id);
        
        if ($resident) {
            fb_update_rec('residents', $resident_id, ['status' => 'archived', 'updated_at' => fb_now()]);

            fb_log($_SESSION['user_id'], 'admin', 'archive_resident', "Archived resident: " . ($resident['resident_number'] ?? '') . " (" . ($resident['first_name'] ?? '') . " " . ($resident['last_name'] ?? '') . ")");

            $success_msg = "Resident \"" . htmlspecialchars($resident['resident_number'] ?? '') . "\" has been archived successfully.";
        }
    } elseif ($action === 'reset_password') {
        $resident_id = (string)$_POST['resident_id'];
        $resident = fb_get_rec('residents', $resident_id);
        
        if ($resident) {
            $hashed = password_hash($resident['resident_number'], PASSWORD_DEFAULT);
            fb_update_rec('residents', $resident_id, ['password' => $hashed, 'first_login' => 1, 'updated_at' => fb_now()]);

            fb_log($_SESSION['user_id'], 'admin', 'reset_resident_password', "Reset password for resident " . ($resident['resident_number'] ?? '') . " to default");

            $success_msg = "Resident password has been reset successfully.";
        } else {
            $error_msg = "Resident record not found.";
        }
    }
}

// Stats - Residents breakdown (Firebase)
$allResidentsFb = fb_all('residents');
$totalResidents = 0; $activeResidents = 0;
foreach ($allResidentsFb as $r) {
    if (($r['status'] ?? '') === 'archived') continue;
    $totalResidents++;
    if (($r['status'] ?? '') === 'active') $activeResidents++;
}
$inactiveResidents = $totalResidents - $activeResidents;

// Stats - Surveys breakdown
$allSurveysFb = fb_all('surveys');
$totalSurveys = count($allSurveysFb);
$activeSurveys = 0;
foreach ($allSurveysFb as $s) { if (($s['status'] ?? '') === 'active') $activeSurveys++; }
$inactiveSurveys = $totalSurveys - $activeSurveys;

$allQuestionsFb = fb_all('survey_questions');
$totalQuestions = count($allQuestionsFb);

$allResponsesFb = fb_all('responses');
$totalResponses = count($allResponsesFb);

// Children counts per resident
$childrenCountByResident = [];
foreach (fb_all('resident_children') as $ch) {
    $rid = (string)($ch['resident_id'] ?? '');
    $childrenCountByResident[$rid] = ($childrenCountByResident[$rid] ?? 0) + 1;
}

// Fetch Residents for Dashboard Management
$dashboardResidents = [];
foreach ($allResidentsFb as $rid => $r) {
    if (($r['status'] ?? '') === 'archived') continue;
    $r['id'] = (string)$rid;
    $r['children_count'] = $childrenCountByResident[(string)$rid] ?? 0;
    $dashboardResidents[] = $r;
}
usort($dashboardResidents, function ($a, $b) { return (int)$b['id'] - (int)$a['id']; });

// Calculate Next Sequential Resident Number
$next_res_num = fb_next_resident_number();

// Chart: Survey Completion Rate
$resCountBySurvey = [];
foreach ($allResponsesFb as $r) {
    $sid = (string)($r['survey_id'] ?? '');
    $resCountBySurvey[$sid] = ($resCountBySurvey[$sid] ?? 0) + 1;
}
$surveyCompletionData = [];
$i = 0;
foreach ($allSurveysFb as $sid => $s) {
    if ($i++ >= 10) break;
    $surveyCompletionData[] = [
        'title' => $s['title'] ?? '',
        'total_active_residents' => $activeResidents,
        'response_count' => $resCountBySurvey[(string)$sid] ?? 0,
    ];
}

// Chart: Response Trends (last 12 months)
$twelveMonthsAgo = date('Y-m-d H:i:s', strtotime('-12 months'));
$trendsByMonth = [];
foreach ($allResponsesFb as $r) {
    $sub = $r['submitted_at'] ?? $r['created_at'] ?? '';
    if ($sub === '' || $sub < $twelveMonthsAgo) continue;
    $m = date('Y-m', strtotime($sub));
    $trendsByMonth[$m] = ($trendsByMonth[$m] ?? 0) + 1;
}
ksort($trendsByMonth);
$responseTrendsData = [];
foreach ($trendsByMonth as $m => $c) $responseTrendsData[] = ['month' => $m, 'count' => $c];

// Chart: Surveys by Category
$catCounts = [];
foreach ($allSurveysFb as $s) {
    $cat = $s['category'] ?? 'Uncategorized';
    $catCounts[$cat] = ($catCounts[$cat] ?? 0) + 1;
}
$surveyCategoryData = [];
foreach ($catCounts as $cat => $c) $surveyCategoryData[] = ['category' => $cat, 'count' => $c];

// Chart: Survey Status
$statusCounts = [];
foreach ($allSurveysFb as $s) {
    $st = $s['status'] ?? 'unknown';
    $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
}
$surveyStatusData = [];
foreach ($statusCounts as $st => $c) $surveyStatusData[] = ['status' => $st, 'count' => $c];

// Recent Activity Log (two-step JOIN via admins + residents nodes)
$allAdminsFb = fb_all('admins');
$adminNameById = [];
foreach ($allAdminsFb as $aid => $a) {
    $nm = trim($a['full_name'] ?? '');
    if ($nm === '') $nm = $a['username'] ?? '';
    $adminNameById[(string)$aid] = $nm;
}
$resNameById = [];
foreach ($allResidentsFb as $rid => $r) {
    $resNameById[(string)$rid] = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
}
$allLogs = [];
foreach (fb_all('activity_logs') as $lid => $al) {
    $al['id'] = (string)$lid;
    if (($al['user_type'] ?? '') === 'admin') {
        $nm = $adminNameById[(string)($al['user_id'] ?? '')] ?? '';
    } else {
        $nm = $resNameById[(string)($al['user_id'] ?? '')] ?? '';
    }
    $al['user_name'] = ($nm !== '') ? $nm : 'System';
    $allLogs[] = $al;
}
usort($allLogs, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });
$recentActivities = array_slice($allLogs, 0, 10);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0 fw-bold"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard</h2>
            <button class="btn btn-primary px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#residentModal" onclick="openCreateModal()">
                <i class="bi bi-person-plus me-1"></i> Add New Resident
            </button>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card stat-card text-bg-primary h-100 card-hover border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-uppercase mb-2 opacity-75">Total Residents</h6>
                            <h4 class="mb-1 fw-bold"><?= number_format($totalResidents) ?> Total</h4>
                            <div class="small opacity-90 fw-medium">(<?= $activeResidents ?> Active, <?= $inactiveResidents ?> Inactive)</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 60px; height: 60px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-people-fill" style="font-size: 1.75rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-bg-success h-100 card-hover border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-uppercase mb-2 opacity-75">Total Surveys</h6>
                            <h4 class="mb-1 fw-bold"><?= number_format($totalSurveys) ?> Total</h4>
                            <div class="small opacity-90 fw-medium">(<?= $activeSurveys ?> Active, <?= $inactiveSurveys ?> Inactive)</div>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 60px; height: 60px; flex-shrink: 0; background-color: rgba(255,255,255,0.25);">
                            <i class="bi bi-clipboard2-data-fill" style="font-size: 1.75rem; color: #fff;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-bg-warning text-dark h-100 card-hover border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-uppercase mb-2 opacity-75 text-dark">Total Questions</h6>
                            <h2 class="mb-0 fw-bold text-dark"><?= number_format($totalQuestions) ?></h2>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 60px; height: 60px; flex-shrink: 0; background-color: rgba(0,0,0,0.12);">
                            <i class="bi bi-question-circle-fill" style="font-size: 1.75rem; color: #422006;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-bg-info text-dark h-100 card-hover border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-uppercase mb-2 opacity-75 text-dark">Total Responses</h6>
                            <h2 class="mb-1 fw-bold text-dark"><?= number_format($totalResponses) ?></h2>
                            <small class="opacity-75 text-dark"><i class="bi bi-shield-check me-1"></i>Preserved on resident removal</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center ms-3" style="width: 60px; height: 60px; flex-shrink: 0; background-color: rgba(0,0,0,0.12);">
                            <i class="bi bi-chat-dots-fill" style="font-size: 1.75rem; color: #164e63;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RESIDENT MANAGEMENT SECTION (Staff Resident Records) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i>Resident Records Management</h5>
                    <small class="text-muted">Quick access to view, search, add, edit, and manage barangay residents</small>
                </div>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width: 280px;">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" id="dash_resident_search" class="form-control" placeholder="Search resident by name, #..." onkeyup="realtimeSearchDashResidents(this.value)" oninput="realtimeSearchDashResidents(this.value)">
                    </div>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#residentModal" onclick="openCreateModal()">
                        <i class="bi bi-person-plus me-1"></i> Add Resident
                    </button>
                    <a href="manage_residents.php" class="btn btn-sm btn-outline-secondary" title="Go to Manage Residents">
                        <i class="bi bi-box-arrow-up-right me-1"></i> View All
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="dash_residents_table">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 60px; text-align: center;">#</th>
                                <th style="width: 150px; min-width: 140px;">Resident No.</th>
                                <th style="min-width: 220px;">Resident Name</th>
                                <th style="min-width: 130px;">Civil Status</th>
                                <th style="min-width: 160px;">Contact Number</th>
                                <th style="min-width: 240px;">Address</th>
                                <th style="width: 110px; min-width: 100px;">Status</th>
                                <th class="text-end" style="min-width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($dashboardResidents)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No resident records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($dashboardResidents as $i => $res): ?>
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
                                                    <img src="../uploads/profile_pics/<?= htmlspecialchars($res['profile_picture']) ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;" alt="Avatar">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center me-2 small fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                        <?= $initials ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="fw-bold"><?= htmlspecialchars($fullName) ?></span>
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
                                            <small class="text-truncate d-inline-block" style="max-width: 280px;" title="<?= htmlspecialchars($res['address'] ?? '') ?>">
                                                <?= htmlspecialchars($res['address'] ?? '') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (($res['status'] ?? '') === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-info" title="View Resident Details" onclick="fetchAndOpenViewModal(<?= $res['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Resident" onclick="fetchAndOpenEditModal(<?= $res['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" id="resetPassDashForm_<?= $res['id'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-warning" title="Reset Resident Password" onclick="confirmResetResidentPassword(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                            </form>
                                            <a href="print_resident.php?id=<?= $res['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Resident Information">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <form method="POST" id="archiveDashForm_<?= $res['id'] ?>" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="archive">
                                                <input type="hidden" name="resident_id" value="<?= $res['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger" title="Archive Resident" onclick="confirmArchiveResident(<?= $res['id'] ?>, '<?= htmlspecialchars($fullName, ENT_QUOTES) ?>', '<?= htmlspecialchars($res['resident_number']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Survey Completion Rate</h5>
                        <canvas id="completionChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Surveys by Category</h5>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Response Trends (Last 12 Months)</h5>
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Survey Status</h5>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Log -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history text-primary me-2"></i>Recent Activity</h5>
                <a href="activity_logs.php" class="btn btn-sm btn-outline-secondary" title="View Full Activity Log">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View All Logs
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="dash_activity_table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" style="width: 230px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">DATE &amp; TIME</th>
                                <th style="min-width: 180px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">USER</th>
                                <th style="width: 130px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">TYPE</th>
                                <th style="width: 160px; font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">ACTION</th>
                                <th class="pe-4" style="font-size: 0.8rem; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase;">DESCRIPTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No recent activities found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap text-muted" style="font-size: 0.88rem;">
                                            <i class="bi bi-clock me-2 text-primary"></i><?= date('M j, Y, g:i A', strtotime($activity['created_at'])) ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($activity['user_name'] ?? 'System') ?></span>
                                        </td>
                                        <td>
                                            <?php if ($activity['user_type'] === 'admin'): ?>
                                                <span class="badge bg-primary rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-success rounded-pill px-3 py-1 fw-semibold" style="font-size: 0.78rem;">Resident</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill px-3 py-1 fw-bold text-uppercase text-white" style="background-color: #64748b; font-size: 0.72rem; letter-spacing: 0.5px;">
                                                <?= htmlspecialchars(str_replace('_', ' ', strtoupper($activity['action']))) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-dark" style="font-size: 0.92rem;">
                                            <?= htmlspecialchars($activity['description']) ?>
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

<!-- View Resident Details Modal for Dashboard -->
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

<!-- Add / Edit Resident Modal for Dashboard -->
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

function realtimeSearchDashResidents(query) {
    const filter = query.toLowerCase().trim();
    const rows = document.querySelectorAll('#dash_residents_table tbody tr');
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
        document.getElementById('resetPassDashForm_' + id).submit();
    }, { title: 'Reset Resident Password', heading: 'Reset Password to Default?', confirmText: 'Yes, Reset Password', confirmClass: 'btn-warning', iconClass: 'bi bi-key text-warning fs-1' });
}

function confirmArchiveResident(id, name, resNum) {
    App.confirmAction(`Are you sure you want to archive resident "${name}" (${resNum})? They will be moved to the archive and can be restored or permanently deleted in Manage Residents.`, function() {
        document.getElementById('archiveDashForm_' + id).submit();
    }, { title: 'Archive Resident', heading: 'Archive Resident?', confirmText: 'Yes, Move to Archive', confirmClass: 'btn-danger', iconClass: 'bi bi-archive text-warning fs-1' });
}

document.addEventListener('DOMContentLoaded', function() {
    // Data preparation
    const completionData = <?= json_encode($surveyCompletionData) ?>;
    const trendsData = <?= json_encode($responseTrendsData) ?>;
    const categoryData = <?= json_encode($surveyCategoryData) ?>;
    const statusData = <?= json_encode($surveyStatusData) ?>;

    // Survey Completion Bar Chart
    const ctxCompletion = document.getElementById('completionChart');
    if (ctxCompletion) {
        new Chart(ctxCompletion, {
            type: 'bar',
            data: {
                labels: completionData.map(item => item.title.substring(0, 20) + '...'),
                datasets: [
                    {
                        label: 'Responses',
                        data: completionData.map(item => item.response_count),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                    },
                    {
                        label: 'Total Active Residents',
                        data: completionData.map(item => item.total_active_residents),
                        backgroundColor: 'rgba(201, 203, 207, 0.5)'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Response Trends Line Chart
    const ctxTrends = document.getElementById('trendsChart');
    if (ctxTrends) {
        new Chart(ctxTrends, {
            type: 'line',
            data: {
                labels: trendsData.map(item => item.month),
                datasets: [{
                    label: 'Responses',
                    data: trendsData.map(item => item.count),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    }

    // Surveys by Category Doughnut Chart
    const ctxCategory = document.getElementById('categoryChart');
    if (ctxCategory) {
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category),
                datasets: [{
                    data: categoryData.map(item => item.count),
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#0dcaf0', '#d63384'
                    ]
                }]
            },
            options: { responsive: true }
        });
    }

    // Survey Status Pie Chart
    const ctxStatus = document.getElementById('statusChart');
    if (ctxStatus) {
        new Chart(ctxStatus, {
            type: 'pie',
            data: {
                labels: statusData.map(item => item.status),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        '#198754', '#dc3545', '#6c757d'
                    ]
                }]
            },
            options: { responsive: true }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
