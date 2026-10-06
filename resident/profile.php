<?php
$pageTitle = "My Profile";
require_once '../config/session.php';
requireResident();

$user_id = (string)$_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request token. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'upload_photo') {
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                $fileName = $_FILES['profile_pic']['name'];
                $fileSize = $_FILES['profile_pic']['size'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $imageInfo = @getimagesize($fileTmpPath);
                if (in_array($fileExtension, $allowedExtensions) && $imageInfo) {
                    if ($fileSize < 2 * 1024 * 1024) { // 2MB limit
                        $uploadFileDir = '../uploads/profile_pics/';
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0755, true);
                        }
                        
                        $newFileName = 'res_' . $user_id . '_' . time() . '.' . $fileExtension;
                        $dest_path = $uploadFileDir . $newFileName;
                        
                        // Fetch old photo to delete
                        $old_res = fb_get_rec('residents', $user_id);
                        $old_pic = $old_res['profile_picture'] ?? null;

                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            fb_update_rec('residents', $user_id, ['profile_picture' => $newFileName, 'updated_at' => fb_now()]);

                            if ($old_pic && file_exists($uploadFileDir . $old_pic)) {
                                @unlink($uploadFileDir . $old_pic);
                            }

                            $success = "Profile picture updated successfully.";

                            // Log activity
                            fb_log($user_id, 'resident', 'Upload Profile Picture', 'User updated their profile picture');

                            // Notify Admins
                            $resNum = $old_res['resident_number'] ?? '';
                            $resName = trim(($old_res['first_name'] ?? '') . ' ' . ($old_res['last_name'] ?? ''));
                            notifyAdmins(
                                null,
                                "Resident Photo Updated",
                                "Resident $resNum ($resName) uploaded a new profile picture.",
                                "/admin/manage_residents.php",
                                "bi-image text-info",
                                $user_id,
                                "resident"
                            );
                        } else {
                            $error = "There was an error saving the uploaded photo.";
                        }
                    } else {
                        $error = "File is too large. Maximum size allowed is 2MB.";
                    }
                } else {
                    $error = "Upload failed. Please select a valid image file (JPG, PNG, GIF, WEBP).";
                }
            } else {
                $error = "No file uploaded or error occurred during file transfer.";
            }
        } else {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $extension_name = trim($_POST['extension_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $gender = $_POST['gender'] ?? 'Male';
            $civil_status = $_POST['civil_status'] ?? 'Single';
            $birthdate = $_POST['birthdate'] ?? '';
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
            $security_question = trim($_POST['security_question'] ?? '');
            $security_answer = strtolower(trim($_POST['security_answer'] ?? ''));
            
            $nameRegex = '/^[\p{L}\s\-\'\.]+$/u';
            if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
                $error = "First Name is required and must contain letters, spaces, hyphens, and apostrophes only.";
            } elseif (empty($last_name) || !preg_match($nameRegex, $last_name)) {
                $error = "Last Name is required and must contain letters, spaces, hyphens, and apostrophes only.";
            } elseif (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
                $error = "Middle Name must contain letters, spaces, hyphens, and apostrophes only.";
            } elseif (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
                $error = "Phone number must be exactly 11 digits (e.g., 09171234567).";
            } elseif (!empty($birthdate) && strtotime($birthdate) > time()) {
                $error = "Birthdate cannot be in the future.";
            } else {
                try {
                    // Fetch old resident info to determine changes
                    $oldResident = fb_get_rec('residents', $user_id);

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
                        'signature'          => $signature,
                        'security_question'  => $security_question,
                        'security_answer'    => $security_answer
                    ];

                    $changes = getResidentChangesDescription($oldResident, $newData);

                    fb_update_rec('residents', $user_id, [
                        'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name,
                        'extension_name' => $extension_name, 'email' => $email, 'phone' => $phone,
                        'address' => $address, 'gender' => $gender, 'civil_status' => $civil_status,
                        'birthdate' => $birthdate, 'occupation' => $occupation, 'employer' => $employer,
                        'employer_address' => $employer_address, 'spouse_name' => $spouse_name,
                        'spouse_occupation' => $spouse_occupation, 'spouse_employer' => $spouse_employer,
                        'father_name' => $father_name, 'mother_name' => $mother_name,
                        'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                        'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact,
                        'signature' => $signature, 'security_question' => $security_question,
                        'security_answer' => $security_answer, 'updated_at' => fb_now(),
                    ]);

                    // Sync children
                    foreach (fb_all('resident_children') as $cid => $cc) {
                        if ((string)($cc['resident_id'] ?? '') === $user_id) fb_delete_rec('resident_children', $cid);
                    }
                    if (!empty($_POST['child_name']) && is_array($_POST['child_name'])) {
                        foreach ($_POST['child_name'] as $idx => $cName) {
                            $cName = trim($cName);
                            if ($cName !== '') {
                                $cAge = isset($_POST['child_age'][$idx]) && $_POST['child_age'][$idx] !== '' ? (int)$_POST['child_age'][$idx] : null;
                                $ncid = fb_next_id('resident_children');
                                fb_set_rec('resident_children', $ncid, ['resident_id' => $user_id, 'child_name' => $cName, 'age' => $cAge, 'created_at' => fb_now()]);
                            }
                        }
                    }

                    $_SESSION['full_name'] = trim("$first_name $last_name");
                    $success = "Profile details updated successfully.";

                    // Log activity
                    fb_log($user_id, 'resident', 'Update Profile', 'User updated their profile information');

                    // Notify admins about the changes made
                    if (!empty($changes)) {
                        $resNum = $oldResident['resident_number'] ?? '';
                        $resName = trim("$first_name $last_name");
                        $changeDetails = implode('; ', $changes);
                        notifyAdmins(
                            null,
                            "Resident Profile Updated",
                            "Resident $resNum ($resName) updated: $changeDetails",
                            "/admin/manage_residents.php",
                            "bi-person-lines-fill text-info",
                            $user_id,
                            "resident"
                        );
                    }
                } catch (Exception $e) {
                    $error = "Failed to update profile. Please check your details.";
                }
            }
        }
    }
}

