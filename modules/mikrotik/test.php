<?php
require_once __DIR__ . '/../../system/RouterosApi.php';

$host = 'sg-03.tunnel.web.id:6156';
$username = 'app-manajemen';
$password = 'qw3rty;';

$api = new RouterosApi();
try {
    $api->connect($host, 8728, false);
    $api->login($username, $password);
    $rows = $api->comm('/system/identity/print');
    var_dump($rows);
    $api->close();
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
