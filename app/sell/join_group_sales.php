<?php
require_once '../../auth/config.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login');
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!userHasAnyRole(['sell_car', 'employee'])) {
        header('Location: ../../auth/login');
        exit();
    }

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/sell/join_group_sales.php'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in join_group_sales.php: ' . $e->getMessage());
    header('Location: ../../auth/login');
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeInviteCode($value) {
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', '', $value) ?? $value;
    return $value;
}

function formatDateTimeValue($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y H:i', $timestamp);
}

function normalizeStatus($status) {
    $status = strtolower(trim((string)$status));
    if (in_array($status, ['pending', 'active', 'suspended'], true)) {
        return $status;
    }

    return 'active';
}

function getStatusLabel($status) {
    $normalized = normalizeStatus($status);
    if ($normalized === 'pending') {
        return 'รออนุมัติ';
    }

    return $normalized === 'suspended' ? 'ระงับ' : 'ใช้งาน';
}

function ensureSalesGroupTables(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_group_invites (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id VARCHAR(30) NOT NULL,
            group_name VARCHAR(150) NOT NULL,
            invite_code VARCHAR(40) NOT NULL,
            manager_user_id VARCHAR(50) NOT NULL,
            status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sales_group_invite_code (invite_code),
            KEY idx_sales_group_manager_branch (manager_user_id, branch_id),
            KEY idx_sales_group_branch_status (branch_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_group_members (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id INT UNSIGNED NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            member_user_id VARCHAR(50) NOT NULL,
            member_title VARCHAR(100) NOT NULL DEFAULT 'Sales',
            status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'active',
            created_by VARCHAR(50) NOT NULL,
            updated_by VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sales_group_member (group_id, member_user_id),
            KEY idx_sales_group_member_branch (branch_id, member_user_id),
            CONSTRAINT fk_sales_group_members_group
                FOREIGN KEY (group_id) REFERENCES sales_group_invites(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Backward compatibility: upgrade existing enum without dropping data.
    $member_status_column_stmt = $pdo->query("SHOW COLUMNS FROM sales_group_members LIKE 'status'");
    $member_status_column = $member_status_column_stmt ? $member_status_column_stmt->fetch(PDO::FETCH_ASSOC) : false;
    $member_status_type = strtolower((string)($member_status_column['Type'] ?? ''));
    if ($member_status_type !== '' && strpos($member_status_type, "'pending'") === false) {
        $pdo->exec(
            "ALTER TABLE sales_group_members
             MODIFY status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'active'"
        );
    }
}

function ensureSalesGroupAuditLogTable(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_group_member_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id VARCHAR(30) NOT NULL,
            group_id INT UNSIGNED NULL,
            member_user_id VARCHAR(50) NOT NULL,
            actor_user_id VARCHAR(50) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            event_note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sgml_branch_group (branch_id, group_id),
            KEY idx_sgml_member (member_user_id),
            KEY idx_sgml_created (created_at),
            CONSTRAINT fk_sales_group_member_logs_group
                FOREIGN KEY (group_id) REFERENCES sales_group_invites(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function writeSalesGroupAuditLog(
    PDO $pdo,
    $eventType,
    $branchId,
    $groupId,
    $memberUserId,
    $actorUserId,
    $eventNote = ''
) {
    $eventType = trim((string)$eventType);
    $branchId = trim((string)$branchId);
    $memberUserId = trim((string)$memberUserId);
    $actorUserId = trim((string)$actorUserId);
    $eventNote = trim((string)$eventNote);
    $groupId = (int)$groupId;

    if ($eventType === '' || $branchId === '' || $memberUserId === '' || $actorUserId === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sales_group_member_logs
            (branch_id, group_id, member_user_id, actor_user_id, event_type, event_note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $branchId,
        $groupId > 0 ? $groupId : null,
        $memberUserId,
        $actorUserId,
        $eventType,
        $eventNote !== '' ? $eventNote : null
    ]);
}

$current_user_id = trim((string)($_SESSION['user_id'] ?? ''));
$current_user_role = trim((string)($_SESSION['user_role'] ?? 'sell_car'));
$current_user_name = trim((string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? ''));
if ($current_user_name === '') {
    $current_user_name = $current_user_id !== '' ? $current_user_id : 'User';
}

$active_branch_id = trim((string)getCurrentBranchId());
if ($active_branch_id === '') {
    $active_branch_id = 'GLOBAL';
}

$dashboard_href = $current_user_role === 'employee'
    ? '../employee/menuemployee.php'
    : 'pagesell.php?module=sales';

$csrf_token = generateCSRFToken();
$form_invite_code = normalizeInviteCode($_GET['invite_code'] ?? '');
$errors = [];
$success_message = '';
$current_membership = null;

try {
    $pdo = getDBConnection();
    ensureSalesGroupTables($pdo);
    ensureSalesGroupAuditLogTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $form_invite_code = normalizeInviteCode($_POST['invite_code'] ?? '');

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token';
        } elseif ($action === 'leave_group') {
            $membership_lookup_stmt = $pdo->prepare(
                'SELECT m.id, m.group_id, g.group_name
                 FROM sales_group_members m
                 LEFT JOIN sales_group_invites g ON g.id = m.group_id
                 WHERE m.member_user_id = ?
                   AND m.branch_id = ?
                 ORDER BY m.updated_at DESC, m.id DESC
                 LIMIT 1'
            );
            $membership_lookup_stmt->execute([$current_user_id, $active_branch_id]);
            $membership_row = $membership_lookup_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$membership_row) {
                $errors[] = 'ไม่พบทีมที่คุณเข้าร่วมในสาขานี้';
            } else {
                try {
                    $pdo->beginTransaction();

                    $delete_stmt = $pdo->prepare(
                        'DELETE FROM sales_group_members
                         WHERE id = ?
                           AND member_user_id = ?
                           AND branch_id = ?
                         LIMIT 1'
                    );
                    $delete_stmt->execute([
                        (int)$membership_row['id'],
                        $current_user_id,
                        $active_branch_id
                    ]);

                    writeSalesGroupAuditLog(
                        $pdo,
                        'leave_by_self',
                        $active_branch_id,
                        (int)($membership_row['group_id'] ?? 0),
                        $current_user_id,
                        $current_user_id,
                        'Leave group from join page'
                    );

                    $pdo->commit();

                    $success_message = 'ออกจากทีมขายเรียบร้อยแล้ว';
                    $form_invite_code = '';
                } catch (Throwable $leave_error) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $leave_error;
                }
            }
        } elseif ($action === 'join_by_invite') {
            if ($form_invite_code === '') {
                $errors[] = 'กรุณากรอกรหัสเชิญทีมขาย';
            } elseif (!preg_match('/^[A-Z0-9-]{6,40}$/', $form_invite_code)) {
                $errors[] = 'รูปแบบรหัสเชิญไม่ถูกต้อง (A-Z, 0-9, - ความยาว 6-40 ตัวอักษร)';
            } else {
                $group_stmt = $pdo->prepare(
                    'SELECT id, branch_id, group_name, invite_code, manager_user_id, status
                     FROM sales_group_invites
                     WHERE invite_code = ?
                       AND branch_id = ?
                     LIMIT 1'
                );
                $group_stmt->execute([$form_invite_code, $active_branch_id]);
                $group_row = $group_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$group_row) {
                    $global_stmt = $pdo->prepare(
                        'SELECT id, branch_id, group_name, status
                         FROM sales_group_invites
                         WHERE invite_code = ?
                         LIMIT 1'
                    );
                    $global_stmt->execute([$form_invite_code]);
                    $global_row = $global_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($global_row) {
                        $global_status = normalizeStatus($global_row['status'] ?? 'active');
                        if ($global_status !== 'active') {
                            $errors[] = 'รหัสเชิญนี้ถูกระงับการใช้งานแล้ว';
                        } else {
                            $errors[] = 'รหัสเชิญนี้อยู่คนละสาขา กรุณาเลือก branch ให้ตรงกับทีม (' . (string)($global_row['branch_id'] ?? '-') . ')';
                        }
                    } else {
                        $errors[] = 'ไม่พบรหัสเชิญนี้ในระบบ';
                    }
                } elseif (normalizeStatus($group_row['status'] ?? 'active') !== 'active') {
                    $errors[] = 'ทีมขายนี้ถูกระงับอยู่ ยังไม่สามารถเข้าร่วมได้';
                } else {
                    if ((string)($group_row['manager_user_id'] ?? '') === $current_user_id) {
                        $errors[] = 'บัญชีหัวหน้าทีมไม่จำเป็นต้องใช้รหัสเชิญเพื่อเข้าร่วมทีม';
                    }

                    if (empty($errors)) {
                        $current_membership_stmt = $pdo->prepare(
                            'SELECT m.id, m.group_id, m.status, g.group_name
                             FROM sales_group_members m
                             LEFT JOIN sales_group_invites g ON g.id = m.group_id
                             WHERE m.member_user_id = ?
                               AND m.branch_id = ?
                             ORDER BY m.updated_at DESC, m.id DESC
                             LIMIT 1'
                        );
                        $current_membership_stmt->execute([$current_user_id, $active_branch_id]);
                        $current_membership_row = $current_membership_stmt->fetch(PDO::FETCH_ASSOC);

                        $target_group_id = (int)($group_row['id'] ?? 0);
                        $target_group_name = trim((string)($group_row['group_name'] ?? '-'));
                        $is_same_group = $current_membership_row
                            && (int)($current_membership_row['group_id'] ?? 0) === $target_group_id;
                        $current_member_status = $current_membership_row
                            ? normalizeStatus($current_membership_row['status'] ?? 'active')
                            : '';
                        $is_active_member = $is_same_group
                            && $current_member_status === 'active';
                        $is_pending_member = $is_same_group
                            && $current_member_status === 'pending';

                        if ($is_active_member) {
                            $success_message = 'คุณอยู่ในทีมขาย "' . $target_group_name . '" อยู่แล้ว';
                        } elseif ($is_pending_member) {
                            $success_message = 'คุณส่งคำขอเข้าทีมขาย "' . $target_group_name . '" แล้ว กรุณารอหัวหน้าทีมอนุมัติ';
                        } else {
                            $event_type = 'request_join_by_invite';
                            $event_note = 'Request join via invite code ' . $form_invite_code;

                            if ($current_membership_row && !$is_same_group) {
                                $event_type = 'request_switch_team_by_invite';
                                $from_group_name = trim((string)($current_membership_row['group_name'] ?? ''));
                                $event_note = $from_group_name !== ''
                                    ? 'Request switch from ' . $from_group_name . ' via invite code ' . $form_invite_code
                                    : 'Request switch team via invite code ' . $form_invite_code;
                            } elseif ($is_same_group) {
                                $event_type = 'request_rejoin_by_invite';
                                $event_note = 'Request rejoin existing team via invite code ' . $form_invite_code;
                            }

                        try {
                            $pdo->beginTransaction();

                                if ($is_same_group) {
                                    $reactive_stmt = $pdo->prepare(
                                        'UPDATE sales_group_members
                                         SET status = "pending",
                                             updated_by = ?,
                                             updated_at = CURRENT_TIMESTAMP
                                         WHERE id = ?
                                           AND member_user_id = ?
                                           AND branch_id = ?
                                         LIMIT 1'
                                    );
                                    $reactive_stmt->execute([
                                        $current_user_id,
                                        (int)$current_membership_row['id'],
                                        $current_user_id,
                                        $active_branch_id
                                    ]);
                                } else {
                                    // จำกัดสมาชิกให้เข้าร่วมได้เพียง 1 ทีมต่อ 1 สาขา
                                    $remove_existing_stmt = $pdo->prepare(
                                        'DELETE FROM sales_group_members
                                         WHERE member_user_id = ?
                                           AND branch_id = ?'
                                    );
                                    $remove_existing_stmt->execute([$current_user_id, $active_branch_id]);

                                    $insert_member_stmt = $pdo->prepare(
                                        'INSERT INTO sales_group_members
                                            (group_id, branch_id, member_user_id, member_title, status, created_by, updated_by)
                                         VALUES (?, ?, ?, "Sales", "pending", ?, ?)'
                                    );
                                    $insert_member_stmt->execute([
                                        $target_group_id,
                                        $active_branch_id,
                                        $current_user_id,
                                        $current_user_id,
                                        $current_user_id
                                    ]);
                                }

                                writeSalesGroupAuditLog(
                                    $pdo,
                                    $event_type,
                                    $active_branch_id,
                                    $target_group_id,
                                    $current_user_id,
                                    $current_user_id,
                                    $event_note
                                );

                            $pdo->commit();

                                if ($event_type === 'request_switch_team_by_invite') {
                                    $success_message = 'ส่งคำขอย้ายเข้าทีมขาย "' . $target_group_name . '" แล้ว กรุณารอหัวหน้าทีมอนุมัติ';
                                } elseif ($event_type === 'request_rejoin_by_invite') {
                                    $success_message = 'ส่งคำขอกลับเข้าทีมขาย "' . $target_group_name . '" แล้ว กรุณารอหัวหน้าทีมอนุมัติ';
                                } else {
                                    $success_message = 'ส่งคำขอเข้าร่วมทีมขาย "' . $target_group_name . '" แล้ว กรุณารอหัวหน้าทีมอนุมัติ';
                                }
                        } catch (Throwable $action_error) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $action_error;
                        }
                        }
                    }
                }
            }
        }
    }

    $membership_stmt = $pdo->prepare(
        'SELECT
            m.id,
            m.member_title,
            m.status AS member_status,
            m.created_at,
            m.updated_at,
            g.group_name,
            g.invite_code,
            g.status AS group_status,
            g.manager_user_id,
            u.first_name AS manager_first_name,
            u.last_name AS manager_last_name
         FROM sales_group_members m
         INNER JOIN sales_group_invites g ON g.id = m.group_id
         LEFT JOIN users u ON u.user_id = g.manager_user_id
         WHERE m.member_user_id = ?
           AND m.branch_id = ?
         ORDER BY m.updated_at DESC, m.id DESC
         LIMIT 1'
    );
    $membership_stmt->execute([$current_user_id, $active_branch_id]);
    $current_membership = $membership_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('join_group_sales.php error: ' . $e->getMessage());
    $errors[] = 'เกิดข้อผิดพลาดระหว่างการเข้าร่วมทีมขาย กรุณาลองใหม่อีกครั้ง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Sales Group</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #143459;
            background: linear-gradient(180deg, #eef4ff 0%, #f9fbff 44%, #f5f9ff 100%);
            padding: 18px;
        }

        .layout {
            width: 100%;
            max-width: 920px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .panel {
            background: #ffffff;
            border: 1px solid #d7e4f5;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(8, 43, 88, 0.08);
            overflow: hidden;
        }

        .panel-head {
            padding: 14px 16px;
            border-bottom: 1px solid #e6eef9;
            background: linear-gradient(90deg, #eef5ff 0%, #f9fbff 100%);
        }

        .panel-head h1,
        .panel-head h2 {
            margin: 0;
            color: #0e3565;
        }

        .panel-head h1 { font-size: 24px; }
        .panel-head h2 { font-size: 18px; }

        .panel-head p {
            margin: 6px 0 0;
            color: #51739d;
            font-size: 13px;
            line-height: 1.5;
        }

        .panel-body {
            padding: 14px 16px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .meta-chip {
            border: 1px solid #ccddf3;
            background: #f4f8ff;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 12px;
            color: #25507d;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 7px;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            color: #1a4b7b;
        }

        .input {
            width: 100%;
            height: 42px;
            border: 1px solid #c8d9ef;
            border-radius: 10px;
            background: #fbfdff;
            padding: 0 12px;
            font-size: 13px;
            color: #1b3d68;
        }

        .input:focus {
            outline: none;
            border-color: #2b88d8;
            box-shadow: 0 0 0 3px rgba(43, 136, 216, 0.15);
            background: #ffffff;
        }

        .help {
            margin: 0;
            color: #5b7da3;
            font-size: 12px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn {
            height: 36px;
            border: 1px solid #2b88d8;
            border-radius: 9px;
            background: #2b88d8;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 0 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .btn:hover {
            background: #1f75c2;
            border-color: #1f75c2;
        }

        .btn.muted {
            border-color: #96aec9;
            background: #ffffff;
            color: #355b85;
        }

        .btn.muted:hover {
            background: #edf4ff;
            border-color: #7c9dc4;
        }

        .btn.warn {
            border-color: #c24f46;
            background: #c24f46;
            color: #ffffff;
        }

        .btn.warn:hover {
            border-color: #ac4038;
            background: #ac4038;
        }

        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert.success {
            border: 1px solid #b8e3c4;
            background: #ecf9f0;
            color: #1a6736;
        }

        .alert.error {
            border: 1px solid #f2c1c1;
            background: #fff1f1;
            color: #9c2c2c;
        }

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            border-radius: 999px;
            padding: 0 10px;
            font-size: 11px;
            font-weight: 700;
        }

        .status.active {
            color: #1b713a;
            border: 1px solid #bae8c8;
            background: #e9f7ee;
        }

        .status.suspended {
            color: #8d2b2b;
            border: 1px solid #f1c1c1;
            background: #fdeeee;
        }

        .status.pending {
            color: #8a6300;
            border: 1px solid #f0d48f;
            background: #fff7e0;
        }

        .member-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .member-card {
            border: 1px solid #d7e5f6;
            border-radius: 10px;
            background: #f9fbff;
            padding: 10px 12px;
            font-size: 12px;
            color: #2b4f76;
            line-height: 1.5;
        }

        .member-card strong {
            color: #163c69;
        }

        .invite-code {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: #163e6d;
            background: #ebf3ff;
            border: 1px solid #cfe0f5;
            border-radius: 8px;
            padding: 3px 8px;
            display: inline-block;
        }

        .empty {
            border: 1px dashed #c8d9ef;
            border-radius: 10px;
            background: #f8fbff;
            text-align: center;
            color: #5c7ea5;
            font-size: 12px;
            padding: 20px 14px;
        }

        @media (max-width: 860px) {
            .meta-grid,
            .member-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <section class="panel">
        <div class="panel-head">
            <h1>Join Sales Group</h1>
            <p>กรอกรหัสเชิญที่ได้รับจากหัวหน้าทีมขาย เพื่อส่งคำขอเข้าร่วมทีมในสาขาปัจจุบัน</p>
        </div>
        <div class="panel-body">
            <div class="meta-grid">
                <div class="meta-chip"><strong>ผู้ใช้งาน:</strong> <?php echo h($current_user_name); ?> (<?php echo h($current_user_id); ?>)</div>
                <div class="meta-chip"><strong>Role:</strong> <?php echo h($current_user_role); ?></div>
                <div class="meta-chip"><strong>Branch ID:</strong> <?php echo h($active_branch_id); ?></div>
            </div>


        </div>
    </section>

    <?php if ($success_message !== ''): ?>
        <div class="alert success"><?php echo h($success_message); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert error">
            <?php foreach ($errors as $error): ?>
                <div><?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head">
            <h2>เข้าร่วมทีมด้วยรหัสเชิญ</h2>
            <p>รองรับทั้งการพิมพ์รหัสเอง และเปิดจากลิงก์เชิญที่หัวหน้าทีมส่งมาให้ (ต้องรอหัวหน้าทีมอนุมัติ)</p>
        </div>
        <div class="panel-body">
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="join_by_invite">

                <div class="field-row">
                    <label for="invite_code">รหัสเชิญทีมขาย</label>
                    <input
                        class="input"
                        type="text"
                        id="invite_code"
                        name="invite_code"
                        maxlength="40"
                        placeholder="เช่น GROUP-APM-123456"
                        value="<?php echo h($form_invite_code); ?>"
                        required
                    >
                    <p class="help">ระบบจะตรวจสอบรหัสกับ branch ปัจจุบันและส่งคำขอให้หัวหน้าทีมยืนยัน</p>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">ส่งคำขอเข้าทีม</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2>ทีมปัจจุบันของฉัน</h2>
            <p>แสดงทีมล่าสุดที่คุณอยู่หรืออยู่ระหว่างรออนุมัติใน branch นี้</p>
        </div>
        <div class="panel-body">
            <?php if (!$current_membership): ?>
                <div class="empty">ยังไม่ได้เข้าร่วมทีมในสาขานี้</div>
            <?php else: ?>
                <?php
                $manager_name = trim(
                    (string)($current_membership['manager_first_name'] ?? '') .
                    ' ' .
                    (string)($current_membership['manager_last_name'] ?? '')
                );
                if ($manager_name === '') {
                    $manager_name = (string)($current_membership['manager_user_id'] ?? '-');
                }
                $member_status = normalizeStatus($current_membership['member_status'] ?? 'active');
                $group_status = normalizeStatus($current_membership['group_status'] ?? 'active');
                $leave_button_label = $member_status === 'pending' ? 'ยกเลิกคำขอเข้าทีม' : 'ออกจากทีมนี้';
                $leave_confirm_message = $member_status === 'pending'
                    ? 'ยืนยันการยกเลิกคำขอเข้าทีมขายนี้?'
                    : 'ยืนยันการออกจากทีมขายนี้?';
                ?>
                <?php if ($member_status === 'pending'): ?>
                    <div class="alert" style="border:1px solid #f0d48f;background:#fff8e7;color:#7a5800;padding:10px 12px;">
                        คำขอเข้าทีมของคุณอยู่ระหว่างรอหัวหน้าทีมอนุมัติ
                    </div>
                <?php endif; ?>
                <div class="member-grid">
                    <div class="member-card">
                        <strong>ทีมขาย</strong><br>
                        <?php echo h($current_membership['group_name'] ?? '-'); ?>
                    </div>
                    <div class="member-card">
                        <strong>รหัสเชิญ</strong><br>
                        <span class="invite-code"><?php echo h($current_membership['invite_code'] ?? '-'); ?></span>
                    </div>
                    <div class="member-card">
                        <strong>หัวหน้าทีม</strong><br>
                        <?php echo h($manager_name); ?>
                        (<?php echo h($current_membership['manager_user_id'] ?? '-'); ?>)
                    </div>
                    <div class="member-card">
                        <strong>ตำแหน่งในทีม</strong><br>
                        <?php echo h($current_membership['member_title'] ?? 'Sales'); ?>
                    </div>
                    <div class="member-card">
                        <strong>สถานะสมาชิก</strong><br>
                        <span class="status <?php echo h($member_status); ?>"><?php echo h(getStatusLabel($member_status)); ?></span>
                    </div>
                    <div class="member-card">
                        <strong>สถานะทีม</strong><br>
                        <span class="status <?php echo h($group_status); ?>"><?php echo h(getStatusLabel($group_status)); ?></span>
                    </div>
                    <div class="member-card">
                        <strong><?php echo $member_status === 'pending' ? 'ส่งคำขอเมื่อ' : 'เข้าร่วมเมื่อ'; ?></strong><br>
                        <?php echo h(formatDateTimeValue($current_membership['created_at'] ?? '')); ?>
                    </div>
                    <div class="member-card">
                        <strong>อัปเดตล่าสุด</strong><br>
                        <?php echo h(formatDateTimeValue($current_membership['updated_at'] ?? '')); ?>
                    </div>
                </div>

                <div class="actions" style="margin-top: 4px;">
                    <form method="post" onsubmit="return confirm('<?php echo h($leave_confirm_message); ?>');">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="leave_group">
                        <button class="btn warn" type="submit"><?php echo h($leave_button_label); ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(function () {
    var inviteInput = document.getElementById('invite_code');
    if (!inviteInput) {
        return;
    }

    function normalizeValue() {
        var value = String(inviteInput.value || '').toUpperCase();
        value = value.replace(/\s+/g, '');
        inviteInput.value = value;
    }

    inviteInput.addEventListener('input', normalizeValue);
    normalizeValue();
})();
</script>
</body>
</html>
