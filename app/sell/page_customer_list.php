<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

if (!isLoggedIn()) {
	header('Location: ../../auth/login');
	exit();
}

try {
	$access_pdo = getDBConnection();
	if (userHasAnyRole(['sales_manager'])) {
		$query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
		$target = '../sales_manager/page_customer_list.php';
		if ($query !== '') {
			$target .= '?' . $query;
		}
		header('Location: ' . $target);
		exit();
	}

	if (!userHasAnyRole(['sell_car', 'employee'])) {
		header('Location: ../../auth/login');
		exit();
	}

	if (!currentUserCanAccessDashboardMenu('customerlist', ['sidebar'])) {
		header('Location: ../../auth/login');
		exit();
	}

	if (shouldRequireBranchSelection($access_pdo)) {
		$active_branch_id = getCurrentBranchId();
		if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
			header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/sell/page_customer_list.php'));
			exit();
		}
	}
} catch (Throwable $e) {
	error_log('Role access check failed in sell/page_customer_list.php: ' . $e->getMessage());
	header('Location: ../../auth/login');
	exit();
}

function h($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeText($value) {
	$value = trim((string)$value);
	$value = preg_replace('/\s+/', ' ', $value) ?? $value;
	return $value;
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

function formatMoneyDisplay($value) {
	return number_format((float)$value, 2);
}

function getPipelineStatusOptions() {
	return [
		'all' => 'All',
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

function getApprovalStatusOptions() {
	return [
		'all' => 'All',
		'pending' => 'Pending',
		'approved' => 'Approved',
		'rejected' => 'Rejected'
	];
}

function getLeadSourceOptions() {
	return [
		'all' => 'All',
		'facebook' => 'Facebook',
		'walk_in' => 'Walk-in',
		'refer' => 'Refer',
		'line' => 'Line',
		'website' => 'Website',
		'other' => 'Other'
	];
}

function getFollowupOptions() {
	return [
		'all' => 'All',
		'due' => 'Due',
		'today' => 'Today',
		'upcoming' => 'Upcoming',
		'none' => 'No Follow-up'
	];
}

function normalizeChoice($value, array $allowedValues, $defaultValue) {
	$value = trim((string)$value);
	if (!in_array($value, $allowedValues, true)) {
		return (string)$defaultValue;
	}

	return $value;
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
			KEY idx_sct_branch_group (branch_id, group_id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
	);
}

function fetchManagedGroups(PDO $pdo, $managerUserId, $branchId) {
	$stmt = $pdo->prepare(
		'SELECT
			g.id,
			g.group_name,
			g.status,
			g.created_at,
			g.updated_at
		 FROM sales_group_invites g
		 WHERE g.manager_user_id = ?
		   AND g.branch_id = ?
		 ORDER BY g.created_at DESC'
	);
	$stmt->execute([(string)$managerUserId, (string)$branchId]);
	return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function fetchCurrentMembership(PDO $pdo, $userId, $branchId) {
	$stmt = $pdo->prepare(
		'SELECT
			m.group_id,
			m.status AS member_status,
			g.group_name,
			g.status AS group_status
		 FROM sales_group_members m
		 INNER JOIN sales_group_invites g ON g.id = m.group_id
		 WHERE m.member_user_id = ?
		   AND m.branch_id = ?
		 ORDER BY m.updated_at DESC, m.id DESC
		 LIMIT 1'
	);
	$stmt->execute([(string)$userId, (string)$branchId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	return $row ?: null;
}

function buildListUrl(array $params, array $overrides = []) {
	$next = array_merge($params, $overrides);

	foreach ($next as $key => $value) {
		if ($value === null) {
			unset($next[$key]);
			continue;
		}

		if (is_string($value) && trim($value) === '') {
			unset($next[$key]);
			continue;
		}

		if (in_array($key, ['group_id', 'page'], true) && (int)$value <= 0) {
			unset($next[$key]);
		}
	}

	if (!isset($next['module'])) {
		$next['module'] = 'customer';
	}

	return 'page_customer_list.php?' . http_build_query($next);
}

function exportCsv(array $rows) {
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="customer_list_' . date('Ymd_His') . '.csv"');

	$output = fopen('php://output', 'w');
	if ($output === false) {
		return;
	}

	fwrite($output, "\xEF\xBB\xBF");
	fputcsv($output, [
		'Customer Name',
		'Phone',
		'Line',
		'Province',
		'Lead Source',
		'Model',
		'Pipeline',
		'Approval',
		'Budget',
		'Down Payment',
		'Monthly Budget',
		'Target Purchase Date',
		'Last Contact',
		'Next Follow-up',
		'Primary Contact User ID',
		'Primary Contact Name',
		'Last Contact By User ID',
		'Last Contact By Name',
		'Group ID',
		'Group Name',
		'Updated At'
	]);

	foreach ($rows as $row) {
		fputcsv($output, [
			(string)($row['customer_name'] ?? ''),
			(string)($row['customer_phone'] ?? ''),
			(string)($row['customer_line'] ?? ''),
			(string)($row['customer_province'] ?? ''),
			(string)($row['lead_source'] ?? ''),
			(string)($row['interested_model'] ?? ''),
			(string)($row['pipeline_status'] ?? ''),
			(string)($row['approval_status'] ?? ''),
			(string)($row['budget_amount'] ?? '0'),
			(string)($row['down_payment'] ?? '0'),
			(string)($row['monthly_budget'] ?? '0'),
			(string)($row['target_purchase_date'] ?? ''),
			(string)($row['last_contact_at'] ?? ''),
			(string)($row['next_followup_at'] ?? ''),
			(string)($row['owner_user_id'] ?? ''),
			(string)($row['owner_name'] ?? ''),
			(string)($row['last_actor_user_id'] ?? ''),
			(string)($row['last_actor_name'] ?? ''),
			(string)($row['group_id'] ?? ''),
			(string)($row['group_name'] ?? ''),
			(string)($row['updated_at'] ?? '')
		]);
	}

	fclose($output);
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

$pipeline_options = getPipelineStatusOptions();
$approval_options = getApprovalStatusOptions();
$lead_source_options = getLeadSourceOptions();
$followup_options = getFollowupOptions();

$sort_options = [
	'updated_at' => 'Updated Time',
	'customer_name' => 'Customer Name',
	'pipeline_status' => 'Pipeline',
	'approval_status' => 'Approval',
	'budget_amount' => 'Budget',
	'next_followup_at' => 'Next Follow-up',
	'target_purchase_date' => 'Target Purchase'
];

$sort_column_map = [
	'updated_at' => 'c.updated_at',
	'customer_name' => 'c.customer_name',
	'pipeline_status' => 'c.pipeline_status',
	'approval_status' => 'c.approval_status',
	'budget_amount' => 'c.budget_amount',
	'next_followup_at' => 'c.next_followup_at',
	'target_purchase_date' => 'c.target_purchase_date'
];

$per_page_options = [25, 50, 100, 200];

$search_query = normalizeText($_GET['q'] ?? '');
$selected_pipeline = normalizeChoice($_GET['pipeline_status'] ?? 'all', array_keys($pipeline_options), 'all');
$selected_approval = normalizeChoice($_GET['approval_status'] ?? 'all', array_keys($approval_options), 'all');
$selected_lead_source = normalizeChoice($_GET['lead_source'] ?? 'all', array_keys($lead_source_options), 'all');
$selected_followup = normalizeChoice($_GET['followup'] ?? 'all', array_keys($followup_options), 'all');

$selected_sort_by = normalizeChoice($_GET['sort_by'] ?? 'updated_at', array_keys($sort_options), 'updated_at');
$selected_sort_dir = strtolower(trim((string)($_GET['sort_dir'] ?? 'desc')));
if (!in_array($selected_sort_dir, ['asc', 'desc'], true)) {
	$selected_sort_dir = 'desc';
}

$selected_per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($selected_per_page, $per_page_options, true)) {
	$selected_per_page = 25;
}

$selected_page = (int)($_GET['page'] ?? 1);
if ($selected_page <= 0) {
	$selected_page = 1;
}

$selected_group_id = (int)($_GET['group_id'] ?? 0);
$selected_owner_user_id = normalizeText($_GET['owner_user_id'] ?? '');

$selected_updated_from = trim((string)($_GET['updated_from'] ?? ''));
$selected_updated_to = trim((string)($_GET['updated_to'] ?? ''));

$export_format = strtolower(trim((string)($_GET['export'] ?? '')));
$is_export_csv = $export_format === 'csv';

$errors = [];
$managed_groups = [];
$managed_group_ids = [];
$managed_group_map = [];
$current_membership = null;
$seller_scope_group_id = 0;
$seller_scope_group_name = '-';
$seller_scope_message = '';
$owner_options = [];
$rows = [];
$total_records = 0;

$updated_from_date = normalizeDateInput($selected_updated_from);
if ($updated_from_date === false) {
	$errors[] = 'Invalid Updated From date format';
	$updated_from_date = null;
}

$updated_to_date = normalizeDateInput($selected_updated_to);
if ($updated_to_date === false) {
	$errors[] = 'Invalid Updated To date format';
	$updated_to_date = null;
}

try {
	$pdo = getDBConnection();
	ensureSalesGroupTables($pdo);
	ensureSalesCustomerTables($pdo);

	if ($current_user_role === 'sales_manager') {
		$managed_groups = fetchManagedGroups($pdo, $current_user_id, $active_branch_id);

		foreach ($managed_groups as $group_row) {
			$group_id = (int)($group_row['id'] ?? 0);
			if ($group_id <= 0) {
				continue;
			}
			$managed_group_ids[] = $group_id;
			$managed_group_map[$group_id] = $group_row;
		}

		if ($selected_group_id > 0 && !isset($managed_group_map[$selected_group_id])) {
			$errors[] = 'Selected group is outside your access scope';
			$selected_group_id = 0;
		}

		if (!empty($managed_group_ids)) {
			if ($selected_group_id > 0) {
				$owner_sql =
					'SELECT DISTINCT
						c.owner_user_id,
						CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name
					 FROM sales_customer_records c
					 LEFT JOIN users u ON u.user_id = c.owner_user_id
					 WHERE c.branch_id = ?
					   AND c.group_id = ?
					 ORDER BY owner_name ASC, c.owner_user_id ASC';
				$owner_stmt = $pdo->prepare($owner_sql);
				$owner_stmt->execute([$active_branch_id, $selected_group_id]);
			} else {
				$placeholders = implode(',', array_fill(0, count($managed_group_ids), '?'));
				$owner_sql =
					'SELECT DISTINCT
						c.owner_user_id,
						CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name
					 FROM sales_customer_records c
					 LEFT JOIN users u ON u.user_id = c.owner_user_id
					 WHERE c.branch_id = ?
					   AND c.group_id IN (' . $placeholders . ')
					 ORDER BY owner_name ASC, c.owner_user_id ASC';
				$owner_stmt = $pdo->prepare($owner_sql);
				$owner_params = [$active_branch_id];
				foreach ($managed_group_ids as $managed_id) {
					$owner_params[] = $managed_id;
				}
				$owner_stmt->execute($owner_params);
			}

			$owner_rows = $owner_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
			foreach ($owner_rows as $owner_row) {
				$owner_user_id = trim((string)($owner_row['owner_user_id'] ?? ''));
				if ($owner_user_id === '') {
					continue;
				}

				$owner_label = trim((string)($owner_row['owner_name'] ?? ''));
				if ($owner_label === '') {
					$owner_label = $owner_user_id;
				}
				$owner_options[$owner_user_id] = $owner_label;
			}
		}

		if ($selected_owner_user_id !== '' && !isset($owner_options[$selected_owner_user_id])) {
			$errors[] = 'Selected owner is outside your access scope';
			$selected_owner_user_id = '';
		}
	} else {
		$selected_group_id = 0;
		$selected_owner_user_id = '';

		$current_membership = fetchCurrentMembership($pdo, $current_user_id, $active_branch_id);
		if ($current_membership) {
			$seller_scope_group_name = trim((string)($current_membership['group_name'] ?? '-'));
			$member_status = strtolower(trim((string)($current_membership['member_status'] ?? 'active')));
			$group_status = strtolower(trim((string)($current_membership['group_status'] ?? 'active')));
			$membership_group_id = (int)($current_membership['group_id'] ?? 0);

			if ($membership_group_id > 0 && $member_status === 'active' && $group_status === 'active') {
				$seller_scope_group_id = $membership_group_id;
			} elseif ($member_status === 'pending') {
				$seller_scope_message = 'คุณกำลังรออนุมัติในทีมขาย จึงยังไม่สามารถดูข้อมูลลูกค้าได้';
			} elseif ($member_status === 'suspended' || $group_status === 'suspended') {
				$seller_scope_message = 'สถานะสมาชิกหรือทีมขายถูกระงับ จึงไม่สามารถดูข้อมูลลูกค้าได้';
			} else {
				$seller_scope_message = 'ไม่พบสิทธิ์ทีมขายที่พร้อมใช้งานในสาขานี้';
			}
		} else {
			$seller_scope_message = 'ยังไม่ได้เข้าร่วม Group Sale ในสาขานี้';
		}
	}

	$where_parts = ['c.branch_id = ?'];
	$query_params = [$active_branch_id];

	if ($current_user_role === 'sales_manager') {
		if ($selected_group_id > 0) {
			$where_parts[] = 'c.group_id = ?';
			$query_params[] = $selected_group_id;
		} elseif (!empty($managed_group_ids)) {
			$placeholders = implode(',', array_fill(0, count($managed_group_ids), '?'));
			$where_parts[] = 'c.group_id IN (' . $placeholders . ')';
			foreach ($managed_group_ids as $managed_id) {
				$query_params[] = $managed_id;
			}
		} else {
			$where_parts[] = '1 = 0';
		}
	} else {
		if ($seller_scope_group_id > 0) {
			$where_parts[] = 'c.group_id = ?';
			$query_params[] = $seller_scope_group_id;
			$where_parts[] = 'c.owner_user_id = ?';
			$query_params[] = $current_user_id;
		} else {
			$where_parts[] = '1 = 0';
		}
	}

	if ($search_query !== '') {
		$like_value = '%' . $search_query . '%';
		$where_parts[] = '(
			c.customer_name LIKE ?
			OR c.customer_phone LIKE ?
			OR c.customer_line LIKE ?
			OR c.customer_province LIKE ?
			OR c.interested_model LIKE ?
			OR c.owner_user_id LIKE ?
		)';
		$query_params[] = $like_value;
		$query_params[] = $like_value;
		$query_params[] = $like_value;
		$query_params[] = $like_value;
		$query_params[] = $like_value;
		$query_params[] = $like_value;
	}

	if ($selected_pipeline !== 'all') {
		$where_parts[] = 'c.pipeline_status = ?';
		$query_params[] = $selected_pipeline;
	}

	if ($selected_approval !== 'all') {
		$where_parts[] = 'c.approval_status = ?';
		$query_params[] = $selected_approval;
	}

	if ($selected_lead_source !== 'all') {
		$where_parts[] = 'c.lead_source = ?';
		$query_params[] = $selected_lead_source;
	}

	if ($current_user_role === 'sales_manager' && $selected_owner_user_id !== '') {
		$where_parts[] = 'c.owner_user_id = ?';
		$query_params[] = $selected_owner_user_id;
	}

	if ($selected_followup === 'due') {
		$where_parts[] = 'c.next_followup_at IS NOT NULL AND c.next_followup_at <= NOW()';
	} elseif ($selected_followup === 'today') {
		$where_parts[] = 'c.next_followup_at >= CURDATE() AND c.next_followup_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
	} elseif ($selected_followup === 'upcoming') {
		$where_parts[] = 'c.next_followup_at IS NOT NULL AND c.next_followup_at > NOW()';
	} elseif ($selected_followup === 'none') {
		$where_parts[] = 'c.next_followup_at IS NULL';
	}

	if ($updated_from_date !== null) {
		$where_parts[] = 'c.updated_at >= ?';
		$query_params[] = $updated_from_date . ' 00:00:00';
	}

	if ($updated_to_date !== null) {
		$where_parts[] = 'c.updated_at < ?';
		$query_params[] = date('Y-m-d', strtotime($updated_to_date . ' +1 day')) . ' 00:00:00';
	}

	$where_sql = implode(' AND ', $where_parts);
	$sort_sql = $sort_column_map[$selected_sort_by] ?? $sort_column_map['updated_at'];
	$sort_dir_sql = $selected_sort_dir === 'asc' ? 'ASC' : 'DESC';

	$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM sales_customer_records c WHERE ' . $where_sql);
	$count_stmt->execute($query_params);
	$total_records = (int)$count_stmt->fetchColumn();

	$base_select_sql =
		'SELECT
			c.id,
			c.branch_id,
			c.group_id,
			c.owner_user_id,
			c.customer_name,
			c.customer_phone,
			c.customer_line,
			c.customer_province,
			c.lead_source,
			c.interested_model,
			c.budget_amount,
			c.down_payment,
			c.monthly_budget,
			c.target_purchase_date,
			c.pipeline_status,
			c.last_contact_at,
			c.next_followup_at,
			c.approval_status,
			c.updated_at,
			g.group_name,
			CONCAT_WS(" ", u.first_name, u.last_name) AS owner_name,
			lc.last_actor_user_id,
			lc.last_actor_name
		 FROM sales_customer_records c
		 LEFT JOIN sales_group_invites g ON g.id = c.group_id
		 LEFT JOIN users u ON u.user_id = c.owner_user_id
		 LEFT JOIN (
			SELECT
				t.customer_id,
				t.actor_user_id AS last_actor_user_id,
				CONCAT_WS(" ", lu.first_name, lu.last_name) AS last_actor_name
			FROM sales_customer_timeline t
			INNER JOIN (
				SELECT customer_id, MAX(id) AS max_id
				FROM sales_customer_timeline
				GROUP BY customer_id
			) latest_t ON latest_t.customer_id = t.customer_id AND latest_t.max_id = t.id
			LEFT JOIN users lu ON lu.user_id = t.actor_user_id
		 ) lc ON lc.customer_id = c.id
		 WHERE ' . $where_sql;

	if ($is_export_csv) {
		$export_stmt = $pdo->prepare(
			$base_select_sql .
			' ORDER BY ' . $sort_sql . ' ' . $sort_dir_sql . ', c.id DESC'
		);
		$export_stmt->execute($query_params);
		$export_rows = $export_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		exportCsv($export_rows);
		exit();
	}

	$total_pages = $total_records > 0 ? (int)ceil($total_records / $selected_per_page) : 1;
	if ($selected_page > $total_pages) {
		$selected_page = $total_pages;
	}

	$offset = ($selected_page - 1) * $selected_per_page;
	if ($offset < 0) {
		$offset = 0;
	}

	$list_stmt = $pdo->prepare(
		$base_select_sql .
		' ORDER BY ' . $sort_sql . ' ' . $sort_dir_sql . ', c.id DESC' .
		' LIMIT ' . (int)$selected_per_page . ' OFFSET ' . (int)$offset
	);
	$list_stmt->execute($query_params);
	$rows = $list_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
	error_log('page_customer_list.php error: ' . $e->getMessage());
	$errors[] = 'Failed to load customer list data';
}

$link_params = [
	'module' => $current_module,
	'q' => $search_query,
	'pipeline_status' => $selected_pipeline,
	'approval_status' => $selected_approval,
	'lead_source' => $selected_lead_source,
	'followup' => $selected_followup,
	'updated_from' => $updated_from_date,
	'updated_to' => $updated_to_date,
	'sort_by' => $selected_sort_by,
	'sort_dir' => $selected_sort_dir,
	'per_page' => $selected_per_page,
	'page' => $selected_page,
	'group_id' => $current_user_role === 'sales_manager' ? $selected_group_id : null,
	'owner_user_id' => $current_user_role === 'sales_manager' ? $selected_owner_user_id : null
];

$reset_url = buildListUrl(['module' => $current_module]);
$export_url = buildListUrl($link_params, ['export' => 'csv', 'page' => 1]);

$management_url = 'page_customer_management.php?module=' . urlencode($current_module);
if ($current_user_role === 'sales_manager' && $selected_group_id > 0) {
	$management_url .= '&group_id=' . (int)$selected_group_id;
}

$sort_labels = $sort_options;

function getSortUrl(array $params, $targetSortBy) {
	$currentSortBy = (string)($params['sort_by'] ?? 'updated_at');
	$currentSortDir = strtolower((string)($params['sort_dir'] ?? 'desc'));
	$nextDir = ($currentSortBy === (string)$targetSortBy && $currentSortDir === 'asc') ? 'desc' : 'asc';
	return buildListUrl($params, ['sort_by' => (string)$targetSortBy, 'sort_dir' => $nextDir, 'page' => 1]);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer List</title>
<style>
* { box-sizing: border-box; }

:root {
	--line: #d7e4ef;
	--card: #ffffff;
	--ink: #123a57;
	--ink-soft: #4f6f85;
	--bg: #f2f7fb;
	--accent: #0f7ab8;
	--accent-dark: #0a5f8f;
	--warn: #c07f1b;
	--ok: #1f8c5a;
	--danger: #be4444;
	--shadow: 0 14px 30px rgba(8, 52, 84, 0.08);
}

html,
body {
	margin: 0;
	min-height: 100%;
}

body {
	font-family: Tahoma, Arial, sans-serif;
	color: var(--ink);
	background:
		radial-gradient(circle at 10% 8%, rgba(15, 122, 184, 0.14), transparent 34%),
		radial-gradient(circle at 88% 20%, rgba(192, 127, 27, 0.12), transparent 32%),
		linear-gradient(180deg, #edf4fa 0%, #f3f8fc 52%, #f8fbfd 100%);
}

.page {
	width: 100%;
	max-width: 1440px;
	margin: 0 auto;
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 14px;
}

.card {
	border: 1px solid var(--line);
	border-radius: 16px;
	background: var(--card);
	box-shadow: var(--shadow);
	overflow: hidden;
}

.head {
	padding: 14px 16px;
	border-bottom: 1px solid #e4edf4;
	background: linear-gradient(120deg, #f7fbff 0%, #ffffff 100%);
}

.head h1,
.head h2 {
	margin: 0;
	color: #0f3f60;
}

.head h1 { font-size: 26px; }
.head h2 { font-size: 18px; }

.head p {
	margin: 6px 0 0;
	color: var(--ink-soft);
	font-size: 12px;
}

.head-layout {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	align-items: flex-start;
}

.chips {
	margin-top: 10px;
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.chip {
	border: 1px solid #c7dae8;
	border-radius: 999px;
	background: #f4fafe;
	color: #265b7f;
	font-size: 12px;
	font-weight: 700;
	padding: 5px 10px;
}

.btn-row {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.btn {
	height: 36px;
	border-radius: 10px;
	border: 1px solid var(--accent);
	background: var(--accent);
	color: #ffffff;
	text-decoration: none;
	font-size: 12px;
	font-weight: 700;
	padding: 0 12px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.btn:hover { background: var(--accent-dark); }

.btn.alt {
	border-color: #c0d5e5;
	background: #f6fbff;
	color: #194f72;
}

.alert {
	border: 1px solid #efcbcb;
	background: #fff3f3;
	color: #9a3b3b;
	border-radius: 12px;
	padding: 10px 12px;
	font-size: 13px;
}

.body {
	padding: 14px 16px 16px;
}

.filter-grid {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: 10px;
}

.field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.f-2 { grid-column: span 2; }
.f-3 { grid-column: span 3; }
.f-4 { grid-column: span 4; }
.f-6 { grid-column: span 6; }
.f-12 { grid-column: span 12; }

.field label {
	font-size: 12px;
	font-weight: 700;
	color: #1f516f;
}

.input,
.select {
	width: 100%;
	height: 38px;
	border: 1px solid #c7d9e7;
	border-radius: 10px;
	background: #fcfeff;
	color: #1c4f70;
	font-size: 13px;
	padding: 0 10px;
}

.input:focus,
.select:focus {
	outline: none;
	border-color: var(--accent);
	box-shadow: 0 0 0 3px rgba(15, 122, 184, 0.15);
}

.filter-actions {
	margin-top: 10px;
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.summary {
	margin-top: 10px;
	font-size: 12px;
	color: #56768f;
}

.table-wrap {
	overflow: auto;
	border: 1px solid #d9e6f0;
	border-radius: 12px;
}

table {
	width: 100%;
	border-collapse: collapse;
	min-width: 1220px;
}

thead th {
	text-align: left;
	font-size: 12px;
	color: #2f5f7f;
	background: #f5faff;
	border-bottom: 1px solid #d8e5f1;
	padding: 10px;
	white-space: nowrap;
}

thead th a {
	color: inherit;
	text-decoration: none;
}

tbody td {
	border-bottom: 1px solid #eaf1f7;
	font-size: 12px;
	color: #284f69;
	padding: 10px;
	vertical-align: top;
}

tbody tr:hover {
	background: #f8fcff;
}

.badge {
	display: inline-flex;
	align-items: center;
	border-radius: 999px;
	border: 1px solid #c6d9e8;
	background: #eef6fc;
	color: #225678;
	padding: 4px 8px;
	font-size: 11px;
	font-weight: 700;
}

.badge.pending {
	border-color: #efdcb8;
	background: #fff8ea;
	color: #8d6114;
}

.badge.approved {
	border-color: #c1e3d0;
	background: #edf9f2;
	color: #246445;
}

.badge.rejected {
	border-color: #efc6c6;
	background: #fff3f3;
	color: #9a3a3a;
}

.row-actions {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.tiny-btn {
	height: 30px;
	border-radius: 8px;
	border: 1px solid #c3d7e7;
	background: #ffffff;
	color: #205170;
	text-decoration: none;
	font-size: 11px;
	font-weight: 700;
	padding: 0 9px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.tiny-btn.primary {
	border-color: var(--accent);
	background: var(--accent);
	color: #ffffff;
}

.empty {
	border: 1px dashed #c7d9e8;
	border-radius: 12px;
	background: #fbfdff;
	color: #567890;
	text-align: center;
	padding: 20px;
	font-size: 13px;
}

.pagination {
	margin-top: 12px;
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.page-link {
	height: 32px;
	min-width: 32px;
	border: 1px solid #c4d8e8;
	border-radius: 8px;
	background: #ffffff;
	color: #204f6d;
	text-decoration: none;
	font-size: 12px;
	font-weight: 700;
	padding: 0 10px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.page-link.current {
	border-color: var(--accent);
	background: var(--accent);
	color: #ffffff;
}

@media (max-width: 1024px) {
	.f-2,
	.f-3,
	.f-4,
	.f-6 {
		grid-column: span 6;
	}
}

@media (max-width: 760px) {
	.page { padding: 12px; }

	.f-2,
	.f-3,
	.f-4,
	.f-6 {
		grid-column: span 12;
	}

	.head h1 { font-size: 22px; }
}
</style>
</head>
<body>
<div class="page">
	<section class="card">
		<div class="head">
			<div class="head-layout">
				<div>
					<h1>Customer List</h1>
					<p>Standalone list page with search, filter, sort, pagination, and CSV export.</p>
					<div class="chips">
						<span class="chip">Branch: <?php echo h($active_branch_id); ?></span>
						<span class="chip">Role: <?php echo h($current_user_role); ?></span>
						<span class="chip">User: <?php echo h($current_user_name); ?></span>
						<?php if ($current_user_role !== 'sales_manager'): ?>
							<span class="chip">Team Scope: <?php echo h($seller_scope_group_id > 0 ? ($seller_scope_group_name . ' (#' . $seller_scope_group_id . ')') : '-'); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="btn-row">
					<a class="btn alt" href="<?php echo h($management_url); ?>">Customer Management</a>
					<a class="btn" href="<?php echo h($export_url); ?>">Export CSV</a>
				</div>
			</div>
		</div>
	</section>

	<?php if (!empty($errors)): ?>
		<section class="alert">
			<?php foreach ($errors as $error): ?>
				<div>- <?php echo h($error); ?></div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php if ($seller_scope_message !== ''): ?>
		<section class="alert">
			<div>- <?php echo h($seller_scope_message); ?></div>
		</section>
	<?php endif; ?>

	<section class="card">
		<div class="head">
			<h2>Search and Filters</h2>
			<p>Filter records within your branch and group scope.</p>
		</div>
		<div class="body">
			<form method="get">
				<input type="hidden" name="module" value="<?php echo h($current_module); ?>">
				<div class="filter-grid">
					<div class="field f-6">
						<label for="q">Search</label>
						<input class="input" id="q" name="q" value="<?php echo h($search_query); ?>" placeholder="Name, phone, line, model, province, owner">
					</div>
					<div class="field f-2">
						<label for="pipeline_status">Pipeline</label>
						<select class="select" id="pipeline_status" name="pipeline_status">
							<?php foreach ($pipeline_options as $value => $label): ?>
								<option value="<?php echo h($value); ?>"<?php echo $selected_pipeline === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field f-2">
						<label for="approval_status">Approval</label>
						<select class="select" id="approval_status" name="approval_status">
							<?php foreach ($approval_options as $value => $label): ?>
								<option value="<?php echo h($value); ?>"<?php echo $selected_approval === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field f-2">
						<label for="lead_source">Lead Source</label>
						<select class="select" id="lead_source" name="lead_source">
							<?php foreach ($lead_source_options as $value => $label): ?>
								<option value="<?php echo h($value); ?>"<?php echo $selected_lead_source === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="field f-2">
						<label for="followup">Follow-up</label>
						<select class="select" id="followup" name="followup">
							<?php foreach ($followup_options as $value => $label): ?>
								<option value="<?php echo h($value); ?>"<?php echo $selected_followup === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field f-2">
						<label for="updated_from">Updated From</label>
						<input class="input" type="date" id="updated_from" name="updated_from" value="<?php echo h($updated_from_date ?? ''); ?>">
					</div>
					<div class="field f-2">
						<label for="updated_to">Updated To</label>
						<input class="input" type="date" id="updated_to" name="updated_to" value="<?php echo h($updated_to_date ?? ''); ?>">
					</div>

					<?php if ($current_user_role === 'sales_manager'): ?>
						<div class="field f-3">
							<label for="group_id">Group</label>
							<select class="select" id="group_id" name="group_id">
								<option value="0">All Groups</option>
								<?php foreach ($managed_groups as $group): ?>
									<?php $group_id = (int)($group['id'] ?? 0); ?>
									<option value="<?php echo $group_id; ?>"<?php echo $selected_group_id === $group_id ? ' selected' : ''; ?>>
										<?php echo h((string)($group['group_name'] ?? '-') . ' (#' . $group_id . ')'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="field f-3">
							<label for="owner_user_id">Owner</label>
							<select class="select" id="owner_user_id" name="owner_user_id">
								<option value="">All Owners</option>
								<?php foreach ($owner_options as $owner_user_id => $owner_label): ?>
									<option value="<?php echo h($owner_user_id); ?>"<?php echo $selected_owner_user_id === $owner_user_id ? ' selected' : ''; ?>>
										<?php echo h($owner_label . ' [' . $owner_user_id . ']'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<div class="field f-2">
						<label for="sort_by">Sort By</label>
						<select class="select" id="sort_by" name="sort_by">
							<?php foreach ($sort_options as $sort_key => $sort_label): ?>
								<option value="<?php echo h($sort_key); ?>"<?php echo $selected_sort_by === $sort_key ? ' selected' : ''; ?>><?php echo h($sort_label); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field f-2">
						<label for="sort_dir">Sort Direction</label>
						<select class="select" id="sort_dir" name="sort_dir">
							<option value="desc"<?php echo $selected_sort_dir === 'desc' ? ' selected' : ''; ?>>Desc</option>
							<option value="asc"<?php echo $selected_sort_dir === 'asc' ? ' selected' : ''; ?>>Asc</option>
						</select>
					</div>
					<div class="field f-2">
						<label for="per_page">Rows Per Page</label>
						<select class="select" id="per_page" name="per_page">
							<?php foreach ($per_page_options as $per_page_option): ?>
								<option value="<?php echo (int)$per_page_option; ?>"<?php echo $selected_per_page === (int)$per_page_option ? ' selected' : ''; ?>>
									<?php echo (int)$per_page_option; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="filter-actions">
					<button class="btn" type="submit">Apply Filters</button>
					<a class="btn alt" href="<?php echo h($reset_url); ?>">Reset</a>
				</div>
			</form>

			<div class="summary">
				Total records: <?php echo number_format($total_records); ?> |
				Page: <?php echo number_format($selected_page); ?>
			</div>
		</div>
	</section>

	<section class="card">
		<div class="head">
			<h2>Customer Records</h2>
			<p>Sorted and filtered list with direct links to customer management.</p>
		</div>
		<div class="body">
			<?php if (!empty($rows)): ?>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th><a href="<?php echo h(getSortUrl($link_params, 'customer_name')); ?>">Customer</a></th>
								<th>Contact</th>
								<th>Model</th>
								<th><a href="<?php echo h(getSortUrl($link_params, 'pipeline_status')); ?>">Pipeline</a></th>
								<th><a href="<?php echo h(getSortUrl($link_params, 'approval_status')); ?>">Approval</a></th>
								<th>Lead</th>
								<th><a href="<?php echo h(getSortUrl($link_params, 'budget_amount')); ?>">Budget</a></th>
								<th><a href="<?php echo h(getSortUrl($link_params, 'next_followup_at')); ?>">Next Follow-up</a></th>
								<th>Primary Contact</th>
								<th>Last Contact By</th>
								<th>Group</th>
								<th><a href="<?php echo h(getSortUrl($link_params, 'updated_at')); ?>">Updated</a></th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($rows as $row): ?>
								<?php
								$row_id = (int)($row['id'] ?? 0);
								$row_group_id = (int)($row['group_id'] ?? 0);
								$view_url = 'page_customer_management.php?module=' . urlencode($current_module) . '&customer_id=' . $row_id;
								$edit_url = 'page_customer_management.php?module=' . urlencode($current_module) . '&customer_id=' . $row_id . '&edit_id=' . $row_id;
								if ($current_user_role === 'sales_manager' && $row_group_id > 0) {
									$view_url .= '&group_id=' . $row_group_id;
									$edit_url .= '&group_id=' . $row_group_id;
								}

								$approval_class = (string)($row['approval_status'] ?? 'pending');
								if (!in_array($approval_class, ['pending', 'approved', 'rejected'], true)) {
									$approval_class = 'pending';
								}
								?>
								<tr>
									<td>
										<strong><?php echo h((string)($row['customer_name'] ?? '-')); ?></strong><br>
										<span><?php echo h((string)($row['customer_province'] ?? '-')); ?></span>
									</td>
									<td>
										Phone: <?php echo h((string)($row['customer_phone'] ?? '-')); ?><br>
										Line: <?php echo h((string)($row['customer_line'] ?? '-')); ?>
									</td>
									<td>
										<?php echo h((string)($row['interested_model'] ?? '-')); ?><br>
										Target: <?php echo h((string)($row['target_purchase_date'] ?? '-')); ?>
									</td>
									<td><span class="badge"><?php echo h((string)($row['pipeline_status'] ?? '-')); ?></span></td>
									<td><span class="badge <?php echo h($approval_class); ?>"><?php echo h((string)($row['approval_status'] ?? '-')); ?></span></td>
									<td><?php echo h((string)($row['lead_source'] ?? '-')); ?></td>
									<td>
										<?php echo h(formatMoneyDisplay($row['budget_amount'] ?? 0)); ?><br>
										Down: <?php echo h(formatMoneyDisplay($row['down_payment'] ?? 0)); ?>
									</td>
									<td>
										<?php echo h(formatDateTimeDisplay($row['next_followup_at'] ?? '')); ?><br>
										Last: <?php echo h(formatDateTimeDisplay($row['last_contact_at'] ?? '')); ?>
									</td>
									<td>
										<?php echo h(trim((string)($row['owner_name'] ?? '')) !== '' ? (string)$row['owner_name'] : (string)($row['owner_user_id'] ?? '-')); ?><br>
										[<?php echo h((string)($row['owner_user_id'] ?? '-')); ?>]
									</td>
									<td>
										<?php echo h(trim((string)($row['last_actor_name'] ?? '')) !== '' ? (string)$row['last_actor_name'] : '-'); ?><br>
										[<?php echo h(trim((string)($row['last_actor_user_id'] ?? '')) !== '' ? (string)$row['last_actor_user_id'] : '-'); ?>]
									</td>
									<td>
										#<?php echo (int)$row_group_id; ?><br>
										<?php echo h((string)($row['group_name'] ?? '-')); ?>
									</td>
									<td><?php echo h(formatDateTimeDisplay($row['updated_at'] ?? '')); ?></td>
									<td>
										<div class="row-actions">
											<a class="tiny-btn" href="<?php echo h($view_url); ?>">View</a>
											<a class="tiny-btn primary" href="<?php echo h($edit_url); ?>">Edit</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="empty">No customer records found for the selected filters.</div>
			<?php endif; ?>

			<?php
			$total_pages = $total_records > 0 ? (int)ceil($total_records / $selected_per_page) : 1;
			$start_page = max(1, $selected_page - 2);
			$end_page = min($total_pages, $selected_page + 2);
			?>

			<?php if ($total_pages > 1): ?>
				<div class="pagination">
					<?php if ($selected_page > 1): ?>
						<a class="page-link" href="<?php echo h(buildListUrl($link_params, ['page' => $selected_page - 1])); ?>">Prev</a>
					<?php endif; ?>

					<?php for ($p = $start_page; $p <= $end_page; $p++): ?>
						<a class="page-link<?php echo $p === $selected_page ? ' current' : ''; ?>" href="<?php echo h(buildListUrl($link_params, ['page' => $p])); ?>">
							<?php echo (int)$p; ?>
						</a>
					<?php endfor; ?>

					<?php if ($selected_page < $total_pages): ?>
						<a class="page-link" href="<?php echo h(buildListUrl($link_params, ['page' => $selected_page + 1])); ?>">Next</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</div>
</body>
</html>
