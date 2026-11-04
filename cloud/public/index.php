<?php
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/auth.php';
auth_start();
header('Location: ' . (auth_user() ? 'dashboard.php' : 'login.php'));
exit;
