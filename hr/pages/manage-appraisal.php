<?php

/**
 * HR Manage Appraisal Questions Page
 * HR can create, edit, and delete appraisal questions
 */
session_start();
include '../../config.php';
include '../classes/HRAuth.php';

// Check if HR is logged in
$hr_auth = new HRAuth($conn);
if (!$hr_auth->isHRLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form submission (create/edit/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_question') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) {
            $message = 'Question title is required.';
            $message_type = 'danger';
        } else {
            $insert_query = $conn->prepare(
                "INSERT INTO training_programs (title, description, training_type, training_date, training_time, location) 
                 VALUES (?, ?, 'Appraisal', CURDATE(), '09:00:00', 'Admin')"
            );
            $insert_query->bind_param("ss", $title, $description);

            if ($insert_query->execute()) {
                $message = 'Appraisal question created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating question. Please try again.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'edit_question') {
        $question_id = (int)$_POST['question_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($title)) {
            $message = 'Question title is required.';
            $message_type = 'danger';
        } else {
            $update_query = $conn->prepare(
                "UPDATE training_programs SET title = ?, description = ? WHERE id = ? AND training_type = 'Appraisal'"
            );
            $update_query->bind_param("ssi", $title, $description, $question_id);

            if ($update_query->execute()) {
                $message = 'Appraisal question updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating question. Please try again.';
                $message_type = 'danger';
            }
        }
    } elseif ($action === 'delete_question') {
        $question_id = (int)$_POST['question_id'];

        $delete_query = $conn->prepare(
            "DELETE FROM training_programs WHERE id = ? AND training_type = 'Appraisal'"
        );
        $delete_query->bind_param("i", $question_id);

        if ($delete_query->execute()) {
            $message = 'Appraisal question deleted successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error deleting question. Please try again.';
            $message_type = 'danger';
        }
    }
}

// Get all appraisal questions
$questions_query = $conn->query(
    "SELECT id, title, description FROM training_programs WHERE training_type = 'Appraisal' ORDER BY id ASC"
);
$questions = $questions_query ? $questions_query->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appraisal Questions - HR Dashboard</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .container {
            max-width: 1000px;
        }

        /* Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 280px;
            right: 0;
            height: 70px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 999;
            transition: all 0.3s ease;
        }

        .topbar.full-width {
            left: 0;
        }

        .toggle-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
            transition: color 0.3s;
        }

        .toggle-btn:hover {
            color: #C82333;
        }

        .topbar-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 220px;
            }

            .topbar {
                left: 220px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 200px;
            }

            .topbar {
                left: 200px;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(200, 35, 51, 0.2);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin: 30px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #C82333 0%, #a01c28 100%);
            color: #fff;
            font-weight: 700;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #C82333;
            box-shadow: 0 0 0 3px rgba(200, 35, 51, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-edit {
            background: #ffc107;
            color: #333;
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-edit:hover {
            background: #ffb300;
        }

        .btn-delete {
            background: #dc3545;
            color: #fff;
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .question-item {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #C82333;
        }

        .question-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .question-description {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .question-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 2.5rem;
            color: #ddd;
            margin-bottom: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-close {
            float: right;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
            border: none;
            background: none;
        }

        .modal-close:hover {
            color: #333;
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .question-actions {
                flex-direction: column;
            }

            .btn-edit,
            .btn-delete {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar" id="topbar">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="toggle-btn" id="toggleBtn"><i class="fas fa-bars"></i></button>
            <h1 class="topbar-title">Manage Appraisal</h1>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-star"></i> Manage Appraisal Questions
                </h1>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Create New Question Section -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus"></i> Create New Appraisal Question
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_question">

                        <div class="form-group">
                            <label for="title">Question Title *</label>
                            <input type="text" class="form-control" id="title" name="title" placeholder="e.g., Job Performance" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Question Description</label>
                            <textarea class="form-control" id="description" name="description" placeholder="Provide context or guidance for this question..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save"></i> Create Question
                        </button>
                    </form>
                </div>
            </div>

            <!-- Existing Questions -->
            <h2 class="section-title">
                <i class="fas fa-list"></i> Existing Appraisal Questions (<?php echo count($questions); ?>)
            </h2>

            <?php if (empty($questions)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p><strong>No appraisal questions created yet</strong></p>
                    <p style="color: #bbb;">Create your first appraisal question using the form above.</p>
                </div>
            <?php else: ?>
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="background: linear-gradient(135deg, #C82333 0%, #a01c28 100%); color: #fff; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
                                        Q<?php echo $index + 1; ?>
                                    </span>
                                    <div class="question-title"><?php echo htmlspecialchars($question['title']); ?></div>
                                </div>
                                <?php if (!empty($question['description'])): ?>
                                    <div class="question-description">
                                        <?php echo nl2br(htmlspecialchars($question['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="question-actions">
                            <button class="btn btn-edit" onclick="openEditModal(<?php echo $question['id']; ?>, '<?php echo htmlspecialchars(addslashes($question['title'])); ?>', '<?php echo htmlspecialchars(addslashes($question['description'] ?? '')); ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-delete" onclick="openDeleteModal(<?php echo $question['id']; ?>, '<?php echo htmlspecialchars(addslashes($question['title'])); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
                <h2 style="color: #333; margin-bottom: 20px; margin-top: 0;">Edit Appraisal Question</h2>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit_question">
                    <input type="hidden" id="editQuestionId" name="question_id">

                    <div class="form-group">
                        <label for="editTitle">Question Title *</label>
                        <input type="text" class="form-control" id="editTitle" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="editDescription">Question Description</label>
                        <textarea class="form-control" id="editDescription" name="description"></textarea>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-submit" style="flex: 1;">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="button" class="btn" style="background: #e0e0e0; flex: 1;" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
                <h2 style="color: #333; margin-bottom: 20px; margin-top: 0;">Delete Question</h2>

                <p style="color: #666; margin-bottom: 20px;">
                    Are you sure you want to delete the question "<strong id="deleteQuestionTitle"></strong>"? This action cannot be undone.
                </p>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" id="deleteQuestionId" name="question_id">

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn" style="background: #dc3545; color: #fff; flex: 1;">
                            <i class="fas fa-check"></i> Yes, Delete
                        </button>
                        <button type="button" class="btn" style="background: #e0e0e0; flex: 1;" onclick="closeDeleteModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
        <script>
            function openEditModal(questionId, title, description) {
                document.getElementById('editQuestionId').value = questionId;
                document.getElementById('editTitle').value = title;
                document.getElementById('editDescription').value = description;
                document.getElementById('editModal').classList.add('show');
            }

            function closeEditModal() {
                document.getElementById('editModal').classList.remove('show');
            }

            function openDeleteModal(questionId, title) {
                document.getElementById('deleteQuestionId').value = questionId;
                document.getElementById('deleteQuestionTitle').textContent = title;
                document.getElementById('deleteModal').classList.add('show');
            }

            function closeDeleteModal() {
                document.getElementById('deleteModal').classList.remove('show');
            }

            // Close modals when clicking outside
            window.onclick = function(event) {
                const editModal = document.getElementById('editModal');
                const deleteModal = document.getElementById('deleteModal');

                if (event.target == editModal) {
                    editModal.classList.remove('show');
                }
                if (event.target == deleteModal) {
                    deleteModal.classList.remove('show');
                }
            }

            // Toggle Sidebar
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
    </div>
    </div>
</body>

</html>