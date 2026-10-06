<?php
$pageTitle = "Print Resident Information";
require_once '../config/session.php';
requireAdmin();

$resident_id = (string)($_GET['id'] ?? '');
if ($resident_id === '' || !ctype_digit($resident_id)) {
    die("Invalid resident ID.");
}

try {
    $resident = fb_get_rec('residents', $resident_id);
} catch (Exception $e) {
    die("Error loading resident record.");
}

if (!$resident) {
    die("Resident record not found.");
}
$resident['id'] = $resident_id;

// Fetch children
$children = [];
try {
    foreach (fb_all('resident_children') as $cid => $c) {
        if (!is_array($c)) continue;
        if ((string)($c['resident_id'] ?? '') !== $resident_id) continue;
        $c['id'] = (string)$cid;
        $children[] = $c;
    }
    usort($children, function ($a, $b) { return (int)($a['id'] ?? 0) - (int)($b['id'] ?? 0); });
} catch (Exception $e) {
    $children = [];
}

// Calculate Age
$ageDisplay = 'N/A';
if (!empty($resident['birthdate'])) {
    $birthDate = new DateTime($resident['birthdate']);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    $ageDisplay = $age . ' years old';
}

$fullName = trim(($resident['first_name'] ?? '') . ' ' . ($resident['middle_name'] ?? '') . ' ' . ($resident['last_name'] ?? '') . ' ' . ($resident['extension_name'] ?? ''));
$initials = strtoupper(substr($resident['first_name'] ?? 'R', 0, 1) . substr($resident['last_name'] ?? 'S', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Record - <?= htmlspecialchars($resident['resident_number']) ?> - <?= htmlspecialchars($fullName) ?></title>
    
    <!-- Bootstrap CSS for print styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #eef4f1;
            color: #14231f;
            font-size: 13px;
            margin: 0;
            padding: 0;
        }

        .print-toolbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .printable-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 20mm;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 4px;
        }

        .form-header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .section-header {
            background-color: #edf6f3;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
            color: #115e59;
            border-left: 4px solid #0f766e;
            margin-top: 18px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .photo-box {
            width: 130px;
            height: 130px;
            border: 2px solid #cbd5e1;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 4px;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .data-label {
            font-weight: 600;
            color: #64748b;
            font-size: 11.5px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .data-value {
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
            padding-bottom: 6px;
            border-bottom: 1px dotted #cbd5e1;
            min-height: 26px;
        }

        .signature-box {
            border-top: 1px solid #000;
            width: 240px;
            text-align: center;
            padding-top: 6px;
            margin-top: 35px;
        }

        @media print {
            body {
                background: #ffffff !important;
                font-size: 12px;
            }

            .print-toolbar, .no-print {
                display: none !important;
            }

            .printable-sheet {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 10mm 15mm !important;
                width: 100% !important;
                min-height: auto !important;
            }

            .section-header {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- Non-printable Action Toolbar -->
    <div class="print-toolbar no-print d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <i class="bi bi-printer text-primary fs-4 me-2"></i>
            <div>
                <h6 class="mb-0 fw-bold">Print Preview: Resident Information</h6>
                <small class="text-muted"><?= htmlspecialchars($resident['resident_number']) ?> - <?= htmlspecialchars($fullName) ?></small>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary px-4 shadow-sm" onclick="window.print()">
                <i class="bi bi-printer-fill me-1"></i> Print Document
            </button>
            <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
                <i class="bi bi-x-lg me-1"></i> Close
            </button>
        </div>
    </div>

    <!-- Official Printable Document -->
    <div class="printable-sheet">
        <!-- Header -->
        <div class="form-header d-flex justify-content-between align-items-start">
            <div class="d-flex align-items-center">
                <div class="me-3 text-primary">
                    <i class="bi bi-heart-pulse-fill" style="font-size: 42px;"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase mb-0 small fw-bold" style="letter-spacing: 1px;">Republic of the Philippines</h6>
                    <h4 class="fw-bold mb-0 text-primary">Barangay Health Center</h4>
                    <small class="text-secondary fw-semibold">Community Health Survey & Information System</small>
                </div>
            </div>
            <!-- Passport Photo Box -->
            <div class="photo-box">
                <?php if (!empty($resident['profile_picture']) && file_exists('../uploads/profile_pics/' . $resident['profile_picture'])): ?>
                    <img src="../uploads/profile_pics/<?= htmlspecialchars($resident['profile_picture']) ?>" alt="Passport Photo">
                <?php else: ?>
                    <div class="text-center text-muted p-2">
                        <i class="bi bi-person fs-1 d-block"></i>
                        <span style="font-size: 10px;">Passport Photo</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mb-3">
            <h5 class="fw-bold text-uppercase mb-0" style="letter-spacing: 1px;">Resident Information Record</h5>
            <span class="badge bg-primary px-3 py-1 font-monospace mt-1">RESIDENT NO: <?= htmlspecialchars($resident['resident_number']) ?></span>
        </div>

        <!-- 1. PERSONAL INFORMATION -->
        <div class="section-header">1. Personal Information</div>
        <div class="row g-3">
            <div class="col-4">
                <div class="data-label">First Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['first_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Middle Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['middle_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Last Name & Extension</div>
                <div class="data-value"><?= htmlspecialchars(trim(($resident['last_name'] ?? '') . ' ' . ($resident['extension_name'] ?? '')) ?: 'N/A') ?></div>
            </div>

            <div class="col-3">
                <div class="data-label">Civil Status</div>
                <div class="data-value"><?= htmlspecialchars($resident['civil_status'] ?: 'Single') ?></div>
            </div>
            <div class="col-3">
                <div class="data-label">Gender</div>
                <div class="data-value"><?= htmlspecialchars($resident['gender'] ?: 'N/A') ?></div>
            </div>
            <div class="col-3">
                <div class="data-label">Date of Birth</div>
                <div class="data-value"><?= !empty($resident['birthdate']) ? date('F d, Y', strtotime($resident['birthdate'])) : 'N/A' ?></div>
            </div>
            <div class="col-3">
                <div class="data-label">Age</div>
                <div class="data-value"><?= htmlspecialchars($ageDisplay) ?></div>
            </div>

            <div class="col-6">
                <div class="data-label">Contact Number</div>
                <div class="data-value"><?= htmlspecialchars($resident['phone'] ?: 'N/A') ?></div>
            </div>
            <div class="col-6">
                <div class="data-label">Email Address</div>
                <div class="data-value"><?= htmlspecialchars($resident['email'] ?: 'N/A') ?></div>
            </div>

            <div class="col-12">
                <div class="data-label">Residential Address</div>
                <div class="data-value"><?= htmlspecialchars($resident['address'] ?: 'N/A') ?></div>
            </div>

            <div class="col-4">
                <div class="data-label">Occupation</div>
                <div class="data-value"><?= htmlspecialchars($resident['occupation'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Employer / Business</div>
                <div class="data-value"><?= htmlspecialchars($resident['employer'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Employer Address</div>
                <div class="data-value"><?= htmlspecialchars($resident['employer_address'] ?: 'N/A') ?></div>
            </div>
        </div>

        <!-- 2. SPOUSE & CHILDREN INFORMATION -->
        <div class="section-header">2. Spouse & Family Information</div>
        <div class="row g-3 mb-2">
            <div class="col-4">
                <div class="data-label">Spouse Full Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['spouse_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Spouse Occupation</div>
                <div class="data-value"><?= htmlspecialchars($resident['spouse_occupation'] ?: 'N/A') ?></div>
            </div>
            <div class="col-4">
                <div class="data-label">Spouse Employer</div>
                <div class="data-value"><?= htmlspecialchars($resident['spouse_employer'] ?: 'N/A') ?></div>
            </div>
        </div>

        <div class="mt-2">
            <div class="data-label mb-1">Children:</div>
            <?php if (empty($children)): ?>
                <div class="data-value text-muted">None recorded</div>
            <?php else: ?>
                <table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Child Full Name</th>
                            <th style="width: 120px;">Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $idx => $child): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= htmlspecialchars($child['child_name']) ?></td>
                                <td><?= $child['age'] !== null ? htmlspecialchars($child['age']) . ' years old' : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 3. PARENTS INFORMATION -->
        <div class="section-header">3. Parents Information</div>
        <div class="row g-3">
            <div class="col-6">
                <div class="data-label">Father's Full Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['father_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-6">
                <div class="data-label">Mother's Maiden Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['mother_name'] ?: 'N/A') ?></div>
            </div>
        </div>

        <!-- 4. CHARACTER REFERENCES -->
        <div class="section-header">4. Character References</div>
        <div class="row g-3">
            <div class="col-6">
                <div class="data-label">Reference 1: Full Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['reference1_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-6">
                <div class="data-label">Reference 1: Contact / Address</div>
                <div class="data-value"><?= htmlspecialchars($resident['reference1_contact'] ?: 'N/A') ?></div>
            </div>
            <div class="col-6">
                <div class="data-label">Reference 2: Full Name</div>
                <div class="data-value"><?= htmlspecialchars($resident['reference2_name'] ?: 'N/A') ?></div>
            </div>
            <div class="col-6">
                <div class="data-label">Reference 2: Contact / Address</div>
                <div class="data-value"><?= htmlspecialchars($resident['reference2_contact'] ?: 'N/A') ?></div>
            </div>
        </div>

        <!-- 5. SIGNATURE & CERTIFICATION -->
        <div class="section-header">5. Certification & Acknowledgment</div>
        <p class="small text-muted mb-4" style="font-size: 11px;">
            I hereby certify that all information provided in this record is true, complete, and correct to the best of my knowledge and belief.
        </p>

        <div class="d-flex justify-content-between align-items-end mt-4">
            <div>
                <small class="text-muted d-block">Printed Date: <strong><?= date('F d, Y, g:i A') ?></strong></small>
                <small class="text-muted d-block">Account Status: <strong class="text-capitalize"><?= htmlspecialchars($resident['status']) ?></strong></small>
            </div>
            <div class="signature-box">
                <div class="fw-bold"><?= htmlspecialchars($resident['signature'] ?: $fullName) ?></div>
                <div class="small text-muted">Signature / Authorized Name</div>
            </div>
        </div>
    </div>

</body>
</html>
