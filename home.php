<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token()) {
        set_flash('error', 'Your session expired. Refresh the page and try again.');
        redirect('home.php');
    }

    $type = $_POST['type'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if (($type !== 'department' && $type !== 'staff') || $id < 1) {
        set_flash('error', 'The selected record could not be deleted.');
        redirect('home.php');
    }

    $table = $type === 'department' ? 'departments' : 'staff';
    $key = $type === 'department' ? 'department_id' : 'staff_id';

    try {
        $statement = $conn->prepare("DELETE FROM $table WHERE $key = ?");
        $statement->bind_param('i', $id);
        $statement->execute();

        if ($statement->affected_rows > 0) {
            set_flash('success', ucfirst($type) . ' deleted.');
        } else {
            set_flash('error', ucfirst($type) . ' was not found.');
        }
    } catch (mysqli_sql_exception $exception) {
        if ($exception->getCode() !== 1451) {
            throw $exception;
        }

        set_flash('error', 'This record is still referenced by another record and cannot be deleted.');
    }

    redirect('home.php');
}

$departmentSearch = trim((string) ($_GET['department_search'] ?? ''));
$departmentLocation = trim((string) ($_GET['department_location'] ?? ''));
$totalDepartmentCount = (int) $conn->query(
    'SELECT COUNT(*) AS total_departments FROM departments'
)->fetch_assoc()['total_departments'];

$departmentWhere = [];
$departmentParams = [];
$departmentTypes = '';

if ($departmentSearch !== '') {
    $departmentWhere[] = '(d.department_name LIKE ? OR d.location LIKE ? OR CAST(d.department_id AS CHAR) LIKE ?)';
    $departmentTypes .= 'sss';
    $departmentParams[] = '%' . $departmentSearch . '%';
    $departmentParams[] = '%' . $departmentSearch . '%';
    $departmentParams[] = '%' . $departmentSearch . '%';
}
if ($departmentLocation !== '') {
    $departmentWhere[] = 'd.location = ?';
    $departmentTypes .= 's';
    $departmentParams[] = $departmentLocation;
}

$departmentSql = 'SELECT d.department_id, d.department_name, d.location, d.annual_budget,
                         COUNT(s.staff_id) AS staff_count
                  FROM departments d
                  LEFT JOIN staff s ON s.department_id = d.department_id';
if ($departmentWhere !== []) {
    $departmentSql .= ' WHERE ' . implode(' AND ', $departmentWhere);
}
$departmentSql .= ' GROUP BY d.department_id, d.department_name, d.location, d.annual_budget
                    ORDER BY d.department_name';

$departmentStatement = $conn->prepare($departmentSql);
if ($departmentParams !== []) {
    $departmentStatement->bind_param($departmentTypes, ...$departmentParams);
}
$departmentStatement->execute();
$departments = $departmentStatement->get_result()->fetch_all(MYSQLI_ASSOC);

$locationResult = $conn->query(
    'SELECT DISTINCT location FROM departments
     WHERE location IS NOT NULL AND location <> ""
     ORDER BY location'
);
$departmentLocations = array_column(
    $locationResult->fetch_all(MYSQLI_NUM),
    0
);

$staffSearch = trim((string) ($_GET['staff_search'] ?? ''));
$staffDepartment = (int) ($_GET['staff_department'] ?? 0);
$staffGender = trim((string) ($_GET['staff_gender'] ?? ''));
$staffStatus = trim((string) ($_GET['staff_status'] ?? ''));

$summary = $conn->query(
    'SELECT COUNT(*) AS total_staff,
            SUM(is_active = 1) AS active_staff,
            SUM(salary)  AS total_payroll,
            SUM(department_id IS NULL) AS unassigned_staff,
            SUM(YEAR(hire_date) = YEAR(CURDATE())) AS new_hires_this_year
     FROM staff'
)->fetch_assoc();
$totalStaffCount = (int) $summary['total_staff'];
$staffCount = $totalStaffCount;
$activeStaff = (int) $summary['active_staff'];
$totalPayroll = (float) $summary['total_payroll'];
$unassignedStaff = (int) $summary['unassigned_staff'];
$newHiresThisYear = (int) $summary['new_hires_this_year'];
$staffWhere = [];
$staffParams = [];
$staffTypes = '';

