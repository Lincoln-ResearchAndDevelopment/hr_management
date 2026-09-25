<?php
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

// Check HR authentication
$hrAuth = new HRAuth($conn);
$current_hr = $hrAuth->getCurrentHR();
if (!$current_hr) {
    header('Location: ../login.php');
    exit;
}

$user = $current_hr;
$page_title = 'Leave Types';

$message = '';
$message_type = 'info';

function leave_type_input($post)
{
    $name = trim($post['name'] ?? '');
    $description = trim($post['description'] ?? '');
    $allocation_unit = ($post['allocation_unit'] ?? 'days') === 'weeks' ? 'weeks' : 'days';
    $is_paid = isset($post['is_paid']) ? 1 : 0;
    $requires_approval = isset($post['requires_approval']) ? 1 : 0;
    $is_unlimited = isset($post['is_unlimited']) ? 1 : 0;
    $is_one_time = isset($post['is_one_time']) ? 1 : 0;
    $gender_specific = in_array($post['gender_specific'] ?? 'none', ['none', 'female', 'male'], true)
        ? $post['gender_specific']
        : 'none';

    $days_allocated = $is_unlimited ? 0 : max(0, intval($post['days_allocated'] ?? 0));
    $min_days = ($is_unlimited || trim($post['min_days'] ?? '') === '') ? null : max(0, intval($post['min_days']));
    $max_days = ($is_unlimited || trim($post['max_days'] ?? '') === '') ? null : max(0, intval($post['max_days']));

    return compact(
        'name',
        'description',
        'allocation_unit',
        'is_paid',
        'requires_approval',
        'is_unlimited',
        'is_one_time',
        'gender_specific',
        'days_allocated',
        'min_days',
        'max_days'
    );
}

