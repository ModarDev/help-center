<?php
require_once __DIR__ . '/../auth/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can run only in CLI mode.\n";
    exit(1);
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([(string)$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function runCountCheck(PDO $pdo, $name, $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();
    return [
        'name' => (string)$name,
        'count' => $count,
        'passed' => $count === 0
    ];
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $results = [];

    $hasCustomer = tableExists($pdo, 'sales_customer_records');
    $hasGroupInvite = tableExists($pdo, 'sales_group_invites');
    $hasGroupMember = tableExists($pdo, 'sales_group_members');
    $hasTimeline = tableExists($pdo, 'sales_customer_timeline');
    $hasSlaAlerts = tableExists($pdo, 'sales_customer_sla_alerts');

    if (!$hasCustomer) {
        echo "SKIP: sales_customer_records table not found.\n";
        exit(0);
    }

    $results[] = runCountCheck(
        $pdo,
        'customer_missing_core_fields',
        'SELECT COUNT(*)
         FROM sales_customer_records
         WHERE TRIM(COALESCE(branch_id, "")) = ""
            OR group_id <= 0
            OR TRIM(COALESCE(owner_user_id, "")) = ""'
    );

    if ($hasGroupInvite && $hasGroupMember) {
        $results[] = runCountCheck(
            $pdo,
            'customer_owner_not_in_group_or_manager',
            'SELECT COUNT(*)
             FROM sales_customer_records c
             LEFT JOIN sales_group_invites g
                    ON g.id = c.group_id
                   AND g.branch_id = c.branch_id
             LEFT JOIN sales_group_members m
                    ON m.group_id = c.group_id
                   AND m.branch_id = c.branch_id
                   AND m.member_user_id = c.owner_user_id
             WHERE g.id IS NULL
                OR (m.id IS NULL AND g.manager_user_id <> c.owner_user_id)'
        );
    }

    $results[] = runCountCheck(
        $pdo,
        'duplicate_phone_in_same_team_exact',
        'SELECT COUNT(*)
         FROM (
            SELECT branch_id, group_id, customer_phone
            FROM sales_customer_records
            WHERE TRIM(COALESCE(customer_phone, "")) <> ""
            GROUP BY branch_id, group_id, customer_phone
            HAVING COUNT(*) > 1
         ) dup'
    );

    $results[] = runCountCheck(
        $pdo,
        'duplicate_line_in_same_team_exact',
        'SELECT COUNT(*)
         FROM (
            SELECT branch_id, group_id, customer_line
            FROM sales_customer_records
            WHERE TRIM(COALESCE(customer_line, "")) <> ""
            GROUP BY branch_id, group_id, customer_line
            HAVING COUNT(*) > 1
         ) dup'
    );

    if ($hasTimeline) {
        $results[] = runCountCheck(
            $pdo,
            'timeline_orphan_customer',
            'SELECT COUNT(*)
             FROM sales_customer_timeline t
             LEFT JOIN sales_customer_records c ON c.id = t.customer_id
             WHERE c.id IS NULL'
        );
    }

    if ($hasSlaAlerts) {
        $results[] = runCountCheck(
            $pdo,
            'open_overdue_alert_due_in_future',
            'SELECT COUNT(*)
             FROM sales_customer_sla_alerts a
             WHERE a.alert_type = "followup_overdue"
               AND a.status = "open"
               AND a.due_at > NOW()'
        );

        $results[] = runCountCheck(
            $pdo,
            'sla_alert_orphan_customer',
            'SELECT COUNT(*)
             FROM sales_customer_sla_alerts a
             LEFT JOIN sales_customer_records c ON c.id = a.customer_id
             WHERE c.id IS NULL'
        );
    }

    $failed = 0;
    echo "CRM Customer Integrity Check\n";
    echo "============================\n";
    foreach ($results as $row) {
        $label = $row['passed'] ? 'PASS' : 'FAIL';
        echo '[' . $label . '] ' . $row['name'] . ' => ' . $row['count'] . "\n";
        if (!$row['passed']) {
            $failed++;
        }
    }

    if ($failed > 0) {
        echo "----------------------------\n";
        echo 'Integrity check failed: ' . $failed . " issue group(s) found.\n";
        exit(1);
    }

    echo "----------------------------\n";
    echo "Integrity check passed.\n";
    exit(0);
} catch (Throwable $e) {
    error_log('crm_customer_integrity_check.php error: ' . $e->getMessage());
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
