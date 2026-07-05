<?php
require_once '../../auth/config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!userHasAnyRole(['sales_manager'])) {
        header("Location: ../../auth/login");
        exit();
    }

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/sales_manager/setup_group_sales.php'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in setup_group_sales.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
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

function textLen($value) {
    if (function_exists('mb_strlen')) {
        return mb_strlen((string)$value, 'UTF-8');
    }

    return strlen((string)$value);
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

function buildInviteJoinUrl($inviteCode) {
    $code = normalizeInviteCode($inviteCode);
    $base = rtrim((string)SITE_URL, '/');
    return $base . '/app/sell/join_group_sales.php?invite_code=' . rawurlencode($code);
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

function generateSystemInviteCode(PDO $pdo) {
    $max_attempts = 30;

    for ($i = 0; $i < $max_attempts; $i++) {
        $code = 'GROUP-APM-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists_stmt = $pdo->prepare('SELECT COUNT(*) FROM sales_group_invites WHERE invite_code = ?');
        $exists_stmt->execute([$code]);

        if ((int)$exists_stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('ไม่สามารถสร้างรหัสเชิญอัตโนมัติได้ กรุณาลองอีกครั้ง');
}

function findOwnedGroup(PDO $pdo, $groupId, $branchId, $managerUserId) {
    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, branch_id, group_name, invite_code, manager_user_id, status, created_at, updated_at
         FROM sales_group_invites
         WHERE id = ? AND branch_id = ? AND manager_user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$groupId, (string)$branchId, (string)$managerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function findManagedMember(PDO $pdo, $memberId, $groupId, $branchId, $managerUserId) {
    $memberId = (int)$memberId;
    $groupId = (int)$groupId;

    if ($memberId <= 0 || $groupId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT m.id, m.group_id, m.branch_id, m.member_user_id, m.member_title, m.status
         FROM sales_group_members m
         INNER JOIN sales_group_invites g ON g.id = m.group_id
         WHERE m.id = ?
           AND m.group_id = ?
           AND m.branch_id = ?
           AND g.manager_user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$memberId, $groupId, (string)$branchId, (string)$managerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

$current_module = trim((string)($_GET['module'] ?? 'groupsetup'));
$current_user_id = trim((string)($_SESSION['user_id'] ?? ''));
$current_user_name = trim(
    (string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? '')
);
if ($current_user_name === '') {
    $current_user_name = $current_user_id !== '' ? $current_user_id : 'sales_manager';
}

$active_branch_id = trim((string)getCurrentBranchId());
if ($active_branch_id === '') {
    $active_branch_id = 'GLOBAL';
}

$csrf_token = generateCSRFToken();
$errors = [];
$success_message = '';

$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$form_create = [
    'group_name' => '',
    'invite_strategy' => 'auto',
    'invite_code' => ''
];

$form_member = [
    'member_user_id' => '',
    'member_title' => 'Sales'
];

$group_rows = [];
$member_rows = [];
$pending_member_rows = [];
$eligible_users = [];
$selected_group = null;

try {
    $pdo = getDBConnection();
    ensureSalesGroupTables($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $posted_group_id = (int)($_POST['group_id'] ?? 0);
        if ($posted_group_id > 0) {
            $selected_group_id = $posted_group_id;
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token';
        } else {
            if ($action === 'create_group') {
                $form_create['group_name'] = trim((string)($_POST['group_name'] ?? ''));
                $form_create['invite_strategy'] = (string)($_POST['invite_strategy_create'] ?? 'auto') === 'custom' ? 'custom' : 'auto';
                $form_create['invite_code'] = normalizeInviteCode($_POST['invite_code'] ?? '');

                if ($form_create['group_name'] === '') {
                    $errors[] = 'กรุณาระบุชื่อทีมขาย';
                } elseif (textLen($form_create['group_name']) > 150) {
                    $errors[] = 'ชื่อทีมขายยาวเกินไป (สูงสุด 150 ตัวอักษร)';
                }

                $final_invite_code = '';
                if ($form_create['invite_strategy'] === 'custom') {
                    if ($form_create['invite_code'] === '') {
                        $errors[] = 'กรุณาระบุรหัสเชิญ';
                    } elseif (!preg_match('/^[A-Z0-9-]{6,40}$/', $form_create['invite_code'])) {
                        $errors[] = 'รหัสเชิญต้องมีเฉพาะ A-Z, 0-9 และ - (ความยาว 6-40 ตัวอักษร)';
                    } else {
                        $final_invite_code = $form_create['invite_code'];
                    }
                } else {
                    try {
                        $final_invite_code = generateSystemInviteCode($pdo);
                    } catch (Throwable $code_error) {
                        $errors[] = $code_error->getMessage();
                    }
                }

                if (empty($errors) && $final_invite_code !== '') {
                    $duplicate_stmt = $pdo->prepare('SELECT id FROM sales_group_invites WHERE invite_code = ? LIMIT 1');
                    $duplicate_stmt->execute([$final_invite_code]);
                    if ($duplicate_stmt->fetch()) {
                        $errors[] = 'รหัสเชิญนี้ถูกใช้งานแล้ว กรุณาใช้รหัสอื่น';
                    }
                }

                if (empty($errors)) {
                    $insert_stmt = $pdo->prepare(
                        'INSERT INTO sales_group_invites (branch_id, group_name, invite_code, manager_user_id, status)
                         VALUES (?, ?, ?, ?, "active")'
                    );
                    $insert_stmt->execute([
                        $active_branch_id,
                        $form_create['group_name'],
                        $final_invite_code,
                        $current_user_id
                    ]);

                    $selected_group_id = (int)$pdo->lastInsertId();
                    $success_message = 'สร้างทีมขายและรหัสเชิญเรียบร้อยแล้ว';
                    $form_create = [
                        'group_name' => '',
                        'invite_strategy' => 'auto',
                        'invite_code' => ''
                    ];
                }
            } elseif ($action === 'update_group') {
                $target_group_id = (int)($_POST['group_id'] ?? 0);
                $owned_group = findOwnedGroup($pdo, $target_group_id, $active_branch_id, $current_user_id);

                if (!$owned_group) {
                    $errors[] = 'ไม่พบทีมขายที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์จัดการ';
                } else {
                    $group_name = trim((string)($_POST['group_name'] ?? ''));
                    $group_status = normalizeStatus($_POST['group_status'] ?? 'active');
                    $invite_strategy = (string)($_POST['invite_strategy_edit'] ?? 'custom') === 'auto' ? 'auto' : 'custom';
                    $invite_code_input = normalizeInviteCode($_POST['invite_code'] ?? '');

                    if ($group_name === '') {
                        $errors[] = 'กรุณาระบุชื่อทีมขาย';
                    } elseif (textLen($group_name) > 150) {
                        $errors[] = 'ชื่อทีมขายยาวเกินไป (สูงสุด 150 ตัวอักษร)';
                    }

                    $final_invite_code = (string)($owned_group['invite_code'] ?? '');
                    if ($invite_strategy === 'auto') {
                        try {
                            $final_invite_code = generateSystemInviteCode($pdo);
                        } catch (Throwable $code_error) {
                            $errors[] = $code_error->getMessage();
                        }
                    } else {
                        if ($invite_code_input !== '') {
                            if (!preg_match('/^[A-Z0-9-]{6,40}$/', $invite_code_input)) {
                                $errors[] = 'รหัสเชิญต้องมีเฉพาะ A-Z, 0-9 และ - (ความยาว 6-40 ตัวอักษร)';
                            } else {
                                $final_invite_code = $invite_code_input;
                            }
                        }
                    }

                    if (empty($errors) && $final_invite_code !== '') {
                        $duplicate_stmt = $pdo->prepare(
                            'SELECT id FROM sales_group_invites WHERE invite_code = ? AND id <> ? LIMIT 1'
                        );
                        $duplicate_stmt->execute([$final_invite_code, $target_group_id]);
                        if ($duplicate_stmt->fetch()) {
                            $errors[] = 'รหัสเชิญนี้ถูกใช้งานแล้ว กรุณาใช้รหัสอื่น';
                        }
                    }

                    if (empty($errors)) {
                        $update_stmt = $pdo->prepare(
                            'UPDATE sales_group_invites
                             SET group_name = ?, invite_code = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                             WHERE id = ? AND branch_id = ? AND manager_user_id = ?
                             LIMIT 1'
                        );
                        $update_stmt->execute([
                            $group_name,
                            $final_invite_code,
                            $group_status,
                            $target_group_id,
                            $active_branch_id,
                            $current_user_id
                        ]);
                        $selected_group_id = $target_group_id;
                        $success_message = 'อัปเดตข้อมูลทีมขายเรียบร้อยแล้ว';
                    }
                }
            } elseif ($action === 'suspend_group' || $action === 'resume_group') {
                $target_group_id = (int)($_POST['group_id'] ?? 0);
                $owned_group = findOwnedGroup($pdo, $target_group_id, $active_branch_id, $current_user_id);

                if (!$owned_group) {
                    $errors[] = 'ไม่พบทีมขายที่ต้องการเปลี่ยนสถานะ';
                } else {
                    $new_status = $action === 'suspend_group' ? 'suspended' : 'active';
                    $status_stmt = $pdo->prepare(
                        'UPDATE sales_group_invites
                         SET status = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ? AND branch_id = ? AND manager_user_id = ?
                         LIMIT 1'
                    );
                    $status_stmt->execute([$new_status, $target_group_id, $active_branch_id, $current_user_id]);
                    $selected_group_id = $target_group_id;
                    $success_message = $new_status === 'suspended'
                        ? 'ระงับทีมขายเรียบร้อยแล้ว'
                        : 'เปิดใช้งานทีมขายเรียบร้อยแล้ว';
                }
            } elseif ($action === 'delete_group') {
                $target_group_id = (int)($_POST['group_id'] ?? 0);
                $owned_group = findOwnedGroup($pdo, $target_group_id, $active_branch_id, $current_user_id);

                if (!$owned_group) {
                    $errors[] = 'ไม่พบทีมขายที่ต้องการลบ';
                } else {
                    $delete_stmt = $pdo->prepare(
                        'DELETE FROM sales_group_invites
                         WHERE id = ? AND branch_id = ? AND manager_user_id = ?
                         LIMIT 1'
                    );
                    $delete_stmt->execute([$target_group_id, $active_branch_id, $current_user_id]);

                    if ($selected_group_id === $target_group_id) {
                        $selected_group_id = 0;
                    }
                    $success_message = 'ลบทีมขายและสมาชิกทั้งหมดในทีมเรียบร้อยแล้ว';
                }
            } elseif (
                $action === 'add_member'
                || $action === 'update_member'
                || $action === 'approve_member'
                || $action === 'reject_member'
                || $action === 'suspend_member'
                || $action === 'resume_member'
                || $action === 'delete_member'
            ) {
                $target_group_id = (int)($_POST['group_id'] ?? 0);
                $owned_group = findOwnedGroup($pdo, $target_group_id, $active_branch_id, $current_user_id);

                if (!$owned_group) {
                    $errors[] = 'ไม่พบทีมขายที่ต้องการจัดการสมาชิก หรือคุณไม่มีสิทธิ์';
                } elseif ($action === 'add_member') {
                    $form_member['member_user_id'] = trim((string)($_POST['member_user_id'] ?? ''));
                    $form_member['member_title'] = trim((string)($_POST['member_title'] ?? 'Sales'));

                    if ($form_member['member_user_id'] === '') {
                        $errors[] = 'กรุณาเลือกพนักงานขายที่ต้องการเพิ่ม';
                    }

                    if ($form_member['member_title'] === '') {
                        $form_member['member_title'] = 'Sales';
                    }

                    if (textLen($form_member['member_title']) > 100) {
                        $errors[] = 'ตำแหน่งในทีมยาวเกินไป (สูงสุด 100 ตัวอักษร)';
                    }

                    if ($form_member['member_user_id'] !== '' && $form_member['member_user_id'] === $current_user_id) {
                        $errors[] = 'ไม่สามารถเพิ่มตัวเองเป็นสมาชิกทีมได้';
                    }

                    if ($form_member['member_user_id'] !== '') {
                        $user_stmt = $pdo->prepare(
                            'SELECT user_id, user_role, is_active FROM users WHERE user_id = ? LIMIT 1'
                        );
                        $user_stmt->execute([$form_member['member_user_id']]);
                        $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$user_row) {
                            $errors[] = 'ไม่พบผู้ใช้ที่เลือก';
                        } else {
                            $user_role = trim((string)($user_row['user_role'] ?? ''));
                            $is_active = (int)($user_row['is_active'] ?? 0);

                            if ($is_active !== 1) {
                                $errors[] = 'ผู้ใช้นี้ถูกปิดการใช้งานอยู่';
                            }

                            if (!in_array($user_role, ['sell_car', 'employee'], true)) {
                                $errors[] = 'เพิ่มได้เฉพาะผู้ใช้ role sell_car หรือ employee';
                            }

                            if (!userCanAccessBranch($pdo, $form_member['member_user_id'], $active_branch_id)) {
                                $errors[] = 'ผู้ใช้นี้ไม่มีสิทธิ์ใช้งานสาขา ' . $active_branch_id;
                            }
                        }
                    }

                    if (empty($errors)) {
                        try {
                            $insert_member_stmt = $pdo->prepare(
                                'INSERT INTO sales_group_members
                                    (group_id, branch_id, member_user_id, member_title, status, created_by, updated_by)
                                 VALUES (?, ?, ?, ?, "active", ?, ?)'
                            );
                            $insert_member_stmt->execute([
                                $target_group_id,
                                $active_branch_id,
                                $form_member['member_user_id'],
                                $form_member['member_title'],
                                $current_user_id,
                                $current_user_id
                            ]);
                            $selected_group_id = $target_group_id;
                            $success_message = 'เพิ่มพนักงานขายเข้าทีมเรียบร้อยแล้ว';
                            $form_member = [
                                'member_user_id' => '',
                                'member_title' => 'Sales'
                            ];
                        } catch (PDOException $member_error) {
                            if ((string)$member_error->getCode() === '23000') {
                                $errors[] = 'พนักงานขายคนนี้อยู่ในทีมแล้ว';
                            } else {
                                throw $member_error;
                            }
                        }
                    }
                } else {
                    $member_id = (int)($_POST['member_id'] ?? 0);
                    $member_row = findManagedMember(
                        $pdo,
                        $member_id,
                        $target_group_id,
                        $active_branch_id,
                        $current_user_id
                    );

                    if (!$member_row) {
                        $errors[] = 'ไม่พบสมาชิกที่ต้องการจัดการ';
                    } elseif ($action === 'update_member') {
                        $member_title = trim((string)($_POST['member_title'] ?? 'Sales'));
                        $member_status = normalizeStatus($_POST['member_status'] ?? 'active');

                        if ($member_title === '') {
                            $member_title = 'Sales';
                        }

                        if (textLen($member_title) > 100) {
                            $errors[] = 'ตำแหน่งในทีมยาวเกินไป (สูงสุด 100 ตัวอักษร)';
                        }

                        if (empty($errors)) {
                            $update_member_stmt = $pdo->prepare(
                                'UPDATE sales_group_members m
                                 INNER JOIN sales_group_invites g ON g.id = m.group_id
                                 SET m.member_title = ?,
                                     m.status = ?,
                                     m.updated_by = ?,
                                     m.updated_at = CURRENT_TIMESTAMP
                                 WHERE m.id = ?
                                   AND m.group_id = ?
                                   AND m.branch_id = ?
                                   AND g.manager_user_id = ?
                                 LIMIT 1'
                            );
                            $update_member_stmt->execute([
                                $member_title,
                                $member_status,
                                $current_user_id,
                                $member_id,
                                $target_group_id,
                                $active_branch_id,
                                $current_user_id
                            ]);
                            $selected_group_id = $target_group_id;
                            $success_message = 'อัปเดตข้อมูลพนักงานขายเรียบร้อยแล้ว';
                        }
                    } elseif ($action === 'approve_member') {
                        if (normalizeStatus($member_row['status'] ?? 'active') !== 'pending') {
                            $errors[] = 'สมาชิกคนนี้ไม่ได้อยู่ในสถานะรออนุมัติ';
                        } else {
                            $approve_stmt = $pdo->prepare(
                                'UPDATE sales_group_members m
                                 INNER JOIN sales_group_invites g ON g.id = m.group_id
                                 SET m.status = "active",
                                     m.updated_by = ?,
                                     m.updated_at = CURRENT_TIMESTAMP
                                 WHERE m.id = ?
                                   AND m.group_id = ?
                                   AND m.branch_id = ?
                                   AND g.manager_user_id = ?
                                 LIMIT 1'
                            );
                            $approve_stmt->execute([
                                $current_user_id,
                                $member_id,
                                $target_group_id,
                                $active_branch_id,
                                $current_user_id
                            ]);
                            $selected_group_id = $target_group_id;
                            $success_message = 'อนุมัติสมาชิกเข้าทีมเรียบร้อยแล้ว';
                        }
                    } elseif ($action === 'reject_member') {
                        if (normalizeStatus($member_row['status'] ?? 'active') !== 'pending') {
                            $errors[] = 'สมาชิกคนนี้ไม่ได้อยู่ในสถานะรออนุมัติ';
                        } else {
                            $reject_stmt = $pdo->prepare(
                                'DELETE m
                                 FROM sales_group_members m
                                 INNER JOIN sales_group_invites g ON g.id = m.group_id
                                 WHERE m.id = ?
                                   AND m.group_id = ?
                                   AND m.branch_id = ?
                                   AND g.manager_user_id = ?
                                 LIMIT 1'
                            );
                            $reject_stmt->execute([
                                $member_id,
                                $target_group_id,
                                $active_branch_id,
                                $current_user_id
                            ]);
                            $selected_group_id = $target_group_id;
                            $success_message = 'ยกเลิกคำขอเข้าทีมเรียบร้อยแล้ว';
                        }
                    } elseif ($action === 'suspend_member' || $action === 'resume_member') {
                        $new_status = $action === 'suspend_member' ? 'suspended' : 'active';

                        $status_stmt = $pdo->prepare(
                            'UPDATE sales_group_members m
                             INNER JOIN sales_group_invites g ON g.id = m.group_id
                             SET m.status = ?,
                                 m.updated_by = ?,
                                 m.updated_at = CURRENT_TIMESTAMP
                             WHERE m.id = ?
                               AND m.group_id = ?
                               AND m.branch_id = ?
                               AND g.manager_user_id = ?
                             LIMIT 1'
                        );
                        $status_stmt->execute([
                            $new_status,
                            $current_user_id,
                            $member_id,
                            $target_group_id,
                            $active_branch_id,
                            $current_user_id
                        ]);
                        $selected_group_id = $target_group_id;
                        $success_message = $new_status === 'suspended'
                            ? 'ระงับสมาชิกทีมเรียบร้อยแล้ว'
                            : 'เปิดใช้งานสมาชิกทีมเรียบร้อยแล้ว';
                    } elseif ($action === 'delete_member') {
                        $delete_member_stmt = $pdo->prepare(
                            'DELETE m
                             FROM sales_group_members m
                             INNER JOIN sales_group_invites g ON g.id = m.group_id
                             WHERE m.id = ?
                               AND m.group_id = ?
                               AND m.branch_id = ?
                               AND g.manager_user_id = ?
                             LIMIT 1'
                        );
                        $delete_member_stmt->execute([
                            $member_id,
                            $target_group_id,
                            $active_branch_id,
                            $current_user_id
                        ]);
                        $selected_group_id = $target_group_id;
                        $success_message = 'ลบสมาชิกทีมเรียบร้อยแล้ว';
                    }
                }
            }
        }
    }

    $group_stmt = $pdo->prepare(
        'SELECT
            g.id,
            g.group_name,
            g.invite_code,
            g.status,
            g.created_at,
            g.updated_at,
            (
                SELECT COUNT(*)
                FROM sales_group_members sm
                WHERE sm.group_id = g.id
            ) AS member_count,
            (
                SELECT COUNT(*)
                FROM sales_group_members sm
                WHERE sm.group_id = g.id
                  AND sm.status = "active"
            ) AS active_member_count
         FROM sales_group_invites g
         WHERE g.branch_id = ?
           AND g.manager_user_id = ?
         ORDER BY g.created_at DESC'
    );
    $group_stmt->execute([$active_branch_id, $current_user_id]);
    $group_rows = $group_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($selected_group_id <= 0 && !empty($group_rows)) {
        $selected_group_id = (int)($group_rows[0]['id'] ?? 0);
    }

    foreach ($group_rows as $row) {
        if ((int)($row['id'] ?? 0) === $selected_group_id) {
            $selected_group = $row;
            break;
        }
    }

    if (!$selected_group && !empty($group_rows)) {
        $selected_group = $group_rows[0];
        $selected_group_id = (int)($selected_group['id'] ?? 0);
    }

    if ($selected_group_id > 0) {
        $member_stmt = $pdo->prepare(
            'SELECT
                m.id,
                m.group_id,
                m.member_user_id,
                m.member_title,
                m.status,
                m.created_at,
                m.updated_at,
                u.first_name,
                u.last_name,
                u.user_role,
                u.is_active
             FROM sales_group_members m
             LEFT JOIN users u ON u.user_id = m.member_user_id
             WHERE m.group_id = ?
               AND m.branch_id = ?
             ORDER BY m.created_at DESC'
        );
        $member_stmt->execute([$selected_group_id, $active_branch_id]);
        $member_rows = $member_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($member_rows as $member_row) {
            if (normalizeStatus($member_row['status'] ?? 'active') === 'pending') {
                $pending_member_rows[] = $member_row;
            }
        }
    }

    $user_stmt = $pdo->query(
        "SELECT user_id, first_name, last_name, user_role
         FROM users
         WHERE is_active = 1
           AND user_role IN ('sell_car', 'employee')
         ORDER BY first_name ASC, last_name ASC, user_id ASC"
    );
    $candidate_users = $user_stmt ? $user_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($candidate_users as $user_row) {
        $candidate_user_id = trim((string)($user_row['user_id'] ?? ''));
        if ($candidate_user_id === '' || $candidate_user_id === $current_user_id) {
            continue;
        }

        if (!userCanAccessBranch($pdo, $candidate_user_id, $active_branch_id)) {
            continue;
        }

        $eligible_users[] = $user_row;
    }
} catch (Throwable $e) {
    error_log('setup_group_sales.php error: ' . $e->getMessage());
    $errors[] = 'เกิดข้อผิดพลาดในการประมวลผลข้อมูล กรุณาลองใหม่อีกครั้ง';
}

$group_base_url = 'setup_group_sales.php?module=' . urlencode($current_module);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Setup - Sales Manager</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(180deg, #eef4ff 0%, #f7f9ff 45%, #f6f9fd 100%);
            color: #183153;
            min-height: 100vh;
            padding: 18px;
        }

        .layout {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .page-head {
            background: #ffffff;
            border: 1px solid #d8e3f2;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(9, 43, 88, 0.08);
            padding: 16px 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
            justify-content: space-between;
        }

        .title-wrap h1 {
            margin: 0;
            font-size: 24px;
            color: #0b2f5d;
        }

        .title-wrap p {
            margin: 6px 0 0;
            color: #4d6f98;
            font-size: 13px;
            line-height: 1.5;
        }

        .meta-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
            min-width: 260px;
        }

        .meta-chip {
            border: 1px solid #c9daf3;
            background: #f4f8ff;
            color: #244b7b;
            border-radius: 10px;
            padding: 8px 10px;
            font-size: 12px;
            line-height: 1.4;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #2b88d8;
            border-radius: 10px;
            color: #ffffff;
            background: #2b88d8;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            height: 34px;
            padding: 0 12px;
        }

        .back-link:hover {
            background: #1f75c2;
            border-color: #1f75c2;
        }

        .alert {
            border-radius: 11px;
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert.success {
            border: 1px solid #b4e1c4;
            background: #edf9f1;
            color: #1c6a39;
        }

        .alert.error {
            border: 1px solid #f3c3c3;
            background: #fff3f3;
            color: #a12f2f;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 14px;
        }

        .card {
            background: #ffffff;
            border: 1px solid #d8e3f2;
            border-radius: 14px;
            box-shadow: 0 10px 24px rgba(8, 43, 88, 0.07);
            overflow: hidden;
        }

        .card-head {
            padding: 14px 16px;
            border-bottom: 1px solid #e3edf9;
            background: linear-gradient(90deg, #edf4ff 0%, #f9fbff 100%);
        }

        .card-head h2 {
            margin: 0;
            font-size: 18px;
            color: #0f3768;
        }

        .card-head p {
            margin: 6px 0 0;
            color: #55769f;
            font-size: 12px;
        }

        .card-body {
            padding: 14px 16px 16px;
        }

        .span-5 { grid-column: span 5; }
        .span-4 { grid-column: span 4; }
        .span-3 { grid-column: span 3; }
        .span-6 { grid-column: span 6; }
        .span-7 { grid-column: span 7; }
        .span-12 { grid-column: span 12; }

        .field-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 7px;
            margin-bottom: 12px;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            color: #194779;
        }

        input[type="text"],
        select {
            width: 100%;
            height: 40px;
            border-radius: 10px;
            border: 1px solid #c9d9ee;
            background: #fbfdff;
            padding: 0 11px;
            font-size: 13px;
            color: #1b3d68;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #2b88d8;
            box-shadow: 0 0 0 3px rgba(43, 136, 216, 0.15);
            background: #ffffff;
        }

        .help {
            font-size: 12px;
            color: #5b7aa0;
            margin-top: -2px;
            line-height: 1.5;
        }

        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 2px;
        }

        .radio-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid #c9daf1;
            border-radius: 999px;
            background: #f5f9ff;
            color: #244b7a;
            font-size: 12px;
            padding: 7px 12px;
        }

        .radio-pill input {
            margin: 0;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 2px;
        }

        .btn {
            height: 36px;
            border-radius: 9px;
            border: 1px solid #2b88d8;
            background: #2b88d8;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            padding: 0 12px;
            cursor: pointer;
        }

        .btn:hover {
            background: #1f75c2;
            border-color: #1f75c2;
        }

        .btn.muted {
            border-color: #97aec9;
            background: #ffffff;
            color: #355b85;
        }

        .btn.muted:hover {
            background: #ecf3ff;
            border-color: #7b9cc3;
        }

        .btn.warn {
            border-color: #cb554b;
            background: #cb554b;
        }

        .btn.warn:hover {
            border-color: #b0443b;
            background: #b0443b;
        }

        .btn.small {
            height: 30px;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border-bottom: 1px solid #e4edf8;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 12px;
            color: #274b74;
        }

        th {
            background: #f8fbff;
            color: #21456f;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .row-form {
            display: inline-flex;
            gap: 6px;
            align-items: center;
            margin: 0;
        }

        .row-form input[type="text"],
        .row-form select {
            height: 30px;
            min-width: 120px;
            font-size: 12px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            height: 24px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            padding: 0 10px;
        }

        .status.active {
            color: #19743b;
            background: #e7f7ed;
            border: 1px solid #b9e7c8;
        }

        .status.suspended {
            color: #8c2b2b;
            background: #fdecec;
            border: 1px solid #f2c0c0;
        }

        .status.pending {
            color: #8a6300;
            background: #fff7e1;
            border: 1px solid #f0d48f;
        }

        .empty {
            border: 1px dashed #c8d8ee;
            border-radius: 10px;
            background: #f8fbff;
            color: #58779d;
            font-size: 12px;
            text-align: center;
            padding: 20px 14px;
        }

        .invite-code {
            font-family: Consolas, 'Courier New', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #1b3f68;
            background: #edf4ff;
            border: 1px solid #d0dff4;
            border-radius: 8px;
            padding: 4px 8px;
            display: inline-block;
        }

        .nowrap {
            white-space: nowrap;
        }

        .invite-toast {
            position: fixed;
            right: 16px;
            bottom: 16px;
            min-width: 220px;
            max-width: 360px;
            border-radius: 10px;
            border: 1px solid #b8d6f4;
            background: #f2f8ff;
            color: #17406c;
            font-size: 12px;
            line-height: 1.4;
            box-shadow: 0 10px 26px rgba(9, 43, 88, 0.16);
            padding: 10px 12px;
            opacity: 0;
            transform: translateY(8px);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
            z-index: 999;
        }

        .invite-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .invite-toast.error {
            border-color: #f0bcbc;
            background: #fff2f2;
            color: #8f2a2a;
        }

        @media (max-width: 1100px) {
            .span-5,
            .span-4,
            .span-3,
            .span-6,
            .span-7 {
                grid-column: span 12;
            }

            .meta-wrap {
                align-items: flex-start;
            }
        }

        @media (max-width: 760px) {
            body {
                padding: 12px;
            }

            .card-head h2 {
                font-size: 16px;
            }

            .row-actions,
            .actions,
            .row-form {
                width: 100%;
            }

            .row-form {
                flex-wrap: wrap;
            }

            .row-form input[type="text"],
            .row-form select {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <header class="page-head">
        <div class="title-wrap">
            <h1>Sales Group Setup</h1>
            <p>สร้างรหัสเชิญทีมฝ่ายขาย และจัดการสมาชิกทีมขายของคุณโดยตรง (อนุมัติ / ยกเลิกคำขอ / แก้ไข / ระงับ / ลบ)</p>
        </div>
        <div class="meta-wrap">
            <div class="meta-chip"><strong>หัวหน้าทีม:</strong> <?php echo h($current_user_name); ?> (<?php echo h($current_user_id); ?>)</div>
            <div class="meta-chip"><strong>Branch ID:</strong> <?php echo h($active_branch_id); ?></div>
        </div>
    </header>

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

    <section class="grid">
        <article class="card span-5">
            <div class="card-head">
                <h2>สร้างทีมขายใหม่</h2>
                <p>ตั้งชื่อทีมขายและสร้างรหัสเชิญได้ทั้งแบบกำหนดเอง หรือระบบกำหนดอัตโนมัติ</p>
            </div>
            <div class="card-body">
                <form method="post" class="invite-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="create_group">

                    <div class="field-row">
                        <label for="group_name">ชื่อทีมขาย</label>
                        <input
                            type="text"
                            id="group_name"
                            name="group_name"
                            maxlength="150"
                            placeholder="ตัวอย่าง: ทีมขายโซนภาคกลาง"
                            value="<?php echo h($form_create['group_name']); ?>"
                            required
                        >
                    </div>

                    <div class="field-row">
                        <label>รูปแบบรหัสเชิญ</label>
                        <div class="radio-group">
                            <label class="radio-pill">
                                <input
                                    type="radio"
                                    name="invite_strategy_create"
                                    value="auto"
                                    data-invite-strategy
                                    <?php echo $form_create['invite_strategy'] === 'auto' ? 'checked' : ''; ?>
                                >
                                ระบบกำหนดอัตโนมัติ (GROUP-APM-123456)
                            </label>
                            <label class="radio-pill">
                                <input
                                    type="radio"
                                    name="invite_strategy_create"
                                    value="custom"
                                    data-invite-strategy
                                    <?php echo $form_create['invite_strategy'] === 'custom' ? 'checked' : ''; ?>
                                >
                                กำหนดรหัสเอง
                            </label>
                        </div>
                    </div>

                    <div class="field-row">
                        <label for="invite_code_create">รหัสเชิญ (Custom)</label>
                        <input
                            type="text"
                            id="invite_code_create"
                            name="invite_code"
                            data-custom-code-input
                            maxlength="40"
                            placeholder="เช่น APM-SALES-001"
                            value="<?php echo h($form_create['invite_code']); ?>"
                        >
                        <p class="help">หากเลือกแบบอัตโนมัติ ระบบจะสุ่มรหัสรูปแบบ GROUP-APM-xxxxxx ให้ทันที</p>
                    </div>

                    <div class="actions">
                        <button class="btn" type="submit">สร้างทีมและรหัสเชิญ</button>
                    </div>
                </form>
            </div>
        </article>

        <article class="card span-7">
            <div class="card-head">
                <h2>ทีมขายของฉันในสาขานี้</h2>
                <p>หัวหน้าทีมสามารถจัดการได้เฉพาะทีมที่ตัวเองสร้างใน branch เดียวกันเท่านั้น</p>
            </div>
            <div class="card-body">
                <?php if (empty($group_rows)): ?>
                    <div class="empty">ยังไม่มีทีมขายในสาขานี้ เริ่มจากการสร้างทีมแรกทางด้านซ้าย</div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>ทีมขาย</th>
                                    <th>รหัสเชิญ</th>
                                    <th>สถานะ</th>
                                    <th>สมาชิก</th>
                                    <th>สร้างเมื่อ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($group_rows as $group_row): ?>
                                    <?php $group_id = (int)($group_row['id'] ?? 0); ?>
                                    <?php $invite_code = (string)($group_row['invite_code'] ?? ''); ?>
                                    <?php $invite_link = buildInviteJoinUrl($invite_code); ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($group_row['group_name'] ?? '-'); ?></strong>
                                        </td>
                                        <td>
                                            <span class="invite-code"><?php echo h($group_row['invite_code'] ?? '-'); ?></span>
                                        </td>
                                        <td>
                                            <?php $group_status = normalizeStatus($group_row['status'] ?? 'active'); ?>
                                            <span class="status <?php echo h($group_status); ?>"><?php echo h(getStatusLabel($group_status)); ?></span>
                                        </td>
                                        <td class="nowrap">
                                            <?php echo h((string)($group_row['active_member_count'] ?? 0)); ?>
                                            /
                                            <?php echo h((string)($group_row['member_count'] ?? 0)); ?>
                                        </td>
                                        <td class="nowrap"><?php echo h(formatDateTimeValue($group_row['created_at'] ?? '')); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <a
                                                    class="btn muted small"
                                                    href="<?php echo h($group_base_url . '&group_id=' . $group_id); ?>"
                                                >จัดการ</a>

                                                <button
                                                    class="btn muted small"
                                                    type="button"
                                                    data-copy-code="<?php echo h($invite_code); ?>"
                                                >คัดลอกรหัส</button>

                                                <button
                                                    class="btn muted small"
                                                    type="button"
                                                    data-share-link="<?php echo h($invite_link); ?>"
                                                    data-invite-code="<?php echo h($invite_code); ?>"
                                                >แชร์ลิงก์</button>

                                                <form method="post" class="row-form" style="display:inline-flex;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="group_id" value="<?php echo h((string)$group_id); ?>">
                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="<?php echo $group_status === 'active' ? 'suspend_group' : 'resume_group'; ?>"
                                                    >
                                                    <button class="btn muted small" type="submit">
                                                        <?php echo $group_status === 'active' ? 'ระงับ' : 'เปิดใช้งาน'; ?>
                                                    </button>
                                                </form>

                                                <form
                                                    method="post"
                                                    class="row-form"
                                                    style="display:inline-flex;"
                                                    onsubmit="return confirm('ยืนยันการลบทีมขายนี้? สมาชิกในทีมจะถูกลบทั้งหมด');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="group_id" value="<?php echo h((string)$group_id); ?>">
                                                    <input type="hidden" name="action" value="delete_group">
                                                    <button class="btn warn small" type="submit">ลบ</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <?php if ($selected_group): ?>
            <article class="card span-12">
                <div class="card-head">
                    <h2>แก้ไขข้อมูลทีมขาย</h2>
                    <p>ทีม: <?php echo h($selected_group['group_name'] ?? '-'); ?> | Branch ID: <?php echo h($active_branch_id); ?></p>
                </div>
                <div class="card-body">
                    <form method="post" class="invite-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="update_group">
                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">

                        <div class="grid">
                            <div class="span-5">
                                <div class="field-row">
                                    <label for="group_name_edit">ชื่อทีมขาย</label>
                                    <input
                                        type="text"
                                        id="group_name_edit"
                                        name="group_name"
                                        maxlength="150"
                                        value="<?php echo h($selected_group['group_name'] ?? ''); ?>"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="span-3">
                                <div class="field-row">
                                    <label for="group_status">สถานะทีม</label>
                                    <select id="group_status" name="group_status">
                                        <option value="active" <?php echo normalizeStatus($selected_group['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>ใช้งาน</option>
                                        <option value="suspended" <?php echo normalizeStatus($selected_group['status'] ?? 'active') === 'suspended' ? 'selected' : ''; ?>>ระงับ</option>
                                    </select>
                                </div>
                            </div>

                            <div class="span-4">
                                <div class="field-row">
                                    <label>วิธีจัดการรหัสเชิญ</label>
                                    <div class="radio-group">
                                        <label class="radio-pill">
                                            <input type="radio" name="invite_strategy_edit" value="custom" data-invite-strategy checked>
                                            ใช้/แก้ไขรหัสเอง
                                        </label>
                                        <label class="radio-pill">
                                            <input type="radio" name="invite_strategy_edit" value="auto" data-invite-strategy>
                                            สุ่มรหัสใหม่อัตโนมัติ
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="span-6">
                                <div class="field-row">
                                    <label for="invite_code_edit">รหัสเชิญปัจจุบัน</label>
                                    <input
                                        type="text"
                                        id="invite_code_edit"
                                        name="invite_code"
                                        data-custom-code-input
                                        maxlength="40"
                                        value="<?php echo h($selected_group['invite_code'] ?? ''); ?>"
                                    >
                                    <p class="help">ถ้าไม่แก้ไขค่า รหัสเดิมจะถูกใช้งานต่อ | ถ้าเลือกสุ่มอัตโนมัติ ระบบจะออกโค้ดใหม่</p>
                                </div>
                            </div>

                            <div class="span-6">
                                <div class="field-row">
                                    <label>ข้อมูลล่าสุด</label>
                                    <div class="help">สร้างเมื่อ: <?php echo h(formatDateTimeValue($selected_group['created_at'] ?? '')); ?></div>
                                    <div class="help">แก้ไขล่าสุด: <?php echo h(formatDateTimeValue($selected_group['updated_at'] ?? '')); ?></div>
                                    <div class="help">รหัสปัจจุบัน: <span class="invite-code"><?php echo h($selected_group['invite_code'] ?? '-'); ?></span></div>
                                </div>
                            </div>
                        </div>

                        <div class="actions">
                            <button class="btn" type="submit">บันทึกการเปลี่ยนแปลงทีมขาย</button>
                            <button
                                class="btn muted"
                                type="button"
                                data-copy-code="<?php echo h((string)($selected_group['invite_code'] ?? '')); ?>"
                            >คัดลอกรหัสเชิญ</button>
                            <button
                                class="btn muted"
                                type="button"
                                data-share-link="<?php echo h(buildInviteJoinUrl((string)($selected_group['invite_code'] ?? ''))); ?>"
                                data-invite-code="<?php echo h((string)($selected_group['invite_code'] ?? '')); ?>"
                            >แชร์ลิงก์เชิญ</button>
                        </div>
                    </form>
                </div>
            </article>

            <article class="card span-5">
                <div class="card-head">
                    <h2>คำขอรออนุมัติ</h2>
                    <p>รายการพนักงานขายที่ส่งคำขอเข้าทีมนี้ และรอหัวหน้าทีมยืนยัน</p>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_member_rows)): ?>
                        <div class="empty">ไม่มีคำขอรออนุมัติในขณะนี้</div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>พนักงานขาย</th>
                                        <th>Role ระบบ</th>
                                        <th>ตำแหน่งในทีม</th>
                                        <th>ส่งคำขอเมื่อ</th>
                                        <th>จัดการคำขอ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pending_member_rows as $pending_row): ?>
                                    <?php
                                    $pending_member_id = (int)($pending_row['id'] ?? 0);
                                    $pending_member_name = trim(
                                        (string)($pending_row['first_name'] ?? '') .
                                        ' ' .
                                        (string)($pending_row['last_name'] ?? '')
                                    );
                                    if ($pending_member_name === '') {
                                        $pending_member_name = (string)($pending_row['member_user_id'] ?? '-');
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($pending_member_name); ?></strong><br>
                                            <span class="help" style="margin:0;display:inline-block;">User ID: <?php echo h($pending_row['member_user_id'] ?? '-'); ?></span>
                                        </td>
                                        <td class="nowrap"><?php echo h($pending_row['user_role'] ?? '-'); ?></td>
                                        <td><?php echo h($pending_row['member_title'] ?? 'Sales'); ?></td>
                                        <td class="nowrap"><?php echo h(formatDateTimeValue($pending_row['created_at'] ?? '')); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <form method="post" class="row-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="approve_member">
                                                    <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                    <input type="hidden" name="member_id" value="<?php echo h((string)$pending_member_id); ?>">
                                                    <button class="btn muted small" type="submit">ยอมรับ</button>
                                                </form>

                                                <form
                                                    method="post"
                                                    class="row-form"
                                                    onsubmit="return confirm('ยืนยันการยกเลิกคำขอเข้าทีมของสมาชิกคนนี้?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="reject_member">
                                                    <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                    <input type="hidden" name="member_id" value="<?php echo h((string)$pending_member_id); ?>">
                                                    <button class="btn warn small" type="submit">ยกเลิกคำขอ</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="card span-5">
                <div class="card-head">
                    <h2>เพิ่มพนักงานขายเข้าทีม</h2>
                    <p>เพิ่มได้เฉพาะผู้ใช้ role sell_car หรือ employee และต้องมีสิทธิ์ branch เดียวกัน</p>
                </div>
                <div class="card-body">
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">

                        <div class="field-row">
                            <label for="member_user_id">พนักงานขาย</label>
                            <select id="member_user_id" name="member_user_id" required>
                                <option value="">-- เลือกพนักงานขาย --</option>
                                <?php foreach ($eligible_users as $eligible_user): ?>
                                    <?php $eligible_user_id = trim((string)($eligible_user['user_id'] ?? '')); ?>
                                    <?php
                                    $eligible_name = trim(
                                        (string)($eligible_user['first_name'] ?? '') .
                                        ' ' .
                                        (string)($eligible_user['last_name'] ?? '')
                                    );
                                    if ($eligible_name === '') {
                                        $eligible_name = $eligible_user_id;
                                    }
                                    ?>
                                    <option
                                        value="<?php echo h($eligible_user_id); ?>"
                                        <?php echo $form_member['member_user_id'] === $eligible_user_id ? 'selected' : ''; ?>
                                    >
                                        <?php echo h($eligible_name . ' (' . ($eligible_user['user_role'] ?? '-') . ' | ' . $eligible_user_id . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field-row">
                            <label for="member_title">ตำแหน่งในทีม</label>
                            <input
                                type="text"
                                id="member_title"
                                name="member_title"
                                maxlength="100"
                                value="<?php echo h($form_member['member_title']); ?>"
                                placeholder="เช่น Senior Sales"
                            >
                        </div>

                        <div class="actions">
                            <button class="btn" type="submit">เพิ่มสมาชิกเข้าทีม</button>
                        </div>
                    </form>
                </div>
            </article>

            <article class="card span-7">
                <div class="card-head">
                    <h2>สมาชิกในทีมขาย</h2>
                    <p>หัวหน้าทีม (ผู้สร้างรหัส) สามารถอนุมัติหรือยกเลิกคำขอสมาชิกใหม่ และจัดการสมาชิกทีมได้จากหน้านี้</p>
                </div>
                <div class="card-body">
                    <?php if (empty($member_rows)): ?>
                        <div class="empty">ยังไม่มีสมาชิกในทีมนี้</div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>พนักงานขาย</th>
                                        <th>Role ระบบ</th>
                                        <th>ตำแหน่งในทีม</th>
                                        <th>สถานะ</th>
                                        <th>อัปเดตล่าสุด</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($member_rows as $member_row): ?>
                                    <?php
                                    $member_id = (int)($member_row['id'] ?? 0);
                                    $member_status = normalizeStatus($member_row['status'] ?? 'active');
                                    $member_name = trim(
                                        (string)($member_row['first_name'] ?? '') .
                                        ' ' .
                                        (string)($member_row['last_name'] ?? '')
                                    );
                                    if ($member_name === '') {
                                        $member_name = (string)($member_row['member_user_id'] ?? '-');
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo h($member_name); ?></strong><br>
                                            <span class="help" style="margin:0;display:inline-block;">User ID: <?php echo h($member_row['member_user_id'] ?? '-'); ?></span>
                                        </td>
                                        <td class="nowrap"><?php echo h($member_row['user_role'] ?? '-'); ?></td>
                                        <td><?php echo h($member_row['member_title'] ?? 'Sales'); ?></td>
                                        <td>
                                            <span class="status <?php echo h($member_status); ?>"><?php echo h(getStatusLabel($member_status)); ?></span>
                                        </td>
                                        <td class="nowrap"><?php echo h(formatDateTimeValue($member_row['updated_at'] ?? $member_row['created_at'] ?? '')); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <form method="post" class="row-form" novalidate>
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="update_member">
                                                    <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                    <input type="hidden" name="member_id" value="<?php echo h((string)$member_id); ?>">
                                                    <input
                                                        type="text"
                                                        name="member_title"
                                                        maxlength="100"
                                                        value="<?php echo h($member_row['member_title'] ?? 'Sales'); ?>"
                                                    >
                                                    <select name="member_status">
                                                        <option value="pending" <?php echo $member_status === 'pending' ? 'selected' : ''; ?>>รออนุมัติ</option>
                                                        <option value="active" <?php echo $member_status === 'active' ? 'selected' : ''; ?>>ใช้งาน</option>
                                                        <option value="suspended" <?php echo $member_status === 'suspended' ? 'selected' : ''; ?>>ระงับ</option>
                                                    </select>
                                                    <button class="btn muted small" type="submit">บันทึก</button>
                                                </form>

                                                <?php if ($member_status === 'pending'): ?>
                                                    <form method="post" class="row-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="approve_member">
                                                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                        <input type="hidden" name="member_id" value="<?php echo h((string)$member_id); ?>">
                                                        <button class="btn muted small" type="submit">ยอมรับ</button>
                                                    </form>

                                                    <form
                                                        method="post"
                                                        class="row-form"
                                                        onsubmit="return confirm('ยืนยันการยกเลิกคำขอเข้าทีมของสมาชิกคนนี้?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="reject_member">
                                                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                        <input type="hidden" name="member_id" value="<?php echo h((string)$member_id); ?>">
                                                        <button class="btn warn small" type="submit">ยกเลิกคำขอ</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="row-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="<?php echo $member_status === 'active' ? 'suspend_member' : 'resume_member'; ?>"
                                                        >
                                                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                        <input type="hidden" name="member_id" value="<?php echo h((string)$member_id); ?>">
                                                        <button class="btn muted small" type="submit">
                                                            <?php echo $member_status === 'active' ? 'ระงับ' : 'เปิดใช้'; ?>
                                                        </button>
                                                    </form>

                                                    <form
                                                        method="post"
                                                        class="row-form"
                                                        onsubmit="return confirm('ยืนยันการลบสมาชิกทีมคนนี้?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                        <input type="hidden" name="action" value="delete_member">
                                                        <input type="hidden" name="group_id" value="<?php echo h((string)($selected_group['id'] ?? 0)); ?>">
                                                        <input type="hidden" name="member_id" value="<?php echo h((string)$member_id); ?>">
                                                        <button class="btn warn small" type="submit">ลบ</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </section>
</div>

<div id="invite-toast" class="invite-toast" role="status" aria-live="polite"></div>

<script>
(function () {
    function setupInviteForm(form) {
        if (!form) {
            return;
        }

        var strategyInputs = form.querySelectorAll('[data-invite-strategy]');
        var customCodeInput = form.querySelector('[data-custom-code-input]');

        if (!strategyInputs.length || !customCodeInput) {
            return;
        }

        function refreshState() {
            var mode = 'auto';
            strategyInputs.forEach(function (input) {
                if (input.checked) {
                    mode = input.value;
                }
            });

            customCodeInput.disabled = mode !== 'custom';
            customCodeInput.style.opacity = mode === 'custom' ? '1' : '0.65';
        }

        strategyInputs.forEach(function (input) {
            input.addEventListener('change', refreshState);
        });

        refreshState();
    }

    var forms = document.querySelectorAll('.invite-form');
    forms.forEach(setupInviteForm);

    var toastEl = document.getElementById('invite-toast');
    var toastTimer = null;

    function showToast(message, isError) {
        if (!toastEl) {
            return;
        }

        toastEl.textContent = message;
        toastEl.classList.toggle('error', !!isError);
        toastEl.classList.add('show');

        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }

        toastTimer = window.setTimeout(function () {
            toastEl.classList.remove('show');
        }, 2200);
    }

    function fallbackCopyText(value) {
        var input = document.createElement('textarea');
        input.value = value;
        input.setAttribute('readonly', 'readonly');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();

        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(input);

        if (!copied) {
            throw new Error('copy-failed');
        }
    }

    async function copyText(value) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            await navigator.clipboard.writeText(value);
            return;
        }

        fallbackCopyText(value);
    }

    var copyCodeButtons = document.querySelectorAll('[data-copy-code]');
    copyCodeButtons.forEach(function (button) {
        button.addEventListener('click', async function () {
            var code = String(button.getAttribute('data-copy-code') || '').trim();
            if (!code) {
                showToast('ไม่พบรหัสเชิญที่ต้องการคัดลอก', true);
                return;
            }

            try {
                await copyText(code);
                showToast('คัดลอกรหัสเชิญแล้ว: ' + code, false);
            } catch (error) {
                showToast('คัดลอกรหัสเชิญไม่สำเร็จ', true);
            }
        });
    });

    var shareLinkButtons = document.querySelectorAll('[data-share-link]');
    shareLinkButtons.forEach(function (button) {
        button.addEventListener('click', async function () {
            var link = String(button.getAttribute('data-share-link') || '').trim();
            var code = String(button.getAttribute('data-invite-code') || '').trim();

            if (!link) {
                showToast('ไม่พบลิงก์เชิญที่ต้องการแชร์', true);
                return;
            }

            if (navigator.share && typeof navigator.share === 'function') {
                try {
                    await navigator.share({
                        title: 'ลิงก์เข้าร่วมทีมขาย',
                        text: code ? ('รหัสเชิญ: ' + code) : 'ลิงก์เข้าร่วมทีมขาย',
                        url: link
                    });
                    showToast('แชร์ลิงก์เชิญเรียบร้อยแล้ว', false);
                    return;
                } catch (shareError) {
                    if (shareError && shareError.name === 'AbortError') {
                        return;
                    }
                }
            }

            try {
                await copyText(link);
                showToast('คัดลอกลิงก์เชิญแล้ว', false);
            } catch (error) {
                showToast('แชร์/คัดลอกลิงก์เชิญไม่สำเร็จ', true);
            }
        });
    });
})();
</script>
</body>
</html>


