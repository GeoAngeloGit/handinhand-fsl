<?php
require_once __DIR__ . '/db_connect.php';
logout_user();
header('Location: login.php');
exit;
