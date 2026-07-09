<?php
/**
 * Admin/Student — View & Print Certificate
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';

startSession();

// Needs to be either admin or logged in student who owns this cert
$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("Certificate not specified.");
}

$pdo = db();
$stmt = $pdo->prepare("
    SELECT c.*, s.name as student_name, s.student_id as sid, s.college, s.course, s.internship_start, s.internship_end, d.name as dept_name
    FROM certificates c
    JOIN students s ON c.student_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$cert = $stmt->fetch();

if (!$cert) {
    die("Certificate not found.");
}

// Security check: if student, they can only view their own cert
if (isset($_SESSION['student_id']) && $_SESSION['student_id'] != $cert['student_id'] && !isset($_SESSION['admin_id'])) {
    die("Access denied.");
}
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['student_id'])) {
    die("Access denied. Please log in.");
}

$start = $cert['internship_start'] ? formatDate($cert['internship_start'], 'd M Y') : 'N/A';
$end   = $cert['internship_end'] ? formatDate($cert['internship_end'], 'd M Y') : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?= htmlspecialchars($cert['cert_number']) ?></title>
    <style>
        body {
            font-family: 'Georgia', serif;
            background: #f0f0f0;
            margin: 0;
            padding: 0;
            display: grid;
            place-items: center;
            min-height: 100vh;
        }

        .certificate-container {
            width: 850px;
            height: 600px;
            padding: 40px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border: 20px solid #1e293b;
            border-image: linear-gradient(135deg, #1e293b, #475569) 20;
            position: relative;
            box-sizing: border-box;
            background-image: radial-gradient(circle, rgba(99, 102, 241, 0.03) 0%, transparent 80%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        /* Gold inner border */
        .inner-border {
            border: 4px double #d97706;
            height: 100%;
            width: 100%;
            box-sizing: border-box;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cert-header {
            font-family: 'Outfit', 'Helvetica Neue', sans-serif;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #1e293b;
        }
        .cert-header h1 {
            margin: 0;
            font-size: 2.2rem;
            text-transform: uppercase;
        }
        .cert-header p {
            margin: 4px 0 0 0;
            font-size: 0.9rem;
            color: #d97706;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        .cert-title {
            font-size: 1.25rem;
            font-style: italic;
            color: #475569;
            margin-top: 14px;
        }

        .cert-recipient {
            margin: 12px 0;
        }
        .cert-recipient h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            border-bottom: 2px solid #e2e8f0;
            display: inline-block;
            padding-bottom: 6px;
            min-width: 320px;
        }

        .cert-body {
            font-size: 1.05rem;
            line-height: 1.6;
            color: #334155;
            padding: 0 40px;
        }
        .cert-body strong {
            color: #0f172a;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 0 30px;
            margin-top: 24px;
        }

        .signature-block {
            text-align: center;
            width: 180px;
        }
        .signature-line {
            border-top: 1px solid #475569;
            margin-top: 40px;
            padding-top: 6px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
        }

        .cert-details {
            font-family: monospace;
            font-size: 0.8rem;
            color: #64748b;
            text-align: left;
        }

        /* Printable setup */
        @media print {
            body {
                background: none;
            }
            .certificate-container {
                box-shadow: none;
                page-break-inside: avoid;
            }
            .no-print {
                display: none;
            }
        }

        .no-print-bar {
            background: #1e293b;
            width: 100%;
            padding: 12px 24px;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 999;
            font-family: sans-serif;
        }
        .no-print-bar button {
            background: #d97706;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .no-print-bar button:hover {
            background: #b45309;
        }
        
        .main-wrapper {
            margin-top: 60px;
        }
    </style>
</head>
<body>

    <div class="no-print-bar no-print">
        <span>Verify Mode: <strong><?= htmlspecialchars($cert['cert_number']) ?></strong></span>
        <button onclick="window.print()"><i class="fa fa-print"></i> Print / Download PDF</button>
    </div>

    <div class="main-wrapper">
        <div class="certificate-container">
            <div class="inner-border">
                
                <div class="cert-header">
                    <h1><?= SITE_NAME ?></h1>
                    <p>Internship Certificate Program</p>
                </div>

                <div class="cert-title">
                    This is proudly presented to
                </div>

                <div class="cert-recipient">
                    <h2><?= htmlspecialchars($cert['student_name']) ?></h2>
                </div>

                <div class="cert-body">
                    <?php if ($cert['cert_type'] === 'Completion'): ?>
                    For successfully completing the internship program in the field of <strong><?= htmlspecialchars($cert['dept_name'] ?? 'Software Engineering') ?></strong> 
                    at our organization. The duration of the program was from <strong><?= $start ?></strong> to <strong><?= $end ?></strong>.
                    During this period, the candidate demonstrated outstanding performance, dedication, and technical competence.
                    <?php elseif ($cert['cert_type'] === 'Appreciation'): ?>
                    In recognition of outstanding performance, initiative, and exemplary dedication shown during the internship program 
                    from <strong><?= $start ?></strong> to <strong><?= $end ?></strong>. The commitment to achieving excellent results and adding value to projects is highly appreciated.
                    <?php else: ?>
                    <strong>Experience Letter / Recommendation:</strong> We verify that the candidate completed a professional internship in the department of 
                    <strong><?= htmlspecialchars($cert['dept_name'] ?? 'Engineering') ?></strong> from <strong><?= $start ?></strong> to <strong><?= $end ?></strong>. 
                    They contributed significantly to project design and implementation while exhibiting professional ethics and reliability.
                    <?php endif; ?>
                </div>

                <div class="cert-footer">
                    <div class="cert-details">
                        <div>Certificate ID: <?= htmlspecialchars($cert['cert_number']) ?></div>
                        <div>Date Issued: <?= formatDate($cert['issued_date']) ?></div>
                        <div>College: <?= htmlspecialchars($cert['college'] ?? 'N/A') ?></div>
                    </div>
                    
                    <div class="signature-block">
                        <!-- Simulated Signature -->
                        <div style="font-family:'Lucida Handwriting', cursive, Georgia; font-size:1.2rem; color:#1e3a8a; transform: rotate(-5deg); margin-bottom:-10px">Admin Signature</div>
                        <div class="signature-line">Program Director</div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
