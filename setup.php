<?php
require_once __DIR__ . '/config.php';
require_role('admin');

$message = '';
$error = '';
$dbInitialized = false;

// Check if database is initialized by looking for a key table
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $dbInitialized = $stmt->fetch() ? true : false;
} catch (PDOException $e) {
    $dbInitialized = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } elseif (isset($_POST['initialize']) || isset($_POST['reset'])) {
        $sqlFile = __DIR__ . '/sql/database.sql';

        if (!file_exists($sqlFile)) {
            $error = 'Database schema file not found at: sql/database.sql';
        } else {
            try {
                $sql = file_get_contents($sqlFile);
                // Remove comments
                $sql = preg_replace('/--.*?\n/', "\n", $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Split by semicolons and execute each statement
                $statements = array_filter(array_map('trim', explode(';', $sql)));

                // If resetting, prepend DROP TABLE IF EXISTS for each CREATE TABLE
                if (isset($_POST['reset'])) {
                    $dropStatements = [];
                    foreach ($statements as $stmt) {
                        if (stripos($stmt, 'CREATE TABLE') === 0) {
                            if (preg_match('/CREATE TABLE `?(\w+)`?/i', $stmt, $matches)) {
                                $dropStatements[] = "DROP TABLE IF EXISTS `{$matches[1]}`";
                            }
                        }
                    }
                    $statements = array_merge($dropStatements, $statements);
                }

                $pdo->beginTransaction();
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                $pdo->commit();

                $message = isset($_POST['reset'])
                    ? 'Database reset successfully! All tables and seed data have been recreated.'
                    : 'Database initialized successfully! All tables and seed data have been created.';
                $dbInitialized = true;
                log_audit('system', 'database', null, null, ['action' => isset($_POST['reset']) ? 'reset' : 'initialize']);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Database Setup Error: ' . $e->getMessage());
                $error = 'Database setup failed: ' . $e->getMessage();
            }
        }
    }
}