if ($staffSearch !== '') {
    $staffWhere[] = '(s.first_name LIKE ? OR s.last_name LIKE ?
                     OR s.job_title LIKE ? OR d.department_name LIKE ?
                     OR CAST(s.staff_id AS CHAR) LIKE ?)';
    $staffTypes .= 'sssss';
    $staffSearchTerm = '%' . $staffSearch . '%';
    $staffParams[] = $staffSearchTerm;
    $staffParams[] = $staffSearchTerm;
    $staffParams[] = $staffSearchTerm;
    $staffParams[] = $staffSearchTerm;
    $staffSearchTerm = '%' . $staffSearch . '%';
    $staffParams[] = $staffSearchTerm;
}
if ($staffDepartment > 0) {
    $staffWhere[] = 's.department_id = ?';
    $staffTypes .= 'i';
    $staffParams[] = $staffDepartment;
}
if ($staffGender !== '') {
    $staffWhere[] = 's.gender = ?';
    $staffTypes .= 's';
    $staffParams[] = $staffGender;
}
if ($staffStatus === 'active' || $staffStatus === 'inactive') {
    $staffWhere[] = 's.is_active = ?';
    $staffTypes .= 'i';
    $staffParams[] = $staffStatus === 'active' ? 1 : 0;
}

$departmentFilterQuery = http_build_query(array_filter(
    [
        'department_search' => $departmentSearch,
        'department_location' => $departmentLocation,
    ],
    static fn ($value) => $value !== ''
));
$staffExportFilterQuery = http_build_query(array_filter(
    [
        'staff_search' => $staffSearch,
        'staff_department' => $staffDepartment > 0 ? (string) $staffDepartment : '',
        'staff_gender' => $staffGender,
        'staff_status' => $staffStatus,
    ],
    static fn ($value) => $value !== ''
));

