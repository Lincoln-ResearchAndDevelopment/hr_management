<?php
// Handle Application Withdrawal
session_start();
include 'config.php';
include 'classes/Auth.php';

$auth = new Auth($conn);

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getCurrentUser();
$app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;

if ($app_id > 0) {
    // Verify that the application belongs to the user and get job_vacancy_id
    $verify_query = $conn->prepare(
        "SELECT job_vacancy_id FROM job_applications WHERE id = ? AND user_id = ?"
    );
    $verify_query->bind_param("ii", $app_id, $user['id']);
    $verify_query->execute();
    $result = $verify_query->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $job_vacancy_id = $row['job_vacancy_id'];

        // Delete the application
        $delete_query = $conn->prepare(
            "DELETE FROM job_applications WHERE id = ? AND user_id = ?"
        );
        $delete_query->bind_param("ii", $app_id, $user['id']);

        if ($delete_query->execute()) {
            // Decrement the applicants_count in job_vacancies table
            $update_count = $conn->prepare(
                "UPDATE job_vacancies SET applicants_count = applicants_count - 1 WHERE id = ?"
            );
            $update_count->bind_param("i", $job_vacancy_id);
            $update_count->execute();

            header('Location: dashboard.php?success=Application withdrawn successfully');
            exit;
        }
    }
}

// If something goes wrong, redirect back to dashboard
header('Location: dashboard.php?error=Failed to withdraw application');
exit;
