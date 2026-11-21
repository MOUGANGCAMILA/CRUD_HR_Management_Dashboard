<?php
require_once __DIR__ . '/db.php';

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (($type !== 'department' && $type !== 'staff') || $id < 1) {
    exit('Record not found.');
}

$mode = 'edit';
$table = $type === 'department' ? 'departments' : 'staff';
$idColumn = $type === 'department' ? 'department_id' : 'staff_id';
$statement = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
$statement->bind_param('i', $id);
$statement->execute();
$record = $statement->get_result()->fetch_assoc();

if (!$record) {
    exit('Record not found.');
}

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
    $record = array_merge($record, $_SESSION['form_old']);
    unset($_SESSION['form_old']);
}

require __DIR__ . '/record_form.php';
