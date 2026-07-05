<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login');
    exit();
}

$errors = [];
$success_message = '';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    error_log('DB error in branch_selector_popup.php: ' . $e->getMessage());
    http_response_code(500);
    echo 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
    exit();
}

$redirect_default = getDashboardByRole($pdo, (string)($_SESSION['user_role'] ?? ''));
$requested_redirect = trim((string)($_POST['redirect'] ?? ($_GET['redirect'] ?? '')));
$redirect_target = $redirect_default;
if ($requested_redirect !== '' && canCurrentUserAccessDashboard($pdo, $requested_redirect)) {
    $redirect_target = $requested_redirect;
}

$branches = getAvailableBranchesForUser($pdo, (string)($_SESSION['user_id'] ?? ''));
$requires_branch = shouldRequireBranchSelection($pdo);
$csrf_token = generateCSRFToken();

if (!$requires_branch) {
    header('Location: ' . $redirect_target);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_branch'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $branch_id = trim((string)($_POST['branch_id'] ?? ''));
        if ($branch_id === '') {
            $errors[] = 'กรุณาเลือกสาขาก่อนเข้าใช้งาน';
        } elseif (!setCurrentBranchContext($pdo, $branch_id)) {
            $errors[] = 'ไม่พบสาขาที่เลือกหรือสาขาไม่พร้อมใช้งาน';
        } else {
            $success_message = 'เลือกสาขาเรียบร้อยแล้ว กำลังเข้าสู่ระบบ...';
            $js_redirect = json_encode($redirect_target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $js_branch_id = json_encode((string)($_SESSION['active_branch_id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกสาขา</title>
</head>
<body>
<script>
(function () {
    var redirectTo = <?php echo $js_redirect; ?>;
    var branchId = <?php echo $js_branch_id; ?>;

    if (window.opener && !window.opener.closed) {
        try {
            window.opener.postMessage({
                type: 'branch-selected',
                redirect: redirectTo,
                branch_id: branchId
            }, window.location.origin);
        } catch (e) {
            // fallback handled below
        }

        try {
            window.close();
            return;
        } catch (e) {
            // ignore
        }
    }

    window.location.href = redirectTo;
})();
</script>
</body>
</html>
            <?php
            exit();
        }
    }
}

$current_branch_id = getCurrentBranchId();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกสาขาที่ใช้งาน</title>
    <style>
        body {
            margin: 0;
            padding: 14px;
            background: #f4f8ff;
            font-family: Verdana, Arial, sans-serif;
            color: #102744;
        }

        .card {
            background: #ffffff;
            border: 1px solid #c7d9ec;
            box-shadow: 0 10px 24px rgba(8, 34, 64, 0.12);
            border-radius: 10px;
            padding: 14px;
            max-width: 760px;
            margin: 0 auto;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        p {
            margin: 0 0 12px;
            font-size: 13px;
            color: #355272;
        }

        .msg-error,
        .msg-success {
            border: 1px solid;
            padding: 8px 10px;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .msg-error {
            border-color: #cc3300;
            background: #ffefea;
            color: #9c2a00;
        }

        .msg-success {
            border-color: #2f8f2f;
            background: #eaffea;
            color: #216121;
        }

        .branch-list {
            border: 1px solid #dae5f0;
            border-radius: 8px;
            max-height: 380px;
            overflow: auto;
            margin-bottom: 12px;
        }

        .branch-item {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 10px;
            border-bottom: 1px solid #ebf2f8;
        }

        .branch-item:last-child {
            border-bottom: 0;
        }

        .branch-item input[type="radio"] {
            margin-top: 4px;
        }

        .branch-title {
            font-size: 14px;
            font-weight: 700;
        }

        .branch-meta {
            font-size: 12px;
            color: #4f6d8c;
            margin-top: 2px;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid #8cacbb;
            background: #dee7ec;
            color: #0f2c45;
            text-decoration: none;
            font-size: 14px;
            padding: 7px 14px;
            cursor: pointer;
            border-radius: 6px;
        }

        .btn.primary {
            background: #0078d4;
            border-color: #0068b8;
            color: #ffffff;
        }

        .btn.danger {
            background: #c62828;
            border-color: #a71919;
            color: #ffffff;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>เลือกสาขาที่ต้องการใช้งาน</h1>
    <p>หลังจากเลือกสาขาแล้ว การบันทึก แก้ไข และยกเลิกข้อมูลจะอ้างอิงตาม Branch ID ของสาขานี้</p>

    <?php if (!empty($errors)): ?>
        <div class="msg-error">
            <?php foreach ($errors as $error): ?>
                <div><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message !== ''): ?>
        <div class="msg-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_target); ?>">

        <?php if (empty($branches)): ?>
            <div class="msg-error">ยังไม่มีข้อมูลสาขาในระบบ กรุณาเพิ่มสาขาก่อนเข้าใช้งาน</div>
            <div class="actions">
                <a class="btn" href="<?php echo htmlspecialchars($redirect_default); ?>">กลับหน้าหลัก</a>
                <a class="btn danger" href="logout.php">ออกจากระบบ</a>
            </div>
        <?php else: ?>
            <div class="branch-list">
                <?php foreach ($branches as $row): ?>
                    <?php $row_branch_id = (string)($row['branch_id'] ?? ''); ?>
                    <label class="branch-item">
                        <input
                            type="radio"
                            name="branch_id"
                            value="<?php echo htmlspecialchars($row_branch_id); ?>"
                            <?php echo $current_branch_id === $row_branch_id ? 'checked' : ''; ?>
                            required
                        >
                        <span>
                            <div class="branch-title"><?php echo htmlspecialchars((string)($row['company_name'] ?? '-')); ?> (<?php echo htmlspecialchars($row_branch_id); ?>)</div>
                            <div class="branch-meta">
                                สาขาที่: <?php echo htmlspecialchars((string)($row['branch_no'] ?? '-')); ?> |
                                จังหวัด: <?php echo htmlspecialchars((string)($row['province'] ?? '-')); ?> |
                                ปีข้อมูล: <?php echo htmlspecialchars((string)($row['data_year'] ?? '-')); ?>
                            </div>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <button class="btn primary" type="submit" name="select_branch" value="1">ยืนยันสาขาที่ใช้งาน</button>
                <a class="btn danger" href="logout.php">ออกจากระบบ</a>
            </div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