// Fetch fresh resident details
$resident = fb_get_rec('residents', $user_id);

// Fetch children
$children = [];
foreach (fb_all('resident_children') as $cid => $cc) {
    if ((string)($cc['resident_id'] ?? '') === $user_id) { $cc['id'] = $cid; $children[] = $cc; }
}
usort($children, function ($a, $b) { return ((int)$a['id']) - ((int)$b['id']); });

$initials = strtoupper(substr($resident['first_name'] ?? 'R', 0, 1) . substr($resident['last_name'] ?? 'S', 0, 1));

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0 fw-bold"><i class="bi bi-person-badge text-primary me-2"></i>Resident Information</h2>
            <button class="btn btn-primary px-4 shadow-sm" onclick="toggleEdit()" id="editBtn">
                <i class="bi bi-pencil-square me-1"></i> Edit Profile
            </button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Sidebar Col (Profile Photo & Summary) -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body text-center py-4">
                        <div class="avatar-upload-container mx-auto mb-3" style="width: 140px; height: 140px; position: relative;">
                            <?php if (!empty($resident['profile_picture']) && file_exists('../uploads/profile_pics/' . $resident['profile_picture'])): ?>
                                <img src="../uploads/profile_pics/<?= htmlspecialchars($resident['profile_picture']) ?>" class="rounded-circle shadow-sm" style="width: 140px; height: 140px; object-fit: cover; border: 4px solid var(--card-bg);" alt="Avatar">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fs-1 fw-bold shadow-sm" style="width: 140px; height: 140px; border: 4px solid var(--card-bg);">
                                    <?= $initials ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-upload-overlay" onclick="document.getElementById('profilePicInput').click()" title="Click to upload passport-size photo" style="position: absolute; bottom: 0; right: 0; background: var(--primary-color); color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid var(--card-bg);">
                                <i class="bi bi-camera-fill"></i>
                            </div>
                        </div>
                        <h5 class="mb-1 fw-bold"><?= htmlspecialchars(trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? '') . ' ' . ($resident['extension_name'] ?? ''))) ?></h5>
                        <p class="text-muted mb-2 font-monospace"><strong><?= htmlspecialchars($resident['resident_number'] ?? '') ?></strong></p>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill mb-3">
                            <i class="bi bi-check-circle me-1"></i>Active Resident
                        </span>
                        
                        <div class="d-grid gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('profilePicInput').click()">
                                <i class="bi bi-upload me-1"></i> Upload Passport Photo
                            </button>
                        </div>
                        <small class="text-muted d-block mt-2" style="font-size: 0.8rem;">Allowed: JPG, PNG, GIF (Max 2MB)</small>
                        
                        <!-- Hidden File Upload Form -->
                        <form method="POST" enctype="multipart/form-data" id="avatarForm" class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="file" name="profile_pic" id="profilePicInput" accept="image/*" onchange="submitAvatarForm()">
                        </form>
                    </div>
                </div>

                <!-- Quick Summary Card -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-0">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-primary me-2"></i>Quick Summary</h6>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Civil Status:</span>
                                <strong><?= htmlspecialchars($resident['civil_status'] ?: 'Not set') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Contact:</span>
                                <strong><?= htmlspecialchars($resident['phone'] ?: 'Not set') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Occupation:</span>
                                <strong><?= htmlspecialchars($resident['occupation'] ?: 'Not set') ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Children:</span>
                                <span class="badge bg-primary rounded-pill"><?= count($children) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Right Col (Profile Details Form) -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Resident Profile Details</h5>
                            <small class="text-muted">View and manage your complete household and personal records</small>
                        </div>
                    </div>

                    <!-- Quick Search Field -->
                    <div class="px-4 pt-2 pb-0">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="profile_search" class="form-control" placeholder="Search profile details (e.g., Name, Employer, Spouse, Children)..." onkeyup="realtimeSearchProfile(this.value)" oninput="realtimeSearchProfile(this.value)">
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <fieldset id="formFieldset" disabled>
                                <!-- 1. PERSONAL INFORMATION -->
                                <div class="profile-section mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                        <i class="bi bi-person me-2"></i>1. Personal Information
                                    </h6>

                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Resident No.</label>
                                            <input type="text" class="form-control bg-light font-monospace" value="<?= htmlspecialchars($resident['resident_number'] ?? '') ?>" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                                            <input type="text" name="first_name" id="first_name" class="form-control" value="<?= htmlspecialchars($resident['first_name'] ?? '') ?>" required oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Middle Name</label>
                                            <input type="text" name="middle_name" id="middle_name" class="form-control" value="<?= htmlspecialchars($resident['middle_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" name="last_name" id="last_name" class="form-control" value="<?= htmlspecialchars($resident['last_name'] ?? '') ?>" required oninput="sanitizeNameInput(this)">
                                        </div>
                                    </div>

                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Extension Name</label>
                                            <input type="text" name="extension_name" id="extension_name" class="form-control" placeholder="e.g. Jr., Sr., III" value="<?= htmlspecialchars($resident['extension_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Civil Status</label>
                                            <select name="civil_status" id="civil_status" class="form-select">
                                                <?php
                                                $statuses = ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'];
                                                $currCivil = $resident['civil_status'] ?? 'Single';
                                                foreach ($statuses as $st) {
                                                    $sel = strcasecmp($currCivil, $st) === 0 ? 'selected' : '';
                                                    echo "<option value=\"$st\" $sel>$st</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Gender</label>
                                            <select name="gender" id="gender" class="form-select">
                                                <option value="Male" <?= ($resident['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                                <option value="Female" <?= ($resident['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                                <option value="Prefer not to say" <?= in_array($resident['gender'] ?? '', ['Prefer not to say', 'Other']) ? 'selected' : '' ?>>Prefer not to say</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Birthdate</label>
                                            <input type="date" name="birthdate" id="birthdate" class="form-control" value="<?= htmlspecialchars($resident['birthdate'] ?? '') ?>" onchange="updateAgeDisplay()">
                                        </div>
                                    </div>

                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-3">
                                            <label class="form-label fw-medium">Age</label>
                                            <input type="text" id="age" class="form-control bg-light" readonly placeholder="Auto-calculated">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Contact Number <span class="text-danger">*</span></label>
                                            <input type="tel" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($resident['phone'] ?? '') ?>" pattern="[0-9]{11}" inputmode="numeric" placeholder="e.g. 09171234567" required maxlength="11" minlength="11" oninput="sanitizePhoneInput(this)">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label fw-medium">Email Address</label>
                                            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($resident['email'] ?? '') ?>" placeholder="name@example.com">
                                        </div>
                                    </div>

                                    <div class="mb-3 profile-field">
                                        <label class="form-label fw-medium">Residential Address</label>
                                        <textarea name="address" id="address" class="form-control" rows="2" placeholder="House No., Street, Purok/Sitio, Barangay"><?= htmlspecialchars($resident['address'] ?? '') ?></textarea>
                                    </div>

                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Occupation</label>
                                            <input type="text" name="occupation" id="occupation" class="form-control" placeholder="e.g. Self-employed, Teacher" value="<?= htmlspecialchars($resident['occupation'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Employer / Business</label>
                                            <input type="text" name="employer" id="employer" class="form-control" placeholder="Company or Business Name" value="<?= htmlspecialchars($resident['employer'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Employer Address</label>
                                            <input type="text" name="employer_address" id="employer_address" class="form-control" placeholder="Workplace Address" value="<?= htmlspecialchars($resident['employer_address'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. SPOUSE INFORMATION -->
                                <div class="profile-section mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                        <i class="bi bi-heart me-2"></i>2. Spouse Information
                                    </h6>
                                    <div class="row g-3 profile-field">
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Spouse Full Name</label>
                                            <input type="text" name="spouse_name" id="spouse_name" class="form-control" placeholder="Spouse Full Name" value="<?= htmlspecialchars($resident['spouse_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Spouse Occupation</label>
                                            <input type="text" name="spouse_occupation" id="spouse_occupation" class="form-control" placeholder="Spouse Occupation" value="<?= htmlspecialchars($resident['spouse_occupation'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-medium">Spouse Employer</label>
                                            <input type="text" name="spouse_employer" id="spouse_employer" class="form-control" placeholder="Spouse Employer Name" value="<?= htmlspecialchars($resident['spouse_employer'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. CHILDREN -->
                                <div class="profile-section mb-4">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                        <h6 class="mb-0 fw-bold text-primary">
                                            <i class="bi bi-people me-2"></i>3. Children Information
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="addChildBtn" onclick="addChildRow()">
                                            <i class="bi bi-plus-circle me-1"></i> Add Child
                                        </button>
                                    </div>

                                    <div id="childrenContainer">
                                        <?php if (empty($children)): ?>
                                            <div id="noChildrenNotice" class="text-muted small py-2">
                                                <i class="bi bi-info-circle me-1"></i>No children registered. Click "Add Child" to record children.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($children as $child): ?>
                                                <div class="row g-2 align-items-center mb-2 child-row profile-field">
                                                    <div class="col-md-8">
                                                        <input type="text" name="child_name[]" class="form-control" placeholder="Child Full Name" value="<?= htmlspecialchars($child['child_name']) ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <input type="number" name="child_age[]" class="form-control" placeholder="Age" min="0" max="120" value="<?= htmlspecialchars($child['age'] !== null ? $child['age'] : '') ?>">
                                                    </div>
                                                    <div class="col-md-1 text-end">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-child-btn" onclick="removeChildRow(this)" title="Remove child">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- 4. PARENTS -->
                                <div class="profile-section mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                        <i class="bi bi-person-heart me-2"></i>4. Parents Information
                                    </h6>
                                    <div class="row g-3 profile-field">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Father's Full Name</label>
                                            <input type="text" name="father_name" id="father_name" class="form-control" placeholder="Father's Full Name" value="<?= htmlspecialchars($resident['father_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Mother's Maiden Name</label>
                                            <input type="text" name="mother_name" id="mother_name" class="form-control" placeholder="Mother's Full Name" value="<?= htmlspecialchars($resident['mother_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. REFERENCES & SIGNATURE -->
                                <div class="profile-section mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                        <i class="bi bi-card-checklist me-2"></i>5. Character References & Signature
                                    </h6>
                                    
                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Reference 1: Full Name</label>
                                            <input type="text" name="reference1_name" id="reference1_name" class="form-control" placeholder="Character Reference 1 Name" value="<?= htmlspecialchars($resident['reference1_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Reference 1: Contact / Details</label>
                                            <input type="text" name="reference1_contact" id="reference1_contact" class="form-control" placeholder="Phone or Address of Reference 1" value="<?= htmlspecialchars($resident['reference1_contact'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 profile-field mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Reference 2: Full Name</label>
                                            <input type="text" name="reference2_name" id="reference2_name" class="form-control" placeholder="Character Reference 2 Name" value="<?= htmlspecialchars($resident['reference2_name'] ?? '') ?>" oninput="sanitizeNameInput(this)">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Reference 2: Contact / Details</label>
                                            <input type="text" name="reference2_contact" id="reference2_contact" class="form-control" placeholder="Phone or Address of Reference 2" value="<?= htmlspecialchars($resident['reference2_contact'] ?? '') ?>">
                                        </div>
                                    </div>

                                    <div class="row g-3 profile-field">
                                        <div class="col-md-12">
                                            <label class="form-label fw-medium">Signature (Optional)</label>
                                            <input type="text" name="signature" id="signature" class="form-control" placeholder="Type your full legal name as digital signature acknowledgement" value="<?= htmlspecialchars($resident['signature'] ?? '') ?>">
                                            <small class="text-muted">Sign by typing your full name to acknowledge the accuracy of the provided information.</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. SECURITY QUESTION -->
                                <div class="profile-section mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold text-primary">
                                        <i class="bi bi-shield-lock me-2"></i>6. Security Question (For Password Recovery)
                                    </h6>
                                    <div class="row g-3 profile-field">
                                        <div class="col-md-12">
                                            <label class="form-label fw-medium">Security Question</label>
                                            <input type="text" name="security_question" id="security_question" class="form-control" value="<?= htmlspecialchars($resident['security_question'] ?? '') ?>" placeholder="e.g. What is your favorite elementary teacher's name?">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label fw-medium">Security Answer</label>
                                            <input type="text" name="security_answer" id="security_answer" class="form-control" value="<?= htmlspecialchars($resident['security_answer'] ?? '') ?>" placeholder="Enter security answer">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-none mt-4 pt-3 border-top" id="actionButtons">
                                    <button type="button" class="btn btn-success px-4 shadow-sm" onclick="confirmSaveProfile()">
                                        <i class="bi bi-check-circle me-1"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary px-4 ms-2" onclick="toggleEdit()">
                                        Cancel
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let isEditing = false;

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

// Update age display on load
document.addEventListener('DOMContentLoaded', updateAgeDisplay);

function toggleEdit() {
    isEditing = !isEditing;
    const fieldset = document.getElementById('formFieldset');
    const btn = document.getElementById('editBtn');
    const actions = document.getElementById('actionButtons');
    const addChildBtn = document.getElementById('addChildBtn');
    
    if (isEditing) {
        fieldset.disabled = false;
        btn.classList.add('d-none');
        actions.classList.remove('d-none');
        if (addChildBtn) addChildBtn.disabled = false;
        const firstInput = document.getElementById('first_name');
        if (firstInput) firstInput.focus();
    } else {
        fieldset.disabled = true;
        btn.classList.remove('d-none');
        actions.classList.add('d-none');
        if (addChildBtn) addChildBtn.disabled = true;
        window.location.reload();
    }
}

function addChildRow(childName = '', childAge = '') {
    const container = document.getElementById('childrenContainer');
    const notice = document.getElementById('noChildrenNotice');
    if (notice) {
        notice.remove();
    }

    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center mb-2 child-row profile-field';
    const escapedName = String(childName).replace(/"/g, '&quot;');
    const escapedAge = childAge !== '' ? String(childAge).replace(/"/g, '&quot;') : '';

    row.innerHTML = `
        <div class="col-md-8">
            <input type="text" name="child_name[]" class="form-control" placeholder="Child Full Name" value="${escapedName}">
        </div>
        <div class="col-md-3">
            <input type="number" name="child_age[]" class="form-control" placeholder="Age" min="0" max="120" value="${escapedAge}">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-child-btn" onclick="removeChildRow(this)" title="Remove child">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;

    container.appendChild(row);
}

function removeChildRow(button) {
    const row = button.closest('.child-row');
    if (row) {
        row.remove();
    }
    const container = document.getElementById('childrenContainer');
    if (container.querySelectorAll('.child-row').length === 0) {
        container.innerHTML = '<div id="noChildrenNotice" class="text-muted small py-2"><i class="bi bi-info-circle me-1"></i>No children registered. Click "Add Child" to record children.</div>';
    }
}

function confirmSaveProfile() {
    const fn = document.getElementById('first_name').value.trim();
    const ln = document.getElementById('last_name').value.trim();
    const mn = document.getElementById('middle_name').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    const nameRegex = /^[a-zA-Z\s\-\'\.]+$/;
    if (!nameRegex.test(fn)) {
        alert("First Name must contain letters only (no numbers or symbols).");
        document.getElementById('first_name').focus();
        return;
    }
    if (!nameRegex.test(ln)) {
        alert("Last Name must contain letters only (no numbers or symbols).");
        document.getElementById('last_name').focus();
        return;
    }
    if (mn !== '' && !nameRegex.test(mn)) {
        alert("Middle Name must contain letters only (no numbers or symbols).");
        document.getElementById('middle_name').focus();
        return;
    }
    if (phone.length !== 11) {
        alert("Phone number must be exactly 11 digits.");
        document.getElementById('phone').focus();
        return;
    }

    App.confirmAction('Are you sure you want to save changes to your resident information?', function() {
        document.getElementById('profileForm').submit();
    }, { title: 'Update Profile', heading: 'Save Profile Changes?', confirmText: 'Yes, Save Changes', confirmClass: 'btn-success', iconClass: 'bi bi-check-circle text-success fs-1' });
}

function realtimeSearchProfile(query) {
    const filter = query.toLowerCase().trim();
    const fields = document.querySelectorAll('.profile-field, .profile-section');
    fields.forEach(field => {
        const text = field.innerText.toLowerCase();
        const inputs = field.querySelectorAll('input, select, textarea');
        let inputValue = '';
        inputs.forEach(inp => { inputValue += ' ' + inp.value.toLowerCase(); });
        
        if (filter === '' || text.includes(filter) || inputValue.includes(filter)) {
            field.style.display = '';
        } else {
            field.style.display = 'none';
        }
    });
}

function submitAvatarForm() {
    const input = document.getElementById('profilePicInput');
    if (input.files && input.files[0]) {
        App.showLoading('Uploading passport photo...');
        document.getElementById('avatarForm').submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
