<?php
require_once __DIR__ . '/auth.php';

if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
