<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!userHasAnyRole(['admin', 'system_admin'])) {
        header("Location: ../../auth/login");
        exit();
    }

    enforceCurrentUserDashboardMenuAccess('discord', ['sidebar']);
} catch (Throwable $e) {
    error_log('Role access check failed in page_setup_discord.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}

$current_module = isset($_GET['module']) ? trim((string)$_GET['module']) : 'discord';
$csrf_token = generateCSRFToken();

$form = [
    'login_webhook_url' => '',
    'logout_webhook_url' => '',
    'user_setup_webhook_url' => '',
    'sales_followup_webhook_url' => ''
];
$errors = [];
$success_message = '';

try {
    $pdo = getDBConnection();
    ensureDiscordWebhookSettingsTable($pdo);
    $settings = getDiscordWebhookSettings($pdo);
    $form['login_webhook_url'] = (string)($settings['login'] ?? '');
    $form['logout_webhook_url'] = (string)($settings['logout'] ?? '');
    $form['user_setup_webhook_url'] = (string)($settings['user_setup'] ?? '');
    $form['sales_followup_webhook_url'] = (string)($settings['sales_followup'] ?? '');
} catch (Throwable $e) {
    error_log('Discord webhook setup load error: ' . $e->getMessage());
    $errors[] = 'ไม่สามารถโหลดค่าการตั้งค่า Discord Webhook ได้';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_discord_webhooks'])) {
    $form['login_webhook_url'] = trim((string)($_POST['login_webhook_url'] ?? ''));
    $form['logout_webhook_url'] = trim((string)($_POST['logout_webhook_url'] ?? ''));
    $form['user_setup_webhook_url'] = trim((string)($_POST['user_setup_webhook_url'] ?? ''));
    $form['sales_followup_webhook_url'] = trim((string)($_POST['sales_followup_webhook_url'] ?? ''));

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    }

    if ($form['login_webhook_url'] !== '' && !filter_var($form['login_webhook_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Webhook สำหรับ Login ต้องเป็น URL ที่ถูกต้อง';
    }

    if ($form['logout_webhook_url'] !== '' && !filter_var($form['logout_webhook_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Webhook สำหรับ Logout ต้องเป็น URL ที่ถูกต้อง';
    }

    if ($form['user_setup_webhook_url'] !== '' && !filter_var($form['user_setup_webhook_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Webhook สำหรับการตั้งค่า User ต้องเป็น URL ที่ถูกต้อง';
    }

    if ($form['sales_followup_webhook_url'] !== '' && !filter_var($form['sales_followup_webhook_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Webhook สำหรับงาน Follow-up ค้าง ต้องเป็น URL ที่ถูกต้อง';
    }

    if (empty($errors) && isset($pdo) && $pdo instanceof PDO) {
        try {
            $updated_by = (string)($_SESSION['user_id'] ?? 'system');
            setDiscordWebhookUrl($pdo, 'login', $form['login_webhook_url'], $updated_by);
            setDiscordWebhookUrl($pdo, 'logout', $form['logout_webhook_url'], $updated_by);
            setDiscordWebhookUrl($pdo, 'user_setup', $form['user_setup_webhook_url'], $updated_by);
            setDiscordWebhookUrl($pdo, 'sales_followup', $form['sales_followup_webhook_url'], $updated_by);
            $success_message = 'บันทึกการตั้งค่า Discord Webhook เรียบร้อยแล้ว';
        } catch (Throwable $e) {
            error_log('Discord webhook setup save error: ' . $e->getMessage());
            $errors[] = 'ไม่สามารถบันทึกการตั้งค่า Discord Webhook ได้';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Webhook Setup</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fc;
            color: #1d2a44;
            padding: 20px;
        }

        .layout {
            max-width: 980px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #d8e3f2;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(6, 45, 92, 0.08);
            overflow: hidden;
        }

        .card-head {
            padding: 16px 18px;
            border-bottom: 1px solid #e6edf8;
            background: linear-gradient(90deg, #eef4ff 0%, #f8fbff 100%);
        }

        .card-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f2b53;
        }

        .card-sub {
            margin: 6px 0 0;
            color: #516e95;
            font-size: 13px;
            line-height: 1.5;
        }

        .card-body {
            padding: 16px 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert.success {
            background: #edf8ef;
            border: 1px solid #b9e4c3;
            color: #176432;
        }

        .alert.error {
            background: #fff1f1;
            border: 1px solid #f5c1c1;
            color: #992d2d;
        }

        .field-group {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        .field-label {
            font-size: 13px;
            font-weight: 700;
            color: #143861;
        }

        .field-help {
            font-size: 12px;
            color: #5d789f;
            margin-top: -2px;
        }

        .field-input {
            width: 100%;
            border: 1px solid #c6d7ee;
            border-radius: 9px;
            font-size: 13px;
            padding: 10px 11px;
            color: #163861;
            background: #fbfdff;
        }

        .field-input:focus {
            outline: none;
            border-color: #2b88d8;
            box-shadow: 0 0 0 3px rgba(43, 136, 216, 0.15);
            background: #ffffff;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 4px;
        }

        .btn {
            height: 38px;
            padding: 0 14px;
            border-radius: 9px;
            border: 1px solid #2b88d8;
            background: #2b88d8;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #1f75c2;
            border-color: #1f75c2;
        }

        .spec-list {
            margin: 0;
            padding-left: 18px;
            color: #365a84;
            font-size: 13px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="layout">
    <section class="card">
        <div class="card-head">
            <h1 class="card-title">Discord Webhook Setup</h1>
            <p class="card-sub">ตั้งค่า Webhook แยกสำหรับ Login, Logout, การตั้งค่า User และงาน Follow-up ค้าง เพื่อส่งแจ้งเตือนการทำงานในระบบ</p>
        </div>
        <div class="card-body">
            <?php if ($success_message !== ''): ?>
                <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars((string)$error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="field-group">
                    <label class="field-label" for="login_webhook_url">Webhook Login</label>
                    <p class="field-help">ใช้สำหรับแจ้งเตือนเมื่อผู้ใช้ Login สำเร็จ</p>
                    <input
                        class="field-input"
                        id="login_webhook_url"
                        name="login_webhook_url"
                        type="url"
                        placeholder="https://discord.com/api/webhooks/..."
                        value="<?php echo htmlspecialchars($form['login_webhook_url']); ?>"
                    >
                </div>

                <div class="field-group">
                    <label class="field-label" for="logout_webhook_url">Webhook Logout</label>
                    <p class="field-help">ใช้สำหรับแจ้งเตือนเมื่อผู้ใช้ Logout สำเร็จ</p>
                    <input
                        class="field-input"
                        id="logout_webhook_url"
                        name="logout_webhook_url"
                        type="url"
                        placeholder="https://discord.com/api/webhooks/..."
                        value="<?php echo htmlspecialchars($form['logout_webhook_url']); ?>"
                    >
                </div>

                <div class="field-group">
                    <label class="field-label" for="user_setup_webhook_url">Webhook การตั้งค่า User</label>
                    <p class="field-help">ใช้สำหรับแจ้งเตือนทุกการกระทำในหน้าแก้ไขผู้ใช้งาน (edit_user.php)</p>
                    <input
                        class="field-input"
                        id="user_setup_webhook_url"
                        name="user_setup_webhook_url"
                        type="url"
                        placeholder="https://discord.com/api/webhooks/..."
                        value="<?php echo htmlspecialchars($form['user_setup_webhook_url']); ?>"
                    >
                </div>

                <div class="field-group">
                    <label class="field-label" for="sales_followup_webhook_url">Webhook งาน Follow-up ค้าง</label>
                    <p class="field-help">ใช้สำหรับแจ้งเตือนสรุปรายวันของลูกค้าที่ Follow-up เกินกำหนดในทีมขาย</p>
                    <input
                        class="field-input"
                        id="sales_followup_webhook_url"
                        name="sales_followup_webhook_url"
                        type="url"
                        placeholder="https://discord.com/api/webhooks/..."
                        value="<?php echo htmlspecialchars($form['sales_followup_webhook_url']); ?>"
                    >
                </div>

                <div class="actions">
                    <button class="btn" type="submit" name="save_discord_webhooks" value="1">บันทึกการตั้งค่า</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title" style="font-size:17px;">ข้อมูลที่ระบบจะส่งไป Discord</h2>
        </div>
        <div class="card-body">
            <ol class="spec-list">
                <li>ชื่อ</li>
                <li>role</li>
                <li>วันที่ทำรายการ</li>
                <li>เวลาที่ทำรายการ</li>
                <li>วันที่ทำรายการสำเร็จ</li>
                <li>เวลาที่ทำรายการสำเร็จ</li>
                <li>เวลาทำรายเฉลี่ย</li>
                <li>ประเภทอุปกรณ์</li>
                <li>ip</li>
                <li>รายการที่กระทำ</li>
                <li>สถานะของรายการ</li>
            </ol>
        </div>
    </section>
</div>
</body>
</html>


