<?php
require_once 'core/init.php';

session_unset();
session_destroy();

header('Location: login.php?status=logout_success');
exit();
