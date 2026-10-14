<?php
require_once __DIR__ . '/../api/auth.php';

logout_user();
header('Location: login.php');
exit;
