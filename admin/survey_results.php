<?php
$pageTitle = "Survey Results";
require_once '../config/session.php';
requireAdmin();

// Fetch all surveys for the dropdown (Firebase)
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
$respondents = [];

if ($survey_id) {
    // Fetch survey details
    $survey = fb_get_rec('surveys', (string)$survey_id);

    if ($survey) {
        $survey['id'] = (string)$survey_id;
        // Calculate stats
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
                $yes_count = 0; $no_count = 0;
                foreach ($qd['data'] as $d) {
                    if (($d['label'] ?? '') === 'Yes') $yes_count = (int)$d['count'];
                    if (($d['label'] ?? '') === 'No') $no_count = (int)$d['count'];
                }
                $row['results'] = ['Yes' => $yes_count, 'No' => $no_count];
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

        // Fetch respondents (including responses from deleted residents)
        $allResponses = fb_all('responses');
        $respRows = [];
        foreach ($allResponses as $rid => $r) {
            if (!is_array($r) || (string)($r['survey_id'] ?? '') !== (string)$survey_id) continue;
            $r['id'] = (string)$rid;
            $respRows[] = $r;
        }
        usort($respRows, function ($a, $b) { return strcmp((string)($b['submitted_at'] ?? ''), (string)($a['submitted_at'] ?? '')); });
        foreach ($respRows as $r) {
            $res = is_array($r['resident_id'] ?? null) ? null : fb_get_rec('residents', (string)($r['resident_id'] ?? ''));
            if (is_array($res)) {
                $full = trim(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? ''));
                $respondents[] = [
                    'id' => $r['id'],
                    'submitted_at' => $r['submitted_at'] ?? '',
                    'resident_number' => $res['resident_number'] ?? 'N/A',
                    'full_name' => $full !== '' ? $full : '[Deleted Account]',
                ];
            } else {
                $respondents[] = [
                    'id' => $r['id'],
                    'submitted_at' => $r['submitted_at'] ?? '',
                    'resident_number' => 'N/A',
                    'full_name' => '[Deleted Account]',
                ];
            }
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
require_once '../includes/navbar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <h2 class="mb-4">Survey Results</h2>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="d-flex align-items-center">
                    <label class="me-2 fw-bold">Select Survey:</label>
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
            <div class="alert alert-info">Select a survey to view its results.</div>
        <?php elseif (!$survey): ?>
            <div class="alert alert-danger">Survey not found.</div>
        <?php else: ?>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card stat-card">
                        <div class="card-body">
                            <h3><?= htmlspecialchars($survey['title']) ?></h3>
                            <p class="text-muted"><?= htmlspecialchars($survey['description']) ?></p>
                            <div class="d-flex flex-wrap gap-3 mt-2">
                                <span class="badge bg-primary">Category: <?= htmlspecialchars($survey['category']) ?></span>
                                <span class="badge bg-secondary">Dates: <?= $survey['opening_date'] ?> to <?= $survey['closing_date'] ?></span>
                                <span class="badge <?= $survey['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">Status: <?= htmlspecialchars(ucwords(strtolower($survey['status']))) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stat-card h-100 bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Respondents</h5>
                            <h2 class="display-4"><?= $stats['total_respondents'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Completion Rate</h5>
                            <h2 class="display-4"><?= $stats['completion_rate'] ?>%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 bg-info text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Total Questions</h5>
                            <h2 class="display-4"><?= $stats['total_questions'] ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <h4 class="mb-3">Results Per Question</h4>
            
            <?php foreach ($questions as $index => $q): ?>
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Q<?= $index + 1 ?>: <?= htmlspecialchars($q['question_text']) ?> 
                            <span class="badge bg-secondary fs-6 float-end"><?= str_replace('_', ' ', ucwords($q['question_type'], '_')) ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($q['question_type'] === 'multiple_choice'): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <thead><tr><th>Choice</th><th>Votes</th><th>Percentage</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $total = array_sum(array_column($q['results'], 'votes'));
                                            foreach ($q['results'] as $res): 
                                                $pct = $total > 0 ? round(($res['votes'] / $total) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($res['choice_text']) ?></td>
                                                <td><?= $res['votes'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2"><?= $pct ?>%</span>
                                                        <div class="progress flex-grow-1" style="height: 10px;">
                                                            <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <canvas id="chart_q_<?= $q['id'] ?>"></canvas>
                                </div>
                            </div>

                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('chart_q_<?= $q['id'] ?>').getContext('2d');
                                new Chart(ctx, {
                                    type: 'pie',
                                    data: {
                                        labels: <?= json_encode(array_column($q['results'], 'choice_text')) ?>,
                                        datasets: [{
                                            data: <?= json_encode(array_column($q['results'], 'votes')) ?>,
                                            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796']
                                        }]
                                    }
                                });
                            });
                            </script>

                        <?php elseif ($q['question_type'] === 'yes_no'): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <thead><tr><th>Answer</th><th>Count</th><th>Percentage</th></tr></thead>
                                        <tbody>
                                            <?php 
                                            $yes = $q['results']['Yes'];
                                            $no = $q['results']['No'];
                                            $total = $yes + $no;
                                            $yesPct = $total > 0 ? round(($yes / $total) * 100, 1) : 0;
                                            $noPct = $total > 0 ? round(($no / $total) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td>Yes</td><td><?= $yes ?></td><td><?= $yesPct ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>No</td><td><?= $no ?></td><td><?= $noPct ?>%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <canvas id="chart_q_<?= $q['id'] ?>"></canvas>
                                </div>
                            </div>
                            
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const ctx = document.getElementById('chart_q_<?= $q['id'] ?>').getContext('2d');
                                new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Yes', 'No'],
                                        datasets: [{
                                            data: [<?= $yes ?>, <?= $no ?>],
                                            backgroundColor: ['#1cc88a', '#e74a3b']
                                        }]
                                    }
                                });
                            });
                            </script>

                        <?php elseif ($q['question_type'] === 'rating'): ?>
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <h4 class="text-muted mb-2">Average Rating</h4>
                                    <h1 class="display-3 text-warning fw-bold">
                                        <?= $q['results']['average'] ?: '0' ?>
                                    </h1>
                                    <div class="text-warning fs-4">
                                        <?php 
                                        $avg = $q['results']['average'];
                                        for($i=1; $i<=5; $i++) {
                                            if ($i <= round($avg)) echo '<i class="bi bi-star-fill"></i>';
                                            else echo '<i class="bi bi-star"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <table class="table table-sm">
                                        <tbody>
                                            <?php 
                                            $totalRatings = array_sum(array_column($q['results']['distribution'], 'count'));
                                            $dist = array_column($q['results']['distribution'], 'count', 'rating_value');
                                            for($i=5; $i>=1; $i--): 
                                                $count = $dist[$i] ?? 0;
                                                $pct = $totalRatings > 0 ? round(($count / $totalRatings) * 100, 1) : 0;
                                            ?>
                                            <tr>
                                                <td width="10%"><?= $i ?> Stars</td>
                                                <td width="70%">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-warning" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                </td>
                                                <td width="20%"><?= $count ?> (<?= $pct ?>%)</td>
                                            </tr>
                                            <?php endfor; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        <?php elseif ($q['question_type'] === 'short_answer'): ?>
                            <div class="alert alert-info">Total Responses: <?= count($q['results']) ?></div>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: .25rem; padding: 10px;">
                                <?php if(empty($q['results'])): ?>
                                    <p class="text-muted">No responses yet.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach($q['results'] as $ans): ?>
                                            <li class="list-group-item"><?= htmlspecialchars($ans) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <h4 class="mb-3 mt-5">Respondent List</h4>
            <div class="card table-container mb-5">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Resident Number</th>
                                    <th>Name</th>
                                    <th>Submission Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($respondents as $idx => $r): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <?php if ($r['full_name'] === '[Deleted Account]'): ?>
                                                <span class="badge bg-secondary">Deleted Resident</span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($r['resident_number']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['full_name'] === '[Deleted Account]'): ?>
                                                <em class="text-muted"><i class="bi bi-person-x me-1"></i>[Deleted Account] (Response Preserved)</em>
                                            <?php else: ?>
                                                <?= htmlspecialchars($r['full_name']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('F j, Y, g:i a', strtotime($r['submitted_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($respondents)): ?>
                                    <tr><td colspan="4" class="text-center">No respondents yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
