<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!userHasAnyRole(['sell_car', 'employee', 'sales_manager'])) {
        header("Location: ../../auth/login");
        exit();
    }

    enforceCurrentUserDashboardMenuAccess('customer', ['sidebar']);

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/sell/page_customer_management.php'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in sell/page_customer_management.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function textLen($value) {
    if (function_exists('mb_strlen')) {
        return mb_strlen((string)$value, 'UTF-8');
    }

    return strlen((string)$value);
}

function normalizeText($value) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
}

function normalizePhone($value) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', '', $value) ?? $value;
    return $value;
}

function normalizePhoneForMatch($value) {
    $value = trim((string)$value);
    $value = preg_replace('/[^0-9]/', '', $value) ?? $value;
    return $value;
}

function parseMoneyInput($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }

    $raw = str_replace([',', ' '], '', $raw);
    if (!is_numeric($raw)) {
        return null;
    }

    $number = (float)$raw;
    if ($number < 0) {
        return null;
    }

    return round($number, 2);
}

function normalizeDateInput($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return false;
    }

    return date('Y-m-d', $timestamp);
}

function normalizeDateTimeInput($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $value = str_replace('T', ' ', $value);
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return false;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function toDateInputValue($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function toDateTimeLocalValue($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\\TH:i', $timestamp);
}

function formatDateTimeDisplay($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatDateDisplay($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y', $timestamp);
}

function formatMoneyDisplay($value) {
    return number_format((float)$value, 2);
}

function getLeadSourceOptions() {
    return [
        'facebook' => 'Facebook',
        'walk_in' => 'Walk-in',
        'refer' => 'Refer',
        'line' => 'LINE',
        'website' => 'Website',
        'other' => 'Other'
    ];
}

function normalizeLeadSource($value) {
    $value = strtolower(trim((string)$value));
    $options = getLeadSourceOptions();
    if (!isset($options[$value])) {
        return 'other';
    }

    return $value;
}

function getPipelineStatusOptions() {
    return [
        'new_lead' => 'New Lead',
        'contacted' => 'Contacted',
        'interested' => 'Interested',
        'test_drive' => 'Test Drive',
        'quotation' => 'Quotation',
        'booking' => 'Booking',
        'delivered' => 'Delivered',
        'lost' => 'Lost'
    ];
}

function normalizePipelineStatus($value) {
    $value = strtolower(trim((string)$value));
    $options = getPipelineStatusOptions();
    if (!isset($options[$value])) {
        return 'new_lead';
    }

    return $value;
}

function getApprovalStatusOptions() {
    return [
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ'
    ];
}

function normalizeApprovalStatus($value) {
    $value = strtolower(trim((string)$value));
    $options = getApprovalStatusOptions();
    if (!isset($options[$value])) {
        return 'pending';
    }

    return $value;
}

function getTimelineActivityOptions() {
    return [
        'call' => 'โทร',
        'chat' => 'แชต',
        'meeting' => 'นัดคุย',
        'test_drive' => 'Test Drive',
        'note' => 'บันทึกทั่วไป'
    ];
}

function normalizeTimelineActivity($value) {
    $value = strtolower(trim((string)$value));
    $options = getTimelineActivityOptions();
    if (!isset($options[$value])) {
        return 'note';
    }

    return $value;
}

function normalizeGroupStatus($status) {
    $status = strtolower(trim((string)$status));
    return $status === 'suspended' ? 'suspended' : 'active';
}

function normalizeMemberStatus($status) {
    $status = strtolower(trim((string)$status));
    if (in_array($status, ['pending', 'active', 'suspended'], true)) {
        return $status;
    }

    return 'active';
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

function ensureSalesCustomerTables(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_customer_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            branch_id VARCHAR(30) NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            owner_user_id VARCHAR(50) NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_phone VARCHAR(30) NOT NULL,
            customer_line VARCHAR(80) DEFAULT NULL,
            customer_province VARCHAR(100) DEFAULT NULL,
            lead_source ENUM('facebook', 'walk_in', 'refer', 'line', 'website', 'other') NOT NULL DEFAULT 'other',
            interested_model VARCHAR(150) NOT NULL,
            budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            down_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
            monthly_budget DECIMAL(12,2) NOT NULL DEFAULT 0,
            target_purchase_date DATE DEFAULT NULL,
            pipeline_status ENUM('new_lead', 'contacted', 'interested', 'test_drive', 'quotation', 'booking', 'delivered', 'lost') NOT NULL DEFAULT 'new_lead',
            last_contact_at DATETIME DEFAULT NULL,
            next_followup_at DATETIME DEFAULT NULL,
            next_followup_note VARCHAR(255) DEFAULT NULL,
            approval_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            approval_note VARCHAR(255) DEFAULT NULL,
            approved_by VARCHAR(50) DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_by VARCHAR(50) NOT NULL,
            updated_by VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_scr_branch_group (branch_id, group_id),
            KEY idx_scr_owner_branch (owner_user_id, branch_id),
            KEY idx_scr_pipeline (pipeline_status),
            KEY idx_scr_approval (approval_status),
            KEY idx_scr_next_followup (next_followup_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_customer_timeline (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            actor_user_id VARCHAR(50) NOT NULL,
            activity_type ENUM('call', 'chat', 'meeting', 'test_drive', 'note') NOT NULL DEFAULT 'note',
            activity_action VARCHAR(255) DEFAULT NULL,
            discussion_topic VARCHAR(255) DEFAULT NULL,
            activity_note VARCHAR(500) NOT NULL,
            next_followup_at DATETIME DEFAULT NULL,
            next_followup_note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sct_customer_created (customer_id, created_at),
            KEY idx_sct_branch_group (branch_id, group_id),
            CONSTRAINT fk_sales_customer_timeline_customer
                FOREIGN KEY (customer_id) REFERENCES sales_customer_records(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_customer_sla_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            owner_user_id VARCHAR(50) NOT NULL,
            alert_type ENUM('followup_overdue') NOT NULL DEFAULT 'followup_overdue',
            severity ENUM('warning', 'critical', 'breach') NOT NULL DEFAULT 'warning',
            status ENUM('open', 'resolved') NOT NULL DEFAULT 'open',
            due_at DATETIME NOT NULL,
            triggered_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            resolved_by VARCHAR(50) DEFAULT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sales_customer_sla_customer_type (customer_id, alert_type),
            KEY idx_sales_customer_sla_scope_status (branch_id, group_id, status),
            KEY idx_sales_customer_sla_owner_status (owner_user_id, status),
            CONSTRAINT fk_sales_customer_sla_customer
                FOREIGN KEY (customer_id) REFERENCES sales_customer_records(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_customer_sla_assignments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            group_id INT UNSIGNED NOT NULL,
            from_owner_user_id VARCHAR(50) NOT NULL,
            to_owner_user_id VARCHAR(50) NOT NULL,
            assign_reason VARCHAR(500) NOT NULL,
            assigned_by VARCHAR(50) NOT NULL,
            assigned_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_scsa_customer_assigned (customer_id, assigned_at),
            KEY idx_scsa_branch_group (branch_id, group_id),
            KEY idx_scsa_to_owner (to_owner_user_id, assigned_at),
            CONSTRAINT fk_sales_customer_sla_assign_customer
                FOREIGN KEY (customer_id) REFERENCES sales_customer_records(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Backward compatibility for timeline detail columns.
    $timeline_action_column_stmt = $pdo->query("SHOW COLUMNS FROM sales_customer_timeline LIKE 'activity_action'");
    $timeline_action_column_exists = $timeline_action_column_stmt && $timeline_action_column_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$timeline_action_column_exists) {
        $pdo->exec("ALTER TABLE sales_customer_timeline ADD COLUMN activity_action VARCHAR(255) DEFAULT NULL AFTER activity_type");
    }

    $timeline_topic_column_stmt = $pdo->query("SHOW COLUMNS FROM sales_customer_timeline LIKE 'discussion_topic'");
    $timeline_topic_column_exists = $timeline_topic_column_stmt && $timeline_topic_column_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$timeline_topic_column_exists) {
        $pdo->exec("ALTER TABLE sales_customer_timeline ADD COLUMN discussion_topic VARCHAR(255) DEFAULT NULL AFTER activity_action");
    }

    $timeline_next_note_column_stmt = $pdo->query("SHOW COLUMNS FROM sales_customer_timeline LIKE 'next_followup_note'");
    $timeline_next_note_column_exists = $timeline_next_note_column_stmt && $timeline_next_note_column_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$timeline_next_note_column_exists) {
        $pdo->exec("ALTER TABLE sales_customer_timeline ADD COLUMN next_followup_note VARCHAR(255) DEFAULT NULL AFTER next_followup_at");
    }
}

function getSlaSeverityByDelaySeconds($delaySeconds) {
    $delaySeconds = max(0, (int)$delaySeconds);

    if ($delaySeconds >= 86400) {
        return 'breach';
    }

    if ($delaySeconds >= 14400) {
        return 'critical';
    }

    return 'warning';
}

function getSlaSeverityLabel($severity) {
    $severity = strtolower(trim((string)$severity));
    if ($severity === 'breach') {
        return 'เกิน SLA';
    }

    if ($severity === 'critical') {
        return 'เร่งด่วนสูง';
    }

    return 'ค้างติดตาม';
}

function formatDurationCompact($seconds) {
    $seconds = max(0, (int)$seconds);

    $days = intdiv($seconds, 86400);
    $remaining = $seconds % 86400;
    $hours = intdiv($remaining, 3600);
    $remaining = $remaining % 3600;
    $minutes = intdiv($remaining, 60);

    if ($days > 0) {
        return $days . ' วัน ' . $hours . ' ชม.';
    }

    if ($hours > 0) {
        return $hours . ' ชม. ' . max(1, $minutes) . ' นาที';
    }

    return max(1, $minutes) . ' นาที';
}

function buildCustomerScopeWhereClause($role, $selectedGroupId, array $managedGroupIds, $sellerGroupId, $userId, $alias, array &$params) {
    $alias = trim((string)$alias);
    if ($alias === '') {
        $alias = 'c';
    }

    if ((string)$role === 'sales_manager') {
        if ((int)$selectedGroupId > 0) {
            $params[] = (int)$selectedGroupId;
            return ' AND ' . $alias . '.group_id = ?';
        }

        $cleanGroupIds = [];
        foreach ($managedGroupIds as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId > 0) {
                $cleanGroupIds[$groupId] = $groupId;
            }
        }
        $cleanGroupIds = array_values($cleanGroupIds);

        if (empty($cleanGroupIds)) {
            return ' AND 1 = 0';
        }

        $placeholders = implode(',', array_fill(0, count($cleanGroupIds), '?'));
        foreach ($cleanGroupIds as $groupId) {
            $params[] = $groupId;
        }

        return ' AND ' . $alias . '.group_id IN (' . $placeholders . ')';
    }

    $sellerGroupId = (int)$sellerGroupId;
    if ($sellerGroupId <= 0) {
        return ' AND 1 = 0';
    }

    $params[] = $sellerGroupId;
    $params[] = (string)$userId;

    return ' AND ' . $alias . '.group_id = ? AND ' . $alias . '.owner_user_id = ?';
}

function syncFollowupSlaAlerts(PDO $pdo, $branchId, $role, $selectedGroupId, array $managedGroupIds, $sellerGroupId, $userId) {
    $nowDateTime = date('Y-m-d H:i:s');
    $nowTs = time();

    $scopeSql =
        'SELECT
            c.id,
            c.branch_id,
            c.group_id,
            c.owner_user_id,
            c.next_followup_at,
            c.pipeline_status
         FROM sales_customer_records c
         WHERE c.branch_id = ?';
    $scopeParams = [(string)$branchId];
    $scopeSql .= buildCustomerScopeWhereClause($role, $selectedGroupId, $managedGroupIds, $sellerGroupId, $userId, 'c', $scopeParams);

    $scopeStmt = $pdo->prepare($scopeSql);
    $scopeStmt->execute($scopeParams);
    $scopeRows = $scopeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $scopeCustomerIds = [];
    $overdueRows = [];
    foreach ($scopeRows as $scopeRow) {
        $customerId = (int)($scopeRow['id'] ?? 0);
        if ($customerId <= 0) {
            continue;
        }

        $scopeCustomerIds[$customerId] = $customerId;

        $pipelineStatus = normalizePipelineStatus($scopeRow['pipeline_status'] ?? 'new_lead');
        if (in_array($pipelineStatus, ['delivered', 'lost'], true)) {
            continue;
        }

        $dueRaw = trim((string)($scopeRow['next_followup_at'] ?? ''));
        if ($dueRaw === '') {
            continue;
        }

        $dueTs = strtotime($dueRaw);
        if ($dueTs === false || $dueTs > $nowTs) {
            continue;
        }

        $delaySeconds = $nowTs - $dueTs;
        $severity = getSlaSeverityByDelaySeconds($delaySeconds);

        $overdueRows[] = [
            'customer_id' => $customerId,
            'branch_id' => (string)($scopeRow['branch_id'] ?? $branchId),
            'group_id' => (int)($scopeRow['group_id'] ?? 0),
            'owner_user_id' => (string)($scopeRow['owner_user_id'] ?? ''),
            'severity' => $severity,
            'due_at' => date('Y-m-d H:i:s', $dueTs)
        ];
    }

    if (!empty($overdueRows)) {
        $upsertStmt = $pdo->prepare(
            'INSERT INTO sales_customer_sla_alerts
                (
                    customer_id, branch_id, group_id, owner_user_id,
                    alert_type, severity, status, due_at,
                    triggered_at, last_seen_at
                )
             VALUES
                (?, ?, ?, ?, "followup_overdue", ?, "open", ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                branch_id = VALUES(branch_id),
                group_id = VALUES(group_id),
                owner_user_id = VALUES(owner_user_id),
                severity = VALUES(severity),
                due_at = VALUES(due_at),
                status = "open",
                last_seen_at = VALUES(last_seen_at),
                triggered_at = IF(status = "open", triggered_at, VALUES(triggered_at)),
                resolved_at = NULL,
                resolved_by = NULL'
        );

        foreach ($overdueRows as $overdueRow) {
            $upsertStmt->execute([
                (int)$overdueRow['customer_id'],
                (string)$overdueRow['branch_id'],
                (int)$overdueRow['group_id'],
                (string)$overdueRow['owner_user_id'],
                (string)$overdueRow['severity'],
                (string)$overdueRow['due_at'],
                $nowDateTime,
                $nowDateTime
            ]);
        }
    }

    $scopeCustomerIds = array_values($scopeCustomerIds);
    if (empty($scopeCustomerIds)) {
        return;
    }

    $overdueCustomerIds = [];
    foreach ($overdueRows as $overdueRow) {
        $overdueCustomerIds[(int)$overdueRow['customer_id']] = (int)$overdueRow['customer_id'];
    }
    $overdueCustomerIds = array_values($overdueCustomerIds);

    $scopePlaceholders = implode(',', array_fill(0, count($scopeCustomerIds), '?'));
    $resolveSql =
        'UPDATE sales_customer_sla_alerts
         SET
            status = "resolved",
            resolved_at = ?,
            resolved_by = NULL,
            last_seen_at = ?
         WHERE alert_type = "followup_overdue"
           AND status = "open"
           AND customer_id IN (' . $scopePlaceholders . ')';
    $resolveParams = [$nowDateTime, $nowDateTime];
    foreach ($scopeCustomerIds as $scopeCustomerId) {
        $resolveParams[] = (int)$scopeCustomerId;
    }

    if (!empty($overdueCustomerIds)) {
        $overduePlaceholders = implode(',', array_fill(0, count($overdueCustomerIds), '?'));
        $resolveSql .= ' AND customer_id NOT IN (' . $overduePlaceholders . ')';
        foreach ($overdueCustomerIds as $overdueCustomerId) {
            $resolveParams[] = (int)$overdueCustomerId;
        }
    }

    $resolveStmt = $pdo->prepare($resolveSql);
    $resolveStmt->execute($resolveParams);
}

function fetchSlaAlertSummary(PDO $pdo, $branchId, $role, $selectedGroupId, array $managedGroupIds, $sellerGroupId, $userId) {
    $summarySql =
        'SELECT
            COUNT(*) AS open_total,
            SUM(CASE WHEN a.severity = "warning" THEN 1 ELSE 0 END) AS warning_total,
            SUM(CASE WHEN a.severity = "critical" THEN 1 ELSE 0 END) AS critical_total,
            SUM(CASE WHEN a.severity = "breach" THEN 1 ELSE 0 END) AS breach_total,
            SUM(CASE WHEN DATE(a.triggered_at) = CURDATE() THEN 1 ELSE 0 END) AS triggered_today_total
         FROM sales_customer_sla_alerts a
         WHERE a.branch_id = ?
           AND a.alert_type = "followup_overdue"
           AND a.status = "open"';
    $summaryParams = [(string)$branchId];
    $summarySql .= buildCustomerScopeWhereClause($role, $selectedGroupId, $managedGroupIds, $sellerGroupId, $userId, 'a', $summaryParams);

    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'open_total' => (int)($summaryRow['open_total'] ?? 0),
        'warning_total' => (int)($summaryRow['warning_total'] ?? 0),
        'critical_total' => (int)($summaryRow['critical_total'] ?? 0),
        'breach_total' => (int)($summaryRow['breach_total'] ?? 0),
        'triggered_today_total' => (int)($summaryRow['triggered_today_total'] ?? 0)
    ];
}

function fetchOpenSlaAlerts(PDO $pdo, $branchId, $role, $selectedGroupId, array $managedGroupIds, $sellerGroupId, $userId, $limit = 80) {
    $limit = max(1, min(200, (int)$limit));

    $alertSql =
        'SELECT
            a.customer_id,
            a.group_id,
            a.owner_user_id,
            a.severity,
            a.due_at,
            a.triggered_at,
            TIMESTAMPDIFF(SECOND, a.due_at, NOW()) AS overdue_seconds,
            c.customer_name,
            c.customer_phone,
            c.interested_model,
            c.pipeline_status,
            c.next_followup_at,
            c.next_followup_note,
            g.group_name,
            CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name
         FROM sales_customer_sla_alerts a
         INNER JOIN sales_customer_records c ON c.id = a.customer_id
         LEFT JOIN sales_group_invites g ON g.id = a.group_id
         LEFT JOIN users u ON u.user_id = a.owner_user_id
         WHERE a.branch_id = ?
           AND a.alert_type = "followup_overdue"
           AND a.status = "open"';
    $alertParams = [(string)$branchId];
    $alertSql .= buildCustomerScopeWhereClause($role, $selectedGroupId, $managedGroupIds, $sellerGroupId, $userId, 'a', $alertParams);
    $alertSql .= ' ORDER BY FIELD(a.severity, "breach", "critical", "warning"), a.due_at ASC LIMIT ' . $limit;

    $alertStmt = $pdo->prepare($alertSql);
    $alertStmt->execute($alertParams);

    return $alertStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchCurrentMembership(PDO $pdo, $userId, $branchId) {
    $stmt = $pdo->prepare(
        'SELECT
            m.id,
            m.group_id,
            m.member_title,
            m.status AS member_status,
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
    $stmt->execute([(string)$userId, (string)$branchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function fetchManagedGroups(PDO $pdo, $managerUserId, $branchId) {
    $stmt = $pdo->prepare(
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
            ) AS member_total,
            (
                SELECT COUNT(*)
                FROM sales_group_members sm
                WHERE sm.group_id = g.id
                  AND sm.status = "pending"
            ) AS pending_total
         FROM sales_group_invites g
         WHERE g.manager_user_id = ?
           AND g.branch_id = ?
         ORDER BY g.created_at DESC'
    );
    $stmt->execute([(string)$managerUserId, (string)$branchId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchGroupActiveMembers(PDO $pdo, $groupId, $branchId) {
    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT
            m.member_user_id,
            m.member_title,
            CONCAT_WS(" ", u.first_name, u.last_name) AS member_name
         FROM sales_group_members m
         LEFT JOIN users u ON u.user_id = m.member_user_id
         WHERE m.group_id = ?
           AND m.branch_id = ?
           AND m.status = "active"
         ORDER BY member_name ASC, m.member_user_id ASC'
    );
    $stmt->execute([$groupId, (string)$branchId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function buildCustomerPageUrl($module, $groupId = 0, $customerId = 0, $editId = 0) {
    $params = ['module' => (string)$module];
    if ((int)$groupId > 0) {
        $params['group_id'] = (int)$groupId;
    }
    if ((int)$customerId > 0) {
        $params['customer_id'] = (int)$customerId;
    }
    if ((int)$editId > 0) {
        $params['edit_id'] = (int)$editId;
    }

    return 'page_customer_management.php?' . http_build_query($params);
}

function findCustomerByAccess(PDO $pdo, $customerId, $branchId, $role, $userId, $selectedGroupId, array $managedGroupIds) {
    $customerId = (int)$customerId;
    if ($customerId <= 0) {
        return null;
    }

    $sql =
        'SELECT
            c.*,
            g.group_name,
            g.manager_user_id,
            CONCAT_WS(" ", ou.first_name, ou.last_name) AS owner_name,
            CONCAT_WS(" ", mu.first_name, mu.last_name) AS manager_name
         FROM sales_customer_records c
         LEFT JOIN sales_group_invites g ON g.id = c.group_id
         LEFT JOIN users ou ON ou.user_id = c.owner_user_id
         LEFT JOIN users mu ON mu.user_id = g.manager_user_id
         WHERE c.id = ?
           AND c.branch_id = ?';
    $params = [$customerId, (string)$branchId];

    if ((string)$role === 'sales_manager') {
        $cleanGroupIds = [];
        foreach ($managedGroupIds as $groupId) {
            $groupId = (int)$groupId;
            if ($groupId > 0) {
                $cleanGroupIds[] = $groupId;
            }
        }

        if (empty($cleanGroupIds)) {
            return null;
        }

        $managerGroupId = (int)$selectedGroupId;
        if ($managerGroupId > 0) {
            if (!in_array($managerGroupId, $cleanGroupIds, true)) {
                return null;
            }

            $sql .= ' AND c.group_id = ?';
            $params[] = $managerGroupId;
        } else {
            $placeholders = implode(',', array_fill(0, count($cleanGroupIds), '?'));
            $sql .= ' AND c.group_id IN (' . $placeholders . ')';
            foreach ($cleanGroupIds as $groupId) {
                $params[] = $groupId;
            }
        }
    } else {
        $sellerGroupId = (int)$selectedGroupId;
        if ($sellerGroupId <= 0) {
            return null;
        }

        $sql .= ' AND c.group_id = ? AND c.owner_user_id = ?';
        $params[] = $sellerGroupId;
        $params[] = (string)$userId;
    }

    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function findDuplicateCustomerInGroup(PDO $pdo, $branchId, $groupId, $customerPhone, $customerLine) {
    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        return null;
    }

    $matchPhone = normalizePhoneForMatch($customerPhone);
    $matchLine = strtolower(normalizeText($customerLine));

    $duplicateParts = [];
    $params = [(string)$branchId, $groupId];

    if ($matchPhone !== '') {
        $duplicateParts[] = "(c.customer_phone = ? OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.customer_phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ?)";
        $params[] = trim((string)$customerPhone);
        $params[] = $matchPhone;
    }

    if ($matchLine !== '') {
        $duplicateParts[] = 'LOWER(TRIM(c.customer_line)) = ?';
        $params[] = $matchLine;
    }

    if (empty($duplicateParts)) {
        return null;
    }

    $sql =
        'SELECT
            c.id,
            c.customer_name,
            c.customer_phone,
            c.customer_line,
            c.owner_user_id,
            CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name
         FROM sales_customer_records c
         LEFT JOIN users u ON u.user_id = c.owner_user_id
         WHERE c.branch_id = ?
           AND c.group_id = ?
           AND (' . implode(' OR ', $duplicateParts) . ')
         ORDER BY c.updated_at DESC, c.id DESC
         LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

$current_module = trim((string)($_GET['module'] ?? 'customer'));
if ($current_module === '') {
    $current_module = 'customer';
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

$dashboard_href = $current_user_role === 'sales_manager'
    ? '../sales_manager/page_sell_manager.php'
    : ($current_user_role === 'employee'
        ? '../employee/menuemployee.php'
        : 'pagesell.php');
$group_manage_href = $current_user_role === 'sales_manager'
    ? '../sales_manager/setup_group_sales.php?module=groupsetup'
    : 'join_group_sales.php?module=groupsjoin';
$group_manage_label = $current_user_role === 'sales_manager'
    ? 'ไปจัดการทีมขาย'
    : 'ไปหน้า Join Group';

$admin_display_name = $current_user_name;
$profile_full_name = $current_user_name;
$profile_first_name = (string)($_SESSION['first_name'] ?? '');
$profile_role = (string)($_SESSION['user_role'] ?? 'sell_car');
$profile_position = (string)($_SESSION['position'] ?? (
    $current_user_role === 'sales_manager'
        ? 'Sales Manager'
        : ($current_user_role === 'employee' ? 'Employee' : 'Sell Car')
));
$profile_image_src = '';
$nav_logo_src = '';

$dashboard_page_title = 'Customer CRM';
$dashboard_portal_label = 'Office Plus ERP - Customer CRM';

$csrf_token = generateCSRFToken();
$errors = [];
$success_message = trim((string)($_SESSION['crm_customer_success'] ?? ''));
unset($_SESSION['crm_customer_success']);

$default_form = [
    'customer_name' => '',
    'customer_phone' => '',
    'customer_line' => '',
    'customer_province' => '',
    'lead_source' => 'other',
    'interested_model' => '',
    'budget_amount' => '',
    'down_payment' => '',
    'monthly_budget' => '',
    'target_purchase_date' => '',
    'pipeline_status' => 'new_lead',
    'next_followup_at' => '',
    'next_followup_note' => '',
    'initial_note' => ''
];
$customer_form = $default_form;
$form_filled_from_post = false;

$timeline_form = [
    'activity_type' => 'call',
    'activity_action' => '',
    'activity_topic' => '',
    'activity_note' => '',
    'timeline_next_followup_at' => '',
    'timeline_next_note' => ''
];

$editing_customer_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$selected_customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

$managed_groups = [];
$managed_group_ids = [];
$managed_active_group_ids = [];
$managed_group_map = [];
$manager_assignable_members = [];
$current_membership = null;
$seller_scope_group_id = 0;
$customer_rows = [];
$pending_approval_rows = [];
$sla_alert_rows = [];
$sla_assignment_rows = [];
$timeline_rows = [];
$selected_customer = null;

$can_manage_records = false;
$group_display_name = '-';
$group_state_text = 'ไม่พบทีม';
$group_state_class = 'neutral';
$team_warning_message = '';

$kpi_total_customers = 0;
$kpi_pending_approval = 0;
$kpi_followup_due = 0;
$kpi_followup_today = 0;
$kpi_sla_open = 0;
$kpi_sla_warning = 0;
$kpi_sla_critical = 0;
$kpi_sla_breach = 0;
$kpi_sla_triggered_today = 0;
$now_ts = time();

try {
    $pdo = getDBConnection();
    ensureSalesGroupTables($pdo);
    ensureSalesCustomerTables($pdo);

    $has_profile_image_column = false;
    $column_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($column_stmt && $column_stmt->fetch()) {
        $has_profile_image_column = true;
    }

    if ($current_user_id !== '') {
        $select_fields = 'first_name, last_name, user_id, position, user_role';
        if ($has_profile_image_column) {
            $select_fields .= ', profile_image';
        }

        $user_stmt = $pdo->prepare('SELECT ' . $select_fields . ' FROM users WHERE user_id = ? LIMIT 1');
        $user_stmt->execute([$current_user_id]);
        $user_row = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_row) {
            $full_name = trim((string)($user_row['first_name'] ?? '') . ' ' . (string)($user_row['last_name'] ?? ''));
            if ($full_name !== '') {
                $admin_display_name = $full_name;
                $profile_full_name = $full_name;
                $profile_first_name = (string)($user_row['first_name'] ?? $profile_first_name);
            }

            if (!empty($user_row['position'])) {
                $profile_position = (string)$user_row['position'];
            }

            if (!empty($user_row['user_role'])) {
                $profile_role = (string)$user_row['user_role'];
            }

            if ($has_profile_image_column && !empty($user_row['profile_image'])) {
                $candidate_path = ltrim((string)$user_row['profile_image'], '/');
                $candidate_file = __DIR__ . '/../../' . $candidate_path;
                if ($candidate_path !== '' && is_file($candidate_file)) {
                    $profile_image_src = '../../' . $candidate_path;
                }
            }
        }
    }

    $default_logo_file = __DIR__ . '/../../assets/images/logo/logo.png';
    if (is_file($default_logo_file)) {
        $nav_logo_src = '../../assets/images/logo/logo.png';
    }

    $logo_stmt = $pdo->query('SELECT header_logo_path FROM company_settings WHERE id = 1 LIMIT 1');
    $logo_row = $logo_stmt ? $logo_stmt->fetch(PDO::FETCH_ASSOC) : false;
    $header_logo_path = (string)($logo_row['header_logo_path'] ?? '');
    if ($header_logo_path !== '') {
        $header_logo_file = __DIR__ . '/../../' . ltrim($header_logo_path, '/');
        if (is_file($header_logo_file)) {
            $nav_logo_src = '../../' . ltrim($header_logo_path, '/');
        }
    }

    $role_labels = getDashboardRoleLabels($pdo);
    $profile_role_label = $role_labels[$profile_role] ?? ucfirst($profile_role !== '' ? $profile_role : 'User');
    $menu_role_config = getDashboardMenuConfigByRole(
        $profile_role,
        $pdo,
        [
            'home_href' => $dashboard_href,
            'page_title' => 'Customer CRM',
            'portal_label' => 'Office Plus ERP - Customer CRM'
        ]
    );
    $dashboard_page_title = (string)($menu_role_config['page_title'] ?? $dashboard_page_title);
    $dashboard_portal_label = (string)($menu_role_config['portal_label'] ?? $dashboard_portal_label);

    if ($current_user_role === 'sales_manager') {
        $managed_groups = fetchManagedGroups($pdo, $current_user_id, $active_branch_id);

        foreach ($managed_groups as $group_row) {
            $group_id = (int)($group_row['id'] ?? 0);
            if ($group_id <= 0) {
                continue;
            }

            $managed_group_ids[] = $group_id;
            $managed_group_map[$group_id] = $group_row;

            $group_status = normalizeGroupStatus($group_row['status'] ?? 'active');
            if ($group_status === 'active') {
                $managed_active_group_ids[] = $group_id;
            }
        }

        if ($selected_group_id <= 0) {
            if (!empty($managed_active_group_ids)) {
                $selected_group_id = (int)$managed_active_group_ids[0];
            } elseif (!empty($managed_group_ids)) {
                $selected_group_id = (int)$managed_group_ids[0];
            }
        }

        if ($selected_group_id > 0 && !isset($managed_group_map[$selected_group_id])) {
            $errors[] = 'ทีมขายที่เลือกไม่อยู่ในสิทธิ์ของคุณ ระบบเลือกทีมแรกให้อัตโนมัติ';
            if (!empty($managed_active_group_ids)) {
                $selected_group_id = (int)$managed_active_group_ids[0];
            } else {
                $selected_group_id = !empty($managed_group_ids) ? (int)$managed_group_ids[0] : 0;
            }
        }

        if ($selected_group_id > 0 && isset($managed_group_map[$selected_group_id])) {
            $current_group_row = $managed_group_map[$selected_group_id];
            $group_display_name = (string)($current_group_row['group_name'] ?? '-');
            $group_state_value = normalizeGroupStatus($current_group_row['status'] ?? 'active');
            $group_state_text = $group_state_value === 'suspended' ? 'ทีมถูกระงับ' : 'ทีมใช้งาน';
            $group_state_class = $group_state_value === 'suspended' ? 'danger' : 'ok';
            $manager_assignable_members = fetchGroupActiveMembers($pdo, $selected_group_id, $active_branch_id);

            if ($group_state_value === 'active') {
                $can_manage_records = true;
            } else {
                $team_warning_message = 'ทีมขายที่เลือกถูกระงับ จึงยังไม่สามารถบันทึก/อนุมัติข้อมูลลูกค้าได้';
            }
        } else {
            $team_warning_message = 'ยังไม่มีทีมขายในสาขานี้ กรุณาสร้างทีมก่อนเริ่มบันทึกลูกค้า';
        }
    } else {
        $current_membership = fetchCurrentMembership($pdo, $current_user_id, $active_branch_id);
        if ($current_membership) {
            $selected_group_id = (int)($current_membership['group_id'] ?? 0);
            $group_display_name = trim((string)($current_membership['group_name'] ?? '-'));
            $member_state = normalizeMemberStatus($current_membership['member_status'] ?? 'active');
            $group_state = normalizeGroupStatus($current_membership['group_status'] ?? 'active');

            if ($member_state === 'active' && $group_state === 'active') {
                $group_state_text = 'สมาชิกทีมขาย (พร้อมใช้งาน)';
                $group_state_class = 'ok';
                $can_manage_records = true;
                $seller_scope_group_id = $selected_group_id;
            } elseif ($member_state === 'pending') {
                $group_state_text = 'รอหัวหน้าทีมอนุมัติ';
                $group_state_class = 'warn';
                $team_warning_message = 'คุณอยู่ระหว่างรออนุมัติเข้าทีมขาย จึงยังบันทึกข้อมูลลูกค้าไม่ได้';
            } elseif ($member_state === 'suspended' || $group_state === 'suspended') {
                $group_state_text = 'ทีม/สมาชิกถูกระงับ';
                $group_state_class = 'danger';
                $team_warning_message = 'สถานะทีมขายหรือสมาชิกถูกระงับ กรุณาติดต่อหัวหน้าทีม';
            } else {
                $team_warning_message = 'ยังไม่พร้อมใช้งานทีมขาย กรุณาตรวจสอบสถานะทีม';
            }
        } else {
            $team_warning_message = 'ยังไม่ได้เข้าร่วม Group Sale ในสาขานี้ กรุณา Join Group ก่อนเริ่มใช้งาน CRM';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));
        $posted_group_id = (int)($_POST['group_id'] ?? 0);
        if ($current_user_role === 'sales_manager' && $posted_group_id > 0) {
            if (isset($managed_group_map[$posted_group_id])) {
                $selected_group_id = $posted_group_id;
                $posted_group_status = normalizeGroupStatus($managed_group_map[$posted_group_id]['status'] ?? 'active');
                $can_manage_records = $posted_group_status === 'active';
                $group_display_name = (string)($managed_group_map[$posted_group_id]['group_name'] ?? $group_display_name);
                $group_state_text = $posted_group_status === 'suspended' ? 'ทีมถูกระงับ' : 'ทีมใช้งาน';
                $group_state_class = $posted_group_status === 'suspended' ? 'danger' : 'ok';
                $manager_assignable_members = fetchGroupActiveMembers($pdo, $selected_group_id, $active_branch_id);
                if (!$can_manage_records) {
                    $team_warning_message = 'ทีมขายที่เลือกถูกระงับ จึงยังไม่สามารถบันทึก/อนุมัติข้อมูลลูกค้าได้';
                }
            } else {
                $errors[] = 'ทีมขายที่ส่งมาไม่อยู่ในสิทธิ์ของคุณ';
            }
        }

        if ($action === 'create_customer' || $action === 'update_customer') {
            $customer_form['customer_name'] = normalizeText($_POST['customer_name'] ?? '');
            $customer_form['customer_phone'] = normalizePhone($_POST['customer_phone'] ?? '');
            $customer_form['customer_line'] = normalizeText($_POST['customer_line'] ?? '');
            $customer_form['customer_province'] = normalizeText($_POST['customer_province'] ?? '');
            $customer_form['lead_source'] = normalizeLeadSource($_POST['lead_source'] ?? 'other');
            $customer_form['interested_model'] = normalizeText($_POST['interested_model'] ?? '');
            $customer_form['budget_amount'] = trim((string)($_POST['budget_amount'] ?? ''));
            $customer_form['down_payment'] = trim((string)($_POST['down_payment'] ?? ''));
            $customer_form['monthly_budget'] = trim((string)($_POST['monthly_budget'] ?? ''));
            $customer_form['target_purchase_date'] = trim((string)($_POST['target_purchase_date'] ?? ''));
            $customer_form['pipeline_status'] = normalizePipelineStatus($_POST['pipeline_status'] ?? 'new_lead');
            $customer_form['next_followup_at'] = trim((string)($_POST['next_followup_at'] ?? ''));
            $customer_form['next_followup_note'] = normalizeText($_POST['next_followup_note'] ?? '');
            $customer_form['initial_note'] = normalizeText($_POST['initial_note'] ?? '');
            $form_filled_from_post = true;
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token';
        } elseif ($action === 'create_customer' || $action === 'update_customer') {
            if (!$can_manage_records) {
                $errors[] = 'คุณยังไม่มีสิทธิ์บันทึกข้อมูลลูกค้า กรุณาตรวจสอบสถานะทีมขาย';
            }

            if ($customer_form['customer_name'] === '') {
                $errors[] = 'กรุณาระบุชื่อลูกค้า';
            } elseif (textLen($customer_form['customer_name']) > 150) {
                $errors[] = 'ชื่อลูกค้ายาวเกินไป (สูงสุด 150 ตัวอักษร)';
            }

            if ($customer_form['customer_phone'] === '') {
                $errors[] = 'กรุณาระบุเบอร์โทรลูกค้า';
            } elseif (textLen($customer_form['customer_phone']) > 30) {
                $errors[] = 'เบอร์โทรยาวเกินไป';
            }

            if ($customer_form['customer_line'] !== '' && textLen($customer_form['customer_line']) > 80) {
                $errors[] = 'LINE ยาวเกินไป (สูงสุด 80 ตัวอักษร)';
            }

            if ($customer_form['customer_province'] !== '' && textLen($customer_form['customer_province']) > 100) {
                $errors[] = 'จังหวัดยาวเกินไป (สูงสุด 100 ตัวอักษร)';
            }

            if ($customer_form['interested_model'] === '') {
                $errors[] = 'กรุณาระบุรุ่นรถที่ลูกค้าสนใจ';
            } elseif (textLen($customer_form['interested_model']) > 150) {
                $errors[] = 'รุ่นรถยาวเกินไป (สูงสุด 150 ตัวอักษร)';
            }

            if ($customer_form['next_followup_note'] !== '' && textLen($customer_form['next_followup_note']) > 255) {
                $errors[] = 'หมายเหตุติดตามครั้งถัดไปยาวเกินไป (สูงสุด 255 ตัวอักษร)';
            }

            if ($customer_form['initial_note'] !== '' && textLen($customer_form['initial_note']) > 500) {
                $errors[] = 'บันทึกเริ่มต้นยาวเกินไป (สูงสุด 500 ตัวอักษร)';
            }

            $budget_amount = parseMoneyInput($customer_form['budget_amount']);
            $down_payment = parseMoneyInput($customer_form['down_payment']);
            $monthly_budget = parseMoneyInput($customer_form['monthly_budget']);

            if ($budget_amount === null) {
                $errors[] = 'งบประมาณไม่ถูกต้อง';
            }
            if ($down_payment === null) {
                $errors[] = 'เงินดาวน์ไม่ถูกต้อง';
            }
            if ($monthly_budget === null) {
                $errors[] = 'ค่างวดต่อเดือนไม่ถูกต้อง';
            }

            $target_purchase_date = normalizeDateInput($customer_form['target_purchase_date']);
            if ($target_purchase_date === false) {
                $errors[] = 'วันที่ต้องการออกรถไม่ถูกต้อง';
            }

            $next_followup_at = normalizeDateTimeInput($customer_form['next_followup_at']);
            if ($next_followup_at === false) {
                $errors[] = 'วันเวลาติดตามครั้งถัดไปไม่ถูกต้อง';
            }

            if (empty($errors)) {
                $effective_group_id = (int)$selected_group_id;
                if ($current_user_role !== 'sales_manager') {
                        $effective_group_id = (int)$seller_scope_group_id;
                }

                if ($effective_group_id <= 0) {
                    $errors[] = 'ไม่พบทีมขายสำหรับบันทึกข้อมูลลูกค้า';
                }

                if (empty($errors) && $action === 'create_customer') {
                    $duplicate_row = findDuplicateCustomerInGroup(
                        $pdo,
                        $active_branch_id,
                        $effective_group_id,
                        $customer_form['customer_phone'],
                        $customer_form['customer_line']
                    );

                    if ($duplicate_row) {
                        $duplicate_customer_id = (int)($duplicate_row['id'] ?? 0);
                        $duplicate_customer_name = trim((string)($duplicate_row['customer_name'] ?? ''));
                        $duplicate_owner = trim((string)($duplicate_row['owner_name'] ?? ''));
                        if ($duplicate_owner === '') {
                            $duplicate_owner = trim((string)($duplicate_row['owner_user_id'] ?? ''));
                        }

                        $errors[] = 'พบข้อมูลลูกค้าซ้ำในทีมเดียวกันจากเบอร์โทรหรือ LINE กรุณาตรวจสอบก่อนบันทึก';
                        if ($current_user_role === 'sales_manager' && $duplicate_customer_id > 0) {
                            $errors[] = 'รายการที่ซ้ำ: #' . $duplicate_customer_id . ' ' . ($duplicate_customer_name !== '' ? $duplicate_customer_name : '(ไม่ระบุชื่อ)');
                            if ($duplicate_owner !== '') {
                                $errors[] = 'เจ้าของปัจจุบัน: ' . $duplicate_owner;
                            }
                            $errors[] = 'เปิดรายการเดิม: ' . buildCustomerPageUrl($current_module, $effective_group_id, $duplicate_customer_id, 0);
                        }
                    }
                }

                if (empty($errors) && $action === 'create_customer') {
                    $approval_status = $current_user_role === 'sales_manager' ? 'approved' : 'pending';
                    $approval_note = null;
                    $approved_by = $current_user_role === 'sales_manager' ? $current_user_id : null;
                    $approved_at = $current_user_role === 'sales_manager' ? date('Y-m-d H:i:s') : null;

                    $insert_stmt = $pdo->prepare(
                        'INSERT INTO sales_customer_records
                            (
                                branch_id, group_id, owner_user_id, customer_name, customer_phone,
                                customer_line, customer_province, lead_source, interested_model,
                                budget_amount, down_payment, monthly_budget, target_purchase_date,
                                pipeline_status, next_followup_at, next_followup_note,
                                approval_status, approval_note, approved_by, approved_at,
                                created_by, updated_by
                            )
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert_stmt->execute([
                        $active_branch_id,
                        $effective_group_id,
                        $current_user_id,
                        $customer_form['customer_name'],
                        $customer_form['customer_phone'],
                        $customer_form['customer_line'] !== '' ? $customer_form['customer_line'] : null,
                        $customer_form['customer_province'] !== '' ? $customer_form['customer_province'] : null,
                        $customer_form['lead_source'],
                        $customer_form['interested_model'],
                        $budget_amount,
                        $down_payment,
                        $monthly_budget,
                        $target_purchase_date,
                        $customer_form['pipeline_status'],
                        $next_followup_at,
                        $customer_form['next_followup_note'] !== '' ? $customer_form['next_followup_note'] : null,
                        $approval_status,
                        $approval_note,
                        $approved_by,
                        $approved_at,
                        $current_user_id,
                        $current_user_id
                    ]);

                    $new_customer_id = (int)$pdo->lastInsertId();

                    if ($customer_form['initial_note'] !== '') {
                        $timeline_insert_stmt = $pdo->prepare(
                            'INSERT INTO sales_customer_timeline
                                (
                                    customer_id, branch_id, group_id, actor_user_id,
                                    activity_type, activity_action, discussion_topic,
                                    activity_note, next_followup_at, next_followup_note
                                )
                             VALUES (?, ?, ?, ?, "note", ?, ?, ?, ?, ?)'
                        );
                        $timeline_insert_stmt->execute([
                            $new_customer_id,
                            $active_branch_id,
                            $effective_group_id,
                            $current_user_id,
                            'บันทึกข้อมูลลูกค้าใหม่',
                            $customer_form['interested_model'] !== '' ? 'สนใจรุ่น ' . $customer_form['interested_model'] : null,
                            $customer_form['initial_note'],
                            $next_followup_at,
                            $customer_form['next_followup_note'] !== '' ? $customer_form['next_followup_note'] : null
                        ]);
                    }

                    $_SESSION['crm_customer_success'] = 'บันทึกข้อมูลลูกค้าเรียบร้อยแล้ว';
                    header('Location: ' . buildCustomerPageUrl(
                        $current_module,
                        $current_user_role === 'sales_manager' ? $effective_group_id : 0,
                        $new_customer_id,
                        0
                    ));
                    exit();
                }

                if (empty($errors) && $action === 'update_customer') {
                    $target_customer_id = (int)($_POST['customer_id'] ?? 0);
                    $target_row = findCustomerByAccess(
                        $pdo,
                        $target_customer_id,
                        $active_branch_id,
                        $current_user_role,
                        $current_user_id,
                        $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
                        $managed_group_ids
                    );

                    if (!$target_row) {
                        $errors[] = 'ไม่พบลูกค้าที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์แก้ไข';
                    } else {
                        $next_approval_status = $current_user_role === 'sales_manager' ? 'approved' : 'pending';
                        $next_approval_note = $current_user_role === 'sales_manager'
                            ? (trim((string)($target_row['approval_note'] ?? '')) !== '' ? trim((string)$target_row['approval_note']) : 'ปรับปรุงโดยหัวหน้าทีม')
                            : null;
                        $next_approved_by = $current_user_role === 'sales_manager' ? $current_user_id : null;
                        $next_approved_at = $current_user_role === 'sales_manager' ? date('Y-m-d H:i:s') : null;

                        $update_stmt = $pdo->prepare(
                            'UPDATE sales_customer_records
                             SET
                                customer_name = ?,
                                customer_phone = ?,
                                customer_line = ?,
                                customer_province = ?,
                                lead_source = ?,
                                interested_model = ?,
                                budget_amount = ?,
                                down_payment = ?,
                                monthly_budget = ?,
                                target_purchase_date = ?,
                                pipeline_status = ?,
                                next_followup_at = ?,
                                next_followup_note = ?,
                                approval_status = ?,
                                approval_note = ?,
                                approved_by = ?,
                                approved_at = ?,
                                updated_by = ?,
                                updated_at = CURRENT_TIMESTAMP
                             WHERE id = ?
                               AND branch_id = ?
                             LIMIT 1'
                        );
                        $update_stmt->execute([
                            $customer_form['customer_name'],
                            $customer_form['customer_phone'],
                            $customer_form['customer_line'] !== '' ? $customer_form['customer_line'] : null,
                            $customer_form['customer_province'] !== '' ? $customer_form['customer_province'] : null,
                            $customer_form['lead_source'],
                            $customer_form['interested_model'],
                            $budget_amount,
                            $down_payment,
                            $monthly_budget,
                            $target_purchase_date,
                            $customer_form['pipeline_status'],
                            $next_followup_at,
                            $customer_form['next_followup_note'] !== '' ? $customer_form['next_followup_note'] : null,
                            $next_approval_status,
                            $next_approval_note,
                            $next_approved_by,
                            $next_approved_at,
                            $current_user_id,
                            $target_customer_id,
                            $active_branch_id
                        ]);

                        $_SESSION['crm_customer_success'] = 'อัปเดตข้อมูลลูกค้าเรียบร้อยแล้ว';
                        header('Location: ' . buildCustomerPageUrl(
                            $current_module,
                            $current_user_role === 'sales_manager' ? (int)($target_row['group_id'] ?? $selected_group_id) : 0,
                            $target_customer_id,
                            0
                        ));
                        exit();
                    }
                }
            }
        } elseif ($action === 'add_timeline') {
            $target_customer_id = (int)($_POST['customer_id'] ?? 0);
            $timeline_form['activity_type'] = normalizeTimelineActivity($_POST['activity_type'] ?? 'note');
            $timeline_form['activity_action'] = normalizeText($_POST['activity_action'] ?? '');
            $timeline_form['activity_topic'] = normalizeText($_POST['activity_topic'] ?? '');
            $timeline_form['activity_note'] = normalizeText($_POST['activity_note'] ?? '');
            $timeline_form['timeline_next_followup_at'] = trim((string)($_POST['timeline_next_followup_at'] ?? ''));
            $timeline_form['timeline_next_note'] = normalizeText($_POST['timeline_next_note'] ?? '');

            $timeline_activity = $timeline_form['activity_type'];
            $timeline_action = $timeline_form['activity_action'];
            $timeline_topic = $timeline_form['activity_topic'];
            $timeline_note = $timeline_form['activity_note'];
            $timeline_next_followup = normalizeDateTimeInput($timeline_form['timeline_next_followup_at']);
            $timeline_next_note = $timeline_form['timeline_next_note'];

            if (!$can_manage_records) {
                $errors[] = 'ทีมขายที่เลือกยังไม่พร้อมใช้งาน จึงยังไม่สามารถบันทึก Timeline ได้';
            }

            if ($timeline_action === '' && $timeline_topic === '') {
                $errors[] = 'กรุณาระบุอย่างน้อยหนึ่งรายการ: ทำอะไร หรือคุยเรื่องอะไร';
            }

            if ($timeline_action !== '' && textLen($timeline_action) > 255) {
                $errors[] = 'รายละเอียดสิ่งที่ทำยาวเกินไป (สูงสุด 255 ตัวอักษร)';
            }

            if ($timeline_topic !== '' && textLen($timeline_topic) > 255) {
                $errors[] = 'หัวข้อที่คุยยาวเกินไป (สูงสุด 255 ตัวอักษร)';
            }

            if ($timeline_note === '') {
                $errors[] = 'กรุณาระบุสรุปผลการคุย';
            } elseif (textLen($timeline_note) > 500) {
                $errors[] = 'สรุปผลการคุยยาวเกินไป (สูงสุด 500 ตัวอักษร)';
            }

            if ($timeline_next_followup === false) {
                $errors[] = 'วันเวลาติดตามครั้งถัดไปไม่ถูกต้อง';
            }

            if ($timeline_next_note !== '' && textLen($timeline_next_note) > 255) {
                $errors[] = 'หมายเหตุติดตามถัดไปยาวเกินไป (สูงสุด 255 ตัวอักษร)';
            }

            if (empty($errors)) {
                $target_row = findCustomerByAccess(
                    $pdo,
                    $target_customer_id,
                    $active_branch_id,
                    $current_user_role,
                    $current_user_id,
                    $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
                    $managed_group_ids
                );

                if (!$target_row) {
                    $errors[] = 'ไม่พบลูกค้าที่ต้องการบันทึก Timeline หรือคุณไม่มีสิทธิ์';
                } else {
                    $timeline_insert_stmt = $pdo->prepare(
                        'INSERT INTO sales_customer_timeline
                            (
                                customer_id, branch_id, group_id, actor_user_id,
                                activity_type, activity_action, discussion_topic,
                                activity_note, next_followup_at, next_followup_note
                            )
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $timeline_insert_stmt->execute([
                        $target_customer_id,
                        $active_branch_id,
                        (int)($target_row['group_id'] ?? 0),
                        $current_user_id,
                        $timeline_activity,
                        $timeline_action !== '' ? $timeline_action : null,
                        $timeline_topic !== '' ? $timeline_topic : null,
                        $timeline_note,
                        $timeline_next_followup,
                        $timeline_next_note !== '' ? $timeline_next_note : null
                    ]);

                    $update_sql = 'UPDATE sales_customer_records
                                   SET last_contact_at = CURRENT_TIMESTAMP,
                                       updated_by = ?,
                                       updated_at = CURRENT_TIMESTAMP';
                    $update_params = [$current_user_id];

                    if ($timeline_next_followup !== null) {
                        $update_sql .= ', next_followup_at = ?, next_followup_note = ?';
                        $update_params[] = $timeline_next_followup;
                        $update_params[] = $timeline_next_note !== '' ? $timeline_next_note : null;
                    }

                    if ($current_user_role !== 'sales_manager') {
                        $update_sql .= ', approval_status = "pending", approval_note = NULL, approved_by = NULL, approved_at = NULL';
                    }

                    $update_sql .= ' WHERE id = ? AND branch_id = ? LIMIT 1';
                    $update_params[] = $target_customer_id;
                    $update_params[] = $active_branch_id;

                    $customer_update_stmt = $pdo->prepare($update_sql);
                    $customer_update_stmt->execute($update_params);

                    $_SESSION['crm_customer_success'] = 'บันทึก Timeline เรียบร้อยแล้ว';
                    header('Location: ' . buildCustomerPageUrl(
                        $current_module,
                        $current_user_role === 'sales_manager' ? (int)($target_row['group_id'] ?? $selected_group_id) : 0,
                        $target_customer_id,
                        0
                    ));
                    exit();
                }
            }
        } elseif ($action === 'assign_sla_followup') {
            if ($current_user_role !== 'sales_manager') {
                $errors[] = 'เฉพาะหัวหน้าทีมเท่านั้นที่สามารถมอบหมายงานเร่งด่วนได้';
            }

            if (!$can_manage_records) {
                $errors[] = 'ทีมขายที่เลือกยังไม่พร้อมใช้งาน จึงยังไม่สามารถมอบหมายงานเร่งด่วนได้';
            }

            $target_customer_id = (int)($_POST['customer_id'] ?? 0);
            $assign_to_user_id = trim((string)($_POST['assign_to_user_id'] ?? ''));
            $assign_reason = normalizeText($_POST['assign_reason'] ?? '');
            $assign_next_followup_at = normalizeDateTimeInput($_POST['assign_next_followup_at'] ?? '');

            if ($target_customer_id <= 0) {
                $errors[] = 'ไม่พบลูกค้าที่ต้องการมอบหมายงาน';
            }

            if ($assign_to_user_id === '') {
                $errors[] = 'กรุณาเลือกเซลล์ผู้รับผิดชอบงานเร่งด่วน';
            }

            if ($assign_reason === '') {
                $errors[] = 'กรุณาระบุเหตุผลในการมอบหมายงาน';
            } elseif (textLen($assign_reason) > 500) {
                $errors[] = 'เหตุผลในการมอบหมายงานยาวเกินไป (สูงสุด 500 ตัวอักษร)';
            }

            if ($assign_next_followup_at === false) {
                $errors[] = 'วันเวลาติดตามใหม่ไม่ถูกต้อง';
            } elseif ($assign_next_followup_at === null) {
                $errors[] = 'กรุณาระบุวันเวลาติดตามใหม่';
            } elseif (strtotime((string)$assign_next_followup_at) <= time()) {
                $errors[] = 'วันเวลาติดตามใหม่ต้องมากกว่าปัจจุบัน';
            }

            if (empty($errors)) {
                $target_row = findCustomerByAccess(
                    $pdo,
                    $target_customer_id,
                    $active_branch_id,
                    $current_user_role,
                    $current_user_id,
                    $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
                    $managed_group_ids
                );

                if (!$target_row) {
                    $errors[] = 'ไม่พบลูกค้าในขอบเขตสิทธิ์ของทีมที่เลือก';
                } else {
                    $target_group_id = (int)($target_row['group_id'] ?? 0);
                    $group_members = fetchGroupActiveMembers($pdo, $target_group_id, $active_branch_id);
                    $allowed_member_ids = [];
                    foreach ($group_members as $member_row) {
                        $member_user_id = trim((string)($member_row['member_user_id'] ?? ''));
                        if ($member_user_id !== '') {
                            $allowed_member_ids[$member_user_id] = true;
                        }
                    }

                    if (!isset($allowed_member_ids[$assign_to_user_id])) {
                        $errors[] = 'ผู้รับผิดชอบที่เลือกไม่อยู่ในทีมขายที่กำลังดูแล';
                    } else {
                        $from_owner_user_id = (string)($target_row['owner_user_id'] ?? '');
                        $assign_time = date('Y-m-d H:i:s');
                        $assignment_topic = 'โอนงานเร่งด่วนจาก ' . ($from_owner_user_id !== '' ? $from_owner_user_id : '-') . ' ไป ' . $assign_to_user_id;
                        $assignment_note = 'เหตุผล: ' . $assign_reason;

                        try {
                            $pdo->beginTransaction();

                            $update_customer_stmt = $pdo->prepare(
                                'UPDATE sales_customer_records
                                 SET owner_user_id = ?,
                                     next_followup_at = ?,
                                     next_followup_note = ?,
                                     updated_by = ?,
                                     updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?
                                   AND branch_id = ?
                                 LIMIT 1'
                            );
                            $update_customer_stmt->execute([
                                $assign_to_user_id,
                                $assign_next_followup_at,
                                $assign_reason,
                                $current_user_id,
                                $target_customer_id,
                                $active_branch_id
                            ]);

                            $insert_assignment_stmt = $pdo->prepare(
                                'INSERT INTO sales_customer_sla_assignments
                                    (
                                        customer_id, branch_id, group_id,
                                        from_owner_user_id, to_owner_user_id,
                                        assign_reason, assigned_by, assigned_at
                                    )
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $insert_assignment_stmt->execute([
                                $target_customer_id,
                                $active_branch_id,
                                $target_group_id,
                                $from_owner_user_id,
                                $assign_to_user_id,
                                $assign_reason,
                                $current_user_id,
                                $assign_time
                            ]);

                            $timeline_insert_stmt = $pdo->prepare(
                                'INSERT INTO sales_customer_timeline
                                    (
                                        customer_id, branch_id, group_id, actor_user_id,
                                        activity_type, activity_action, discussion_topic,
                                        activity_note, next_followup_at, next_followup_note
                                    )
                                 VALUES (?, ?, ?, ?, "note", ?, ?, ?, ?, ?)'
                            );
                            $timeline_insert_stmt->execute([
                                $target_customer_id,
                                $active_branch_id,
                                $target_group_id,
                                $current_user_id,
                                'มอบหมายงานเร่งด่วน SLA',
                                $assignment_topic,
                                $assignment_note,
                                $assign_next_followup_at,
                                $assign_reason
                            ]);

                            $resolve_alert_stmt = $pdo->prepare(
                                'UPDATE sales_customer_sla_alerts
                                 SET owner_user_id = ?,
                                     status = "resolved",
                                     resolved_at = CURRENT_TIMESTAMP,
                                     resolved_by = ?,
                                     last_seen_at = CURRENT_TIMESTAMP,
                                     updated_at = CURRENT_TIMESTAMP
                                 WHERE customer_id = ?
                                   AND branch_id = ?
                                   AND alert_type = "followup_overdue"
                                   AND status = "open"'
                            );
                            $resolve_alert_stmt->execute([
                                $assign_to_user_id,
                                $current_user_id,
                                $target_customer_id,
                                $active_branch_id
                            ]);

                            $pdo->commit();
                        } catch (Throwable $txe) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $txe;
                        }

                        $_SESSION['crm_customer_success'] = 'มอบหมายงานเร่งด่วนเรียบร้อยแล้ว';
                        header('Location: ' . buildCustomerPageUrl(
                            $current_module,
                            (int)($target_row['group_id'] ?? $selected_group_id),
                            $target_customer_id,
                            0
                        ));
                        exit();
                    }
                }
            }
        } elseif ($action === 'approve_customer' || $action === 'reject_customer') {
            if ($current_user_role !== 'sales_manager') {
                $errors[] = 'เฉพาะหัวหน้าทีมเท่านั้นที่สามารถอนุมัติรายการได้';
            }

            if (!$can_manage_records) {
                $errors[] = 'ทีมขายที่เลือกยังไม่พร้อมใช้งาน จึงยังไม่สามารถอนุมัติรายการได้';
            }

            $target_customer_id = (int)($_POST['customer_id'] ?? 0);
            $approval_note_input = normalizeText($_POST['approval_note'] ?? '');
            if ($approval_note_input !== '' && textLen($approval_note_input) > 255) {
                $errors[] = 'หมายเหตุอนุมัติยาวเกินไป (สูงสุด 255 ตัวอักษร)';
            }

            if (empty($errors)) {
                $target_row = findCustomerByAccess(
                    $pdo,
                    $target_customer_id,
                    $active_branch_id,
                    $current_user_role,
                    $current_user_id,
                    $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
                    $managed_group_ids
                );

                if (!$target_row) {
                    $errors[] = 'ไม่พบรายการลูกค้าที่ต้องการอนุมัติ';
                } else {
                    $next_status = $action === 'approve_customer' ? 'approved' : 'rejected';
                    $update_approval_stmt = $pdo->prepare(
                        'UPDATE sales_customer_records
                         SET approval_status = ?,
                             approval_note = ?,
                             approved_by = ?,
                             approved_at = CURRENT_TIMESTAMP,
                             updated_by = ?,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?
                           AND branch_id = ?
                         LIMIT 1'
                    );
                    $update_approval_stmt->execute([
                        $next_status,
                        $approval_note_input !== '' ? $approval_note_input : null,
                        $current_user_id,
                        $current_user_id,
                        $target_customer_id,
                        $active_branch_id
                    ]);

                    $_SESSION['crm_customer_success'] = $next_status === 'approved'
                        ? 'อนุมัติรายการลูกค้าเรียบร้อยแล้ว'
                        : 'ไม่อนุมัติรายการลูกค้าเรียบร้อยแล้ว';
                    header('Location: ' . buildCustomerPageUrl(
                        $current_module,
                        (int)($target_row['group_id'] ?? $selected_group_id),
                        $target_customer_id,
                        0
                    ));
                    exit();
                }
            }
        }
    }

    $customer_sql =
        'SELECT
            c.*,
            g.group_name,
            g.manager_user_id,
            CONCAT_WS(" ", ou.first_name, ou.last_name) AS owner_name,
            CONCAT_WS(" ", mu.first_name, mu.last_name) AS manager_name
         FROM sales_customer_records c
         LEFT JOIN sales_group_invites g ON g.id = c.group_id
         LEFT JOIN users ou ON ou.user_id = c.owner_user_id
         LEFT JOIN users mu ON mu.user_id = g.manager_user_id
         WHERE c.branch_id = ?';
    $customer_params = [$active_branch_id];

    if ($current_user_role === 'sales_manager') {
        if ($selected_group_id > 0) {
            $customer_sql .= ' AND c.group_id = ?';
            $customer_params[] = $selected_group_id;
        } elseif (!empty($managed_group_ids)) {
            $placeholders = implode(',', array_fill(0, count($managed_group_ids), '?'));
            $customer_sql .= ' AND c.group_id IN (' . $placeholders . ')';
            foreach ($managed_group_ids as $group_id) {
                $customer_params[] = (int)$group_id;
            }
        } else {
            $customer_sql .= ' AND 1 = 0';
        }
    } else {
        if ($seller_scope_group_id > 0) {
            $customer_sql .= ' AND c.group_id = ? AND c.owner_user_id = ?';
            $customer_params[] = $seller_scope_group_id;
            $customer_params[] = $current_user_id;
        } else {
            $customer_sql .= ' AND 1 = 0';
        }
    }

    $customer_sql .= ' ORDER BY c.updated_at DESC LIMIT 300';

    $customer_stmt = $pdo->prepare($customer_sql);
    $customer_stmt->execute($customer_params);
    $customer_rows = $customer_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    syncFollowupSlaAlerts(
        $pdo,
        $active_branch_id,
        $current_user_role,
        $selected_group_id,
        $managed_group_ids,
        $seller_scope_group_id,
        $current_user_id
    );

    $sla_summary = fetchSlaAlertSummary(
        $pdo,
        $active_branch_id,
        $current_user_role,
        $selected_group_id,
        $managed_group_ids,
        $seller_scope_group_id,
        $current_user_id
    );
    $kpi_sla_open = (int)($sla_summary['open_total'] ?? 0);
    $kpi_sla_warning = (int)($sla_summary['warning_total'] ?? 0);
    $kpi_sla_critical = (int)($sla_summary['critical_total'] ?? 0);
    $kpi_sla_breach = (int)($sla_summary['breach_total'] ?? 0);
    $kpi_sla_triggered_today = (int)($sla_summary['triggered_today_total'] ?? 0);

    if ($current_user_role === 'sales_manager') {
        $sla_alert_rows = fetchOpenSlaAlerts(
            $pdo,
            $active_branch_id,
            $current_user_role,
            $selected_group_id,
            $managed_group_ids,
            $seller_scope_group_id,
            $current_user_id,
            120
        );
    }

    $kpi_total_customers = count($customer_rows);
    $today_start_ts = strtotime(date('Y-m-d 00:00:00'));
    $today_end_ts = strtotime(date('Y-m-d 23:59:59'));
    $now_ts = time();

    foreach ($customer_rows as $row) {
        $approval_state = normalizeApprovalStatus($row['approval_status'] ?? 'pending');
        if ($approval_state === 'pending') {
            $kpi_pending_approval++;
        }

        $next_followup_ts = strtotime((string)($row['next_followup_at'] ?? ''));
        if ($next_followup_ts !== false) {
            if ($next_followup_ts <= $now_ts) {
                $kpi_followup_due++;
            }

            if ($next_followup_ts >= $today_start_ts && $next_followup_ts <= $today_end_ts) {
                $kpi_followup_today++;
            }
        }
    }

    if ($selected_customer_id <= 0 && !empty($customer_rows)) {
        $selected_customer_id = (int)($customer_rows[0]['id'] ?? 0);
    }

    if ($selected_customer_id > 0) {
        $selected_customer = findCustomerByAccess(
            $pdo,
            $selected_customer_id,
            $active_branch_id,
            $current_user_role,
            $current_user_id,
            $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
            $managed_group_ids
        );
        if (!$selected_customer) {
            $selected_customer_id = 0;
        }
    }

    if ($selected_customer_id > 0 && $selected_customer) {
        $timeline_stmt = $pdo->prepare(
            'SELECT
                t.id,
                t.activity_type,
                t.activity_action,
                t.discussion_topic,
                t.activity_note,
                t.next_followup_at,
                t.next_followup_note,
                t.created_at,
                t.actor_user_id,
                CONCAT_WS(" ", u.first_name, u.last_name) AS actor_name
             FROM sales_customer_timeline t
             LEFT JOIN users u ON u.user_id = t.actor_user_id
             WHERE t.customer_id = ?
               AND t.branch_id = ?
             ORDER BY t.created_at DESC
             LIMIT 100'
        );
        $timeline_stmt->execute([$selected_customer_id, $active_branch_id]);
        $timeline_rows = $timeline_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $assignment_stmt = $pdo->prepare(
                        'SELECT
                                a.id,
                                a.from_owner_user_id,
                                a.to_owner_user_id,
                                a.assign_reason,
                                a.assigned_by,
                                a.assigned_at,
                                CONCAT_WS(" ", fu.first_name, fu.last_name) AS from_owner_name,
                                CONCAT_WS(" ", tu.first_name, tu.last_name) AS to_owner_name,
                                CONCAT_WS(" ", au.first_name, au.last_name) AS assigned_by_name
                         FROM sales_customer_sla_assignments a
                         LEFT JOIN users fu ON fu.user_id = a.from_owner_user_id
                         LEFT JOIN users tu ON tu.user_id = a.to_owner_user_id
                         LEFT JOIN users au ON au.user_id = a.assigned_by
                         WHERE a.customer_id = ?
                             AND a.branch_id = ?
                         ORDER BY a.assigned_at DESC, a.id DESC
                         LIMIT 50'
                );
                $assignment_stmt->execute([$selected_customer_id, $active_branch_id]);
                $sla_assignment_rows = $assignment_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($current_user_role === 'sales_manager' && $selected_group_id > 0) {
        $pending_stmt = $pdo->prepare(
            'SELECT
                c.id,
                c.customer_name,
                c.customer_phone,
                c.interested_model,
                c.pipeline_status,
                c.updated_at,
                c.owner_user_id,
                CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name
             FROM sales_customer_records c
             LEFT JOIN users u ON u.user_id = c.owner_user_id
             WHERE c.branch_id = ?
               AND c.group_id = ?
               AND c.approval_status = "pending"
             ORDER BY c.updated_at DESC
             LIMIT 100'
        );
        $pending_stmt->execute([$active_branch_id, $selected_group_id]);
        $pending_approval_rows = $pending_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!$form_filled_from_post && $editing_customer_id > 0) {
        $edit_row = findCustomerByAccess(
            $pdo,
            $editing_customer_id,
            $active_branch_id,
            $current_user_role,
            $current_user_id,
            $current_user_role === 'sales_manager' ? $selected_group_id : $seller_scope_group_id,
            $managed_group_ids
        );

        if (!$edit_row) {
            $errors[] = 'ไม่พบลูกค้าที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์เข้าถึง';
            $editing_customer_id = 0;
        } else {
            $customer_form = [
                'customer_name' => (string)($edit_row['customer_name'] ?? ''),
                'customer_phone' => (string)($edit_row['customer_phone'] ?? ''),
                'customer_line' => (string)($edit_row['customer_line'] ?? ''),
                'customer_province' => (string)($edit_row['customer_province'] ?? ''),
                'lead_source' => normalizeLeadSource($edit_row['lead_source'] ?? 'other'),
                'interested_model' => (string)($edit_row['interested_model'] ?? ''),
                'budget_amount' => number_format((float)($edit_row['budget_amount'] ?? 0), 2, '.', ''),
                'down_payment' => number_format((float)($edit_row['down_payment'] ?? 0), 2, '.', ''),
                'monthly_budget' => number_format((float)($edit_row['monthly_budget'] ?? 0), 2, '.', ''),
                'target_purchase_date' => toDateInputValue($edit_row['target_purchase_date'] ?? ''),
                'pipeline_status' => normalizePipelineStatus($edit_row['pipeline_status'] ?? 'new_lead'),
                'next_followup_at' => toDateTimeLocalValue($edit_row['next_followup_at'] ?? ''),
                'next_followup_note' => (string)($edit_row['next_followup_note'] ?? ''),
                'initial_note' => ''
            ];
        }
    }
} catch (Throwable $e) {
    error_log('page_customer_management.php error: ' . $e->getMessage());
    $errors[] = 'เกิดข้อผิดพลาดในการประมวลผลข้อมูลลูกค้า กรุณาลองใหม่อีกครั้ง';
}

$profile_avatar = 'U';
$avatar_source = $profile_first_name !== '' ? $profile_first_name : $profile_full_name;
if ($avatar_source !== '') {
    if (function_exists('mb_substr')) {
        $profile_avatar = mb_substr($avatar_source, 0, 1, 'UTF-8');
        if (function_exists('mb_strtoupper')) {
            $profile_avatar = mb_strtoupper($profile_avatar, 'UTF-8');
        }
    } else {
        $profile_avatar = strtoupper(substr($avatar_source, 0, 1));
    }
}

$pipeline_options = getPipelineStatusOptions();
$lead_source_options = getLeadSourceOptions();
$approval_options = getApprovalStatusOptions();
$timeline_activity_options = getTimelineActivityOptions();
$timeline_activity_counts = array_fill_keys(array_keys($timeline_activity_options), 0);

foreach ($timeline_rows as $timeline_count_row) {
    $timeline_type_key = normalizeTimelineActivity($timeline_count_row['activity_type'] ?? 'note');
    if (!isset($timeline_activity_counts[$timeline_type_key])) {
        $timeline_activity_counts[$timeline_type_key] = 0;
    }
    $timeline_activity_counts[$timeline_type_key]++;
}

$editing_mode = $editing_customer_id > 0;

$default_page_href = buildCustomerPageUrl(
    $current_module,
    $current_user_role === 'sales_manager' ? $selected_group_id : 0,
    $selected_customer_id,
    0
);

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($dashboard_page_title); ?></title>
<style>
* { box-sizing: border-box; }

:root {
    --transition: 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    --bg-main: #f2f7fb;
    --surface: #ffffff;
    --surface-soft: #f8fcff;
    --line: #d8e6f0;
    --ink-main: #11354f;
    --ink-sub: #41637c;
    --ink-soft: #6c8aa0;
    --ok: #1f8e5a;
    --warn: #d98e1a;
    --danger: #c64545;
    --accent: #0f7ab8;
    --accent-deep: #0a5c8a;
    --accent-soft: #e6f3fb;
    --shadow: 0 14px 34px rgba(6, 56, 92, 0.09);
    --radius-xl: 18px;
    --radius-md: 12px;
}

html, body {
    margin: 0;
    min-height: 100%;
}

body {
    font-family: "IBM Plex Sans Thai", "Noto Sans Thai", Tahoma, sans-serif;
    color: var(--ink-main);
    background:
        radial-gradient(circle at 12% 8%, rgba(15, 122, 184, 0.16), transparent 36%),
        radial-gradient(circle at 90% 14%, rgba(217, 142, 26, 0.12), transparent 34%),
        linear-gradient(180deg, #eef5fb 0%, #f4f9fd 54%, #f8fcff 100%);
}

#app {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.main {
    flex: 1;
    overflow: auto;
    padding: 18px;
}

.shell {
    width: 100%;
    max-width: 1320px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
    animation: fadeUp 0.38s ease;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero {
    border: 1px solid var(--line);
    border-radius: var(--radius-xl);
    background: linear-gradient(120deg, #ffffff 0%, #f7fbff 62%, #f2f9ff 100%);
    box-shadow: var(--shadow);
    padding: 16px 18px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 16px;
}

.hero h1 {
    margin: 0;
    font-size: 27px;
    letter-spacing: 0.2px;
    color: #0a3550;
}

.hero p {
    margin: 7px 0 0;
    color: var(--ink-sub);
    font-size: 13px;
    line-height: 1.55;
}

.hero-actions {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.chip-row {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    border: 1px solid #c9dbe8;
    background: #f5fbff;
    color: #255675;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 700;
}

.chip.ok {
    border-color: #b7e1cf;
    background: #edf9f2;
    color: #236544;
}

.chip.warn {
    border-color: #f1ddb8;
    background: #fff8ea;
    color: #8e6111;
}

.chip.danger {
    border-color: #efc3c3;
    background: #fff2f2;
    color: #9b3a3a;
}

.chip.neutral {
    border-color: #d7e2ec;
    background: #f8fbff;
    color: #506f85;
}

.btn {
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--accent);
    background: var(--accent);
    color: #ffffff;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform var(--transition), filter var(--transition), border-color var(--transition), background var(--transition);
}

.btn:hover {
    transform: translateY(-1px);
    filter: brightness(1.02);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn.alt {
    border-color: #bcd2e3;
    background: #f7fbff;
    color: #174b6c;
}

.btn.warn {
    border-color: #d98e1a;
    background: #d98e1a;
}

.btn.danger {
    border-color: #c64545;
    background: #c64545;
}

.alert {
    border-radius: 13px;
    padding: 12px 14px;
    border: 1px solid transparent;
    font-size: 13px;
    line-height: 1.55;
}

.alert.success {
    border-color: #b9e3ca;
    background: #edf8f2;
    color: #1f6b44;
}

.alert.error {
    border-color: #efc4c4;
    background: #fff3f3;
    color: #a63b3b;
}

.alert.warn {
    border-color: #f1ddb8;
    background: #fff8eb;
    color: #916317;
}

.grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 14px;
}

.card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.card-head {
    padding: 14px 16px;
    border-bottom: 1px solid #e2edf5;
    background: linear-gradient(90deg, #f6fbff 0%, #fdfefe 100%);
}

.card-head h2 {
    margin: 0;
    font-size: 18px;
    color: #0f3957;
}

.card-head p {
    margin: 6px 0 0;
    font-size: 12px;
    color: var(--ink-sub);
}

.card-body {
    padding: 14px 16px 16px;
}

.span-8 { grid-column: span 8; }
.span-4 { grid-column: span 4; }
.span-12 { grid-column: span 12; }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.kpi {
    border: 1px solid #d2e2ef;
    border-radius: 11px;
    background: #f5fbff;
    padding: 10px 11px;
}

.kpi .kpi-label {
    font-size: 11px;
    color: var(--ink-soft);
    text-transform: uppercase;
    letter-spacing: 0.6px;
}

.kpi .kpi-value {
    margin-top: 5px;
    font-size: 21px;
    color: #0f4568;
    font-weight: 800;
}

.manager-filter {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-top: 10px;
}

.manager-filter label {
    font-size: 12px;
    color: var(--ink-sub);
    font-weight: 700;
}

.manager-filter select {
    height: 36px;
    border-radius: 9px;
    border: 1px solid #c7d8e7;
    background: #ffffff;
    padding: 0 10px;
    color: #1f4f6d;
    font-size: 13px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 10px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field.span-3 { grid-column: span 3; }
.field.span-4 { grid-column: span 4; }
.field.span-6 { grid-column: span 6; }
.field.span-12 { grid-column: span 12; }

.field label {
    font-size: 12px;
    color: #215170;
    font-weight: 700;
}

.input,
.textarea,
.select {
    width: 100%;
    border: 1px solid #c8d9e7;
    background: #fcfeff;
    color: #1b4a68;
    border-radius: 10px;
    font-size: 13px;
}

.input,
.select {
    height: 40px;
    padding: 0 11px;
}

.textarea {
    min-height: 84px;
    resize: vertical;
    padding: 9px 11px;
}

.input:focus,
.textarea:focus,
.select:focus {
    outline: none;
    border-color: #0f7ab8;
    box-shadow: 0 0 0 3px rgba(15, 122, 184, 0.15);
}

.form-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.table-wrap {
    overflow: auto;
    border: 1px solid #d9e6f1;
    border-radius: 12px;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 940px;
}

thead th {
    text-align: left;
    background: #f4f9fd;
    color: #2f5f7f;
    font-size: 12px;
    padding: 10px;
    border-bottom: 1px solid #d7e5f1;
}

tbody td {
    font-size: 12px;
    color: #284f69;
    padding: 10px;
    border-bottom: 1px solid #eaf1f7;
    vertical-align: top;
}

tbody tr:hover {
    background: #f8fcff;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid transparent;
}

.badge.pipeline-new_lead,
.badge.pipeline-contacted,
.badge.pipeline-interested,
.badge.pipeline-test_drive,
.badge.pipeline-quotation,
.badge.pipeline-booking,
.badge.pipeline-delivered,
.badge.pipeline-lost {
    border-color: #c5d9e8;
    background: #eef5fb;
    color: #1f5678;
}

.badge.approval-pending {
    border-color: #f0debc;
    background: #fff8eb;
    color: #8f6113;
}

.badge.approval-approved {
    border-color: #c2e3d1;
    background: #eef9f3;
    color: #226341;
}

.badge.approval-rejected {
    border-color: #efc7c7;
    background: #fff3f3;
    color: #9d3b3b;
}

.badge.sla-warning {
    border-color: #f0debc;
    background: #fff8eb;
    color: #8f6113;
}

.badge.sla-critical {
    border-color: #f0c9a4;
    background: #fff3e9;
    color: #9a4a18;
}

.badge.sla-breach {
    border-color: #efc7c7;
    background: #fff3f3;
    color: #a33838;
}

.row-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.row-actions a {
    text-decoration: none;
}

.tiny-btn {
    height: 30px;
    border-radius: 8px;
    border: 1px solid #c2d6e7;
    background: #ffffff;
    color: #1b4f6f;
    padding: 0 9px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.tiny-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.tiny-btn.primary {
    border-color: #0f7ab8;
    background: #0f7ab8;
    color: #ffffff;
}

.tiny-btn.danger {
    border-color: #c64545;
    background: #c64545;
    color: #ffffff;
}

.timeline-summary {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
}

.timeline-summary-item {
    border: 1px solid #d3e3ef;
    border-radius: 10px;
    background: #f7fbff;
    padding: 8px 10px;
}

.timeline-summary-label {
    font-size: 11px;
    color: #5b7d98;
    text-transform: uppercase;
}

.timeline-summary-value {
    margin-top: 4px;
    font-size: 20px;
    font-weight: 800;
    color: #184f73;
}

.timeline-list {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-left: 28px;
    margin-top: 12px;
}

.timeline-list::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 4px;
    bottom: 4px;
    width: 2px;
    background: linear-gradient(180deg, #bcd6e8 0%, #dce9f4 100%);
}

.timeline-item {
    position: relative;
    border: 1px solid #d6e4ef;
    border-radius: 12px;
    background: #fbfdff;
    padding: 10px 11px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 15px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #0f7ab8;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px #bbd8ea;
}

.timeline-item.type-call::before {
    background: #0f7ab8;
    box-shadow: 0 0 0 2px #bdd9ec;
}

.timeline-item.type-chat::before {
    background: #16885d;
    box-shadow: 0 0 0 2px #bde4d1;
}

.timeline-item.type-meeting::before {
    background: #b77718;
    box-shadow: 0 0 0 2px #edd9bb;
}

.timeline-item.type-test_drive::before {
    background: #7b5bd0;
    box-shadow: 0 0 0 2px #d8cef5;
}

.timeline-item.type-note::before {
    background: #5f7486;
    box-shadow: 0 0 0 2px #d2dde6;
}

.timeline-item-head {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 6px;
}

.timeline-item-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.badge.activity-call,
.badge.activity-chat,
.badge.activity-meeting,
.badge.activity-test_drive,
.badge.activity-note {
    border-color: #c5d9e8;
    background: #eef5fb;
    color: #1f5678;
}

.timeline-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 8px;
}

.timeline-detail-box {
    border: 1px solid #d6e4ef;
    background: #f7fbff;
    border-radius: 9px;
    padding: 7px 8px;
}

.timeline-detail-label {
    font-size: 11px;
    color: #6a879e;
}

.timeline-detail-value {
    margin-top: 2px;
    font-size: 12px;
    color: #244f6e;
    line-height: 1.45;
    white-space: pre-wrap;
}

.timeline-note-block {
    border: 1px solid #d6e4ef;
    background: #ffffff;
    border-radius: 9px;
    padding: 8px;
}

.timeline-note {
    margin-top: 3px;
    font-size: 12px;
    color: #295574;
    line-height: 1.5;
    white-space: pre-wrap;
}

.subtle {
    color: var(--ink-soft);
    font-size: 12px;
}

.empty-state {
    border: 1px dashed #c7d9e8;
    border-radius: 12px;
    background: #fbfdff;
    color: #5a7c96;
    font-size: 13px;
    text-align: center;
    padding: 18px;
}

.approval-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.approval-item {
    border: 1px solid #d6e4ef;
    border-radius: 12px;
    background: #fbfdff;
    padding: 10px;
}

.approval-item.sla-warning {
    border-color: #f1dfbe;
    background: #fffbf2;
}

.approval-item.sla-critical {
    border-color: #f1ceb0;
    background: #fff6ef;
}

.approval-item.sla-breach {
    border-color: #efc3c3;
    background: #fff4f4;
}

.approval-item h4 {
    margin: 0;
    font-size: 14px;
    color: #164d71;
}

.approval-meta {
    margin-top: 4px;
    font-size: 12px;
    color: #4f6f88;
}

.approval-form {
    margin-top: 8px;
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 6px;
    align-items: center;
}

.approval-form .input {
    height: 34px;
}

.sla-assign-form {
    margin-top: 8px;
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr) minmax(0, 1.45fr) auto;
    gap: 6px;
    align-items: center;
}

.sla-assign-form .input,
.sla-assign-form .select {
    height: 34px;
    font-size: 12px;
}

.sla-assign-form .tiny-btn {
    height: 34px;
}

@media (max-width: 1160px) {
    .span-8,
    .span-4 {
        grid-column: span 12;
    }
}

@media (max-width: 900px) {
    .main {
        padding: 12px;
    }

    .hero {
        padding: 13px;
    }

    .hero h1 {
        font-size: 22px;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .field.span-3,
    .field.span-4,
    .field.span-6 {
        grid-column: span 12;
    }

    .approval-form {
        grid-template-columns: 1fr;
    }

    .sla-assign-form {
        grid-template-columns: 1fr;
    }

    .timeline-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .timeline-detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div id="app">
    <main class="main">
        <div class="shell">
            <section class="hero">
                <div>
                    <h1>Customer CRM</h1>
                    <p>บันทึกลูกค้าซื้อรถแบบทีมขาย: ข้อมูลลูกค้า, ความต้องการซื้อ, Pipeline, Timeline ติดตาม และงาน Follow-up ถัดไป</p>
                    <div class="chip-row">
                        <span class="chip"><strong>Branch ID:</strong> <?php echo h($active_branch_id); ?></span>
                        <span class="chip"><strong>ทีม:</strong> <?php echo h($group_display_name !== '' ? $group_display_name : '-'); ?></span>
                        <span class="chip <?php echo h($group_state_class); ?>"><?php echo h($group_state_text); ?></span>
                        <span class="chip"><strong>Role:</strong> <?php echo h($profile_role_label ?? $profile_role); ?></span>
                        <span class="chip"><strong>ผู้ใช้งาน:</strong> <?php echo h($profile_full_name !== '' ? $profile_full_name : $current_user_name); ?></span>
                    </div>

                    <?php if ($current_user_role === 'sales_manager'): ?>
                        <form method="get" class="manager-filter">
                            <input type="hidden" name="module" value="<?php echo h($current_module); ?>">
                            <label for="group_id">เลือกทีมขาย</label>
                            <select id="group_id" name="group_id" onchange="this.form.submit()">
                                <option value="">-- เลือกทีม --</option>
                                <?php foreach ($managed_groups as $group_row): ?>
                                    <?php
                                    $group_id_value = (int)($group_row['id'] ?? 0);
                                    $group_pending = (int)($group_row['pending_total'] ?? 0);
                                    $group_label = (string)($group_row['group_name'] ?? '-') . ' (รออนุมัติ ' . number_format($group_pending) . ')';
                                    ?>
                                    <option value="<?php echo $group_id_value; ?>"<?php echo $group_id_value === (int)$selected_group_id ? ' selected' : ''; ?>>
                                        <?php echo h($group_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>

            </section>

            <?php if ($success_message !== ''): ?>
                <div class="alert success"><?php echo h($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert error">
                    <?php foreach ($errors as $error_item): ?>
                        <div>- <?php echo h($error_item); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($team_warning_message !== ''): ?>
                <div class="alert warn"><?php echo h($team_warning_message); ?></div>
            <?php endif; ?>

            <div class="grid">
                <section class="card span-8">
                    <div class="card-head">
                        <h2><?php echo $editing_mode ? 'แก้ไขข้อมูลลูกค้า' : 'เพิ่มลูกค้าใหม่'; ?></h2>
                        <p>ผูกข้อมูลลูกค้ากับ Group Sale และ Branch ปัจจุบันอัตโนมัติ เพื่อให้หัวหน้าทีมตรวจสอบ/อนุมัติได้</p>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <input type="hidden" name="action" value="<?php echo $editing_mode ? 'update_customer' : 'create_customer'; ?>">
                            <?php if ($editing_mode): ?>
                                <input type="hidden" name="customer_id" value="<?php echo (int)$editing_customer_id; ?>">
                            <?php endif; ?>
                            <?php if ($current_user_role === 'sales_manager' && $selected_group_id > 0): ?>
                                <input type="hidden" name="group_id" value="<?php echo (int)$selected_group_id; ?>">
                            <?php endif; ?>

                            <div class="form-grid">
                                <div class="field span-6">
                                    <label for="customer_name">ชื่อลูกค้า</label>
                                    <input class="input" id="customer_name" name="customer_name" value="<?php echo h($customer_form['customer_name']); ?>" required>
                                </div>
                                <div class="field span-3">
                                    <label for="customer_phone">เบอร์โทร</label>
                                    <input class="input" id="customer_phone" name="customer_phone" value="<?php echo h($customer_form['customer_phone']); ?>" required>
                                </div>
                                <div class="field span-3">
                                    <label for="customer_line">LINE</label>
                                    <input class="input" id="customer_line" name="customer_line" value="<?php echo h($customer_form['customer_line']); ?>">
                                </div>

                                <div class="field span-3">
                                    <label for="customer_province">จังหวัด</label>
                                    <input class="input" id="customer_province" name="customer_province" value="<?php echo h($customer_form['customer_province']); ?>">
                                </div>
                                <div class="field span-3">
                                    <label for="lead_source">ช่องทางลูกค้า</label>
                                    <select class="select" id="lead_source" name="lead_source">
                                        <?php foreach ($lead_source_options as $source_key => $source_label): ?>
                                            <option value="<?php echo h($source_key); ?>"<?php echo $customer_form['lead_source'] === $source_key ? ' selected' : ''; ?>>
                                                <?php echo h($source_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field span-6">
                                    <label for="interested_model">รุ่นรถที่สนใจ</label>
                                    <input class="input" id="interested_model" name="interested_model" value="<?php echo h($customer_form['interested_model']); ?>" required>
                                </div>

                                <div class="field span-4">
                                    <label for="budget_amount">งบประมาณ (บาท)</label>
                                    <input class="input" id="budget_amount" name="budget_amount" value="<?php echo h($customer_form['budget_amount']); ?>" placeholder="0.00">
                                </div>
                                <div class="field span-4">
                                    <label for="down_payment">เงินดาวน์ (บาท)</label>
                                    <input class="input" id="down_payment" name="down_payment" value="<?php echo h($customer_form['down_payment']); ?>" placeholder="0.00">
                                </div>
                                <div class="field span-4">
                                    <label for="monthly_budget">ค่างวดที่รับได้/เดือน</label>
                                    <input class="input" id="monthly_budget" name="monthly_budget" value="<?php echo h($customer_form['monthly_budget']); ?>" placeholder="0.00">
                                </div>

                                <div class="field span-4">
                                    <label for="target_purchase_date">วันที่ต้องการออกรถ</label>
                                    <input class="input" type="date" id="target_purchase_date" name="target_purchase_date" value="<?php echo h($customer_form['target_purchase_date']); ?>">
                                </div>
                                <div class="field span-4">
                                    <label for="pipeline_status">สถานะใน Pipeline</label>
                                    <select class="select" id="pipeline_status" name="pipeline_status">
                                        <?php foreach ($pipeline_options as $pipeline_key => $pipeline_label): ?>
                                            <option value="<?php echo h($pipeline_key); ?>"<?php echo $customer_form['pipeline_status'] === $pipeline_key ? ' selected' : ''; ?>>
                                                <?php echo h($pipeline_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field span-4">
                                    <label for="next_followup_at">ติดตามครั้งถัดไป</label>
                                    <input class="input" type="datetime-local" id="next_followup_at" name="next_followup_at" value="<?php echo h($customer_form['next_followup_at']); ?>">
                                </div>

                                <div class="field span-12">
                                    <label for="next_followup_note">หมายเหตุงานติดตามครั้งถัดไป</label>
                                    <input class="input" id="next_followup_note" name="next_followup_note" value="<?php echo h($customer_form['next_followup_note']); ?>">
                                </div>

                                <?php if (!$editing_mode): ?>
                                    <div class="field span-12">
                                        <label for="initial_note">บันทึกเริ่มต้น (จะเพิ่มลง Timeline อัตโนมัติ)</label>
                                        <textarea class="textarea" id="initial_note" name="initial_note" placeholder="เช่น ลูกค้าขอเทียบรุ่น A/B และนัดคุยวันเสาร์"><?php echo h($customer_form['initial_note']); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <button class="btn" type="submit"<?php echo !$can_manage_records ? ' disabled' : ''; ?>>
                                    <?php echo $editing_mode ? 'บันทึกการแก้ไข' : 'เพิ่มลูกค้า'; ?>
                                </button>
                                <?php if ($editing_mode): ?>
                                    <a class="btn alt" href="<?php echo h($default_page_href); ?>">ยกเลิกการแก้ไข</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card span-4">
                    <div class="card-head">
                        <h2>สรุปงานลูกค้า</h2>
                        <p>ภาพรวมลูกค้าตามสิทธิ์ที่คุณเข้าถึงได้ใน Branch ปัจจุบัน</p>
                    </div>
                    <div class="card-body">
                        <div class="kpi-grid">
                            <div class="kpi">
                                <div class="kpi-label">ลูกค้าทั้งหมด</div>
                                <div class="kpi-value"><?php echo number_format($kpi_total_customers); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">รออนุมัติ</div>
                                <div class="kpi-value"><?php echo number_format($kpi_pending_approval); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Follow-up ค้าง</div>
                                <div class="kpi-value"><?php echo number_format($kpi_followup_due); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Follow-up วันนี้</div>
                                <div class="kpi-value"><?php echo number_format($kpi_followup_today); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">SLA เปิดอยู่</div>
                                <div class="kpi-value"><?php echo number_format($kpi_sla_open); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">SLA เสี่ยงสูง</div>
                                <div class="kpi-value"><?php echo number_format($kpi_sla_critical + $kpi_sla_breach); ?></div>
                            </div>
                            <div class="kpi">
                                <div class="kpi-label">Escalation วันนี้</div>
                                <div class="kpi-value"><?php echo number_format($kpi_sla_triggered_today); ?></div>
                            </div>
                        </div>

                        <div style="margin-top: 12px;" class="subtle">
                            ข้อมูลนี้ถูกล็อกตาม <strong>Branch ID</strong> และ <strong>Group Sale</strong> เพื่อให้หัวหน้าทีมตรวจสอบการขายในทีมได้
                        </div>
                    </div>
                </section>

                <?php if ($current_user_role === 'sales_manager'): ?>
                    <section class="card span-12">
                        <div class="card-head">
                            <h2>คิวรออนุมัติจากทีม</h2>
                            <p>หัวหน้าทีมสามารถตรวจสอบและอนุมัติ/ไม่อนุมัติรายการที่สมาชิกบันทึกเข้ามา</p>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pending_approval_rows)): ?>
                                <div class="approval-list">
                                    <?php foreach ($pending_approval_rows as $pending_row): ?>
                                        <?php
                                        $pending_customer_id = (int)($pending_row['id'] ?? 0);
                                        $pending_view_href = buildCustomerPageUrl($current_module, $selected_group_id, $pending_customer_id, 0);
                                        ?>
                                        <div class="approval-item">
                                            <h4><?php echo h((string)($pending_row['customer_name'] ?? '-')); ?></h4>
                                            <div class="approval-meta">
                                                ผู้ติดต่อหลัก: <?php echo h(trim((string)($pending_row['owner_name'] ?? '')) !== '' ? (string)$pending_row['owner_name'] : (string)($pending_row['owner_user_id'] ?? '-')); ?> |
                                                เบอร์: <?php echo h((string)($pending_row['customer_phone'] ?? '-')); ?> |
                                                รุ่น: <?php echo h((string)($pending_row['interested_model'] ?? '-')); ?> |
                                                อัปเดต: <?php echo h(formatDateTimeDisplay($pending_row['updated_at'] ?? '')); ?>
                                            </div>

                                            <div class="row-actions" style="margin-top: 8px;">
                                                <a class="tiny-btn" href="<?php echo h($pending_view_href); ?>">เปิดดูรายละเอียด</a>
                                            </div>

                                            <form method="post" class="approval-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                <input type="hidden" name="group_id" value="<?php echo (int)$selected_group_id; ?>">
                                                <input type="hidden" name="customer_id" value="<?php echo $pending_customer_id; ?>">
                                                <input class="input" name="approval_note" placeholder="หมายเหตุ (ไม่บังคับ)">
                                                <button class="tiny-btn primary" type="submit" name="action" value="approve_customer"<?php echo !$can_manage_records ? ' disabled' : ''; ?>>อนุมัติ</button>
                                                <button class="tiny-btn danger" type="submit" name="action" value="reject_customer" onclick="return confirm('ยืนยันไม่อนุมัติรายการนี้?');"<?php echo !$can_manage_records ? ' disabled' : ''; ?>>ไม่อนุมัติ</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">ตอนนี้ไม่มีรายการรออนุมัติ</div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="card span-12">
                        <div class="card-head">
                            <h2>SLA Escalation Queue</h2>
                            <p>ระบบแจ้งเตือนอัตโนมัติเมื่อลูกค้าเลยเวลานัดติดตาม เพื่อให้หัวหน้าทีมเร่งจัดการงานเร่งด่วน</p>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sla_alert_rows)): ?>
                                <div class="approval-list">
                                    <?php foreach ($sla_alert_rows as $sla_row): ?>
                                        <?php
                                        $sla_customer_id = (int)($sla_row['customer_id'] ?? 0);
                                        $sla_group_id = (int)($sla_row['group_id'] ?? $selected_group_id);
                                        $sla_view_href = buildCustomerPageUrl($current_module, $sla_group_id, $sla_customer_id, 0);
                                        $sla_severity = strtolower(trim((string)($sla_row['severity'] ?? 'warning')));
                                        if (!in_array($sla_severity, ['warning', 'critical', 'breach'], true)) {
                                            $sla_severity = 'warning';
                                        }
                                        $sla_overdue_seconds = max(0, (int)($sla_row['overdue_seconds'] ?? 0));
                                        $sla_delay_text = formatDurationCompact($sla_overdue_seconds);
                                        $sla_owner_name = trim((string)($sla_row['owner_name'] ?? '')) !== ''
                                            ? (string)$sla_row['owner_name']
                                            : (string)($sla_row['owner_user_id'] ?? '-');
                                        ?>
                                        <div class="approval-item sla-<?php echo h($sla_severity); ?>">
                                            <h4><?php echo h((string)($sla_row['customer_name'] ?? '-')); ?></h4>
                                            <div class="approval-meta">
                                                ระดับ SLA:
                                                <span class="badge sla-<?php echo h($sla_severity); ?>"><?php echo h(getSlaSeverityLabel($sla_severity)); ?></span>
                                                |
                                                ค้างมาแล้ว: <?php echo h($sla_delay_text); ?> |
                                                เวลานัด: <?php echo h(formatDateTimeDisplay($sla_row['due_at'] ?? '')); ?>
                                            </div>
                                            <div class="approval-meta">
                                                ทีม: <?php echo h((string)($sla_row['group_name'] ?? '-')); ?> |
                                                ผู้ติดต่อหลัก: <?php echo h($sla_owner_name); ?> |
                                                เบอร์: <?php echo h((string)($sla_row['customer_phone'] ?? '-')); ?> |
                                                รุ่น: <?php echo h((string)($sla_row['interested_model'] ?? '-')); ?>
                                            </div>
                                            <?php if (trim((string)($sla_row['next_followup_note'] ?? '')) !== ''): ?>
                                                <div class="subtle" style="margin-top: 6px;">
                                                    งานติดตามถัดไป: <?php echo h((string)$sla_row['next_followup_note']); ?>
                                                </div>
                                            <?php endif; ?>

                                            <form method="post" class="sla-assign-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                                <input type="hidden" name="action" value="assign_sla_followup">
                                                <input type="hidden" name="group_id" value="<?php echo $sla_group_id; ?>">
                                                <input type="hidden" name="customer_id" value="<?php echo $sla_customer_id; ?>">

                                                <select class="select" name="assign_to_user_id" required>
                                                    <option value="">เลือกผู้รับผิดชอบ</option>
                                                    <?php foreach ($manager_assignable_members as $member_row): ?>
                                                        <?php
                                                        $member_user_id = trim((string)($member_row['member_user_id'] ?? ''));
                                                        if ($member_user_id === '') {
                                                            continue;
                                                        }
                                                        $member_name = trim((string)($member_row['member_name'] ?? ''));
                                                        if ($member_name === '') {
                                                            $member_name = $member_user_id;
                                                        }
                                                        $member_title = trim((string)($member_row['member_title'] ?? ''));
                                                        $member_label = $member_name;
                                                        if ($member_title !== '') {
                                                            $member_label .= ' (' . $member_title . ')';
                                                        }
                                                        ?>
                                                        <option value="<?php echo h($member_user_id); ?>"<?php echo $member_user_id === (string)($sla_row['owner_user_id'] ?? '') ? ' selected' : ''; ?>>
                                                            <?php echo h($member_label . ' [' . $member_user_id . ']'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <input class="input" type="datetime-local" name="assign_next_followup_at" value="<?php echo h(toDateTimeLocalValue($sla_row['next_followup_at'] ?? '')); ?>" required>
                                                <input class="input" name="assign_reason" maxlength="500" placeholder="เหตุผลการมอบหมายงานเร่งด่วน" required>
                                                <button class="tiny-btn primary" type="submit"<?php echo (!$can_manage_records || empty($manager_assignable_members)) ? ' disabled' : ''; ?>>Assign งาน</button>
                                            </form>

                                            <div class="row-actions" style="margin-top: 8px;">
                                                <a class="tiny-btn" href="<?php echo h($sla_view_href); ?>">เปิดลูกค้า</a>
                                            </div>

                                            <?php if (empty($manager_assignable_members)): ?>
                                                <div class="subtle" style="margin-top: 6px;">ยังไม่มีสมาชิกทีมที่เป็น active สำหรับมอบหมายงาน</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">ยังไม่มีรายการที่เกิน SLA ในทีมที่เลือก</div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="card span-12">
                    <div class="card-head">
                        <h2>รายการลูกค้า</h2>
                        <p>ค้นดูสถานะดีลและนัดติดตามล่าสุดของลูกค้าในความรับผิดชอบ</p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($customer_rows)): ?>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ลูกค้า</th>
                                            <th>ข้อมูลดีล</th>
                                            <th>Pipeline</th>
                                            <th>Follow-up</th>
                                            <th>อนุมัติ</th>
                                            <th>จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($customer_rows as $row): ?>
                                            <?php
                                            $row_id = (int)($row['id'] ?? 0);
                                            $row_pipeline = normalizePipelineStatus($row['pipeline_status'] ?? 'new_lead');
                                            $row_approval = normalizeApprovalStatus($row['approval_status'] ?? 'pending');
                                            $row_view_href = buildCustomerPageUrl(
                                                $current_module,
                                                $current_user_role === 'sales_manager' ? (int)($row['group_id'] ?? $selected_group_id) : 0,
                                                $row_id,
                                                0
                                            );
                                            $row_edit_href = buildCustomerPageUrl(
                                                $current_module,
                                                $current_user_role === 'sales_manager' ? (int)($row['group_id'] ?? $selected_group_id) : 0,
                                                $row_id,
                                                $row_id
                                            );
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo h((string)($row['customer_name'] ?? '-')); ?></strong><br>
                                                    <span class="subtle">โทร: <?php echo h((string)($row['customer_phone'] ?? '-')); ?></span><br>
                                                    <span class="subtle">LINE: <?php echo h((string)($row['customer_line'] ?? '-')); ?></span>
                                                </td>
                                                <td>
                                                    รุ่น: <?php echo h((string)($row['interested_model'] ?? '-')); ?><br>
                                                    งบ: <?php echo h(formatMoneyDisplay($row['budget_amount'] ?? 0)); ?> บาท<br>
                                                    ผู้ติดต่อหลัก: <?php echo h(trim((string)($row['owner_name'] ?? '')) !== '' ? (string)$row['owner_name'] : (string)($row['owner_user_id'] ?? '-')); ?>
                                                </td>
                                                <td>
                                                    <span class="badge pipeline-<?php echo h($row_pipeline); ?>">
                                                        <?php echo h($pipeline_options[$row_pipeline] ?? $row_pipeline); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    ครั้งล่าสุด: <?php echo h(formatDateTimeDisplay($row['last_contact_at'] ?? '')); ?><br>
                                                    ครั้งถัดไป: <?php echo h(formatDateTimeDisplay($row['next_followup_at'] ?? '')); ?><br>
                                                    <?php
                                                    $row_sla_severity = '';
                                                    $row_sla_text = '';
                                                    $row_next_followup_raw = trim((string)($row['next_followup_at'] ?? ''));
                                                    $row_next_followup_ts = $row_next_followup_raw !== '' ? strtotime($row_next_followup_raw) : false;
                                                    if ($row_next_followup_ts !== false && $row_next_followup_ts <= $now_ts) {
                                                        $row_pipeline_state = normalizePipelineStatus($row['pipeline_status'] ?? 'new_lead');
                                                        if (!in_array($row_pipeline_state, ['delivered', 'lost'], true)) {
                                                            $row_sla_severity = getSlaSeverityByDelaySeconds($now_ts - $row_next_followup_ts);
                                                            $row_sla_text = formatDurationCompact($now_ts - $row_next_followup_ts);
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($row_sla_severity !== ''): ?>
                                                        <span class="badge sla-<?php echo h($row_sla_severity); ?>">
                                                            SLA: <?php echo h(getSlaSeverityLabel($row_sla_severity)); ?> (<?php echo h($row_sla_text); ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge approval-<?php echo h($row_approval); ?>">
                                                        <?php echo h($approval_options[$row_approval] ?? $row_approval); ?>
                                                    </span><br>
                                                    <span class="subtle">โดย: <?php echo h((string)($row['approved_by'] ?? '-')); ?></span>
                                                </td>
                                                <td>
                                                    <div class="row-actions">
                                                        <a class="tiny-btn" href="<?php echo h($row_view_href); ?>">ดู</a>
                                                        <a class="tiny-btn primary" href="<?php echo h($row_edit_href); ?>">แก้ไข</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">ยังไม่มีข้อมูลลูกค้าในขอบเขตทีม/สาขานี้</div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card span-12">
                    <div class="card-head">
                        <h2>Timeline การติดตามลูกค้า</h2>
                        <p>บันทึกการติดต่อแต่ละครั้ง พร้อมกำหนดวันนัดติดตามครั้งถัดไป</p>
                    </div>
                    <div class="card-body">
                        <?php if ($selected_customer): ?>
                            <div style="margin-bottom: 10px;">
                                <strong><?php echo h((string)($selected_customer['customer_name'] ?? '-')); ?></strong>
                                <span class="subtle">(<?php echo h((string)($selected_customer['customer_phone'] ?? '-')); ?>)</span>
                                <br>
                                <span class="subtle">
                                    ทีม: <?php echo h((string)($selected_customer['group_name'] ?? '-')); ?> |
                                    ผู้ติดต่อหลัก: <?php echo h(trim((string)($selected_customer['owner_name'] ?? '')) !== '' ? (string)$selected_customer['owner_name'] : (string)($selected_customer['owner_user_id'] ?? '-')); ?> |
                                    รุ่นที่สนใจ: <?php echo h((string)($selected_customer['interested_model'] ?? '-')); ?> |
                                    สถานะ: <?php echo h($pipeline_options[normalizePipelineStatus($selected_customer['pipeline_status'] ?? 'new_lead')] ?? '-'); ?>
                                </span>
                            </div>

                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                <input type="hidden" name="action" value="add_timeline">
                                <input type="hidden" name="customer_id" value="<?php echo (int)$selected_customer_id; ?>">
                                <?php if ($current_user_role === 'sales_manager' && $selected_group_id > 0): ?>
                                    <input type="hidden" name="group_id" value="<?php echo (int)$selected_group_id; ?>">
                                <?php endif; ?>

                                <div class="form-grid">
                                    <div class="field span-3">
                                        <label for="activity_type">ประเภทกิจกรรม</label>
                                        <select class="select" id="activity_type" name="activity_type">
                                            <?php foreach ($timeline_activity_options as $activity_key => $activity_label): ?>
                                                <option value="<?php echo h($activity_key); ?>"<?php echo ($timeline_form['activity_type'] ?? '') === $activity_key ? ' selected' : ''; ?>><?php echo h($activity_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="field span-4">
                                        <label for="activity_action">ทำอะไร</label>
                                        <input class="input" id="activity_action" name="activity_action" value="<?php echo h($timeline_form['activity_action'] ?? ''); ?>" placeholder="เช่น โทรติดตาม / ส่งใบเสนอราคา">
                                    </div>
                                    <div class="field span-5">
                                        <label for="activity_topic">คุยเรื่องอะไร</label>
                                        <input class="input" id="activity_topic" name="activity_topic" value="<?php echo h($timeline_form['activity_topic'] ?? ''); ?>" placeholder="เช่น เงื่อนไขไฟแนนซ์ / เทียบรุ่นรถ">
                                    </div>
                                    <div class="field span-4">
                                        <label for="timeline_next_followup_at">นัดติดตามครั้งถัดไป</label>
                                        <input class="input" type="datetime-local" id="timeline_next_followup_at" name="timeline_next_followup_at" value="<?php echo h($timeline_form['timeline_next_followup_at'] ?? ''); ?>">
                                    </div>
                                    <div class="field span-8">
                                        <label for="timeline_next_note">หัวข้องานติดตามถัดไป</label>
                                        <input class="input" id="timeline_next_note" name="timeline_next_note" value="<?php echo h($timeline_form['timeline_next_note'] ?? ''); ?>" placeholder="เช่น ส่งใบเสนอราคาและนัด Test Drive">
                                    </div>
                                    <div class="field span-12">
                                        <label for="activity_note">สรุปผลการคุย</label>
                                        <textarea class="textarea" id="activity_note" name="activity_note" required placeholder="ลูกค้าตอบว่าอะไร ติดเงื่อนไขไหน และตกลงขั้นตอนไหนต่อ"><?php echo h($timeline_form['activity_note'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="btn" type="submit"<?php echo !$can_manage_records ? ' disabled' : ''; ?>>เพิ่ม Timeline</button>
                                </div>
                            </form>

                            <?php if (!empty($timeline_rows)): ?>
                                <div class="timeline-summary">
                                    <?php foreach ($timeline_activity_options as $activity_key => $activity_label): ?>
                                        <div class="timeline-summary-item">
                                            <div class="timeline-summary-label"><?php echo h($activity_label); ?></div>
                                            <div class="timeline-summary-value"><?php echo number_format((int)($timeline_activity_counts[$activity_key] ?? 0)); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="timeline-list">
                                    <?php foreach ($timeline_rows as $timeline_row): ?>
                                        <?php
                                        $timeline_type = normalizeTimelineActivity($timeline_row['activity_type'] ?? 'note');
                                        $timeline_actor_name = trim((string)($timeline_row['actor_name'] ?? '')) !== ''
                                            ? (string)$timeline_row['actor_name']
                                            : (string)($timeline_row['actor_user_id'] ?? '-');
                                        $timeline_action_text = trim((string)($timeline_row['activity_action'] ?? ''));
                                        $timeline_topic_text = trim((string)($timeline_row['discussion_topic'] ?? ''));
                                        $timeline_next_note_text = trim((string)($timeline_row['next_followup_note'] ?? ''));
                                        ?>
                                        <div class="timeline-item type-<?php echo h($timeline_type); ?>">
                                            <div class="timeline-item-head">
                                                <div class="timeline-item-meta">
                                                    <span class="badge activity-<?php echo h($timeline_type); ?>">
                                                        <?php echo h($timeline_activity_options[$timeline_type] ?? $timeline_type); ?>
                                                    </span>
                                                    <span class="subtle">โดย <?php echo h($timeline_actor_name); ?></span>
                                                </div>
                                                <span class="subtle"><?php echo h(formatDateTimeDisplay($timeline_row['created_at'] ?? '')); ?></span>
                                            </div>

                                            <div class="timeline-detail-grid">
                                                <div class="timeline-detail-box">
                                                    <div class="timeline-detail-label">ทำอะไร</div>
                                                    <div class="timeline-detail-value"><?php echo h($timeline_action_text !== '' ? $timeline_action_text : '-'); ?></div>
                                                </div>
                                                <div class="timeline-detail-box">
                                                    <div class="timeline-detail-label">คุยเรื่องอะไร</div>
                                                    <div class="timeline-detail-value"><?php echo h($timeline_topic_text !== '' ? $timeline_topic_text : '-'); ?></div>
                                                </div>
                                                <div class="timeline-detail-box">
                                                    <div class="timeline-detail-label">นัดถัดไป</div>
                                                    <div class="timeline-detail-value"><?php echo h(!empty($timeline_row['next_followup_at']) ? formatDateTimeDisplay($timeline_row['next_followup_at']) : '-'); ?></div>
                                                </div>
                                            </div>

                                            <?php if ($timeline_next_note_text !== ''): ?>
                                                <div class="timeline-detail-box" style="margin-bottom: 8px;">
                                                    <div class="timeline-detail-label">หัวข้องานติดตามถัดไป</div>
                                                    <div class="timeline-detail-value"><?php echo h($timeline_next_note_text); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="timeline-note-block">
                                                <div class="timeline-detail-label">สรุปผลการคุย</div>
                                                <div class="timeline-note"><?php echo h((string)($timeline_row['activity_note'] ?? '')); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="margin-top: 12px;">ยังไม่มีบันทึก Timeline ของลูกค้ารายนี้</div>
                            <?php endif; ?>

                            <div style="margin-top: 12px;">
                                <div class="subtle" style="margin-bottom: 6px; font-size: 13px; color: #1b4f6f;">ประวัติมอบหมายงานเร่งด่วน (SLA Audit Trail)</div>
                                <?php if (!empty($sla_assignment_rows)): ?>
                                    <div class="approval-list">
                                        <?php foreach ($sla_assignment_rows as $assign_row): ?>
                                            <?php
                                            $from_owner_display = trim((string)($assign_row['from_owner_name'] ?? '')) !== ''
                                                ? (string)$assign_row['from_owner_name']
                                                : (string)($assign_row['from_owner_user_id'] ?? '-');
                                            $to_owner_display = trim((string)($assign_row['to_owner_name'] ?? '')) !== ''
                                                ? (string)$assign_row['to_owner_name']
                                                : (string)($assign_row['to_owner_user_id'] ?? '-');
                                            $assigned_by_display = trim((string)($assign_row['assigned_by_name'] ?? '')) !== ''
                                                ? (string)$assign_row['assigned_by_name']
                                                : (string)($assign_row['assigned_by'] ?? '-');
                                            ?>
                                            <div class="approval-item">
                                                <div class="approval-meta">
                                                    มอบหมายเมื่อ: <?php echo h(formatDateTimeDisplay($assign_row['assigned_at'] ?? '')); ?> |
                                                    โดย: <?php echo h($assigned_by_display); ?>
                                                </div>
                                                <div class="approval-meta">
                                                    จาก: <?php echo h($from_owner_display); ?> [<?php echo h((string)($assign_row['from_owner_user_id'] ?? '-')); ?>]
                                                    ->
                                                    ถึง: <?php echo h($to_owner_display); ?> [<?php echo h((string)($assign_row['to_owner_user_id'] ?? '-')); ?>]
                                                </div>
                                                <div class="subtle" style="margin-top: 5px;">
                                                    เหตุผล: <?php echo h((string)($assign_row['assign_reason'] ?? '-')); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state" style="margin-top: 8px;">ยังไม่มีประวัติมอบหมายงานเร่งด่วนของลูกค้ารายนี้</div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">เลือกลูกค้าจากตารางด้านบนเพื่อดูและบันทึก Timeline</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
</body>
</html>
