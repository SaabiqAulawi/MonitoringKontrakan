<?php
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
verifyCsrf();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
