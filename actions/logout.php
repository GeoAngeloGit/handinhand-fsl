<?php
require_once __DIR__ . '/../includes/db_connect.php';
logout_user();
header('Location: ../login.php');
exit;
