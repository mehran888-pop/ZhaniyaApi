<?php
require_once dirname(__DIR__) . '/src/helpers.php';
require_once dirname(__DIR__) . '/src/auth.php';
auth_logout();
header('Location: login.php');
exit;
