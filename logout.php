<?php
require_once __DIR__ . '/core/init.php';

session_unset();
session_destroy();

header('Location: ' . BASE_URL . 'login.php?status=logout_success');
exit();
