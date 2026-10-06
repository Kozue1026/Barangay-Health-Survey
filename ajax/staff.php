<?php
require_once '../config/session.php';
requireLogin();
requireAdmin();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $q = trim($_GET['q'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $rows = [];
            foreach (fb_all('admins') as $id => $a) {
                $a['id'] = $id;
                $rows[$id] = fb_public_admin($a);
                $rows[$id]['id'] = $id;
            }
            if ($q !== '') $rows = fb_search_rows($rows, $q, ['username', 'full_name', 'email']);
            list($pageRows, $total) = fb_sort_paginate($rows, 'id', 'DESC', $page, $limit);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => ['rows' => array_values($pageRows), 'total' => $total, 'total_pages' => ceil($total / $limit)]]);
            break;

        case 'get':
            $id = (string)($_GET['id'] ?? 0);
            $admin = fb_get_rec('admins', $id);
            if ($admin) {
                $admin = fb_public_admin($admin);
                // re-add safe fields
                $full = fb_get_rec('admins', $id);
                $admin['id'] = $id; $admin['username'] = $full['username'] ?? ''; $admin['full_name'] = $full['full_name'] ?? '';
                $admin['email'] = $full['email'] ?? ''; $admin['role'] = $full['role'] ?? ''; $admin['status'] = $full['status'] ?? '';
            }
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $admin]);
            break;

        case 'create':
            $username = trim(htmlspecialchars($_POST['username'] ?? ''));
            $full_name = trim(htmlspecialchars($_POST['full_name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $password_input = $_POST['password'] ?? '';
            $role = trim(htmlspecialchars($_POST['role'] ?? 'admin'));
            if (!empty($full_name) && !preg_match('/^[a-zA-Z\s\-\'\.]+$/', $full_name)) {
                echo json_encode(['success' => false, 'message' => 'Full name must contain letters only (no numbers or symbols).', 'data' => null]); break;
            }
            if (!empty($username) && !preg_match('/^[a-zA-Z0-9_\-]+$/', $username)) {
                echo json_encode(['success' => false, 'message' => 'Username must contain letters, numbers, hyphens, or underscores only.', 'data' => null]); break;
            }
            if (fb_find_one('admins', 'username', $username)) {
                echo json_encode(['success' => false, 'message' => 'Username already exists', 'data' => null]); break;
            }
            $password = password_hash($password_input, PASSWORD_DEFAULT);
            $nid = fb_next_id('admins');
            fb_set_rec('admins', $nid, ['username' => $username, 'password' => $password, 'full_name' => $full_name, 'email' => $email, 'role' => $role, 'status' => 'active', 'created_at' => fb_now(), 'updated_at' => fb_now()]);
            echo json_encode(['success' => true, 'message' => 'Admin created successfully', 'data' => null]);
            break;

        case 'update':
            $id = (string)$_POST['id'];
            $full_name = trim(htmlspecialchars($_POST['full_name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $role = trim(htmlspecialchars($_POST['role'] ?? 'admin'));
            $password_input = $_POST['password'] ?? '';
            if ($password_input !== '') {
                fb_update_rec('admins', $id, ['full_name' => $full_name, 'email' => $email, 'role' => $role, 'password' => password_hash($password_input, PASSWORD_DEFAULT)]);
            } else {
                fb_update_rec('admins', $id, ['full_name' => $full_name, 'email' => $email, 'role' => $role]);
            }
            echo json_encode(['success' => true, 'message' => 'Admin updated successfully', 'data' => null]);
            break;

        case 'delete':
            $id = (string)$_POST['id'];
            if ($id === (string)$_SESSION['user_id']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete yourself', 'data' => null]); break;
            }
            fb_delete_rec('admins', $id);
            echo json_encode(['success' => true, 'message' => 'Admin deleted successfully', 'data' => null]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action', 'data' => null]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
}
