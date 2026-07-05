<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login');
    exit();
}

if (!userHasAnyRole(['admin', 'system_admin'])) {
    header('Location: ../../auth/login');
    exit();
}

enforceCurrentUserDashboardMenuAccess('menu-config', ['top_nav', 'sidebar']);

$csrf_token = generateCSRFToken();
$errors = [];
$success = '';
$action = (string)($_POST['action'] ?? '');

$pdo = null;
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    $errors[] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลสำหรับ audit log ได้';
}

$currentRegistry = getDashboardMenuRegistry();
$currentRules = getDashboardRoleMenuRules();
$selectedRole = normalizeRoleKeyForMenuConfig((string)($_POST['role_key'] ?? $_GET['role'] ?? ''));
$maxConfigJsonBytes = 1024 * 1024; // 1 MB hard limit per payload.

$roleLabels = getDashboardRoleLabels($pdo instanceof PDO ? $pdo : null);
foreach (array_keys($currentRules) as $roleKey) {
    if (!isset($roleLabels[$roleKey])) {
        $roleLabels[$roleKey] = formatRoleLabelFromKey($roleKey);
    }
}
$editableRoleKeys = array_values(array_unique(array_merge(array_keys($currentRules), array_keys($roleLabels))));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    }

    if (empty($errors) && ($action === 'export_current' || $action === 'export_default')) {
        $mode = $action === 'export_default' ? 'default' : 'effective';
        $payload = getDashboardMenuExportPayload($mode);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            $errors[] = 'ไม่สามารถสร้างไฟล์ export ได้';
            if ($pdo instanceof PDO) {
                logDashboardMenuAuditEvent($pdo, $action, 'failed', 'Export failed', ['mode' => $mode]);
            }
        } else {
            if ($pdo instanceof PDO) {
                logDashboardMenuAuditEvent($pdo, $action, 'success', 'Export ' . $mode . ' config', ['mode' => $mode]);
            }
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="dashboard_menu_' . $mode . '_' . gmdate('Ymd_His') . '.json"');
            echo $json;
            exit();
        }
    }

    if (empty($errors) && $action === 'save_role_access') {
        $roleKey = normalizeRoleKeyForMenuConfig((string)($_POST['role_key'] ?? ''));
        if ($roleKey === '') {
            $errors[] = 'กรุณาเลือก Role ที่ต้องการแก้สิทธิ์';
        } elseif (!in_array($roleKey, $editableRoleKeys, true)) {
            $errors[] = 'Role ที่เลือกไม่อยู่ในรายการที่อนุญาตให้แก้ไข';
        } else {
            $nextRules = $currentRules;
            $nextRules[$roleKey] = [
                'top_nav' => [],
                'sidebar' => [],
                'footer' => [],
            ];

            foreach (['top_nav', 'sidebar', 'footer'] as $section) {
                $postedKeys = (array)($_POST['role_' . $section] ?? []);
                $validKeys = array_keys((array)($currentRegistry[$section] ?? []));
                $sectionKeys = [];

                foreach ($postedKeys as $menuKey) {
                    $menuKey = trim((string)$menuKey);
                    if ($menuKey !== '' && in_array($menuKey, $validKeys, true)) {
                        $sectionKeys[] = $menuKey;
                    }
                }

                $nextRules[$roleKey][$section] = array_values(array_unique($sectionKeys));
            }

            $saveError = '';
            $saved = saveDashboardMenuAdminOverrides([
                'registry' => $currentRegistry,
                'rules' => $nextRules,
            ], $saveError);

            if (!$saved) {
                $errors[] = $saveError !== '' ? $saveError : 'ไม่สามารถบันทึกสิทธิ์ role ได้';
                if ($pdo instanceof PDO) {
                    logDashboardMenuAuditEvent($pdo, 'save_role_access', 'failed', 'Save role access failed', [
                        'role' => $roleKey,
                        'error' => $saveError,
                    ]);
                }
            } else {
                $success = 'บันทึกสิทธิ์เมนูของ role เรียบร้อยแล้ว: ' . $roleKey;
                if ($pdo instanceof PDO) {
                    logDashboardMenuAuditEvent($pdo, 'save_role_access', 'success', 'Saved role access', [
                        'role' => $roleKey,
                        'top_nav_count' => count($nextRules[$roleKey]['top_nav']),
                        'sidebar_count' => count($nextRules[$roleKey]['sidebar']),
                        'footer_count' => count($nextRules[$roleKey]['footer']),
                    ]);
                }

                $selectedRole = $roleKey;
                $currentRegistry = getDashboardMenuRegistry();
                $currentRules = getDashboardRoleMenuRules();
            }
        }
    }

    if (empty($errors) && $action === 'save') {
        $registryJson = trim((string)($_POST['registry_json'] ?? ''));
        $rulesJson = trim((string)($_POST['rules_json'] ?? ''));

        if (strlen($registryJson) > $maxConfigJsonBytes || strlen($rulesJson) > $maxConfigJsonBytes) {
            $errors[] = 'ขนาด JSON เกินกำหนด (สูงสุด 1 MB ต่อช่อง)';
        }

        $decodedRegistry = json_decode($registryJson, true);
        if (!is_array($decodedRegistry)) {
            $errors[] = 'Registry JSON ไม่ถูกต้อง';
        }

        $decodedRules = json_decode($rulesJson, true);
        if (!is_array($decodedRules)) {
            $errors[] = 'Rules JSON ไม่ถูกต้อง';
        }

        if (empty($errors)) {
            $saveError = '';
            $saved = saveDashboardMenuAdminOverrides([
                'registry' => $decodedRegistry,
                'rules' => $decodedRules,
            ], $saveError);

            if (!$saved) {
                $errors[] = $saveError !== '' ? $saveError : 'ไม่สามารถบันทึกการตั้งค่าได้';
                if ($pdo instanceof PDO) {
                    logDashboardMenuAuditEvent($pdo, 'save', 'failed', 'Save config failed', ['error' => $saveError]);
                }
            } else {
                $success = 'บันทึก Dashboard Menu Config เรียบร้อยแล้ว';
                if ($pdo instanceof PDO) {
                    logDashboardMenuAuditEvent($pdo, 'save', 'success', 'Saved dashboard menu config', [
                        'registry_sections' => array_keys($decodedRegistry),
                        'rules_count' => count($decodedRules),
                    ]);
                }
                $currentRegistry = getDashboardMenuRegistry();
                $currentRules = getDashboardRoleMenuRules();
            }
        }
    }

    if (empty($errors) && $action === 'import') {
        $importRaw = trim((string)($_POST['import_json'] ?? ''));

        if (isset($_FILES['import_file']) && is_array($_FILES['import_file']) && (int)($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpFile = (string)($_FILES['import_file']['tmp_name'] ?? '');
            $fileSize = (int)($_FILES['import_file']['size'] ?? 0);
            if ($fileSize > $maxConfigJsonBytes) {
                $errors[] = 'ไฟล์นำเข้าใหญ่เกินกำหนด (สูงสุด 1 MB)';
            }
            if ($tmpFile !== '' && is_file($tmpFile) && is_uploaded_file($tmpFile)) {
                $fileRaw = file_get_contents($tmpFile);
                if (is_string($fileRaw) && trim($fileRaw) !== '') {
                    $importRaw = $fileRaw;
                }
            } elseif ($tmpFile !== '') {
                $errors[] = 'ไฟล์นำเข้าไม่ถูกต้อง';
            }
        }

        if (strlen($importRaw) > $maxConfigJsonBytes) {
            $errors[] = 'ข้อมูลนำเข้าใหญ่เกินกำหนด (สูงสุด 1 MB)';
        }

        if ($importRaw === '') {
            $errors[] = 'กรุณาใส่ JSON หรือเลือกไฟล์นำเข้า';
        }

        $decoded = null;
        if (empty($errors)) {
            $decoded = json_decode($importRaw, true);
            if (!is_array($decoded)) {
                $errors[] = 'Import JSON ไม่ถูกต้อง';
            }
        }

        if (empty($errors)) {
            $registry = $decoded['registry'] ?? null;
            $rules = $decoded['rules'] ?? null;

            if (!is_array($registry) || !is_array($rules)) {
                $errors[] = 'Import JSON ต้องมี key ชื่อ registry และ rules';
            } else {
                $saveError = '';
                $saved = saveDashboardMenuAdminOverrides([
                    'registry' => $registry,
                    'rules' => $rules,
                ], $saveError);

                if (!$saved) {
                    $errors[] = $saveError !== '' ? $saveError : 'ไม่สามารถนำเข้าการตั้งค่าได้';
                    if ($pdo instanceof PDO) {
                        logDashboardMenuAuditEvent($pdo, 'import', 'failed', 'Import config failed', ['error' => $saveError]);
                    }
                } else {
                    $success = 'นำเข้า Dashboard Menu Config เรียบร้อยแล้ว';
                    if ($pdo instanceof PDO) {
                        logDashboardMenuAuditEvent($pdo, 'import', 'success', 'Imported dashboard menu config', [
                            'rules_count' => count($rules),
                        ]);
                    }
                    $currentRegistry = getDashboardMenuRegistry();
                    $currentRules = getDashboardRoleMenuRules();
                }
            }
        }
    }

    if (empty($errors) && $action === 'reset') {
        $resetError = '';
        $cleared = clearDashboardMenuAdminOverrides($resetError);
        if (!$cleared) {
            $errors[] = $resetError !== '' ? $resetError : 'ไม่สามารถรีเซ็ตการตั้งค่าได้';
            if ($pdo instanceof PDO) {
                logDashboardMenuAuditEvent($pdo, 'reset', 'failed', 'Reset config failed', ['error' => $resetError]);
            }
        } else {
            $success = 'รีเซ็ตกลับค่าเริ่มต้นเรียบร้อยแล้ว';
            if ($pdo instanceof PDO) {
                logDashboardMenuAuditEvent($pdo, 'reset', 'success', 'Reset dashboard menu config', []);
            }
            $currentRegistry = getDashboardMenuRegistry();
            $currentRules = getDashboardRoleMenuRules();
        }
    }
}

