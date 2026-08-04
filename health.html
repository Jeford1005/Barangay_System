<?php
require_once __DIR__ . '/config.php';
require_role(['admin', 'staff']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $resident_id = $_POST['resident_id'] ?? null;
            $blood_type = $_POST['blood_type'] ?? 'Unknown';
            $height_cm = !empty($_POST['height_cm']) ? $_POST['height_cm'] : null;
            $weight_kg = !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null;
            $bmi = !empty($_POST['bmi']) ? $_POST['bmi'] : null;
            $vaccination_status = $_POST['vaccination_status'] ?? 'Unknown';
            $medical_conditions = trim($_POST['medical_conditions'] ?? '');
            $allergies = trim($_POST['allergies'] ?? '');
            $last_checkup = $_POST['last_checkup'] ?? null;
            $notes = trim($_POST['notes'] ?? '');

            if (!$resident_id) {
                $error = 'Resident is required.';
            } else {
                // Calculate BMI if height and weight provided
                if ($height_cm && $weight_kg && !$bmi) {
                    $height_m = $height_cm / 100;
                    $bmi = round($weight_kg / ($height_m * $height_m), 2);
                }

                $oldValues = null;
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM health_records WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldValues = $stmt->fetch();
                }
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO health_records (resident_id, blood_type, height_cm, weight_kg, bmi, vaccination_status, medical_conditions, allergies, last_checkup, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$resident_id, $blood_type, $height_cm, $weight_kg, $bmi, $vaccination_status, $medical_conditions ?: null, $allergies ?: null, $last_checkup ?: null, $notes ?: null]);
                    $newId = $pdo->lastInsertId();
                    log_audit('create', 'health_record', $newId);
                    $message = 'Health record added successfully.';
                } elseif ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("UPDATE health_records SET resident_id=?, blood_type=?, height_cm=?, weight_kg=?, bmi=?, vaccination_status=?, medical_conditions=?, allergies=?, last_checkup=?, notes=? WHERE id=?");
                    $stmt->execute([$resident_id, $blood_type, $height_cm, $weight_kg, $bmi, $vaccination_status, $medical_conditions ?: null, $allergies ?: null, $last_checkup ?: null, $notes ?: null, $id]);
                    log_audit('update', 'health_record', $id, $oldValues);
                    $message = 'Health record updated successfully.';
                }
            }
        } elseif ($action === 'delete' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM health_records WHERE id = ?");
            $stmt->execute([$id]);
            log_audit('delete', 'health_record', $id);
            $message = 'Health record deleted.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];
if ($search) {
    $where[] = "(r.first_name LIKE ? OR r.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM health_records hr LEFT JOIN residents r ON hr.resident_id = r.id WHERE $whereSql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("
    SELECT hr.*, r.first_name, r.last_name, r.middle_name
    FROM health_records hr
    LEFT JOIN residents r ON hr.resident_id = r.id
    WHERE $whereSql
    ORDER BY hr.last_checkup DESC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$records = $stmt->fetchAll();

$residents = $pdo->query("SELECT id, first_name, last_name, middle_name FROM residents WHERE status='Active' ORDER BY last_name, first_name")->fetchAll();

$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/img/Brgy_Bidduang.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Brgy_Bidduang.png">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health - Barangay Bidduang Portal</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . "/assets/css/dashboard.css") ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="assets/img/Brgy_Bidduang.png" alt="Barangay Bidduang Seal">
            <div class="brand-title">Barangay Bidduang<span class="brand-sub">Management Portal</span></div>
        </div>
        <nav>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="residents.php"><i class="fas fa-users"></i> Residents</a></li>
                <li><a href="households.php"><i class="fas fa-home"></i> Households</a></li>
                <li><a href="officials.php"><i class="fas fa-user-tie"></i> Officials</a></li>
                <li><a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
                <li><a href="blotter.php"><i class="fas fa-gavel"></i> Blotter</a></li>
                <li><a href="welfare.php"><i class="fas fa-hand-holding-heart"></i> Welfare</a></li>
                <li><a href="health.php" class="active"><i class="fas fa-heartbeat"></i> Health</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="accounts.php"><i class="fas fa-user-cog"></i> Accounts</a></li>
                <li><a href="setup.php">Setup</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-heartbeat"></i> Community Health Records</h1>
                <p>Track health information and vaccination status of residents</p>
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

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--accent);"><i class="fas fa-file-medical"></i></div>
                <div class="stat-info"><h3><?= number_format(count($records)) ?></h3><p>Total Records</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--success);"><i class="fas fa-syringe"></i></div>
                <div class="stat-info"><h3><?= number_format($pdo->query("SELECT COUNT(*) FROM health_records WHERE vaccination_status='Fully Vaccinated'")->fetchColumn()) ?></h3><p>Fully Vaccinated</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--warning);"><i class="fas fa-notes-medical"></i></div>
                <div class="stat-info"><h3><?= number_format($pdo->query("SELECT COUNT(*) FROM health_records WHERE medical_conditions IS NOT NULL AND medical_conditions!=''")->fetchColumn()) ?></h3><p>With Medical Conditions</p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Health Records</h2>
                <div class="toolbar">
                    <form method="GET" class="search-box" style="flex:1;min-width:220px;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by resident name..." value="<?= esc($search) ?>">
                    </form>
                    <button class="btn btn-primary" onclick="openModal('healthModal')"><i class="fas fa-plus"></i> Add Record</button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr><th>Resident</th><th>Blood Type</th><th>Height</th><th>Weight</th><th>BMI</th><th>Vaccination</th><th>Last Checkup</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8"><div class="empty-state"><i class="fas fa-heartbeat"></i><h3>No health records found</h3><p>Add a health record for a resident.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                            <tr>
                                <td><strong><?= esc($r['first_name'] . ' ' . ($r['middle_name'] ? substr($r['middle_name'],0,1).'. ' : '') . $r['last_name']) ?></strong></td>
                                <td><span class="badge badge-<?= $r['blood_type']=='Unknown'?'secondary':'info' ?>"><?= esc($r['blood_type']) ?></span></td>
                                <td><?= $r['height_cm'] ? $r['height_cm'].' cm' : '-' ?></td>
                                <td><?= $r['weight_kg'] ? $r['weight_kg'].' kg' : '-' ?></td>
                                <td><?= $r['bmi'] ?: '-' ?></td>
                                <td><span class="badge badge-<?= $r['vaccination_status']=='Fully Vaccinated'?'success':($r['vaccination_status']=='Not Vaccinated'?'danger':'warning') ?>"><?= esc($r['vaccination_status']) ?></span></td>
                                <td><?= $r['last_checkup'] ? date('M d, Y', strtotime($r['last_checkup'])) : '-' ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-sm btn-info" onclick="editHealth(<?= $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteHealth(<?= $r['id'] ?>)"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    </div>
</div>

<div class="modal-backdrop" id="healthModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Health Record</h3>
            <button class="modal-close" onclick="closeModal('healthModal')">&times;</button>
        </div>
        <form id="healthForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="recordId">
                <div class="form-group">
                    <label>Resident *</label>
                    <select name="resident_id" id="residentId" class="form-control" required>
                        <option value="">Select Resident</option>
                        <?php foreach ($residents as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= esc($r['first_name'] . ' ' . $r['last_name'] . ($r['middle_name'] ? ' '.substr($r['middle_name'],0,1).'.' : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label>Blood Type</label>
                        <select name="blood_type" id="bloodType" class="form-control">
                            <option>Unknown</option>
                            <option>A+</option><option>A-</option><option>B+</option><option>B-</option>
                            <option>AB+</option><option>AB-</option><option>O+</option><option>O-</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Height (cm)</label><input type="number" name="height_cm" id="heightCm" class="form-control" placeholder="170"></div>
                    <div class="form-group"><label>Weight (kg)</label><input type="number" step="0.1" name="weight_kg" id="weightKg" class="form-control" placeholder="65.5"></div>
                </div>
                <div class="form-group"><label>BMI</label><input type="number" step="0.1" name="bmi" id="bmi" class="form-control" placeholder="Auto-calculated or manual entry"></div>
                <div class="form-group">
                    <label>Vaccination Status</label>
                    <select name="vaccination_status" id="vaccinationStatus" class="form-control">
                        <option>Unknown</option><option>Fully Vaccinated</option><option>Partially Vaccinated</option><option>Not Vaccinated</option>
                    </select>
                </div>
                <div class="form-group"><label>Medical Conditions</label><textarea name="medical_conditions" id="medicalConditions" class="form-control" rows="2" placeholder="Hypertension, Diabetes, Asthma..."></textarea></div>
                <div class="form-group"><label>Allergies</label><textarea name="allergies" id="allergies" class="form-control" rows="2" placeholder="Food, Drug, Environmental..."></textarea></div>
                <div class="form-group"><label>Last Checkup</label><input type="date" name="last_checkup" id="lastCheckup" class="form-control"></div>
                <div class="form-group"><label>Notes</label><textarea name="notes" id="healthNotes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('healthModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Record</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="deleteModal">
    <div class="modal" style="max-width:450px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:17px;">Delete this health record? This cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
            <form id="deleteForm" method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function editHealth(id) {
    fetch('<?= BASE_URL ?>/api/health.php?id=' + id).then(r => r.json()).then(data => {
        if (!data.id) return alert('Not found');
        document.getElementById('formAction').value = 'edit';
        document.getElementById('recordId').value = data.id;
        document.getElementById('modalTitle').textContent = 'Edit Health Record';
        document.getElementById('residentId').value = data.resident_id || '';
        document.getElementById('bloodType').value = data.blood_type || 'Unknown';
        document.getElementById('heightCm').value = data.height_cm || '';
        document.getElementById('weightKg').value = data.weight_kg || '';
        document.getElementById('bmi').value = data.bmi || '';
        document.getElementById('vaccinationStatus').value = data.vaccination_status || 'Unknown';
        document.getElementById('medicalConditions').value = data.medical_conditions || '';
        document.getElementById('allergies').value = data.allergies || '';
        document.getElementById('lastCheckup').value = data.last_checkup || '';
        document.getElementById('healthNotes').value = data.notes || '';
        openModal('healthModal');
    });
}
function deleteHealth(id) { document.getElementById('deleteId').value = id; openModal('deleteModal'); }
document.querySelectorAll('.modal-backdrop').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
});
</script>
</body>
</html>
