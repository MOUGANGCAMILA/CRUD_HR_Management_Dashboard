<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) {
    exit('Invalid form submission. Go back and try again.');
}

$type = $_POST['type'] ?? '';
$mode = $_POST['mode'] ?? '';
$id = (int) ($_POST['id'] ?? 0);

if (($type !== 'department' && $type !== 'staff') || ($mode !== 'create' && $mode !== 'edit')) {
    exit('Invalid form.');
}

$back = $mode === 'edit'
    ? 'edit.php?type=' . urlencode($type) . '&id=' . $id
    : 'add.php?type=' . urlencode($type);

if ($type === 'department') {
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $name = trim($_POST['department_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $budget = trim($_POST['annual_budget'] ?? '');

    if ($mode === 'create' && $departmentId < 1) {
        $error = 'Enter a positive department ID.';
    } elseif ($name === '' || $location === '' || !is_numeric($budget) || $budget < 0) {
        $error = 'Enter a name, location, and a valid non-negative budget.';
    } elseif ($mode === 'create') {
        $statement = $conn->prepare(
            'INSERT INTO departments (department_id, department_name, location, annual_budget) VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param('isss', $departmentId, $name, $location, $budget);
    } else {
        $departmentId = $id;
        $statement = $conn->prepare(
            'UPDATE departments SET department_name = ?, location = ?, annual_budget = ? WHERE department_id = ?'
        );
        $statement->bind_param('sssi', $name, $location, $budget, $departmentId);
    }
} else {
    $staffId = $mode === 'edit' ? $id : (int) ($_POST['staff_id'] ?? 0);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $transgenderValue = $_POST['is_transgender'] ?? '';
    $transgender = $transgenderValue === '1' ? 1 : ($transgenderValue === '0' ? 0 : null);
    $departmentId = ($_POST['department_id'] ?? '') === '' ? null : (int) $_POST['department_id'];
    $jobTitle = trim($_POST['job_title'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $hireDate = $_POST['hire_date'] ?? '';
    $managerId = ($_POST['manager_id'] ?? '') === '' ? null : (int) $_POST['manager_id'];
    $active = (int) ($_POST['is_active'] ?? 0);

    if ($mode === 'create' && $staffId < 1) {
        $error = 'Enter a positive staff ID.';
    } elseif ($firstName === '' || $lastName === '' || $gender === '' || $jobTitle === '') {
        $error = 'First name, last name, gender, and job title are required.';
    } elseif ($transgender === null) {
        $error = 'Choose Yes or No for the transgender field.';
    } elseif (!is_numeric($salary) || $salary < 0 || strtotime($hireDate) === false) {
        $error = 'Enter a valid non-negative salary and hire date.';
    } elseif ($active !== 0 && $active !== 1) {
        $error = 'Choose a valid employment status.';
    } elseif ($managerId === $staffId && $managerId !== null) {
        $error = 'A staff member cannot be their own manager.';
    } elseif ($mode === 'create') {
        $statement = $conn->prepare(
            'INSERT INTO staff
             (staff_id, first_name, last_name, gender, is_transgender, department_id,
              job_title, salary, hire_date, manager_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param(
            'isssiisssii',
            $staffId,
            $firstName,
            $lastName,
            $gender,
            $transgender,
            $departmentId,
            $jobTitle,
            $salary,
            $hireDate,
            $managerId,
            $active
        );
    } else {
        $staffId = $id;
        $statement = $conn->prepare(
            'UPDATE staff SET first_name = ?, last_name = ?, gender = ?, is_transgender = ?,
             department_id = ?, job_title = ?, salary = ?, hire_date = ?, manager_id = ?, is_active = ?
             WHERE staff_id = ?'
        );
        $statement->bind_param(
            'sssiisssiii',
            $firstName,
            $lastName,
            $gender,
            $transgender,
            $departmentId,
            $jobTitle,
            $salary,
            $hireDate,
            $managerId,
            $active,
            $staffId
        );
    }
}

if (isset($error)) {
    $_SESSION['form_old'] = $_POST;
    set_flash('error', $error);
    redirect($back);
}

try {
    $statement->execute();
} catch (mysqli_sql_exception $exception) {
    if ($exception->getCode() !== 1062 && $exception->getCode() !== 1452) {
        throw $exception;
    }

    $_SESSION['form_old'] = $_POST;
    set_flash('error', 'That ID is already in use, or a selected department or manager does not exist.');
    redirect($back);
}

set_flash('success', ucfirst($type) . ($mode === 'create' ? ' added.' : ' updated.'));
redirect('home.php');
