<?php
// Migrate MySQL survey_system -> Firebase RTDB, preserving IDs.
// Run: C:\xampp\php\php.exe tools\migrate_mysql_to_firebase.php [--dry-run]
// Requires: config/firebase.php + config/database.php (MySQL source), config/firebase_credentials.json
require_once __DIR__ . '/../config/firebase.php';

$dryRun = in_array('--dry-run', $argv ?? []);
$errors = [];
$stats = [];

function mysql_pdo() {
    $host = 'localhost'; $dbname = 'survey_system'; $username = 'root'; $password = '';
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

try {
    $pdo = mysql_pdo();
    echo "MySQL connected.\n";
} catch (Exception $e) {
    echo "MySQL connection failed: " . $e->getMessage() . "\n";
    echo "If MySQL is not running in OpenCode, run this script locally via XAMPP shell.\n";
    exit(1);
}

$tables = ['admins','residents','resident_children','surveys','survey_questions','survey_choices','responses','response_answers','notifications','activity_logs','login_history'];

foreach ($tables as $t) {
    try {
        $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll();
    } catch (Exception $e) {
        echo "SKIP $t: " . $e->getMessage() . "\n";
        $errors[] = "$t read: " . $e->getMessage();
        continue;
    }
    $count = 0; $skipped = 0;
    $existing = $dryRun ? [] : fb_all($t);
    $maxId = 0;
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        $maxId = max($maxId, (int)$id);
        // Normalize: convert int-like is_read/required/first_login to int, keep timestamps as-is
        foreach (['is_read','required','first_login'] as $b) {
            if (array_key_exists($b, $row)) $row[$b] = (int)$row[$b];
        }
        foreach (['age','choice_order','question_order','rating_value','choice_id','question_id','response_id','survey_id','resident_id','user_id','related_id','created_by'] as $n) {
            if (array_key_exists($n, $row) && $row[$n] !== null && $row[$n] !== '') {
                // keep as-is; Firebase stores numbers fine, but keep IDs as int-compatible
            }
        }
        if (!$dryRun) {
            if (isset($existing[$id])) {
                // duplicate check: skip if identical, else overwrite with warning
                if ($existing[$id] == $row) { $skipped++; continue; }
                echo "WARN $t/$id exists with different data - overwriting.\n";
            }
            try {
                fb_set_rec($t, $id, $row);
                $count++;
            } catch (Exception $e) {
                $errors[] = "$t/$id write: " . $e->getMessage();
            }
        } else {
            $count++;
        }
    }
    // Set counter
    if (!$dryRun) {
        try { fb_set('/_counters/' . $t, ['next_id' => $maxId + 1]); } catch (Exception $e) { $errors[] = "counter $t: " . $e->getMessage(); }
    }
    $stats[$t] = ['migrated' => $count, 'skipped_identical' => $skipped, 'max_id' => $maxId];
    echo "$t: migrated=$count skipped=$skipped max_id=$maxId\n";
}

// Indexes for fast login + meta
if (!$dryRun) {
    try {
        $idxR = []; foreach (fb_all('residents') as $id => $r) { if (!empty($r['resident_number'])) $idxR[$r['resident_number']] = (string)$id; }
        if ($idxR) fb_set('/_indexes/resident_number_to_id', $idxR);
        $idxA = []; foreach (fb_all('admins') as $id => $a) { if (!empty($a['username'])) $idxA[$a['username']] = (string)$id; }
        if ($idxA) fb_set('/_indexes/admin_username_to_id', $idxA);
        fb_set('/_meta', ['migrated_at' => fb_now(), 'source' => 'mysql:survey_system', 'by' => 'migrate_mysql_to_firebase.php']);
        echo "Indexes + _meta written.\n";
    } catch (Exception $e) { $errors[] = "indexes: " . $e->getMessage(); }

    // Verify
    echo "--- VERIFY ---\n";
    foreach ($tables as $t) {
        try {
            $mysqlCount = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            $fbCount = count(fb_all($t));
            $ok = $mysqlCount === $fbCount ? 'OK' : 'MISMATCH';
            echo "$t mysql=$mysqlCount firebase=$fbCount $ok\n";
        } catch (Exception $e) { echo "$t verify failed: " . $e->getMessage() . "\n"; }
    }
}

if ($errors) { echo "ERRORS:\n - " . implode("\n - ", $errors) . "\n"; exit(2); }
echo $dryRun ? "DRY RUN complete.\n" : "MIGRATION complete. MySQL left untouched.\n";
