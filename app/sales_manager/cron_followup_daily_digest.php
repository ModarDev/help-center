<?php
require_once __DIR__ . '/../../auth/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can run only in CLI mode.\n";
    exit(1);
}

function ensureFollowupDigestLogTable(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sales_followup_digest_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            digest_date DATE NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            manager_user_id VARCHAR(50) NOT NULL,
            overdue_total INT UNSIGNED NOT NULL DEFAULT 0,
            critical_total INT UNSIGNED NOT NULL DEFAULT 0,
            high_total INT UNSIGNED NOT NULL DEFAULT 0,
            sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sales_followup_digest (digest_date, branch_id, manager_user_id),
            KEY idx_sales_followup_digest_manager (manager_user_id, digest_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function buildDigestPayload(array $row, $digestDate) {
    $managerName = trim((string)($row['manager_name'] ?? ''));
    $managerUserId = trim((string)($row['manager_user_id'] ?? '-'));
    $branchId = trim((string)($row['branch_id'] ?? '-'));
    $overdueTotal = (int)($row['overdue_total'] ?? 0);
    $criticalTotal = (int)($row['critical_total'] ?? 0);
    $highTotal = (int)($row['high_total'] ?? 0);
    $oldestDueAtRaw = trim((string)($row['oldest_due_at'] ?? ''));
    $groupNames = trim((string)($row['group_names'] ?? '-'));

    $oldestDueAtLabel = '-';
    if ($oldestDueAtRaw !== '') {
        $oldestDueTs = strtotime($oldestDueAtRaw);
        if ($oldestDueTs !== false) {
            $oldestDueAtLabel = date('d/m/Y H:i', $oldestDueTs);
        }
    }

    if ($managerName === '') {
        $managerName = $managerUserId !== '' ? $managerUserId : 'Sales Manager';
    }

    return [
        'username' => 'Office Plus Sales Bot',
        'embeds' => [[
            'title' => 'Daily Follow-up Overdue Digest',
            'color' => 15105570,
            'fields' => [
                ['name' => 'Digest Date', 'value' => (string)$digestDate, 'inline' => true],
                ['name' => 'Branch', 'value' => $branchId !== '' ? $branchId : '-', 'inline' => true],
                ['name' => 'Manager', 'value' => $managerName . ' [' . ($managerUserId !== '' ? $managerUserId : '-') . ']', 'inline' => false],
                ['name' => 'Overdue Total', 'value' => (string)$overdueTotal, 'inline' => true],
                ['name' => 'Critical', 'value' => (string)$criticalTotal, 'inline' => true],
                ['name' => 'High', 'value' => (string)$highTotal, 'inline' => true],
                ['name' => 'Oldest Due At', 'value' => $oldestDueAtLabel, 'inline' => true],
                ['name' => 'Teams', 'value' => $groupNames !== '' ? $groupNames : '-', 'inline' => false]
            ],
            'timestamp' => gmdate('c')
        ]]
    ];
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    ensureFollowupDigestLogTable($pdo);

    $webhookUrl = getDiscordWebhookUrl($pdo, 'sales_followup');
    if ($webhookUrl === '') {
        echo "SKIP: sales_followup webhook is not configured.\n";
        exit(0);
    }

    $digestDate = date('Y-m-d');

    $digestStmt = $pdo->query(
        'SELECT
            a.branch_id,
            g.manager_user_id,
            CONCAT_WS(" ", mu.first_name, mu.last_name) AS manager_name,
            COUNT(*) AS overdue_total,
            SUM(CASE WHEN a.severity = "critical" THEN 1 ELSE 0 END) AS critical_total,
            SUM(CASE WHEN a.severity = "high" THEN 1 ELSE 0 END) AS high_total,
            MIN(a.due_at) AS oldest_due_at,
            GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ", ") AS group_names
         FROM sales_customer_sla_alerts a
         INNER JOIN sales_group_invites g ON g.id = a.group_id AND g.branch_id = a.branch_id
         LEFT JOIN users mu ON mu.user_id = g.manager_user_id
         WHERE a.alert_type = "followup_overdue"
           AND a.status = "open"
         GROUP BY a.branch_id, g.manager_user_id, mu.first_name, mu.last_name
         HAVING g.manager_user_id <> ""
         ORDER BY critical_total DESC, overdue_total DESC'
    );

    $rows = $digestStmt ? $digestStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (empty($rows)) {
        echo "OK: no overdue follow-up records to notify.\n";
        exit(0);
    }

    $alreadySentStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sales_followup_digest_logs
         WHERE digest_date = ?
           AND branch_id = ?
           AND manager_user_id = ?'
    );

    $insertLogStmt = $pdo->prepare(
        'INSERT INTO sales_followup_digest_logs
            (digest_date, branch_id, manager_user_id, overdue_total, critical_total, high_total)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $sentCount = 0;
    $skipCount = 0;
    foreach ($rows as $row) {
        $branchId = trim((string)($row['branch_id'] ?? ''));
        $managerUserId = trim((string)($row['manager_user_id'] ?? ''));

        if ($branchId === '' || $managerUserId === '') {
            $skipCount++;
            continue;
        }

        $alreadySentStmt->execute([$digestDate, $branchId, $managerUserId]);
        if ((int)$alreadySentStmt->fetchColumn() > 0) {
            echo 'SKIP already sent for manager ' . $managerUserId . ' branch ' . $branchId . "\n";
            $skipCount++;
            continue;
        }

        $payload = buildDigestPayload($row, $digestDate);
        $sent = sendDiscordWebhookMessage($webhookUrl, $payload);
        if (!$sent) {
            echo 'ERROR send failed for manager ' . $managerUserId . ' branch ' . $branchId . "\n";
            continue;
        }

        $insertLogStmt->execute([
            $digestDate,
            $branchId,
            $managerUserId,
            (int)($row['overdue_total'] ?? 0),
            (int)($row['critical_total'] ?? 0),
            (int)($row['high_total'] ?? 0)
        ]);

        echo 'SEND digest to manager ' . $managerUserId . ' branch ' . $branchId . "\n";
        $sentCount++;
    }

    echo 'DONE sent=' . $sentCount . ' skipped=' . $skipCount . "\n";
} catch (Throwable $e) {
    error_log('cron_followup_daily_digest.php error: ' . $e->getMessage());
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
