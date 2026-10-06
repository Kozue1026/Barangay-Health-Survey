<?php
require_once __DIR__ . '/_common.php';
echo json_encode(['ok' => true, 'service' => 'bhc-resident-api', 'time' => fb_now()]);
