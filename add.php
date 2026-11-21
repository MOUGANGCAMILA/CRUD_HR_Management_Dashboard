<?php
require_once __DIR__ . '/db.php';

$type = $_GET['type'] ?? 'department';
if ($type !== 'department' && $type !== 'staff') {
    exit('Unknown record type.');
}

$mode = 'create';
$record = [];
$departments = [];
$genderOptions = [];

if ($type === 'staff') {
    $result = $conn->query('SELECT department_id, department_name FROM departments ORDER BY department_name');
    $departments = $result->fetch_all(MYSQLI_ASSOC);

    $result = $conn->query(
        'SELECT DISTINCT gender FROM staff
         WHERE gender IS NOT NULL AND gender <> ""
         ORDER BY gender'
    );
    $genderOptions = array_column($result->fetch_all(MYSQLI_NUM), 0);
}

if (isset($_SESSION['form_old'])) {
    $record = $_SESSION['form_old'];
    unset($_SESSION['form_old']);
}

require __DIR__ . '/record_form.php';
