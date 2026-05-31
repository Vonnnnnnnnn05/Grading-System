<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
session_destroy();
header('Location: /grading-system/index.php');
exit;