if (isset($_GET['export'])) {
    $exportType = (string) $_GET['export'];
    if ($exportType !== 'departments' && $exportType !== 'staff') {
        http_response_code(400);
        exit('Invalid export type.');
    }

    if ($exportType === 'departments') {
        $exportSql = 'SELECT d.department_id, d.department_name, d.location, d.annual_budget,
                             COUNT(s.staff_id) AS staff_count
                      FROM departments d
                      LEFT JOIN staff s ON s.department_id = d.department_id';
        if ($departmentWhere !== []) {
            $exportSql .= ' WHERE ' . implode(' AND ', $departmentWhere);
        }
        $exportSql .= ' GROUP BY d.department_id, d.department_name, d.location, d.annual_budget
                        ORDER BY d.department_name';
        $exportStatement = $conn->prepare($exportSql);
        if ($departmentParams !== []) {
            $exportStatement->bind_param($departmentTypes, ...$departmentParams);
        }
        $exportStatement->execute();
        $exportRows = $exportStatement->get_result()->fetch_all(MYSQLI_NUM);
        $exportHeaders = ['Department ID', 'Department', 'Location', 'Annual budget', 'Staff count'];
    } else {
        $exportSql = 'SELECT s.staff_id, s.first_name, s.last_name, d.department_name,
                             s.job_title, s.gender,
                             CASE
                                 WHEN s.is_transgender IS NULL THEN \'Not specified\'
                                 WHEN s.is_transgender = 1 THEN \'Yes\'
                                 ELSE \'No\'
                             END AS transgender_status,
                             s.salary, s.hire_date,
                             CONCAT(m.first_name, \' \', m.last_name) AS manager_name,
                             IF(s.is_active = 1, \'Active\', \'Inactive\') AS employment_status
                      FROM staff s
                      LEFT JOIN departments d ON d.department_id = s.department_id
                      LEFT JOIN staff m ON m.staff_id = s.manager_id';
        if ($staffWhere !== []) {
            $exportSql .= ' WHERE ' . implode(' AND ', $staffWhere);
        }
        $exportSql .= ' ORDER BY s.staff_id';
        $exportStatement = $conn->prepare($exportSql);
        if ($staffParams !== []) {
            $exportStatement->bind_param($staffTypes, ...$staffParams);
        }
        $exportStatement->execute();
        $exportRows = $exportStatement->get_result()->fetch_all(MYSQLI_NUM);
        $exportHeaders = ['Staff ID', 'First name', 'Last name', 'Department', 'Job title', 'Gender', 'Transgender', 'Salary', 'Hire date', 'Manager', 'Status'];
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $exportType . '-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream.');
    }
    fputcsv($output, $exportHeaders);
    foreach ($exportRows as $row) {
        $safeRow = array_map(
            static function ($value) {
                if (is_string($value) && preg_match('/^[\t\r ]*[=+\-@]/', $value) === 1) {
                    return "'" . $value;
                }

                return $value;
            },
            $row
        );
        fputcsv($output, $safeRow);
    }
    fclose($output);
    exit;
}

$staffCountSql = 'SELECT COUNT(*) AS total_staff FROM staff s
                  LEFT JOIN departments d ON d.department_id = s.department_id';
if ($staffWhere !== []) {
    $staffCountSql .= ' WHERE ' . implode(' AND ', $staffWhere);
}
$staffCountStatement = $conn->prepare($staffCountSql);
if ($staffParams !== []) {
    $staffCountStatement->bind_param($staffTypes, ...$staffParams);
}
$staffCountStatement->execute();
$staffCount = (int) $staffCountStatement->get_result()->fetch_assoc()['total_staff'];

$pageSize = 25;
$pageCount = max(1, (int) ceil($staffCount / $pageSize));
$currentPage = max(1, min($pageCount, (int) ($_GET['page'] ?? 1)));
$offset = ($currentPage - 1) * $pageSize;
$staffFilterParams = [
    'department_search' => $departmentSearch,
    'department_location' => $departmentLocation,
    'staff_search' => $staffSearch,
    'staff_department' => $staffDepartment > 0 ? (string) $staffDepartment : '',
    'staff_gender' => $staffGender,
    'staff_status' => $staffStatus,
];
$staffFilterQuery = http_build_query(array_filter(
    $staffFilterParams,
    static fn ($value) => $value !== '' && $value !== null
));

$staffSql = 'SELECT s.staff_id, s.first_name, s.last_name, s.gender, s.is_transgender,
                    s.department_id, s.job_title, s.salary, s.hire_date, s.manager_id,
                    s.is_active, d.department_name,
                    CONCAT(m.first_name, \' \', m.last_name) AS manager_name
             FROM staff s
             LEFT JOIN departments d ON d.department_id = s.department_id
             LEFT JOIN staff m ON m.staff_id = s.manager_id';
if ($staffWhere !== []) {
    $staffSql .= ' WHERE ' . implode(' AND ', $staffWhere);
}
$staffSql .= ' ORDER BY s.staff_id LIMIT ' . $pageSize . ' OFFSET ' . $offset;

$staffStatement = $conn->prepare($staffSql);
if ($staffParams !== []) {
    $staffStatement->bind_param($staffTypes, ...$staffParams);
}
$staffStatement->execute();
$staff = $staffStatement->get_result()->fetch_all(MYSQLI_ASSOC);

$departmentOptionsResult = $conn->query(
    'SELECT department_id, department_name FROM departments ORDER BY department_name'
);
$departmentOptions = $departmentOptionsResult->fetch_all(MYSQLI_ASSOC);

$genderResult = $conn->query(
    'SELECT DISTINCT gender FROM staff
     WHERE gender IS NOT NULL AND gender <> ""
     ORDER BY gender'
);
$genderOptions = array_column(
    $genderResult->fetch_all(MYSQLI_NUM),
    0
);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f8fafc">
    <title>People overview | Northstar HR</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <div class="min-h-screen">
        <aside class="fixed inset-x-0 top-0 z-50 flex h-16 items-center justify-between gap-2 bg-slate-950 px-3 text-white shadow-lg sm:px-5 lg:inset-y-0 lg:left-0 lg:right-auto lg:h-screen lg:w-64 lg:flex-col lg:items-stretch lg:justify-start lg:gap-0 lg:border-r lg:border-white/10 lg:px-0 lg:shadow-none">
            <a href="home.php" class="flex shrink-0 items-center gap-2 py-0 sm:gap-3 lg:px-6 lg:py-6">
                <span class="grid size-9 place-items-center rounded-xl bg-indigo-500 text-lg font-bold sm:size-10">N</span>
                <span class="hidden sm:block">
                    <span class="block text-sm font-semibold tracking-wide">NORTHSTAR</span>
                    <span class="block text-xs text-slate-400">People operations</span>
                </span>
            </a>
            <nav aria-label="Primary navigation" class="flex min-w-0 items-center justify-end gap-0.5 sm:gap-1 lg:mt-8 lg:block lg:px-3 lg:pb-4">
                <a href="#overview" class="flex items-center gap-1 whitespace-nowrap rounded-lg bg-white/10 px-2 py-2 text-[11px] font-medium text-white sm:gap-2 sm:px-3 sm:text-sm lg:py-2.5">
                    <span aria-hidden="true">▦</span> Overview
                </a>
                <a href="#departments" class="mt-0 flex items-center gap-1 whitespace-nowrap rounded-lg px-2 py-2 text-[11px] text-slate-400 transition hover:bg-white/5 hover:text-white sm:gap-2 sm:px-3 sm:text-sm lg:mt-1 lg:py-2.5">
                    <span aria-hidden="true">▤</span> Departments
                </a>
                <a href="#staff" class="mt-0 flex items-center gap-1 whitespace-nowrap rounded-lg px-2 py-2 text-[11px] text-slate-400 transition hover:bg-white/5 hover:text-white sm:gap-2 sm:px-3 sm:text-sm lg:mt-1 lg:py-2.5">
                    <span aria-hidden="true">♧</span> Staff
                </a>
            </nav>
            <div class="mt-auto hidden border-t border-white/10 px-6 py-5 text-xs text-slate-500 lg:block">
                HR management dashboard
            </div>
        </aside>

        <main id="overview" class="min-w-0 flex-1 pt-16 lg:ml-64 lg:pt-0">
            <header class="border-b border-slate-200 bg-white px-5 py-5 sm:px-8">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Workspace / Overview</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">People overview</h1>
                    </div>
                    <div class="hidden items-center gap-3 sm:flex">
                        <span class="grid size-9 place-items-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">HR</span>
                        <span class="text-sm font-medium text-slate-700">HR administrator</span>
                    </div>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-8 px-5 py-8 sm:px-0">
                <?php if ($flash !== null): ?>
                    <div role="status" class="rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>">
                        <?= h($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section aria-label="HR summary" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Total staff</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $totalStaffCount ?></p>
                        <p class="mt-1 text-xs text-slate-500">Across all departments</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Active staff</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $activeStaff ?></p>
                        <p class="mt-1 text-xs text-slate-500">Currently marked active</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Departments</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $totalDepartmentCount ?></p>
                        <p class="mt-1 text-xs text-slate-500">Organization teams</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Annual payroll ($)</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= number_format($totalPayroll, 0) ?></p>
                        <p class="mt-1 text-xs text-slate-500">Sum of listed salaries</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">Unassigned staff</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $unassignedStaff ?></p>
                        <p class="mt-1 text-xs text-slate-500">Not linked to a department</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-slate-500">New hires this year</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900"><?= $newHiresThisYear ?></p>
                        <p class="mt-1 text-xs text-slate-500">Based on hire date</p>
                    </article>
                </section>

                <section id="departments" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:scroll-mt-0">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Departments</h2>
                            <p class="mt-1 text-sm text-slate-500">Manage teams, locations, and annual budgets.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="home.php?<?= h($departmentFilterQuery . ($departmentFilterQuery !== '' ? '&' : '') . 'export=departments') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Export CSV</a>
                            <a href="add.php?type=department" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                <span aria-hidden="true">+</span> Add department
                            </a>
                        </div>
                    </div>
                    <form method="get" action="home.php" class="grid gap-3 border-b border-slate-100 px-5 py-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_220px_auto] sm:px-6">
                        <input type="hidden" name="staff_search" value="<?= h($staffSearch) ?>">
                        <input type="hidden" name="staff_department" value="<?= h($staffDepartment) ?>">
                        <input type="hidden" name="staff_gender" value="<?= h($staffGender) ?>">
                        <input type="hidden" name="staff_status" value="<?= h($staffStatus) ?>">
                        <label class="text-xs font-semibold text-slate-500">
                            Search departments
                            <input type="search" name="department_search" value="<?= h($departmentSearch) ?>" placeholder="Name or location"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                        </label>
                        <label class="text-xs font-semibold text-slate-500">
                            Location
                            <select name="department_location" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500">
                                <option value="">All locations</option>
                                <?php foreach ($departmentLocations as $location): ?>
                                    <option value="<?= h($location) ?>" <?= $departmentLocation === $location ? 'selected' : '' ?>><?= h($location) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                            <a href="<?= $staffExportFilterQuery !== '' ? 'home.php?' . h($staffExportFilterQuery) : 'home.php' ?>#departments" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-6 py-3 font-semibold">Department</th>
                                    <th scope="col" class="px-6 py-3 font-semibold">Location</th>
                                    <th scope="col" class="px-6 py-3 font-semibold">Annual budget</th>
                                    <th scope="col" class="px-6 py-3 font-semibold">Staff</th>
                                    <th scope="col" class="px-6 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($departments as $department): ?>
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span class="block font-medium text-slate-900"><?= h($department['department_name']) ?></span>
                                            <span class="mt-0.5 block text-xs text-slate-500">ID <?= h($department['department_id']) ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600"><?= h($department['location']) ?></td>
                                        <td class="whitespace-nowrap px-6 py-4 text-slate-700"><?= number_format((float) $department['annual_budget'], 2) ?></td>
                                        <td class="px-6 py-4 text-slate-600"><?= h($department['staff_count']) ?></td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right">
                                            <a href="edit.php?type=department&id=<?= h($department['department_id']) ?>" class="font-medium text-indigo-600 hover:text-indigo-800">Edit</a>
                                            <form method="post" class="ml-3 inline" onsubmit="return confirm('Delete this department? It cannot be deleted while staff are assigned to it.')">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type" value="department">
                                                <input type="hidden" name="id" value="<?= h($department['department_id']) ?>">
                                                <button type="submit" class="font-medium text-rose-600 hover:text-rose-800">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($departments === []): ?>
                                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">No departments yet. Add one to get started.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="staff" class="scroll-mt-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:scroll-mt-0">
                    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Staff directory</h2>
                            <p class="mt-1 text-sm text-slate-500">Review staff details and employment status.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="home.php?<?= h($staffExportFilterQuery . ($staffExportFilterQuery !== '' ? '&' : '') . 'export=staff') ?>" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Export CSV</a>
                            <a href="add.php?type=staff" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                                <span aria-hidden="true">+</span> Add staff member
                            </a>
                        </div>
                    </div>
                    <form method="get" action="home.php" class="grid gap-3 border-b border-slate-100 px-5 py-4 md:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_180px_160px_160px_auto] sm:px-6">
                        <input type="hidden" name="department_search" value="<?= h($departmentSearch) ?>">
                        <input type="hidden" name="department_location" value="<?= h($departmentLocation) ?>">
                        <label class="text-xs font-semibold text-slate-500">
                            Search staff
                            <input type="search" name="staff_search" value="<?= h($staffSearch) ?>" placeholder="Name, title, department, or ID"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                        </label>
                        <label class="text-xs font-semibold text-slate-500">
                            Department
                            <select name="staff_department" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500">
                                <option value="0">All departments</option>
                                <?php foreach ($departmentOptions as $department): ?>
                                    <option value="<?= h($department['department_id']) ?>" <?= $staffDepartment === (int) $department['department_id'] ? 'selected' : '' ?>><?= h($department['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-slate-500">
                            Gender
                            <select name="staff_gender" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500">
                                <option value="">All genders</option>
                                <?php foreach ($genderOptions as $gender): ?>
                                    <option value="<?= h($gender) ?>" <?= $staffGender === $gender ? 'selected' : '' ?>><?= h($gender) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="text-xs font-semibold text-slate-500">
                            Status
                            <select name="staff_status" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-indigo-500">
                                <option value="">All statuses</option>
                                <option value="active" <?= $staffStatus === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $staffStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </label>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                            <a href="<?= $departmentFilterQuery !== '' ? 'home.php?' . h($departmentFilterQuery) : 'home.php' ?>#staff" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Clear</a>
                        </div>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1320px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-semibold">Staff member</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Department</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Job title</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Gender</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Transgender</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Salary</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Hire date</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Manager</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($staff as $person): ?>
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="whitespace-nowrap px-5 py-4">
                                            <span class="block font-medium text-slate-900"><?= h($person['first_name'] . ' ' . $person['last_name']) ?></span>
                                            <span class="mt-0.5 block text-xs text-slate-500">ID <?= h($person['staff_id']) ?></span>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600"><?= h($person['department_name'] ?? 'Unassigned') ?></td>
                                        <td class="px-5 py-4 text-slate-600"><?= h($person['job_title']) ?></td>
                                        <td class="px-5 py-4 text-slate-600"><?= h($person['gender']) ?></td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <?= $person['is_transgender'] === null ? 'Not specified' : ((int) $person['is_transgender'] === 1 ? 'Yes' : 'No') ?>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-700"><?= number_format((float) $person['salary'], 2) ?></td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?= h($person['hire_date']) ?></td>
                                        <td class="px-5 py-4 text-slate-600"><?= h($person['manager_name'] ?? '—') ?></td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium <?= (int) $person['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                                <?= (int) $person['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-right">
                                            <a href="edit.php?type=staff&id=<?= h($person['staff_id']) ?>" class="font-medium text-indigo-600 hover:text-indigo-800">Edit</a>
                                            <form method="post" class="ml-3 inline" onsubmit="return confirm('Delete this staff member?')">
                                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="type" value="staff">
                                                <input type="hidden" name="id" value="<?= h($person['staff_id']) ?>">
                                                <button type="submit" class="font-medium text-rose-600 hover:text-rose-800">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($staff === []): ?>
                                    <tr><td colspan="10" class="px-6 py-12 text-center text-sm text-slate-500">No staff members match these filters.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($staffCount > 0): ?>
                        <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <p>Showing <?= $offset + 1 ?>–<?= min($offset + $pageSize, $staffCount) ?> of <?= $staffCount ?> staff members</p>
                            <nav aria-label="Staff pages" class="flex items-center gap-2">
                                <?php if ($currentPage > 1): ?>
                                    <a href="?<?= h($staffFilterQuery . ($staffFilterQuery !== '' ? '&' : '') . 'page=' . ($currentPage - 1)) ?>#staff" class="rounded-lg border border-slate-300 px-3 py-2 font-medium text-slate-700 transition hover:bg-slate-50">Previous</a>
                                <?php endif; ?>
                                <span class="px-2">Page <?= $currentPage ?> of <?= $pageCount ?></span>
                                <?php if ($currentPage < $pageCount): ?>
                                    <a href="?<?= h($staffFilterQuery . ($staffFilterQuery !== '' ? '&' : '') . 'page=' . ($currentPage + 1)) ?>#staff" class="rounded-lg border border-slate-300 px-3 py-2 font-medium text-slate-700 transition hover:bg-slate-50">Next</a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    <?php endif; ?>
                </section>

                <footer class="pb-2 text-center text-xs text-slate-400">Northstar HR · People operations dashboard</footer>
            </div>
        </main>
    </div>
</body>
</html>