$registryJsonOutput = json_encode($currentRegistry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$rulesJsonOutput = json_encode($currentRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$validationIssues = getDashboardMenuValidationIssues($currentRegistry, $currentRules);
$overridePath = getDashboardMenuAdminConfigPath();
$hasOverride = is_file($overridePath);
$auditLogs = $pdo instanceof PDO ? getDashboardMenuAuditLogs($pdo, 25) : [];

if ($selectedRole === '' || !isset($currentRules[$selectedRole])) {
    $selectedRole = isset($currentRules['admin'])
        ? 'admin'
        : ((array_key_first($currentRules) !== null) ? (string)array_key_first($currentRules) : 'default');
}

$selectedRoleRules = $currentRules[$selectedRole] ?? [
    'top_nav' => [],
    'sidebar' => [],
    'footer' => [],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Menu Config</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fc;
            color: #1d2a44;
            padding: 20px;
        }
        .wrap { max-width: 1240px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }
        .card {
            background: #fff;
            border: 1px solid #d8e3f2;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(6, 45, 92, 0.08);
            overflow: hidden;
        }
        .card-head {
            padding: 14px 16px;
            border-bottom: 1px solid #e6edf8;
            background: linear-gradient(90deg, #eef4ff 0%, #f8fbff 100%);
        }
        .card-title { margin: 0; font-size: 20px; font-weight: 800; color: #0f2b53; }
        .card-sub { margin: 6px 0 0; color: #516e95; font-size: 13px; line-height: 1.5; }
        .card-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .field { display: flex; flex-direction: column; gap: 8px; }
        .label { font-size: 13px; font-weight: 700; color: #143861; }
        .textarea {
            width: 100%; min-height: 420px; border: 1px solid #c6d7ee; border-radius: 10px;
            font-family: Consolas, 'Courier New', monospace; font-size: 12px; line-height: 1.45;
            padding: 10px; color: #163861; background: #fbfdff; resize: vertical;
        }
        .textarea.compact { min-height: 130px; }
        .textarea:focus { outline: none; border-color: #2b88d8; box-shadow: 0 0 0 3px rgba(43,136,216,0.15); background: #fff; }
        .input-file { width: 100%; font-size: 13px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            height: 38px; padding: 0 14px; border-radius: 9px; border: 1px solid #2b88d8;
            background: #2b88d8; color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
        }
        .btn:hover { background: #1f75c2; border-color: #1f75c2; }
        .btn.secondary { background: #fff; color: #2b88d8; }
        .btn.secondary:hover { background: #eef5ff; }
        .btn.danger { border-color: #d32f2f; color: #d32f2f; background: #fff; }
        .btn.danger:hover { background: #ffefef; }
        .alert { border-radius: 10px; padding: 10px 12px; font-size: 13px; line-height: 1.5; }
        .alert.success { background: #edf8ef; border: 1px solid #b9e4c3; color: #176432; }
        .alert.error { background: #fff1f1; border: 1px solid #f5c1c1; color: #992d2d; }
        .meta { font-size: 12px; color: #5f789f; }
        .issue-list { margin: 0; padding-left: 16px; color: #8b2c2c; font-size: 13px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e6edf8; padding: 8px; text-align: left; }
        th { background: #f6f9ff; color: #304f76; font-weight: 700; }
        .mono { font-family: Consolas, 'Courier New', monospace; }
        .role-picker { display: flex; gap: 8px; flex-wrap: wrap; }
        .role-pill {
            display: inline-flex; align-items: center; height: 34px; padding: 0 12px;
            border: 1px solid #b8cae5; border-radius: 9px; background: #fff;
            color: #1e4f86; font-size: 12px; font-weight: 700; text-decoration: none;
        }
        .role-pill.active { background: #2b88d8; border-color: #2b88d8; color: #fff; }
        .perm-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .perm-card { border: 1px solid #d5e2f3; border-radius: 10px; overflow: hidden; background: #fdfefe; }
        .perm-card-head { padding: 10px 12px; background: #eff5ff; border-bottom: 1px solid #dce8f8; }
        .perm-card-title { margin: 0; font-size: 13px; font-weight: 800; color: #18467b; text-transform: uppercase; letter-spacing: .2px; }
        .perm-list { padding: 10px 12px; display: flex; flex-direction: column; gap: 9px; max-height: 300px; overflow: auto; }
        .perm-item { display: flex; align-items: flex-start; gap: 8px; }
        .perm-item input { margin-top: 2px; }
        .perm-item-text { display: flex; flex-direction: column; gap: 2px; }
        .perm-main { font-size: 13px; font-weight: 700; color: #173e67; }
        .perm-sub { font-size: 11px; color: #6a84a8; }
        @media (max-width: 980px) {
            .grid { grid-template-columns: 1fr; }
            .perm-grid { grid-template-columns: 1fr; }
            .textarea { min-height: 280px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <section class="card">
        <div class="card-head">
            <h1 class="card-title">Dashboard Menu Config (Admin)</h1>
            <p class="card-sub">แก้เมนูตามสิทธิ์, Import/Export และติดตามประวัติการแก้ไขได้จากหน้านี้</p>
        </div>
        <div class="card-body">
            <?php if ($success !== ''): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars((string)$error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="meta">Override file: <?php echo htmlspecialchars($overridePath); ?> | Status: <?php echo $hasOverride ? 'CUSTOM' : 'DEFAULT'; ?></div>

            <?php if (!empty($validationIssues)): ?>
                <div class="alert error">
                    <div><strong>พบคีย์เมนูไม่ตรงกัน:</strong></div>
                    <ul class="issue-list">
                        <?php foreach ($validationIssues as $issue): ?>
                            <li><?php echo htmlspecialchars((string)$issue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="actions">
                <form method="post" style="display:inline-flex; gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <button class="btn secondary" type="submit" name="action" value="export_current">Export Current</button>
                    <button class="btn secondary" type="submit" name="action" value="export_default">Export Default</button>
                </form>
                <a class="btn secondary" href="menuadmin.php?module=menuconfig" target="_top" style="display:inline-flex; align-items:center;">Back to Dashboard</a>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Role Permission Builder</h2>
            <p class="card-sub">เลือก role แล้วติ๊กเมนูที่อนุญาตได้ทันที ระบบจะบันทึกลงไฟล์กลางอัตโนมัติ</p>
        </div>
        <div class="card-body">
            <div class="role-picker">
                <?php foreach ($roleLabels as $roleKey => $roleLabel): ?>
                    <?php $isActiveRole = ($roleKey === $selectedRole); ?>
                    <a class="role-pill<?php echo $isActiveRole ? ' active' : ''; ?>" href="?role=<?php echo urlencode((string)$roleKey); ?>">
                        <?php echo htmlspecialchars((string)$roleLabel); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="meta">กำลังแก้สิทธิ์ role: <?php echo htmlspecialchars((string)$selectedRole); ?></div>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="save_role_access">
                <input type="hidden" name="role_key" value="<?php echo htmlspecialchars((string)$selectedRole); ?>">

                <div class="perm-grid">
                    <?php foreach (['top_nav' => 'Top Navigation', 'sidebar' => 'Sidebar', 'footer' => 'Footer'] as $section => $sectionLabel): ?>
                        <?php $allowedKeys = (array)($selectedRoleRules[$section] ?? []); ?>
                        <?php $registryItems = (array)($currentRegistry[$section] ?? []); ?>
                        <div class="perm-card">
                            <div class="perm-card-head">
                                <h3 class="perm-card-title"><?php echo htmlspecialchars($sectionLabel); ?></h3>
                            </div>
                            <div class="perm-list">
                                <?php foreach ($registryItems as $itemKey => $item): ?>
                                    <?php
                                        $itemLabel = trim((string)($item['label'] ?? $item['tip'] ?? $itemKey));
                                        $itemSub = trim((string)($item['sub'] ?? $item['href'] ?? ''));
                                        $isChecked = in_array((string)$itemKey, $allowedKeys, true);
                                    ?>
                                    <label class="perm-item">
                                        <input
                                            type="checkbox"
                                            name="role_<?php echo htmlspecialchars($section); ?>[]"
                                            value="<?php echo htmlspecialchars((string)$itemKey); ?>"
                                            <?php echo $isChecked ? 'checked' : ''; ?>
                                        >
                                        <span class="perm-item-text">
                                            <span class="perm-main"><?php echo htmlspecialchars($itemLabel); ?> (<?php echo htmlspecialchars((string)$itemKey); ?>)</span>
                                            <?php if ($itemSub !== ''): ?>
                                                <span class="perm-sub"><?php echo htmlspecialchars($itemSub); ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Save Role Permissions</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Import Config</h2>
            <p class="card-sub">นำเข้าจากไฟล์ JSON หรือวาง JSON (ต้องมี key: registry และ rules)</p>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="field">
                    <label class="label" for="import_file">Import File (.json)</label>
                    <input class="input-file" id="import_file" type="file" name="import_file" accept="application/json,.json">
                </div>
                <div class="field">
                    <label class="label" for="import_json">หรือวาง Import JSON</label>
                    <textarea class="textarea compact" id="import_json" name="import_json" placeholder="{ \"registry\": {...}, \"rules\": {...} }"></textarea>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" name="action" value="import">Import Config</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Edit Config JSON</h2>
            <p class="card-sub">แก้ค่า Registry และ Rules แล้วบันทึก หรือรีเซ็ตกลับค่าเริ่มต้น</p>
        </div>
        <div class="card-body">
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="grid">
                    <div class="field">
                        <label class="label" for="registry_json">Registry JSON</label>
                        <textarea id="registry_json" class="textarea mono" name="registry_json"><?php echo htmlspecialchars((string)$registryJsonOutput); ?></textarea>
                    </div>
                    <div class="field">
                        <label class="label" for="rules_json">Rules JSON</label>
                        <textarea id="rules_json" class="textarea mono" name="rules_json"><?php echo htmlspecialchars((string)$rulesJsonOutput); ?></textarea>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" name="action" value="save">Save Config</button>
                    <button class="btn danger" type="submit" name="action" value="reset" onclick="return confirm('ยืนยันรีเซ็ตกลับค่าเริ่มต้น?')">Reset to Default</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Role Matrix</h2>
            <p class="card-sub">ภาพรวมสิทธิ์เมนูของทุก role (อ่านอย่างเดียว)</p>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th style="width: 160px;">Role</th>
                        <th>Top Navigation</th>
                        <th>Sidebar</th>
                        <th>Footer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currentRules as $roleKey => $sections): ?>
                        <?php
                            $roleLabel = (string)($roleLabels[$roleKey] ?? formatRoleLabelFromKey((string)$roleKey));
                            $topKeys = (array)($sections['top_nav'] ?? []);
                            $sideKeys = (array)($sections['sidebar'] ?? []);
                            $footKeys = (array)($sections['footer'] ?? []);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($roleLabel); ?></strong><br>
                                <span class="meta"><?php echo htmlspecialchars((string)$roleKey); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars(implode(', ', $topKeys)); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $sideKeys)); ?></td>
                            <td><?php echo htmlspecialchars(implode(', ', $footKeys)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Audit Log</h2>
            <p class="card-sub">ประวัติการแก้ไขเมนูล่าสุดจากฝั่ง admin</p>
        </div>
        <div class="card-body">
            <table>
                <thead>
                    <tr>
                        <th style="width: 160px;">Time</th>
                        <th style="width: 120px;">User</th>
                        <th style="width: 120px;">Role</th>
                        <th style="width: 120px;">Action</th>
                        <th style="width: 90px;">Status</th>
                        <th>Summary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="6">No audit logs yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($log['created_at'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($log['actor_user_id'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($log['actor_role'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($log['action'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($log['status'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)($log['summary'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>