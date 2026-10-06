<?php
require_once '../config/session.php';
requireLogin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            requireAdmin();
            $q = trim($_GET['q'] ?? '');
            $status = trim($_GET['status'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $sort_by = in_array($_GET['sort_by'] ?? '', ['id', 'resident_number', 'first_name', 'last_name', 'created_at']) ? $_GET['sort_by'] : 'created_at';
            $sort_order = strtoupper($_GET['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

            $rows = [];
            foreach (fb_all('residents') as $id => $r) {
                $st = $r['status'] ?? 'active';
                if ($status !== '' && in_array($status, ['active', 'inactive'])) {
                    if ($st !== $status) continue;
                } else {
                    if ($st === 'archived') continue;
                }
                $r['id'] = $id;
                $rows[$id] = $r;
            }
            if ($q !== '') {
                $rows = fb_search_rows($rows, $q, ['resident_number', 'first_name', 'last_name', 'middle_name', 'email', 'phone', 'address']);
            }
            list($pageRows, $total) = fb_sort_paginate($rows, $sort_by, $sort_order, $page, $limit);
            // project list columns (no password)
            $out = [];
            foreach ($pageRows as $r) {
                $out[] = [
                    'id' => $r['id'] ?? $r['_id'] ?? '', 'resident_number' => $r['resident_number'] ?? '',
                    'first_name' => $r['first_name'] ?? '', 'last_name' => $r['last_name'] ?? '',
                    'middle_name' => $r['middle_name'] ?? '', 'extension_name' => $r['extension_name'] ?? '',
                    'email' => $r['email'] ?? '', 'phone' => $r['phone'] ?? '', 'address' => $r['address'] ?? '',
                    'gender' => $r['gender'] ?? '', 'civil_status' => $r['civil_status'] ?? '',
                    'birthdate' => $r['birthdate'] ?? '', 'occupation' => $r['occupation'] ?? '',
                    'employer' => $r['employer'] ?? '', 'status' => $r['status'] ?? '',
                    'profile_picture' => $r['profile_picture'] ?? '', 'first_login' => $r['first_login'] ?? 0,
                    'created_at' => $r['created_at'] ?? '',
                ];
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['rows' => $out, 'total' => $total, 'total_pages' => ceil($total / $limit)]]);
            break;

        case 'get':
            requireAdmin();
            $id = (string)($_GET['id'] ?? 0);
            $resident = fb_get_rec('residents', $id);
            if ($resident) {
                unset($resident['password']);
                $ch = [];
                foreach (fb_all('resident_children') as $cid => $c) {
                    if ((string)($c['resident_id'] ?? '') === $id) { $c['id'] = $cid; $ch[] = $c; }
                }
                usort($ch, function ($a, $b) { return ((int)$a['id']) - ((int)$b['id']); });
                $resident['children'] = $ch;
                $resident['id'] = $id;
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $resident]);
            break;

        case 'create':
            requireAdmin();
            $resident_number = trim($_POST['resident_number'] ?? '');
            if (!$resident_number) $resident_number = fb_next_resident_number();
            $first_name = trim(htmlspecialchars($_POST['first_name'] ?? ''));
            $last_name = trim(htmlspecialchars($_POST['last_name'] ?? ''));
            $middle_name = trim(htmlspecialchars($_POST['middle_name'] ?? ''));
            $extension_name = trim(htmlspecialchars($_POST['extension_name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
            $address = trim(htmlspecialchars($_POST['address'] ?? ''));
            $gender = trim(htmlspecialchars($_POST['gender'] ?? 'Male'));
            $civil_status = trim(htmlspecialchars($_POST['civil_status'] ?? 'Single'));
            $birthdate = trim(htmlspecialchars($_POST['birthdate'] ?? ''));
            $status = trim(htmlspecialchars($_POST['status'] ?? 'active'));
            $occupation = trim(htmlspecialchars($_POST['occupation'] ?? ''));
            $employer = trim(htmlspecialchars($_POST['employer'] ?? ''));
            $employer_address = trim(htmlspecialchars($_POST['employer_address'] ?? ''));
            $spouse_name = trim(htmlspecialchars($_POST['spouse_name'] ?? ''));
            $spouse_occupation = trim(htmlspecialchars($_POST['spouse_occupation'] ?? ''));
            $spouse_employer = trim(htmlspecialchars($_POST['spouse_employer'] ?? ''));
            $father_name = trim(htmlspecialchars($_POST['father_name'] ?? ''));
            $mother_name = trim(htmlspecialchars($_POST['mother_name'] ?? ''));
            $reference1_name = trim(htmlspecialchars($_POST['reference1_name'] ?? ''));
            $reference1_contact = trim(htmlspecialchars($_POST['reference1_contact'] ?? ''));
            $reference2_name = trim(htmlspecialchars($_POST['reference2_name'] ?? ''));
            $reference2_contact = trim(htmlspecialchars($_POST['reference2_contact'] ?? ''));
            $signature = trim(htmlspecialchars($_POST['signature'] ?? ''));

            $nameRegex = '/^[\p{L}\s\-\'\.]+$/u';
            if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
                echo json_encode(['success' => false, 'message' => 'First name is required and must contain letters only.', 'data' => null]); break;
            }
            if (empty($last_name) || !preg_match($nameRegex, $last_name)) {
                echo json_encode(['success' => false, 'message' => 'Last name is required and must contain letters only.', 'data' => null]); break;
            }
            if (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
                echo json_encode(['success' => false, 'message' => 'Middle name must contain letters only.', 'data' => null]); break;
            }
            if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits (e.g. 09171234567).', 'data' => null]); break;
            }
            if (!empty($birthdate) && strtotime($birthdate) > time()) {
                echo json_encode(['success' => false, 'message' => 'Birthdate cannot be in the future.', 'data' => null]); break;
            }
            if (fb_find_one('residents', 'resident_number', $resident_number)) {
                echo json_encode(['success' => false, 'message' => 'Resident number already exists', 'data' => null]); break;
            }
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
            $new_resident_id = fb_next_id('residents');
            fb_set_rec('residents', $new_resident_id, [
                'resident_number' => $resident_number, 'password' => $password,
                'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name,
                'extension_name' => $extension_name, 'email' => $email, 'phone' => $phone, 'address' => $address,
                'gender' => $gender, 'civil_status' => $civil_status, 'birthdate' => $birthdate ?: null,
                'occupation' => $occupation, 'employer' => $employer, 'employer_address' => $employer_address,
                'spouse_name' => $spouse_name, 'spouse_occupation' => $spouse_occupation, 'spouse_employer' => $spouse_employer,
                'father_name' => $father_name, 'mother_name' => $mother_name,
                'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact,
                'signature' => $signature, 'profile_picture' => $profile_picture, 'status' => $status,
                'first_login' => 1, 'security_question' => null, 'security_answer' => null,
                'created_at' => fb_now(), 'updated_at' => fb_now(),
            ]);
            $rawChildren = $_POST['children'] ?? $_POST['child_name'] ?? [];
            if (is_string($rawChildren)) $rawChildren = json_decode($rawChildren, true) ?? [];
            if (!empty($rawChildren) && is_array($rawChildren)) {
                foreach ($rawChildren as $k => $c) {
                    $cName = is_array($c) ? trim($c['child_name'] ?? $c['name'] ?? '') : trim($c);
                    $cAge = is_array($c) ? ($c['age'] ?? null) : ($_POST['child_age'][$k] ?? null);
                    if ($cName !== '') {
                        $cid = fb_next_id('resident_children');
                        fb_set_rec('resident_children', $cid, ['resident_id' => $new_resident_id, 'child_name' => $cName, 'age' => ($cAge !== null && $cAge !== '' ? (int)$cAge : null), 'created_at' => fb_now()]);
                    }
                }
            }
            initializeResidentNotifications($new_resident_id, $first_name);
            fb_log($_SESSION['user_id'], 'admin', 'create_resident', "Created resident $resident_number");
            echo json_encode(['success' => true, 'message' => 'Resident created successfully', 'data' => ['id' => $new_resident_id, 'resident_number' => $resident_number]]);
            break;

        case 'update':
            requireAdmin();
            $id = (string)$_POST['id'];
            $first_name = trim(htmlspecialchars($_POST['first_name'] ?? ''));
            $last_name = trim(htmlspecialchars($_POST['last_name'] ?? ''));
            $middle_name = trim(htmlspecialchars($_POST['middle_name'] ?? ''));
            $extension_name = trim(htmlspecialchars($_POST['extension_name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
            $address = trim(htmlspecialchars($_POST['address'] ?? ''));
            $gender = trim(htmlspecialchars($_POST['gender'] ?? 'Male'));
            $civil_status = trim(htmlspecialchars($_POST['civil_status'] ?? 'Single'));
            $birthdate = trim(htmlspecialchars($_POST['birthdate'] ?? ''));
            $status = trim(htmlspecialchars($_POST['status'] ?? 'active'));
            $occupation = trim(htmlspecialchars($_POST['occupation'] ?? ''));
            $employer = trim(htmlspecialchars($_POST['employer'] ?? ''));
            $employer_address = trim(htmlspecialchars($_POST['employer_address'] ?? ''));
            $spouse_name = trim(htmlspecialchars($_POST['spouse_name'] ?? ''));
            $spouse_occupation = trim(htmlspecialchars($_POST['spouse_occupation'] ?? ''));
            $spouse_employer = trim(htmlspecialchars($_POST['spouse_employer'] ?? ''));
            $father_name = trim(htmlspecialchars($_POST['father_name'] ?? ''));
            $mother_name = trim(htmlspecialchars($_POST['mother_name'] ?? ''));
            $reference1_name = trim(htmlspecialchars($_POST['reference1_name'] ?? ''));
            $reference1_contact = trim(htmlspecialchars($_POST['reference1_contact'] ?? ''));
            $reference2_name = trim(htmlspecialchars($_POST['reference2_name'] ?? ''));
            $reference2_contact = trim(htmlspecialchars($_POST['reference2_contact'] ?? ''));
            $signature = trim(htmlspecialchars($_POST['signature'] ?? ''));
            $nameRegex = '/^[\p{L}\s\-\'\.]+$/u';
            if (empty($first_name) || !preg_match($nameRegex, $first_name)) {
                echo json_encode(['success' => false, 'message' => 'First name is required and must contain letters only.', 'data' => null]); break;
            }
            if (empty($last_name) || !preg_match($nameRegex, $last_name)) {
                echo json_encode(['success' => false, 'message' => 'Last name is required and must contain letters only.', 'data' => null]); break;
            }
            if (!empty($middle_name) && !preg_match($nameRegex, $middle_name)) {
                echo json_encode(['success' => false, 'message' => 'Middle name must contain letters only.', 'data' => null]); break;
            }
            if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits (e.g. 09171234567).', 'data' => null]); break;
            }
            if (!empty($birthdate) && strtotime($birthdate) > time()) {
                echo json_encode(['success' => false, 'message' => 'Birthdate cannot be in the future.', 'data' => null]); break;
            }
            $oldResident = fb_get_rec('residents', $id);
            if (!$oldResident) { echo json_encode(['success' => false, 'message' => 'Resident not found.', 'data' => null]); break; }
            $profile_picture = $oldResident['profile_picture'] ?? null;
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
                        if (!empty($profile_picture) && file_exists($uploadFileDir . $profile_picture)) @unlink($uploadFileDir . $profile_picture);
                        $profile_picture = $newPicName;
                    }
                }
            }
            fb_update_rec('residents', $id, [
                'first_name' => $first_name, 'last_name' => $last_name, 'middle_name' => $middle_name,
                'extension_name' => $extension_name, 'email' => $email, 'phone' => $phone, 'address' => $address,
                'gender' => $gender, 'civil_status' => $civil_status, 'birthdate' => $birthdate ?: null,
                'status' => $status, 'occupation' => $occupation, 'employer' => $employer, 'employer_address' => $employer_address,
                'spouse_name' => $spouse_name, 'spouse_occupation' => $spouse_occupation, 'spouse_employer' => $spouse_employer,
                'father_name' => $father_name, 'mother_name' => $mother_name,
                'reference1_name' => $reference1_name, 'reference1_contact' => $reference1_contact,
                'reference2_name' => $reference2_name, 'reference2_contact' => $reference2_contact,
                'signature' => $signature, 'profile_picture' => $profile_picture, 'updated_at' => fb_now(),
            ]);
            if (isset($_POST['child_name']) || isset($_POST['children'])) {
                foreach (fb_all('resident_children') as $cid => $c) {
                    if ((string)($c['resident_id'] ?? '') === $id) fb_delete_rec('resident_children', $cid);
                }
                $rawChildren = $_POST['children'] ?? $_POST['child_name'] ?? [];
                if (is_string($rawChildren)) $rawChildren = json_decode($rawChildren, true) ?? [];
                if (!empty($rawChildren) && is_array($rawChildren)) {
                    foreach ($rawChildren as $k => $c) {
                        $cName = is_array($c) ? trim($c['child_name'] ?? $c['name'] ?? '') : trim($c);
                        $cAge = is_array($c) ? ($c['age'] ?? null) : ($_POST['child_age'][$k] ?? null);
                        if ($cName !== '') {
                            $cid = fb_next_id('resident_children');
                            fb_set_rec('resident_children', $cid, ['resident_id' => $id, 'child_name' => $cName, 'age' => ($cAge !== null && $cAge !== '' ? (int)$cAge : null), 'created_at' => fb_now()]);
                        }
                    }
                }
            }
            fb_log($_SESSION['user_id'], 'admin', 'update_resident', "Updated resident ID $id");
            echo json_encode(['success' => true, 'message' => 'Resident updated successfully', 'data' => null]);
            break;

        case 'archive':
            requireAdmin();
            $id = (string)$_POST['id'];
            $res = fb_get_rec('residents', $id);
            if ($res) {
                fb_update_rec('residents', $id, ['status' => 'archived', 'updated_at' => fb_now()]);
                fb_log($_SESSION['user_id'], 'admin', 'archive_resident', "Archived resident: " . ($res['resident_number'] ?? $id));
                echo json_encode(['success' => true, 'message' => 'Resident archived successfully', 'data' => null]);
            } else echo json_encode(['success' => false, 'message' => 'Resident not found', 'data' => null]);
            break;

        case 'restore':
            requireAdmin();
            $id = (string)$_POST['id'];
            $res = fb_get_rec('residents', $id);
            if ($res) {
                fb_update_rec('residents', $id, ['status' => 'active', 'updated_at' => fb_now()]);
                fb_log($_SESSION['user_id'], 'admin', 'restore_resident', "Restored resident: " . ($res['resident_number'] ?? $id));
                echo json_encode(['success' => true, 'message' => 'Resident restored successfully', 'data' => null]);
            } else echo json_encode(['success' => false, 'message' => 'Resident not found', 'data' => null]);
            break;

        case 'delete':
        case 'permanent_delete':
            requireAdmin();
            $id = (string)$_POST['id'];
            $res = fb_get_rec('residents', $id);
            if ($res && !empty($res['profile_picture'])) {
                $picPath = '../uploads/profile_pics/' . $res['profile_picture'];
                if (file_exists($picPath)) @unlink($picPath);
            }
            foreach (fb_all('resident_children') as $cid => $c) { if ((string)($c['resident_id'] ?? '') === $id) fb_delete_rec('resident_children', $cid); }
            // cascade: responses + their answers
            foreach (fb_all('responses') as $rid => $r) {
                if ((string)($r['resident_id'] ?? '') === $id) {
                    foreach (fb_all('response_answers') as $aid => $a) { if ((string)($a['response_id'] ?? '') === (string)$rid) fb_delete_rec('response_answers', $aid); }
                    fb_delete_rec('responses', $rid);
                }
            }
            foreach (fb_all('notifications') as $nid => $n) { if ((string)($n['user_id'] ?? '') === $id && ($n['user_type'] ?? '') === 'resident') fb_delete_rec('notifications', $nid); }
            foreach (fb_all('activity_logs') as $lid => $l) { if ((string)($l['user_id'] ?? '') === $id && ($l['user_type'] ?? '') === 'resident') fb_delete_rec('activity_logs', $lid); }
            foreach (fb_all('login_history') as $hid => $h) { if ((string)($h['user_id'] ?? '') === $id && ($h['user_type'] ?? '') === 'resident') fb_delete_rec('login_history', $hid); }
            fb_delete_rec('residents', $id);
            fb_log($_SESSION['user_id'], 'admin', 'permanent_delete_resident', "Permanently deleted resident: " . ($res['resident_number'] ?? "ID $id"));
            echo json_encode(['success' => true, 'message' => 'Resident permanently deleted successfully', 'data' => null]);
            break;

        case 'toggle_status':
            requireAdmin();
            $id = (string)$_POST['id'];
            $res = fb_get_rec('residents', $id);
            if ($res) {
                $new = (($res['status'] ?? 'active') === 'active') ? 'inactive' : 'active';
                fb_update_rec('residents', $id, ['status' => $new]);
            }
            echo json_encode(['success' => true, 'message' => 'Status toggled successfully', 'data' => null]);
            break;

        case 'reset_password':
            requireAdmin();
            $id = (string)$_POST['id'];
            $res = fb_get_rec('residents', $id);
            if ($res) {
                fb_update_rec('residents', $id, ['password' => password_hash($res['resident_number'], PASSWORD_DEFAULT), 'first_login' => 1, 'updated_at' => fb_now()]);
                fb_log($_SESSION['user_id'], 'admin', 'reset_resident_password', "Reset password for resident " . $res['resident_number'] . " to default");
                echo json_encode(['success' => true, 'message' => 'Resident password has been reset successfully.', 'data' => null]);
            } else echo json_encode(['success' => false, 'message' => 'Resident record not found.', 'data' => null]);
            break;

        case 'get_next_number':
            requireAdmin();
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['resident_number' => fb_next_resident_number()]]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
