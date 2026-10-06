<?php
$pageTitle = "Reports";
require_once '../config/session.php';
requireAdmin();

// Determine report type: 'resident' (default) or 'survey'
$report_type = $_GET['type'] ?? 'resident';

// ==========================================
// 1. RESIDENT REPORT LOGIC (Firebase)
// ==========================================
$search_query  = trim($_GET['search'] ?? '');
$civil_status  = trim($_GET['civil_status'] ?? '');
$gender_filter = trim($_GET['gender'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$allResidents = fb_all('residents');
$allChildren = fb_all('resident_children');
$childCountMap = [];
foreach ($allChildren as $ch) {
    if (!is_array($ch) || !isset($ch['resident_id'])) continue;
    $rid = (string)$ch['resident_id'];
    $childCountMap[$rid] = ($childCountMap[$rid] ?? 0) + 1;
}

$reportResidents = [];
$sq = strtolower($search_query);
foreach ($allResidents as $rid => $r) {
    if (!is_array($r)) continue;
    if (($r['status'] ?? '') === 'archived') continue;
    if ($civil_status !== '' && ($r['civil_status'] ?? '') !== $civil_status) continue;
    if ($gender_filter !== '' && ($r['gender'] ?? '') !== $gender_filter) continue;
    if ($status_filter !== '' && ($r['status'] ?? '') !== $status_filter) continue;
    if ($sq !== '') {
        $hay = strtolower(implode(' ', [
            $r['resident_number'] ?? '', $r['first_name'] ?? '', $r['last_name'] ?? '',
            $r['middle_name'] ?? '', $r['phone'] ?? '', $r['address'] ?? '', $r['occupation'] ?? '',
        ]));
        if (strpos($hay, $sq) === false) continue;
    }
    $r['id'] = (string)$rid;
    $r['children_count'] = $childCountMap[(string)$rid] ?? 0;
    $reportResidents[] = $r;
}
usort($reportResidents, function ($a, $b) {
    return strcmp((string)($a['resident_number'] ?? ''), (string)($b['resident_number'] ?? ''));
});

// Calculate resident report metrics
$totalFilteredResidents = count($reportResidents);
$activeCount = 0;
$maleCount = 0;
$femaleCount = 0;

foreach ($reportResidents as $resRow) {
    if ($resRow['status'] === 'active') $activeCount++;
    if (strtolower($resRow['gender'] ?? '') === 'male') $maleCount++;
    if (strtolower($resRow['gender'] ?? '') === 'female') $femaleCount++;
}

// Build active filter description
$activeFilters = [];
if (!empty($search_query)) $activeFilters[] = "Keyword: \"$search_query\"";
if (!empty($civil_status)) $activeFilters[] = "Civil Status: $civil_status";
if (!empty($gender_filter)) $activeFilters[] = "Gender: $gender_filter";
if (!empty($status_filter)) $activeFilters[] = "Status: " . ucfirst($status_filter);
$filterSummaryText = !empty($activeFilters) ? implode(' | ', $activeFilters) : 'All Records (No Filters Applied)';

// ==========================================
// 2. SURVEY REPORT LOGIC (Firebase)
// ==========================================
$allSurveys = [];
foreach (fb_all('surveys') as $sid => $s) {
    if (!is_array($s)) continue;
    $allSurveys[] = ['id' => (string)$sid, 'title' => $s['title'] ?? '', 'created_at' => $s['created_at'] ?? ''];
}
usort($allSurveys, function ($a, $b) { return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')); });

$survey_id = $_GET['survey_id'] ?? null;
$survey = null;
$stats = [];
$questions = [];

if ($report_type === 'survey' && $survey_id) {
    $survey = fb_get_rec('surveys', (string)$survey_id);

    if ($survey) {
        $survey['id'] = (string)$survey_id;
        $totalRespondents = fb_survey_respondent_count((string)$survey_id);

        $totalActiveResidents = 0;
        foreach (fb_all('residents') as $r) {
            if (is_array($r) && ($r['status'] ?? '') === 'active') $totalActiveResidents++;
        }
        $completionRate = $totalActiveResidents > 0 ? round(($totalRespondents / $totalActiveResidents) * 100, 2) : 0;

        $totalQuestions = 0;
        foreach (fb_all('survey_questions') as $q) {
            if (is_array($q) && (string)($q['survey_id'] ?? '') === (string)$survey_id) $totalQuestions++;
        }

        $stats = [
            'total_respondents' => $totalRespondents,
            'completion_rate' => $completionRate,
            'total_questions' => $totalQuestions
        ];

        // Fetch questions and results via shared helper, reshaped to legacy template format
        list($_qs, $_qres) = fb_question_results((string)$survey_id);
        foreach ($_qs as $qi => $q) {
            $qd = $_qres[$qi];
            $row = $q;
            $row['id'] = (string)($q['_id'] ?? '');
            if ($q['question_type'] === 'multiple_choice') {
                $row['results'] = array_map(function ($d) {
                    return ['choice_text' => $d['choice_text'] ?? '', 'votes' => (int)($d['count'] ?? 0)];
                }, $qd['data']);
            } elseif ($q['question_type'] === 'yes_no') {
                $yes = 0; $no = 0;
                foreach ($qd['data'] as $d) {
                    if (($d['label'] ?? '') === 'Yes') $yes = (int)$d['count'];
                    if (($d['label'] ?? '') === 'No') $no = (int)$d['count'];
                }
                $row['results'] = ['Yes' => $yes, 'No' => $no];
            } elseif ($q['question_type'] === 'rating') {
                $dist = [];
                foreach ($qd['data'] as $d) {
                    $dist[] = ['rating_value' => $d['label'] ?? '', 'count' => (int)$d['count']];
                }
                $row['results'] = ['distribution' => $dist, 'average' => round($qd['average'] ?? 0, 2)];
            } elseif ($q['question_type'] === 'short_answer') {
                $row['results'] = $qd['data'];
            } else {
                $row['results'] = $qd['data'];
            }
            $questions[] = $row;
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<!-- Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>

<div class="main-content">
    <div class="content-wrapper p-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <h2 class="mb-1 fw-bold"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Reports & Analytics</h2>
                <p class="text-muted mb-0">Generate, filter, and print resident masterlists and survey reports</p>
            </div>
            <!-- Report Type Switcher Tabs -->
            <ul class="nav nav-pills bg-light p-1 rounded-pill border">
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-medium <?= $report_type === 'resident' ? 'active' : 'text-dark' ?>" href="reports.php?type=resident">
                        <i class="bi bi-people me-1"></i> Resident Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link rounded-pill px-3 py-1 fw-medium <?= $report_type === 'survey' ? 'active' : 'text-dark' ?>" href="reports.php?type=survey">
                        <i class="bi bi-clipboard2-data me-1"></i> Survey Reports
                    </a>
                </li>
            </ul>
        </div>

        <?php if ($report_type === 'resident'): ?>
            <!-- RESIDENT REPORT SECTION -->

            <!-- Search & Filter Card (Hidden on print) -->
            <div class="card shadow-sm border-0 mb-4 d-print-none">
                <div class="card-body p-4">
                    <form method="GET" action="reports.php" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="resident">
                        
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">Search Resident</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="Resident #, Name, Phone, Address..." value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted text-uppercase">Civil Status</label>
                            <select name="civil_status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Single" <?= $civil_status === 'Single' ? 'selected' : '' ?>>Single</option>
                                <option value="Married" <?= $civil_status === 'Married' ? 'selected' : '' ?>>Married</option>
                                <option value="Widowed" <?= $civil_status === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                <option value="Separated" <?= $civil_status === 'Separated' ? 'selected' : '' ?>>Separated</option>
                                <option value="Divorced" <?= $civil_status === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted text-uppercase">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">All Genders</option>
                                <option value="Male" <?= $gender_filter === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $gender_filter === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Prefer not to say" <?= in_array($gender_filter, ['Prefer not to say', 'Other']) ? 'selected' : '' ?>>Prefer not to say</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-muted text-uppercase">Account Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 shadow-sm">
                                <i class="bi bi-funnel-fill me-1"></i> Filter
                            </button>
                            <a href="reports.php?type=resident" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Action Toolbar (Hidden on print) -->
            <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
                <div class="text-muted small">
                    Showing <strong><?= number_format($totalFilteredResidents) ?></strong> records
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary shadow-sm" onclick="window.print()">
                        <i class="bi bi-printer-fill me-1"></i> Print Report
                    </button>
                    <button type="button" class="btn btn-success shadow-sm" onclick="exportResidentExcel()">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel
                    </button>
                </div>
            </div>

            <!-- Printable Resident Report Document -->
            <div class="report-content bg-white p-4 p-md-5 border shadow-sm rounded" id="residentReportContent">
                
                <!-- Report Official Header -->
                <div class="text-center mb-4 border-bottom pb-4">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <i class="bi bi-heart-pulse-fill text-primary me-2" style="font-size: 2.25rem;"></i>
                        <div class="text-start">
                            <h6 class="text-muted text-uppercase mb-0 small fw-bold" style="letter-spacing: 1px;">Republic of the Philippines</h6>
                            <h4 class="fw-bold mb-0 text-primary">Barangay Health Center</h4>
                        </div>
                    </div>
                    <h3 class="fw-bold mt-3 mb-1">Resident Masterlist & Demographic Report</h3>
                    <p class="text-muted mb-2 small">Comprehensive registry of barangay residents and personal profiles</p>
                    
                    <div class="bg-light py-2 px-3 rounded d-inline-block border small text-muted mt-2">
                        <span class="me-3"><i class="bi bi-calendar-event me-1"></i>Generated: <strong><?= date('F j, Y, g:i A') ?></strong></span>
                        <span class="me-3"><i class="bi bi-person-check me-1"></i>Prepared by: <strong><?= htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'Staff')) ?></strong></span>
                        <span><i class="bi bi-filter me-1"></i>Filter: <strong><?= htmlspecialchars($filterSummaryText) ?></strong></span>
                    </div>
                </div>

                <!-- Report Summary Stats Strip -->
                <div class="row g-3 mb-4 text-center report-summary-strip">
                    <div class="col-md-3 col-6 report-summary-col">
                        <div class="p-3 bg-light border rounded report-summary-card">
                            <div class="text-muted small fw-bold text-uppercase">Total Records</div>
                            <h4 class="fw-bold text-primary mb-0"><?= number_format($totalFilteredResidents) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 report-summary-col">
                        <div class="p-3 bg-light border rounded report-summary-card">
                            <div class="text-muted small fw-bold text-uppercase">Active Residents</div>
                            <h4 class="fw-bold text-success mb-0"><?= number_format($activeCount) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 report-summary-col">
                        <div class="p-3 bg-light border rounded report-summary-card">
                            <div class="text-muted small fw-bold text-uppercase">Male Residents</div>
                            <h4 class="fw-bold text-info mb-0"><?= number_format($maleCount) ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 report-summary-col">
                        <div class="p-3 bg-light border rounded report-summary-card">
                            <div class="text-muted small fw-bold text-uppercase">Female Residents</div>
                            <h4 class="fw-bold text-danger mb-0"><?= number_format($femaleCount) ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Resident Records Table -->
                <?php if (empty($reportResidents)): ?>
                    <div class="alert alert-warning text-center py-4 my-3">
                        <i class="bi bi-search fs-3 d-block mb-2 text-warning"></i>
                        <h6 class="fw-bold mb-1">No resident records found.</h6>
                        <small class="text-muted">Try adjusting your search keyword or clearing the filter options.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" id="residentReportTable" style="font-size: 12px; width: 100%;">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 3%;" class="text-center">#</th>
                                    <th style="width: 10%;">Resident No.</th>
                                    <th style="width: 15%;">Full Name</th>
                                    <th style="width: 8%;">Civil Status</th>
                                    <th style="width: 8%;">Gender</th>
                                    <th style="width: 10%;">Birthdate / Age</th>
                                    <th style="width: 11%;">Contact No.</th>
                                    <th style="width: 18%;">Address</th>
                                    <th style="width: 10%;">Occupation</th>
                                    <th style="width: 7%;" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportResidents as $idx => $res): ?>
                                    <?php 
                                        $rFullName = trim(($res['first_name'] ?? '') . ' ' . ($res['middle_name'] ?? '') . ' ' . ($res['last_name'] ?? '') . ' ' . ($res['extension_name'] ?? ''));
                                        $ageText = 'N/A';
                                        if (!empty($res['birthdate'])) {
                                            $bDate = new DateTime($res['birthdate']);
                                            $today = new DateTime('today');
                                            $ageText = $bDate->diff($today)->y . ' yrs';
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted"><?= $idx + 1 ?></td>
                                        <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($res['resident_number']) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($rFullName) ?></td>
                                        <td><?= htmlspecialchars($res['civil_status'] ?: 'Single') ?></td>
                                        <td><?= htmlspecialchars($res['gender'] ?: 'N/A') ?></td>
                                        <td>
                                            <?= !empty($res['birthdate']) ? date('M d, Y', strtotime($res['birthdate'])) : 'N/A' ?>
                                            <small class="text-muted d-block">(<?= $ageText ?>)</small>
                                        </td>
                                        <td><?= htmlspecialchars($res['phone'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($res['address'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($res['occupation'] ?: 'N/A') ?></td>
                                        <td class="text-center">
                                            <?php if ($res['status'] === 'active'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sign-off block on print -->
                    <div class="row mt-5 pt-4 d-flex justify-content-between align-items-end report-signoff">
                        <div class="col-6">
                            <small class="text-muted d-block">Barangay Health Center Management System</small>
                            <small class="text-muted d-block">Confidential Official Record</small>
                        </div>
                        <div class="col-5 text-center">
                            <div style="border-top: 1px solid #000; padding-top: 5px; width: 220px; margin: 0 auto;">
                                <strong class="d-block"><?= htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'Staff')) ?></strong>
                                <small class="text-muted">Authorized Barangay Staff</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- SURVEY REPORT SECTION (PRESERVED) -->
            <div class="card mb-4 d-print-none shadow-sm border-0">
                <div class="card-body p-4">
                    <form method="GET" class="d-flex align-items-center">
                        <input type="hidden" name="type" value="survey">
                        <label class="me-3 fw-bold">Select Survey:</label>
                        <select name="survey_id" class="form-select me-2" style="max-width: 400px;" onchange="this.form.submit()">
                            <option value="">-- Choose a survey --</option>
                            <?php foreach ($allSurveys as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $survey_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if (!$survey_id): ?>
                <div class="alert alert-info d-print-none">Select a survey to generate a report.</div>
            <?php elseif (!$survey): ?>
                <div class="alert alert-danger d-print-none">Survey not found.</div>
            <?php else: ?>
                
                <div class="d-flex justify-content-end mb-3 gap-2 d-print-none">
                    <button onclick="window.print()" class="btn btn-secondary shadow-sm"><i class="bi bi-printer me-2"></i>Print</button>
                    <button onclick="exportPDF()" class="btn btn-danger shadow-sm"><i class="bi bi-file-earmark-pdf me-2"></i>Export PDF</button>
                    <button onclick="exportExcel()" class="btn btn-success shadow-sm"><i class="bi bi-file-earmark-excel me-2"></i>Export Excel</button>
                </div>

                <!-- Report Content Wrapper -->
                <div class="report-content bg-white p-5 border shadow-sm rounded" id="reportContent">
                    
                    <!-- Report Header -->
                    <div class="text-center mb-5 border-bottom pb-4">
                        <div class="mb-3">
                            <i class="bi bi-hospital text-primary" style="font-size: 3rem;"></i>
                        </div>
                        <h2>Barangay Health Center</h2>
                        <h4 class="text-muted">Survey Report</h4>
                        <h1 class="mt-4 mb-2"><?= htmlspecialchars($survey['title']) ?></h1>
                        <p class="lead"><?= htmlspecialchars($survey['description']) ?></p>
                        <div class="mt-3 text-muted">
                            <p class="mb-1">Date Generated: <?= date('F j, Y, g:i A') ?></p>
                            <p class="mb-0">Prepared by: <strong><?= htmlspecialchars(!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'Administrator')) ?></strong></p>
                        </div>
                    </div>

                    <!-- Survey Details -->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <h5>Survey Details</h5>
                            <table class="table table-sm table-borderless">
                                <tr><th>Category:</th><td><?= htmlspecialchars($survey['category']) ?></td></tr>
                                <tr><th>Status:</th><td><?= ucfirst(htmlspecialchars($survey['status'])) ?></td></tr>
                                <tr><th>Opening Date:</th><td><?= $survey['opening_date'] ?></td></tr>
                                <tr><th>Closing Date:</th><td><?= $survey['closing_date'] ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Summary Statistics</h5>
                            <table class="table table-sm table-borderless">
                                <tr><th>Total Respondents:</th><td><?= $stats['total_respondents'] ?></td></tr>
                                <tr><th>Total Questions:</th><td><?= $stats['total_questions'] ?></td></tr>
                                <tr><th>Completion Rate:</th><td><?= $stats['completion_rate'] ?>%</td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Results -->
                    <h4 class="mb-4 border-bottom pb-2">Detailed Results</h4>
                    
                    <div id="tablesForExcel">
                        <?php foreach ($questions as $index => $q): ?>
                            <div class="mb-5 question-block">
                                <h5 class="mb-3">
                                    <?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?>
                                    <span class="badge bg-light text-dark border ms-2" style="font-size: 0.8rem;">
                                        <?= str_replace('_', ' ', ucwords($q['question_type'], '_')) ?>
                                    </span>
                                </h5>

                                <?php if ($q['question_type'] === 'multiple_choice'): ?>
                                    <table class="table table-bordered excel-table" data-sheet="Q<?= $index + 1 ?>">
                                        <thead class="table-light"><tr><th>Choice</th><th>Votes</th><th>Percentage</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $total = array_sum(array_column($q['results'], 'votes'));
                                            foreach ($q['results'] as $res): 
                                                $pct = $total > 0 ? round(($res['votes'] / $total) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($res['choice_text']) ?></td>
                                                <td><?= $res['votes'] ?></td>
                                                <td><?= $pct ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($q['question_type'] === 'yes_no'): ?>
                                    <table class="table table-bordered excel-table" data-sheet="Q<?= $index + 1 ?>">
                                        <thead class="table-light"><tr><th>Answer</th><th>Count</th><th>Percentage</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $yes = $q['results']['Yes'];
                                            $no = $q['results']['No'];
                                            $total = $yes + $no;
                                            $yesPct = $total > 0 ? round(($yes / $total) * 100, 1) : 0;
                                            $noPct = $total > 0 ? round(($no / $total) * 100, 1) : 0;
                                            ?>
                                            <tr><td>Yes</td><td><?= $yes ?></td><td><?= $yesPct ?>%</td></tr>
                                            <tr><td>No</td><td><?= $no ?></td><td><?= $noPct ?>%</td></tr>
                                        </tbody>
                                    </table>

                                <?php elseif ($q['question_type'] === 'rating'): ?>
                                    <p class="fw-bold">Average Rating: <?= $q['results']['average'] ?: '0' ?> / 5</p>
                                    <table class="table table-bordered excel-table" data-sheet="Q<?= $index + 1 ?>">
                                        <thead class="table-light"><tr><th>Rating</th><th>Count</th><th>Percentage</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $totalRatings = array_sum(array_column($q['results']['distribution'], 'count'));
                                            $dist = array_column($q['results']['distribution'], 'count', 'rating_value');
                                            for($i=5; $i>=1; $i--): 
                                                $count = $dist[$i] ?? 0;
                                                $pct = $totalRatings > 0 ? round(($count / $totalRatings) * 100, 1) : 0;
                                            ?>
                                            <tr><td><?= $i ?> Stars</td><td><?= $count ?></td><td><?= $pct ?>%</td></tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($q['question_type'] === 'short_answer'): ?>
                                    <p>Total Responses: <?= count($q['results']) ?></p>
                                    <table class="table table-bordered excel-table" data-sheet="Q<?= $index + 1 ?>">
                                        <thead class="table-light"><tr><th>Responses</th></tr></thead>
                                        <tbody>
                                            <?php if(empty($q['results'])): ?>
                                                <tr><td>No responses yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach(array_slice($q['results'], 0, 50) as $ans): ?>
                                                    <tr><td><?= htmlspecialchars($ans) ?></td></tr>
                                                <?php endforeach; ?>
                                                <?php if(count($q['results']) > 50): ?>
                                                    <tr><td class="text-muted">...and <?= count($q['results']) - 50 ?> more.</td></tr>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    @page {
        size: landscape;
        margin: 8mm 10mm;
    }
    
    *, *::before, *::after {
        box-sizing: border-box !important;
    }
    
    html, body {
        background-color: #fff !important;
        color: #0f172a !important;
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
        font-size: 10.5px !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    .d-print-none, .sidebar, .navbar, .top-navbar, header, footer, .toast-container, .btn, .nav-pills, .card:not(.report-content) {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .content-wrapper {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .report-content {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        background: transparent !important;
    }
    
    .report-summary-strip {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
        margin-bottom: 15px !important;
    }
    
    .report-summary-col {
        flex: 1 1 25% !important;
        max-width: 25% !important;
        padding: 0 !important;
    }
    
    .report-summary-card {
        padding: 8px 12px !important;
        background-color: #f8fafc !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        text-align: center !important;
    }
    
    .report-summary-card h4 {
        font-size: 16px !important;
        margin: 2px 0 0 0 !important;
        font-weight: 700 !important;
    }
    
    .report-summary-card .small {
        font-size: 9px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    .table-responsive {
        overflow: visible !important;
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    #residentReportTable {
        width: 100% !important;
        max-width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        font-size: 9.5px !important;
        margin-bottom: 15px !important;
    }
    
    #residentReportTable th,
    #residentReportTable td {
        padding: 4px 6px !important;
        vertical-align: middle !important;
        word-break: break-word !important;
        overflow-wrap: break-word !important;
        line-height: 1.25 !important;
        border: 1px solid #cbd5e1 !important;
    }
    
    .table-dark th {
        background-color: #f1f5f9 !important;
        color: #0f172a !important;
        font-weight: 700 !important;
        font-size: 9.5px !important;
        border-bottom: 2px solid #94a3b8 !important;
    }
    
    #residentReportTable tr:nth-child(even) {
        background-color: #f8fafc !important;
    }
    
    #residentReportTable tr:nth-child(odd) {
        background-color: #ffffff !important;
    }
    
    tr {
        page-break-inside: avoid !important;
    }
    
    .badge {
        display: inline-block !important;
        padding: 2px 6px !important;
        font-size: 8.5px !important;
        font-weight: 600 !important;
        border-radius: 4px !important;
        border: 1px solid currentColor !important;
    }
    
    .bg-success-subtle {
        background-color: #dcfce7 !important;
        color: #15803d !important;
    }
    
    .bg-danger-subtle {
        background-color: #fee2e2 !important;
        color: #b91c1c !important;
    }

    .report-signoff {
        margin-top: 25px !important;
        padding-top: 15px !important;
        page-break-inside: avoid !important;
    }
}
</style>

<script>
function exportResidentExcel() {
    const table = document.getElementById('residentReportTable');
    if (!table) return;
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.table_to_sheet(table);
    XLSX.utils.book_append_sheet(wb, ws, "Resident_Masterlist");
    XLSX.writeFile(wb, 'Resident_Report_<?= date('Ymd_His') ?>.xlsx');
}

async function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'pt', 'a4');
    const content = document.getElementById('reportContent');
    
    await html2canvas(content, { scale: 2 }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 595.28;
        const pageHeight = 841.89;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;

        doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            doc.addPage();
            doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        doc.save('Survey_Report_<?= htmlspecialchars($survey['title'] ?? 'Export') ?>.pdf');
    });
}

function exportExcel() {
    const wb = XLSX.utils.book_new();
    const summaryData = [
        ["Survey Title", "<?= addslashes($survey['title'] ?? '') ?>"],
        ["Description", "<?= addslashes($survey['description'] ?? '') ?>"],
        ["Date Generated", "<?= date('Y-m-d') ?>"],
        [""],
        ["Total Respondents", "<?= $stats['total_respondents'] ?? 0 ?>"],
        ["Total Questions", "<?= $stats['total_questions'] ?? 0 ?>"],
        ["Completion Rate", "<?= $stats['completion_rate'] ?? 0 ?>%"]
    ];
    const wsSummary = XLSX.utils.aoa_to_sheet(summaryData);
    XLSX.utils.book_append_sheet(wb, wsSummary, "Summary");

    const tables = document.querySelectorAll('.excel-table');
    tables.forEach((table, index) => {
        const sheetName = table.getAttribute('data-sheet') || `Sheet${index+1}`;
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, sheetName.substring(0, 31));
    });

    XLSX.writeFile(wb, 'Survey_Data_<?= htmlspecialchars($survey['title'] ?? 'Export') ?>.xlsx');
}
</script>

<?php require_once '../includes/footer.php'; ?>