// Get current DB info
$dbInfo = [];
try {
    $dbInfo['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $dbInfo['residents'] = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();
    $dbInfo['households'] = $pdo->query("SELECT COUNT(*) FROM households")->fetchColumn();
    $dbInfo['officials'] = $pdo->query("SELECT COUNT(*) FROM officials")->fetchColumn();
    $dbInfo['document_types'] = $pdo->query("SELECT COUNT(*) FROM document_types")->fetchColumn();
    $dbInfo['document_requests'] = $pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
    $dbInfo['blotter_cases'] = $pdo->query("SELECT COUNT(*) FROM blotter_cases")->fetchColumn();
    $dbInfo['health_records'] = $pdo->query("SELECT COUNT(*) FROM health_records")->fetchColumn();
    $dbInfo['welfare_programs'] = $pdo->query("SELECT COUNT(*) FROM welfare_programs")->fetchColumn();
    $dbInfo['welfare_beneficiaries'] = $pdo->query("SELECT COUNT(*) FROM welfare_beneficiaries")->fetchColumn();
    $dbInfo['audit_logs'] = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
} catch (PDOException $e) {
    $dbInfo = [];
}

$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Barangay Bidduang Portal</title>
    <link rel="shortcut icon" type="image/png" href="assets/img/Brgy_Bidduang.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="assets/img/Brgy_Bidduang.png" alt="Barangay Bidduang Seal">
            <div class="brand-title">Barangay Bidduang<span class="brand-sub">Management Portal</span></div>
        </div>
        <nav>
            <ul>
                <li><a href="<?= BASE_URL ?>/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/residents.php"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="<?= BASE_URL ?>/households.php"><i class="fas fa-home"></i> Households</a></li>
                <li><a href="<?= BASE_URL ?>/officials.php"><i class="fas fa-user-tie"></i> Officials</a></li>
                <li><a href="<?= BASE_URL ?>/documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
                <li><a href="<?= BASE_URL ?>/blotter.php"><i class="fas fa-gavel"></i> Blotter</a></li>
                <li><a href="<?= BASE_URL ?>/welfare.php"><i class="fas fa-hand-holding-heart"></i> Welfare</a></li>
                <li><a href="<?= BASE_URL ?>/health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
                <li><a href="<?= BASE_URL ?>/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="<?= BASE_URL ?>/accounts.php"><i class="fas fa-user-cog"></i> Accounts</a></li>
                <li><a href="<?= BASE_URL ?>/setup.php" class="active"><i class="fas fa-database"></i> Setup</a></li>
                <li><a href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-database"></i> Database Setup</h1>
                <p>Initialize or reset the Barangay Bidduang database</p>
            </div>
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?></div>
                <div><strong><?= esc($currentUser['full_name']) ?></strong><br><small class="text-muted"><?= ucfirst($currentUser['role']) ?></small></div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="toast-alert toast-success" id="floatingAlert">
                <i class="fas fa-check-circle"></i>
                <span><?= esc($message) ?></span>
                <button onclick="this.parentElement.remove()" class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="toast-alert toast-danger" id="floatingAlert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= esc($error) ?></span>
                <button onclick="this.parentElement.remove()" class="toast-close">&times;</button>
            </div>
        <?php endif; ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.getElementById('floatingAlert');
            if (alertBox) {
                setTimeout(function() {
                    alertBox.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    alertBox.style.opacity = '0';
                    alertBox.style.transform = 'translateY(-20px)';
                    setTimeout(function() { alertBox.remove(); }, 400);
                }, 3000);
            }
        });
        </script>

        <!-- Database Status -->
        <div class="card" style="border-left:5px solid <?= $dbInitialized ? 'var(--accent)' : 'var(--danger)' ?>;">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Database Status</h2>
                <span class="badge badge-<?= $dbInitialized ? 'success' : 'danger' ?>"><?= $dbInitialized ? 'Initialized' : 'Not Initialized' ?></span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Table</th><th>Record Count</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dbInfo)): ?>
                            <tr><td colspan="2"><div class="empty-state"><i class="fas fa-database"></i><h3>Database not yet initialized</h3><p>Click Initialize Database below to set up all tables and seed data.</p></div></td></tr>
                        <?php else: ?>
                            <tr><td>Users</td><td><?= number_format($dbInfo['users']) ?></td></tr>
                            <tr><td>Residents</td><td><?= number_format($dbInfo['residents']) ?></td></tr>
                            <tr><td>Households</td><td><?= number_format($dbInfo['households']) ?></td></tr>
                            <tr><td>Officials</td><td><?= number_format($dbInfo['officials']) ?></td></tr>
                            <tr><td>Document Types</td><td><?= number_format($dbInfo['document_types']) ?></td></tr>
                            <tr><td>Document Requests</td><td><?= number_format($dbInfo['document_requests']) ?></td></tr>
                            <tr><td>Blotter Cases</td><td><?= number_format($dbInfo['blotter_cases']) ?></td></tr>
                            <tr><td>Health Records</td><td><?= number_format($dbInfo['health_records']) ?></td></tr>
                            <tr><td>Welfare Programs</td><td><?= number_format($dbInfo['welfare_programs']) ?></td></tr>
                            <tr><td>Welfare Beneficiaries</td><td><?= number_format($dbInfo['welfare_beneficiaries']) ?></td></tr>
                            <tr><td>Audit Logs</td><td><?= number_format($dbInfo['audit_logs']) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Schema File Info -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-file-code"></i> Schema File</h2>
            </div>
            <p><strong>Location:</strong> <code><?= BASE_URL ?>/sql/database.sql</code></p>
            <?php
            $sqlFile = __DIR__ . '/sql/database.sql';
            if (file_exists($sqlFile)) {
                $size = filesize($sqlFile);
                echo '<p><strong>Size:</strong> - setup.php:210' . number_format($size) . ' bytes</p>';
                echo '<p class="textmuted">This file contains all table definitions, foreign keys, indexes, and seed data including the default admin account, puroks, and document types.</p> - setup.php:211';
            } else {
                echo '<div class="toastalert toastdanger" id="floatingAlert"><i class="fas faexclamationcircle"></i> Schema file not found! Please ensure sql/database.sql exists.</div> - setup.php:213';
            }
            ?>
        </div>

        <!-- Actions -->
        <div class="card" style="text-align:center;">
            <h2 style="margin-top:0;">Database Actions</h2>
            <p class="text-muted" style="font-size:16px;margin-bottom:25px;">
                Initialize will create all tables and seed data. Reset will drop and recreate everything (all data will be lost).
            </p>
            <div class="d-flex" style="justify-content:center;gap:15px;flex-wrap:wrap;">
                <?php if (!$dbInitialized): ?>
                    <form method="POST" onsubmit="return confirm('Initialize database? This will create all tables and seed data.')">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="initialize" value="1">
                        <button type="submit" class="btn btn-primary" style="font-size:18px;padding:15px 30px;"><i class="fas fa-play"></i> Initialize Database</button>
                    </form>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('WARNING: Reset will DELETE ALL DATA and recreate tables. This cannot be undone. Are you absolutely sure?')">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="reset" value="1">
                        <button type="submit" class="btn btn-danger" style="font-size:18px;padding:15px 30px;"><i class="fas fa-redo"></i> Reset Database</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($dbInitialized): ?>
                <div class="toast-alert toast-warning" id="floatingAlert" style="margin-top:20px;text-align:left;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><strong>Default Admin Credentials:</strong><br>
                    Username: <code>admin</code> | Password: <code>Admin@123</code><br>
                    <small>Please change the default password immediately after first login.</small></span>
                    <button onclick="this.parentElement.remove()" class="toast-close">&times;</button>
                </div>
            <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