// Keep this year's staff allocations in sync after HR changes a type's day count.
function sync_current_year_allocations($conn, $leave_type_id, $days_allocated)
{
    $year = (int) date('Y');
    $update = $conn->prepare(
        "UPDATE leave_allocations
         SET days_allocated = ?,
             days_remaining = GREATEST(? - days_used, 0),
             is_exhausted = IF(GREATEST(? - days_used, 0) <= 0, 1, 0)
         WHERE leave_type_id = ? AND year = ?"
    );
    $update->bind_param('iiiii', $days_allocated, $days_allocated, $days_allocated, $leave_type_id, $year);
    $update->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_leave_type') {
        $data = leave_type_input($_POST);

        if ($data['name'] === '') {
            $message = 'Please provide a name for the leave type.';
            $message_type = 'danger';
        } elseif ($data['min_days'] !== null && $data['max_days'] !== null && $data['min_days'] > $data['max_days']) {
            $message = 'Minimum days cannot be greater than maximum days.';
            $message_type = 'danger';
        } else {
            $insert = $conn->prepare(
                "INSERT INTO leave_types
                    (name, description, days_allocated, allocation_unit, min_days, max_days,
                     is_paid, requires_approval, is_unlimited, is_one_time, gender_specific, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $insert->bind_param(
                'ssisiiiiiis',
                $data['name'],
                $data['description'],
                $data['days_allocated'],
                $data['allocation_unit'],
                $data['min_days'],
                $data['max_days'],
                $data['is_paid'],
                $data['requires_approval'],
                $data['is_unlimited'],
                $data['is_one_time'],
                $data['gender_specific']
            );

            if ($insert->execute()) {
                $message = 'Leave type added successfully.';
                $message_type = 'success';
            } elseif ($conn->errno === 1062) {
                $message = 'A leave type with that name already exists.';
                $message_type = 'danger';
            } else {
                $message = 'Failed to add leave type: ' . htmlspecialchars($conn->error);
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'update_leave_type') {
        $leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
        $data = leave_type_input($_POST);

        if ($leave_type_id <= 0 || $data['name'] === '') {
            $message = 'Please provide a name for the leave type.';
            $message_type = 'danger';
        } elseif ($data['min_days'] !== null && $data['max_days'] !== null && $data['min_days'] > $data['max_days']) {
            $message = 'Minimum days cannot be greater than maximum days.';
            $message_type = 'danger';
        } else {
            $update = $conn->prepare(
                "UPDATE leave_types
                 SET name = ?, description = ?, days_allocated = ?, allocation_unit = ?,
                     min_days = ?, max_days = ?, is_paid = ?, requires_approval = ?,
                     is_unlimited = ?, is_one_time = ?, gender_specific = ?
                 WHERE id = ?"
            );
            $update->bind_param(
                'ssisiiiiiisi',
                $data['name'],
                $data['description'],
                $data['days_allocated'],
                $data['allocation_unit'],
                $data['min_days'],
                $data['max_days'],
                $data['is_paid'],
                $data['requires_approval'],
                $data['is_unlimited'],
                $data['is_one_time'],
                $data['gender_specific'],
                $leave_type_id
            );

            if ($update->execute()) {
                sync_current_year_allocations($conn, $leave_type_id, $data['days_allocated']);
                $message = 'Leave type updated. Staff balances for this year have been adjusted to match.';
                $message_type = 'success';
            } elseif ($conn->errno === 1062) {
                $message = 'A leave type with that name already exists.';
                $message_type = 'danger';
            } else {
                $message = 'Failed to update leave type: ' . htmlspecialchars($conn->error);
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'toggle_active') {
        $leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
        if ($leave_type_id > 0) {
            $toggle = $conn->prepare("UPDATE leave_types SET is_active = NOT is_active WHERE id = ?");
            $toggle->bind_param('i', $leave_type_id);
            if ($toggle->execute()) {
                $message = 'Leave type status updated.';
                $message_type = 'success';
            }
        }
    } elseif ($action === 'delete_leave_type') {
        $leave_type_id = (int) ($_POST['leave_type_id'] ?? 0);
        if ($leave_type_id > 0) {
            $usage_check = $conn->prepare("SELECT COUNT(*) as cnt FROM leave_requests WHERE leave_type_id = ?");
            $usage_check->bind_param('i', $leave_type_id);
            $usage_check->execute();
            $in_use = (int) $usage_check->get_result()->fetch_assoc()['cnt'];

            if ($in_use > 0) {
                $message = 'This leave type has existing leave requests and cannot be deleted. Deactivate it instead so it no longer shows for staff.';
                $message_type = 'danger';
            } else {
                $delete = $conn->prepare("DELETE FROM leave_types WHERE id = ?");
                $delete->bind_param('i', $leave_type_id);
                if ($delete->execute()) {
                    $message = 'Leave type deleted successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to delete leave type: ' . htmlspecialchars($conn->error);
                    $message_type = 'danger';
                }
            }
        }
    }
}

$leave_types_result = $conn->query("SELECT * FROM leave_types ORDER BY is_active DESC, name ASC");
$leave_types = $leave_types_result ? $leave_types_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Types - HR Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .leave-container {
            position: relative;
            overflow: hidden;
            padding: 36px 40px;
            background: linear-gradient(135deg, #C82333 0%, #7a1420 100%);
            border-radius: 16px;
            margin-bottom: 30px;
            color: white;
        }

        .leave-container::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .leave-container h2 {
            position: relative;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .leave-container p {
            position: relative;
        }

        .form-section {
            background: white;
            padding: 34px;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(20, 20, 43, 0.06);
            margin-bottom: 26px;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .form-section h3 {
            color: #1a1a1a;
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section h3 i {
            color: #C82333;
        }

        .field-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .premium-input {
            width: 100%;
            border: 1.5px solid #e6e6ea;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #222;
            background: #fafafb;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .premium-input:focus {
            outline: none;
            border-color: #C82333;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(200, 35, 51, 0.08);
        }

        select.premium-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath fill='%23999' d='M0 0l6 8 6-8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        .btn-premium {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            border: none;
            padding: 13px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(200, 35, 51, 0.28);
            color: #fff;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .section-header h3 {
            margin: 0;
            padding: 0;
            border: none;
        }

        .history-count {
            font-size: 0.8rem;
            font-weight: 600;
            color: #999;
            background: #f5f5f7;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .form-check-label {
            font-size: 0.9rem;
            color: #333;
        }

        .alert-custom {
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
        }

        .badge-days {
            background: #e7f3ff;
            color: #0c5faa;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-unlimited {
            background: #eafaf1;
            color: #1e8449;
        }

        .badge-inactive {
            background: #f5f5f7;
            color: #888;
        }

        .gender-tag {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 6px;
        }

        .gender-tag.female {
            background: #ffe4ec;
            color: #c2185b;
        }

        .gender-tag.male {
            background: #e3f0ff;
            color: #1565c0;
        }

        .row-inactive {
            opacity: 0.55;
        }

        .btn-pill {
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 12px;
        }

        .btn-icon {
            width: 34px;
            height: 34px;
            padding: 0;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .btn-delete {
            color: #dc3545;
            border-color: #f3d1d5;
            background: #fff;
        }

        .btn-delete:hover {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-deactivate:hover {
            background: #ffc107;
            border-color: #ffc107;
            color: #333;
        }

        .btn-activate:hover {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }
    </style>
</head>

<body>
    <?php include '../components/sidebar.php'; ?>
    <?php include '../components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="leave-container">
                <h2><i class="fas fa-calendar-day"></i> Leave Types</h2>
                <p style="opacity: 0.9;">Set how many days each leave type allocates, add new leave types, or deactivate ones you no longer use. Changes apply to staff balances immediately.</p>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-custom" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Add Leave Type Form -->
            <div class="form-section">
                <h3><i class="fas fa-plus-circle"></i> Add Leave Type</h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_leave_type">

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="field-label">Leave Type Name</div>
                            <input type="text" class="premium-input" name="name" placeholder="e.g., Annual Leave" required>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="field-label">Days Allocated</div>
                            <input type="number" min="0" class="premium-input" name="days_allocated" value="0" required>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="field-label">Gender Restriction</div>
                            <select class="premium-input" name="gender_specific">
                                <option value="none">Everyone</option>
                                <option value="female">Female staff only</option>
                                <option value="male">Male staff only</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="field-label">Minimum Days <span class="text-muted" style="font-weight: 400;">(optional)</span></div>
                            <input type="number" min="0" class="premium-input" name="min_days" placeholder="e.g., 1">
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="field-label">Maximum Days <span class="text-muted" style="font-weight: 400;">(optional)</span></div>
                            <input type="number" min="0" class="premium-input" name="max_days" placeholder="e.g., 14">
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="field-label">Description <span class="text-muted" style="font-weight: 400;">(optional)</span></div>
                            <input type="text" class="premium-input" name="description" placeholder="Short note about this leave type">
                        </div>
                    </div>

                    <div class="mb-4 d-flex flex-wrap gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_paid" id="add_is_paid" checked>
                            <label class="form-check-label" for="add_is_paid">Paid leave</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_approval" id="add_requires_approval" checked>
                            <label class="form-check-label" for="add_requires_approval">Requires approval</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_unlimited" id="add_is_unlimited">
                            <label class="form-check-label" for="add_is_unlimited">Unlimited (e.g., unpaid leave)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_one_time" id="add_is_one_time">
                            <label class="form-check-label" for="add_is_one_time">One-time use only (e.g., marriage leave)</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-premium">
                        <i class="fas fa-save"></i> Add Leave Type
                    </button>
                </form>
            </div>

            <!-- Leave Types List -->
            <div class="form-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> All Leave Types</h3>
                    <span class="history-count"><?php echo count($leave_types); ?> type<?php echo count($leave_types) === 1 ? '' : 's'; ?></span>
                </div>

                <?php if (empty($leave_types)): ?>
                    <p class="text-muted">No leave types configured yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Days</th>
                                    <th>Min / Max</th>
                                    <th>Rules</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_types as $lt): ?>
                                    <tr class="<?php echo $lt['is_active'] ? '' : 'row-inactive'; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($lt['name']); ?></strong>
                                            <?php if ($lt['gender_specific'] !== 'none'): ?>
                                                <span class="gender-tag <?php echo htmlspecialchars($lt['gender_specific']); ?>"><?php echo htmlspecialchars($lt['gender_specific']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($lt['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($lt['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($lt['is_unlimited']): ?>
                                                <span class="badge-days badge-unlimited">Unlimited</span>
                                            <?php else: ?>
                                                <span class="badge-days"><?php echo (int) $lt['days_allocated']; ?> <?php echo htmlspecialchars($lt['allocation_unit']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($lt['is_unlimited']): ?>
                                                &mdash;
                                            <?php else: ?>
                                                <?php echo $lt['min_days'] !== null ? (int) $lt['min_days'] : '—'; ?> / <?php echo $lt['max_days'] !== null ? (int) $lt['max_days'] : '—'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $lt['is_paid'] ? '<span class="badge bg-light text-dark border">Paid</span>' : '<span class="badge bg-light text-dark border">Unpaid</span>'; ?>
                                            <?php if ($lt['is_one_time']): ?><span class="badge bg-light text-dark border">One-time</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge-days <?php echo $lt['is_active'] ? '' : 'badge-inactive'; ?>">
                                                <?php echo $lt['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-icon btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $lt['id']; ?>" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="leave_type_id" value="<?php echo $lt['id']; ?>">
                                                    <?php if ($lt['is_active']): ?>
                                                        <button type="submit" class="btn btn-icon btn-outline-secondary btn-deactivate" title="Deactivate">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" class="btn btn-icon btn-outline-secondary btn-activate" title="Activate">
                                                            <i class="fas fa-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                                <form method="POST" action=""
                                                    onsubmit="return confirm('Delete this leave type permanently? This only works if no staff have ever requested it.');">
                                                    <input type="hidden" name="action" value="delete_leave_type">
                                                    <input type="hidden" name="leave_type_id" value="<?php echo $lt['id']; ?>">
                                                    <button type="submit" class="btn btn-icon btn-delete" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $lt['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="update_leave_type">
                                                    <input type="hidden" name="leave_type_id" value="<?php echo $lt['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit "<?php echo htmlspecialchars($lt['name']); ?>"</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <div class="field-label">Leave Type Name</div>
                                                                <input type="text" class="premium-input" name="name" value="<?php echo htmlspecialchars($lt['name']); ?>" required>
                                                            </div>
                                                            <div class="col-md-3 mb-3">
                                                                <div class="field-label">Days Allocated</div>
                                                                <input type="number" min="0" class="premium-input" name="days_allocated" value="<?php echo (int) $lt['days_allocated']; ?>" required>
                                                            </div>
                                                            <div class="col-md-3 mb-3">
                                                                <div class="field-label">Gender Restriction</div>
                                                                <select class="premium-input" name="gender_specific">
                                                                    <option value="none" <?php echo $lt['gender_specific'] === 'none' ? 'selected' : ''; ?>>Everyone</option>
                                                                    <option value="female" <?php echo $lt['gender_specific'] === 'female' ? 'selected' : ''; ?>>Female staff only</option>
                                                                    <option value="male" <?php echo $lt['gender_specific'] === 'male' ? 'selected' : ''; ?>>Male staff only</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-3 mb-3">
                                                                <div class="field-label">Minimum Days</div>
                                                                <input type="number" min="0" class="premium-input" name="min_days" value="<?php echo $lt['min_days'] !== null ? (int) $lt['min_days'] : ''; ?>">
                                                            </div>
                                                            <div class="col-md-3 mb-3">
                                                                <div class="field-label">Maximum Days</div>
                                                                <input type="number" min="0" class="premium-input" name="max_days" value="<?php echo $lt['max_days'] !== null ? (int) $lt['max_days'] : ''; ?>">
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <div class="field-label">Description</div>
                                                                <input type="text" class="premium-input" name="description" value="<?php echo htmlspecialchars($lt['description'] ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-4">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_paid" id="edit_is_paid<?php echo $lt['id']; ?>" <?php echo $lt['is_paid'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="edit_is_paid<?php echo $lt['id']; ?>">Paid leave</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="requires_approval" id="edit_requires_approval<?php echo $lt['id']; ?>" <?php echo $lt['requires_approval'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="edit_requires_approval<?php echo $lt['id']; ?>">Requires approval</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_unlimited" id="edit_is_unlimited<?php echo $lt['id']; ?>" <?php echo $lt['is_unlimited'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="edit_is_unlimited<?php echo $lt['id']; ?>">Unlimited</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_one_time" id="edit_is_one_time<?php echo $lt['id']; ?>" <?php echo $lt['is_one_time'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="edit_is_one_time<?php echo $lt['id']; ?>">One-time use only</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-premium"><i class="fas fa-save"></i> Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleBtn = document.getElementById('toggleBtn');
        const sidebar = document.getElementById('sidebar');
        const topbar = document.getElementById('topbar');
        const mainContent = document.getElementById('mainContent');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                topbar.classList.toggle('full-width');
                mainContent.classList.toggle('full-width');
            });
        }
    </script>
</body>

</html>
