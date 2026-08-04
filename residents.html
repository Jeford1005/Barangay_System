<?php
/**
 * residents.php
 * Barangay Bidduang - Resident Management
 * Role: admin, staff
 */

require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_role(['admin', 'staff']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
        header('Location: residents.php');
        exit;
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $birth_date = $_POST['birth_date'] ?? '';
            $birth_place = trim($_POST['birth_place'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $civil_status = $_POST['civil_status'] ?? 'Single';
            $citizenship = trim($_POST['citizenship'] ?? 'Filipino');
            $religion = trim($_POST['religion'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $voter_status = $_POST['voter_status'] ?? 'Not Registered';
            $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
            $is_senior = isset($_POST['is_senior']) ? 1 : 0;
            $is_indigent = isset($_POST['is_indigent']) ? 1 : 0;
            $fourps_beneficiary = isset($_POST['fourps_beneficiary']) ? 1 : 0;
            $household_id = !empty($_POST['household_id']) ? $_POST['household_id'] : null;
            $purok_id = !empty($_POST['purok_id']) ? $_POST['purok_id'] : null;
            $status = $_POST['status'] ?? 'Active';

            if (!$first_name || !$last_name || !$birth_date || !$gender) {
                $_SESSION['flash_error'] = 'First Name, Last Name, Birth Date, and Gender are required.';
                header('Location: residents.php');
                exit;
            } else {
                $oldValues = null;
                if ($action === 'edit' && $id) {
                    $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldValues = $stmt->fetch();
                }

                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                    $imageInfo = @getimagesize($_FILES['photo']['tmp_name']);
                    $mime = $imageInfo['mime'] ?? '';
                    if (in_array($mime, $allowed)) {
                        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $filename = 'resident_' . ($id ?? uniqid()) . '_' . time() . '.' . $ext;
                        $dest = UPLOAD_PATH . '/photos/' . $filename;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                            $photo_path = $filename;
                        }
                    }
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, birth_place, gender, civil_status, citizenship, religion, occupation, contact_number, email, photo_path, voter_status, is_pwd, is_senior, is_indigent, fourps_beneficiary, household_id, purok_id, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$first_name, $middle_name ?: null, $last_name, $suffix ?: null, $birth_date, $birth_place ?: null, $gender, $civil_status, $citizenship, $religion ?: null, $occupation ?: null, $contact_number ?: null, $email ?: null, $photo_path, $voter_status, $is_pwd, $is_senior, $is_indigent, $fourps_beneficiary, $household_id, $purok_id, $status]);
                    $newId = $pdo->lastInsertId();
                    log_audit('create', 'resident', $newId, null, ['name' => "$first_name $last_name"]);
                    
                    $_SESSION['flash_message'] = 'Resident added successfully.';
                    header('Location: residents.php');
                    exit;
                } elseif ($action === 'edit' && $id) {
                    if (!$photo_path && $oldValues) {
                        $photo_path = $oldValues['photo_path'];
                    }
                    $stmt = $pdo->prepare("
                        UPDATE residents SET first_name=?, middle_name=?, last_name=?, suffix=?, birth_date=?, birth_place=?, gender=?, civil_status=?, citizenship=?, religion=?, occupation=?, contact_number=?, email=?, photo_path=?, voter_status=?, is_pwd=?, is_senior=?, is_indigent=?, fourps_beneficiary=?, household_id=?, purok_id=?, status=?
                        WHERE id=?
                    ");
                    $stmt->execute([$first_name, $middle_name ?: null, $last_name, $suffix ?: null, $birth_date, $birth_place ?: null, $gender, $civil_status, $citizenship, $religion ?: null, $occupation ?: null, $contact_number ?: null, $email ?: null, $photo_path, $voter_status, $is_pwd, $is_senior, $is_indigent, $fourps_beneficiary, $household_id, $purok_id, $status, $id]);
                    log_audit('update', 'resident', $id, $oldValues, ['name' => "$first_name $last_name"]);
                    
                    $_SESSION['flash_message'] = 'Resident updated successfully.';
                    header('Location: residents.php');
                    exit;
                }
            }
        } elseif ($action === 'delete' && isset($_POST['id'])) {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM residents WHERE id = ?");
            $stmt->execute([$id]);
            log_audit('delete', 'resident', $id);
            
            $_SESSION['flash_message'] = 'Resident deleted successfully.';
            header('Location: residents.php');
            exit;
        }
    }
}

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(r.first_name LIKE ? OR r.middle_name LIKE ? OR r.last_name LIKE ? OR r.contact_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $where[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM residents r WHERE $whereSql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

$stmt = $pdo->prepare("
    SELECT r.*, h.household_number, p.purok_name
    FROM residents r
    LEFT JOIN households h ON r.household_id = h.id
    LEFT JOIN puroks p ON r.purok_id = p.id
    WHERE $whereSql
    ORDER BY r.last_name ASC, r.first_name ASC
    LIMIT ? OFFSET ?
");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$residents = $stmt->fetchAll();

$households = $pdo->query("SELECT id, household_number FROM households ORDER BY household_number")->fetchAll();
$puroks = $pdo->query("SELECT id, purok_name FROM puroks ORDER BY purok_name")->fetchAll();

$currentUser = current_user();
$user = current_user();
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/img/Brgy_Bidduang.png">
    <link rel="shortcut icon" type="image/png" href="assets/img/Brgy_Bidduang.png">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents - Barangay Bidduang Portal</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= filemtime(__DIR__ . "/assets/css/dashboard.css") ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .toast-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-weight: 600;
            font-size: 14px;
            animation: slideInDown 0.3s ease-out forwards;
        }
        .toast-success { background-color: #dcfce7; color: #166534; border: 1px solid #16a34a; }
        .toast-danger { background-color: #fee2e2; color: #991b1b; border: 1px solid #c0392b; }
        .toast-close { background: none; border: none; font-size: 18px; color: inherit; cursor: pointer; margin-left: 8px; line-height: 1; }
        @keyframes slideInDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="app">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="assets/img/Brgy_Bidduang.png" alt="Barangay Bidduang Seal">
            <div class="brand-title">Barangay Bidduang<span class="brand-sub">Management Portal</span></div>
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="residents.php" class="active"><i class="fas fa-users"></i> Residents</a></li>
            <li><a href="households.php"><i class="fas fa-home"></i> Households</a></li>
            <li><a href="officials.php"><i class="fas fa-user-tie"></i> Officials</a></li>
            <li><a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a></li>
            <li><a href="blotter.php"><i class="fas fa-gavel"></i> Blotter</a></li>
            <li><a href="welfare.php"><i class="fas fa-hand-holding-heart"></i> Welfare</a></li>
            <li><a href="health.php"><i class="fas fa-heartbeat"></i> Health</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="accounts.php"><i class="fas fa-user-cog"></i> Accounts</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
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

        <div class="page-header">
            <div>
                <h1 class="page-title">Resident Management</h1>
                <p class="page-subtitle">Profiling and records of Barangay Bidduang residents</p>
            </div>
            <div class="user-badge">
                <?php echo esc(ucfirst($user['role'])); ?>
            </div>
        </div>

        <div class="metrics-grid">
            <?php
            $totalResidents = $pdo->query("SELECT COUNT(*) FROM residents WHERE status='Active'")->fetchColumn();
            $totalSeniors = $pdo->query("SELECT COUNT(*) FROM residents WHERE is_senior=1 AND status='Active'")->fetchColumn();
            $totalPWD = $pdo->query("SELECT COUNT(*) FROM residents WHERE is_pwd=1 AND status='Active'")->fetchColumn();
            $totalIndigent = $pdo->query("SELECT COUNT(*) FROM residents WHERE is_indigent=1 AND status='Active'")->fetchColumn();
            ?>
            <div class="metric-card">
                <div class="metric-icon blue"><i class="fas fa-users"></i></div>
                <div class="metric-info"><h3><?= number_format($totalResidents) ?></h3><p>Total Active Residents</p></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon orange"><i class="fas fa-user-clock"></i></div>
                <div class="metric-info"><h3><?= number_format($totalSeniors) ?></h3><p>Senior Citizens</p></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon red"><i class="fas fa-wheelchair"></i></div>
                <div class="metric-info"><h3><?= number_format($totalPWD) ?></h3><p>PWD</p></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon green"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="metric-info"><h3><?= number_format($totalIndigent) ?></h3><p>Indigent</p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="justify-content:space-between; flex-wrap:wrap; gap:12px;">
                <span>Resident Records</span>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <form method="GET" class="search-box" style="flex:1;min-width:220px;">
                        <input type="hidden" name="status" value="<?= esc($statusFilter) ?>">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name or contact..." value="<?= esc($search) ?>">
                    </form>
                    <select class="form-control" style="width:auto;min-width:150px;" onchange="window.location.href='?status='+this.value+'&search=<?= urlencode($search) ?>'">
                        <option value="">All Status</option>
                        <option value="Active" <?= $statusFilter==='Active'?'selected':'' ?>>Active</option>
                        <option value="Deceased" <?= $statusFilter==='Deceased'?'selected':'' ?>>Deceased</option>
                        <option value="Moved Out" <?= $statusFilter==='Moved Out'?'selected':'' ?>>Moved Out</option>
                    </select>
                    <button class="btn btn-primary" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Add Resident</button>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Birth Date</th>
                            <th>Purok</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($residents)): ?>
                            <tr><td colspan="7" style="text-align:center; color:#6b7280;">No residents found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($residents as $r): ?>
                                <tr>
                                    <td><?= esc($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td><?= esc(ucfirst($r['gender'])) ?></td>
                                    <td><?= !empty($r['birth_date']) ? esc(date('F j, Y', strtotime($r['birth_date']))) : 'N/A' ?></td>
                                    <td><?= esc($r['purok_name'] ?? 'N/A') ?></td>
                                    <td><?= esc($r['contact_number'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge <?= $r['status']==='Active' ? 'badge-success' : 'badge-warning' ?>">
                                            <?= esc($r['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-secondary" onclick="openEditModal(
                                            <?= (int)$r['id'] ?>,
                                            '<?= esc(addslashes($r['first_name'])) ?>',
                                            '<?= esc(addslashes($r['last_name'])) ?>',
                                            '<?= esc($r['birth_date']) ?>',
                                            '<?= esc($r['gender']) ?>',
                                            '<?= (int)($r['purok_id'] ?? 0) ?>',
                                            '<?= esc(addslashes($r['contact_number'] ?? '')) ?>',
                                            '<?= esc($r['status']) ?>'
                                        )">Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resident?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
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

    <div id="addModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2>Add Resident</h2>
                <button onclick="closeModal('addModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                <div class="form-group">
                    <label>First Name <span style="color:#c0392b;">*</span></label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color:#c0392b;">*</span></label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Birth Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="birth_date" required>
                </div>
                <div class="form-group">
                    <label>Gender <span style="color:#c0392b;">*</span></label>
                    <select name="gender" required>
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purok</label>
                    <select name="purok_id">
                        <option value="">None</option>
                        <?php foreach ($puroks as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= esc($p['purok_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact_number">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option>Active</option>
                        <option>Deceased</option>
                        <option>Moved Out</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;"><i class="fas fa-save"></i> Save</button>
            </form>
        </div>
    </div>


    <div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2>Edit Resident</h2>
                <button onclick="closeModal('editModal')" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                <div class="form-group">
                    <label>First Name <span style="color:#c0392b;">*</span></label>
                    <input type="text" name="first_name" id="editFirstName" required>
                </div>
                <div class="form-group">
                    <label>Last Name <span style="color:#c0392b;">*</span></label>
                    <input type="text" name="last_name" id="editLastName" required>
                </div>
                <div class="form-group">
                    <label>Birth Date <span style="color:#c0392b;">*</span></label>
                    <input type="date" name="birth_date" id="editBirthDate" required>
                </div>
                <div class="form-group">
                    <label>Gender <span style="color:#c0392b;">*</span></label>
                    <select name="gender" id="editGender" required>
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purok</label>
                    <select name="purok_id" id="editPurok">
                        <option value="">None</option>
                        <?php foreach ($puroks as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= esc($p['purok_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contact</label>
                    <input type="text" name="contact_number" id="editContact">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editStatus">
                        <option>Active</option>
                        <option>Deceased</option>
                        <option>Moved Out</option>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" style="width:100%;"><i class="fas fa-save"></i> Update</button>
            </form>
        </div>
    </div>

    <script>
    function openModal(id) { 
        document.getElementById(id).style.display = 'flex'; 
    }

    function closeModal(id) { 
        document.getElementById(id).style.display = 'none'; 
    }

    function openEditModal(id, first, last, birthDate, gender, purokId, contact, status) {
        document.getElementById('editId').value = id;
        document.getElementById('editFirstName').value = first;
        document.getElementById('editLastName').value = last;
        document.getElementById('editBirthDate').value = birthDate;
        
        document.getElementById('editGender').value = gender || '';
        document.getElementById('editPurok').value = (purokId && purokId > 0) ? purokId : '';
        document.getElementById('editContact').value = contact || '';
        document.getElementById('editStatus').value = status || 'Active';

        openModal('editModal');
    }

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
</body>
</html>