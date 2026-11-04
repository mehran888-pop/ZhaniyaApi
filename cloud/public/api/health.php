<?php
require_once dirname(__DIR__, 2) . '/src/helpers.php';
require_once dirname(__DIR__, 2) . '/src/db.php';

json_out([
    'ok' => true,
    'service' => 'zhaniya-cloud',
    'time' => gmdate('c'),
    'db' => config('db_driver'),
]);
