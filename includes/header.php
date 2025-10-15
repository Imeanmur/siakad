<?php
require_once __DIR__ . '/../core/init.php';

if (!isset($_SESSION['user_id'])) {
            // If in the middle of OTP, force to verify page
            if (isset($_SESSION['pending_user_id'])) {
                        header('Location: ' . BASE_URL . 'verify_otp.php');
                        exit();
            }
            header('Location: ' . BASE_URL . 'login.php');
            exit();
}

$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . 'login.php?status=timeout');
            exit();
}
$_SESSION['last_activity'] = time();

$user_role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $page_title ?? 'Dashboard SIAKAD'; ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>

<body>
            <div class="d-flex" id="wrapper">