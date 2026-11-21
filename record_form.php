<?php
$isDepartment = $type === 'department';
$title = $mode === 'create'
    ? 'Add ' . ($isDepartment ? 'department' : 'staff member')
    : 'Edit ' . ($isDepartment ? 'department' : 'staff member');
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> | Northstar HR</title>
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
            <nav aria-label="Primary navigation" class="flex min-w-0 items-center justify-end lg:mt-8 lg:px-3 lg:pb-4">
                <a href="home.php" class="whitespace-nowrap rounded-lg px-2 py-2 text-[11px] text-slate-400 transition hover:bg-white/5 hover:text-white sm:px-3 sm:text-sm lg:text-sm">← Back to overview</a>
            </nav>
            <div class="mt-auto hidden border-t border-white/10 px-6 py-5 text-xs text-slate-500 lg:block">HR management dashboard</div>
        </aside>

        <main class="min-w-0 flex-1 pt-16 lg:ml-64 lg:pt-0">
            <header class="border-b border-slate-200 bg-white px-5 py-5 sm:px-8">
                <div class="mx-auto max-w-4xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">Workspace / <?= $isDepartment ? 'Departments' : 'Staff' ?></p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900"><?= h($title) ?></h1>
                </div>
            </header>

            <div class="mx-auto max-w-4xl px-5 py-8 sm:px-8">
                <?php if ($flash !== null): ?>
                    <div role="alert" class="mb-5 rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>">
                        <?= h($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form action="save.php" method="post" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="mode" value="<?= h($mode) ?>">
                    <input type="hidden" name="type" value="<?= h($type) ?>">
                    <?php if ($mode === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= h($record[$isDepartment ? 'department_id' : 'staff_id']) ?>">
                    <?php endif; ?>

                    <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
                        <h2 class="font-semibold text-slate-900"><?= $isDepartment ? 'Department details' : 'Personal and role details' ?></h2>
                        <p class="mt-1 text-sm text-slate-500">
                            <?= $isDepartment ? 'Enter the department name, location, and annual budget.' : 'Fields marked with an asterisk are required.' ?>
                        </p>
                    </div>

                    <div class="grid gap-x-6 gap-y-5 px-5 py-6 sm:grid-cols-2 sm:px-7">
                        <?php if ($isDepartment): ?>
                            <?php if ($mode === 'create'): ?>
                                <label class="block text-sm font-medium text-slate-700">
                                    Department ID <span class="text-rose-500">*</span>
                                    <input type="number" name="department_id" min="1" required value="<?= h($record['department_id'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                </label>
                            <?php else: ?>
                                <div class="text-sm font-medium text-slate-700">
                                    Department ID
                                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2.5 text-slate-500"><?= h($record['department_id']) ?> <span class="text-xs">(record ID cannot be changed)</span></p>
                                </div>
                            <?php endif; ?>
                            <label class="block text-sm font-medium text-slate-700">
                                Department name <span class="text-rose-500">*</span>
                                <input type="text" name="department_name" maxlength="100" required value="<?= h($record['department_name'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Location <span class="text-rose-500">*</span>
                                <input type="text" name="location" maxlength="100" required value="<?= h($record['location'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Annual budget <span class="text-rose-500">*</span>
                                <input type="number" name="annual_budget" min="0" step="0.01" required value="<?= h($record['annual_budget'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                        <?php else: ?>
                            <?php if ($mode === 'create'): ?>
                                <label class="block text-sm font-medium text-slate-700">
                                    Staff ID <span class="text-rose-500">*</span>
                                    <input type="number" name="staff_id" min="1" required value="<?= h($record['staff_id'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                </label>
                            <?php else: ?>
                                <div class="text-sm font-medium text-slate-700">
                                    Staff ID
                                    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2.5 text-slate-500"><?= h($record['staff_id']) ?> <span class="text-xs">(record ID cannot be changed)</span></p>
                                </div>
                            <?php endif; ?>
                            <label class="block text-sm font-medium text-slate-700">
                                First name <span class="text-rose-500">*</span>
                                <input type="text" name="first_name" maxlength="50" required value="<?= h($record['first_name'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Last name <span class="text-rose-500">*</span>
                                <input type="text" name="last_name" maxlength="50" required value="<?= h($record['last_name'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Gender <span class="text-rose-500">*</span>
                                <select name="gender" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                    <option value="" disabled <?= empty($record['gender']) ? 'selected' : '' ?>>Select a gender</option>
                                    <?php foreach ($genderOptions as $genderOption): ?>
                                        <option value="<?= h($genderOption) ?>" <?= (string) ($record['gender'] ?? '') === (string) $genderOption ? 'selected' : '' ?>>
                                            <?= h($genderOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Transgender <span class="text-rose-500">*</span>
                                <select name="is_transgender" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                    <option value="" disabled <?= !isset($record['is_transgender']) || $record['is_transgender'] === '' ? 'selected' : '' ?>>Select yes or no</option>
                                    <option value="1" <?= (string) ($record['is_transgender'] ?? '') === '1' ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= (string) ($record['is_transgender'] ?? '') === '0' ? 'selected' : '' ?>>No</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Department
                                <select name="department_id" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?= h($department['department_id']) ?>" <?= (string) ($record['department_id'] ?? '') === (string) $department['department_id'] ? 'selected' : '' ?>>
                                            <?= h($department['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Job title <span class="text-rose-500">*</span>
                                <input type="text" name="job_title" maxlength="100" required value="<?= h($record['job_title'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Salary <span class="text-rose-500">*</span>
                                <input type="number" name="salary" min="0" step="0.01" required value="<?= h($record['salary'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Hire date <span class="text-rose-500">*</span>
                                <input type="date" name="hire_date" required value="<?= h($record['hire_date'] ?? '') ?>" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Manager
                                <input type="hidden" name="manager_id" id="manager-id" value="<?= h($record['manager_id'] ?? '') ?>">
                                <input type="search" id="manager-search" autocomplete="off" placeholder="Search by name, title, or ID"
                                       aria-controls="manager-results" aria-expanded="false"
                                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                <div id="manager-results" role="listbox" class="relative z-10 hidden max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg"></div>
                                <p id="manager-selection" class="mt-1 text-xs font-normal text-slate-500">Leave blank if this staff member has no manager. Search for at least two characters.</p>
                                <button type="button" id="clear-manager" class="mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-800">Clear manager</button>
                            </label>
                            <label class="block text-sm font-medium text-slate-700">
                                Employment status <span class="text-rose-500">*</span>
                                <select name="is_active" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100">
                                    <option value="1" <?= (string) ($record['is_active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (string) ($record['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
                        <a href="home.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                            <?= $mode === 'create' ? 'Create record' : 'Save changes' ?>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <?php if (!$isDepartment): ?>
        <script>
            (() => {
                for (const select of document.querySelectorAll('select[name="gender"], select[name="is_transgender"]')) {
                    const updatePlaceholderColor = () => {
                        select.classList.toggle('text-slate-400', select.value === '');
                        select.classList.toggle('text-slate-800', select.value !== '');
                    };
                    select.addEventListener('change', updatePlaceholderColor);
                    updatePlaceholderColor();
                }

                const search = document.getElementById('manager-search');
                const managerId = document.getElementById('manager-id');
                const results = document.getElementById('manager-results');
                const selection = document.getElementById('manager-selection');
                const clearButton = document.getElementById('clear-manager');
                const excludeId = <?= $mode === 'edit' ? (int) $record['staff_id'] : 0 ?>;
                let activeRequest;

                const hideResults = () => {
                    results.replaceChildren();
                    results.classList.add('hidden');
                    search.setAttribute('aria-expanded', 'false');
                };

                const showError = () => {
                    hideResults();
                    selection.textContent = 'Could not load staff matches. Please try again.';
                    selection.classList.add('text-rose-600');
                };

                const selectManager = (person) => {
                    managerId.value = person.staff_id;
                    search.value = `${person.first_name} ${person.last_name}`;
                    selection.textContent = `${person.job_title} · Staff ID ${person.staff_id}`;
                    selection.classList.remove('text-rose-600');
                    hideResults();
                };

                const renderResults = (people) => {
                    results.replaceChildren();
                    if (people.length === 0) {
                        selection.textContent = 'No matching staff members found.';
                        results.classList.add('hidden');
                        search.setAttribute('aria-expanded', 'false');
                        return;
                    }

                    for (const person of people) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.setAttribute('role', 'option');
                        option.className = 'block w-full border-b border-slate-100 px-3 py-2 text-left last:border-0 hover:bg-indigo-50';
                        option.textContent = `${person.first_name} ${person.last_name} — ${person.job_title} (#${person.staff_id})`;
                        option.addEventListener('click', () => selectManager(person));
                        results.append(option);
                    }
                    results.classList.remove('hidden');
                    search.setAttribute('aria-expanded', 'true');
                };

                search.addEventListener('input', async () => {
                    managerId.value = '';
                    selection.textContent = 'Leave blank if this staff member has no manager. Search for at least two characters.';
                    selection.classList.remove('text-rose-600');
                    if (activeRequest) {
                        activeRequest.abort();
                    }

                    const query = search.value.trim();
                    if (query.length < 2) {
                        hideResults();
                        return;
                    }

                    activeRequest = new AbortController();
                    try {
                        const response = await fetch(`manager_search.php?q=${encodeURIComponent(query)}&exclude=${excludeId}`, {
                            signal: activeRequest.signal,
                            headers: { Accept: 'application/json' }
                        });
                        if (!response.ok) {
                            throw new Error('Manager search failed.');
                        }
                        renderResults(await response.json());
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            showError();
                        }
                    }
                });

                clearButton.addEventListener('click', () => {
                    managerId.value = '';
                    search.value = '';
                    selection.textContent = 'No manager selected.';
                    selection.classList.remove('text-rose-600');
                    hideResults();
                });

                document.addEventListener('click', (event) => {
                    if (!results.contains(event.target) && event.target !== search) {
                        hideResults();
                    }
                });

                if (managerId.value) {
                    fetch(`manager_search.php?id=${encodeURIComponent(managerId.value)}&exclude=${excludeId}`, {
                        headers: { Accept: 'application/json' }
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('Manager lookup failed.');
                            }
                            return response.json();
                        })
                        .then((people) => {
                            if (people.length > 0) {
                                selectManager(people[0]);
                            } else {
                                managerId.value = '';
                                selection.textContent = 'The previously selected manager could not be found.';
                            }
                        })
                        .catch(showError);
                }
            })();
        </script>
    <?php endif; ?>
</body>
</html>
