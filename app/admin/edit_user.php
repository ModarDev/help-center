<?php
require_once '../../auth/config.php';

if (!isLoggedIn()) {
	header('Location: ../../auth/login');
	exit();
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
	header('Location: ../../auth/login');
	exit();
}

$allowed_modules = ['sales', 'purchases', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'banking-gl', 'setup'];
$current_module = isset($_POST['module'])
	? trim((string)$_POST['module'])
	: (isset($_GET['module']) ? trim((string)$_GET['module']) : 'setup');
if (!in_array($current_module, $allowed_modules, true)) {
	$current_module = 'setup';
}

$back_to_users_href = 'user.php?module=' . urlencode($current_module);
$back_to_menu_href = 'menuadmin.php?module=' . urlencode($current_module);

$role_labels = [
	'admin' => 'System Administrator',
	'manager' => 'Manager',
	'employee' => 'Employee'
];

$edit_type_labels = [
	'user_id' => 'แก้ไขชื่อผู้ใช้งานที่เข้าระบบ (User login)',
	'full_name' => 'แก้ไขชื่อ (Full Name)',
	'phone' => 'แก้ไขเบอร์โทร (Phone)',
	'email' => 'แก้ไขอีเมล์ (E-mail)',
	'user_role' => 'แก้ไขสิทธิ์การใช้งาน (Access Level)',
	'branch_access' => 'กำหนดสาขาที่เข้าใช้งานได้ (Branch Access)'
];

function loadUserByLogin(PDO $pdo, string $userId): ?array {
	$stmt = $pdo->prepare('SELECT id, user_id, first_name, last_name, phone, email, user_role, is_active FROM users WHERE user_id = ? LIMIT 1');
	$stmt->execute([$userId]);
	$user = $stmt->fetch();
	return $user ?: null;
}

function loadUserById(PDO $pdo, int $id): ?array {
	$stmt = $pdo->prepare('SELECT id, user_id, first_name, last_name, phone, email, user_role, is_active FROM users WHERE id = ? LIMIT 1');
	$stmt->execute([$id]);
	$user = $stmt->fetch();
	return $user ?: null;
}

function toFullName(array $user): string {
	return trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
}

function getOldDisplayValue(array $user, string $editType, array $roleLabels): string {
	if ($editType === 'user_id') {
		return (string)$user['user_id'];
	}
	if ($editType === 'full_name') {
		return toFullName($user);
	}
	if ($editType === 'phone') {
		return (string)$user['phone'];
	}
	if ($editType === 'email') {
		return (string)$user['email'];
	}
	if ($editType === 'user_role') {
		$role = (string)$user['user_role'];
		return $roleLabels[$role] ?? $role;
	}
	return '';
}

function formatBranchSelectionDisplay(array $branchIds, array $branchNameMap): string {
	if (empty($branchIds)) {
		return 'ทุกสาขา (ไม่จำกัด)';
	}

	$labels = [];
	foreach ($branchIds as $branchId) {
		$id = trim((string)$branchId);
		if ($id === '') {
			continue;
		}
		$name = trim((string)($branchNameMap[$id] ?? ''));
		$labels[] = $name !== '' ? ($id . ' - ' . $name) : $id;
	}

	if (empty($labels)) {
		return '-';
	}

	return implode(', ', $labels);
}

$errors = [];
$success_message = '';
$search_user_id = sanitizeInput($_POST['search_user_id'] ?? ($_GET['user_id'] ?? ''));
$selected_user = null;
$selected_edit_type = '';
$new_value = '';
$pending_change = null;
$confirmed_change = null;
$available_branches = [];
$branch_name_map = [];
$selected_user_branch_ids = [];
$new_branch_all = false;
$selected_new_branch_ids = [];
$request_started_microtime = microtime(true);
$request_started_at = date('Y-m-d H:i:s');
$audit_should_send = false;
$audit_action_label = '';
$audit_action_detail = '';

if (isset($_SESSION['pending_user_edit']) && is_array($_SESSION['pending_user_edit'])) {
	$pending_saved_at = (int)($_SESSION['pending_user_edit']['saved_at'] ?? 0);
	if ($pending_saved_at > 0 && (time() - $pending_saved_at) > 900) {
		unset($_SESSION['pending_user_edit']);
	}
}

try {
	$pdo = getDBConnection();
	$role_labels = getRoleOptions($pdo, true);
	ensureUserBranchAccessTable($pdo);
	$available_branches = getAvailableBranches($pdo);
	foreach ($available_branches as $branch_row) {
		$branch_id = trim((string)($branch_row['branch_id'] ?? ''));
		if ($branch_id === '') {
			continue;
		}
		$branch_name_map[$branch_id] = trim((string)($branch_row['company_name'] ?? ''));
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
			$errors[] = 'Invalid CSRF token';
		}

		$action = (string)($_POST['action'] ?? '');
		$target_user_id = sanitizeInput($_POST['target_user_id'] ?? $search_user_id);
		$audit_should_send = in_array($action, ['search_user', 'choose_edit_type', 'prepare_update', 'confirm_update'], true);

		if ($audit_should_send) {
			if ($action === 'search_user') {
				$audit_action_label = 'User Setup - Search User';
				$audit_action_detail = 'search_user_id=' . ($search_user_id !== '' ? $search_user_id : '-');
			} elseif ($action === 'choose_edit_type') {
				$posted_edit_type = sanitizeInput($_POST['edit_type'] ?? '');
				$posted_edit_label = $edit_type_labels[$posted_edit_type] ?? $posted_edit_type;
				$audit_action_label = 'User Setup - Choose Edit Type';
				$audit_action_detail = 'target_user_id=' . ($target_user_id !== '' ? $target_user_id : '-') . '; edit_type=' . ($posted_edit_label !== '' ? $posted_edit_label : '-');
			} elseif ($action === 'prepare_update') {
				$posted_edit_type = sanitizeInput($_POST['edit_type'] ?? '');
				$posted_edit_label = $edit_type_labels[$posted_edit_type] ?? $posted_edit_type;
				$audit_action_label = 'User Setup - Prepare Update';
				$audit_action_detail = 'target_user_id=' . ($target_user_id !== '' ? $target_user_id : '-') . '; edit_type=' . ($posted_edit_label !== '' ? $posted_edit_label : '-');
			} elseif ($action === 'confirm_update') {
				$pending_preview = $_SESSION['pending_user_edit'] ?? null;
				$pending_preview = is_array($pending_preview) ? $pending_preview : [];
				$audit_action_label = 'User Setup - Confirm Update';
				$audit_action_detail = 'target_user_id=' . ((string)($pending_preview['target_user_id'] ?? ($target_user_id !== '' ? $target_user_id : '-')));
				$audit_action_detail .= '; edit_type=' . ((string)($pending_preview['edit_type_label'] ?? '-'));
			}
		}

		if ($action === 'search_user' && empty($errors)) {
			unset($_SESSION['pending_user_edit']);
			if ($search_user_id === '') {
				$errors[] = 'กรุณากรอก User login ที่ต้องการค้นหา';
			} else {
				$selected_user = loadUserByLogin($pdo, $search_user_id);
				if (!$selected_user) {
					$errors[] = 'ไม่พบผู้ใช้งานในระบบ';
				} else {
					$audit_action_detail = 'search_user_id=' . (string)$selected_user['user_id'] . '; found=1';
				}
			}
		}

		if ($action === 'choose_edit_type' && empty($errors)) {
			$selected_edit_type = sanitizeInput($_POST['edit_type'] ?? '');
			if ($target_user_id === '') {
				$errors[] = 'ไม่พบผู้ใช้งานที่เลือก';
			} elseif (!array_key_exists($selected_edit_type, $edit_type_labels)) {
				$errors[] = 'ประเภทการแก้ไขไม่ถูกต้อง';
			} else {
				$selected_user = loadUserByLogin($pdo, $target_user_id);
				if (!$selected_user) {
					$errors[] = 'ไม่พบผู้ใช้งานในระบบ';
				} else {
					$audit_action_detail = 'target_user_id=' . (string)$selected_user['user_id'] . '; edit_type=' . (string)$edit_type_labels[$selected_edit_type];
				}
			}
		}

		if ($action === 'prepare_update' && empty($errors)) {
			$selected_edit_type = sanitizeInput($_POST['edit_type'] ?? '');
			$new_value = sanitizeInput($_POST['new_value'] ?? '');
			if ($selected_edit_type === 'user_role') {
				$new_value = sanitizeInput($_POST['new_user_role'] ?? '');
			}
			if ($selected_edit_type === 'branch_access') {
				$new_branch_all = isset($_POST['new_branch_all']) && (string)$_POST['new_branch_all'] === '1';
				$posted_branch_ids = $_POST['new_branch_ids'] ?? [];
				$posted_branch_ids = is_array($posted_branch_ids) ? $posted_branch_ids : [];
				$selected_new_branch_ids = [];
				foreach ($posted_branch_ids as $branch_id_raw) {
					$branch_id = sanitizeInput((string)$branch_id_raw);
					if ($branch_id !== '') {
						$selected_new_branch_ids[] = $branch_id;
					}
				}
				$selected_new_branch_ids = array_values(array_unique($selected_new_branch_ids));
			}

			if ($target_user_id === '') {
				$errors[] = 'ไม่พบผู้ใช้งานที่เลือก';
			} elseif (!array_key_exists($selected_edit_type, $edit_type_labels)) {
				$errors[] = 'ประเภทการแก้ไขไม่ถูกต้อง';
			}

			$selected_user = loadUserByLogin($pdo, $target_user_id);
			if (!$selected_user) {
				$errors[] = 'ไม่พบผู้ใช้งานในระบบ';
			}

			if (empty($errors)) {
				$old_raw = '';
				$old_display_value = '';
				$new_display_value = '';
				$new_value_payload = $new_value;
				if ($selected_edit_type === 'user_id') {
					$old_raw = (string)$selected_user['user_id'];
					if ($new_value === '') {
						$errors[] = 'กรุณากรอก User login ใหม่';
					} elseif ($new_value === $old_raw) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					} else {
						$ref_stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM login_logs WHERE user_id = ?) + (SELECT COUNT(*) FROM user_sessions WHERE user_id = ?) AS ref_count');
						$ref_stmt->execute([$old_raw, $old_raw]);
						$ref_count = (int)$ref_stmt->fetchColumn();
						if ($ref_count > 0) {
							$errors[] = 'ไม่สามารถแก้ไข User login ได้ เพราะมีข้อมูลประวัติการใช้งานผูกอยู่ในระบบ';
						}

						$check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id = ? AND id <> ?');
						$check_stmt->execute([$new_value, (int)$selected_user['id']]);
						if ((int)$check_stmt->fetchColumn() > 0) {
							$errors[] = 'User login ใหม่นี้มีอยู่แล้วในระบบ';
						}
					}
				}

				if ($selected_edit_type === 'full_name') {
					$old_raw = toFullName($selected_user);
					if ($new_value === '') {
						$errors[] = 'กรุณากรอก Full Name ใหม่';
					} elseif ($new_value === $old_raw) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					}
				}

				if ($selected_edit_type === 'phone') {
					$old_raw = (string)$selected_user['phone'];
					$digits = preg_replace('/\D+/', '', $new_value);
					if ($new_value === '') {
						$errors[] = 'กรุณากรอก Phone ใหม่';
					} elseif (!preg_match('/^[0-9]{10}$/', (string)$digits)) {
						$errors[] = 'รูปแบบ Phone ใหม่ไม่ถูกต้อง';
					} elseif ($new_value === $old_raw) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					}
				}

				if ($selected_edit_type === 'email') {
					$old_raw = (string)$selected_user['email'];
					if (!filter_var($new_value, FILTER_VALIDATE_EMAIL)) {
						$errors[] = 'รูปแบบ E-mail ใหม่ไม่ถูกต้อง';
					} elseif ($new_value === $old_raw) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					} else {
						$check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
						$check_stmt->execute([$new_value, (int)$selected_user['id']]);
						if ((int)$check_stmt->fetchColumn() > 0) {
							$errors[] = 'E-mail นี้ถูกใช้งานแล้ว';
						}
					}
				}

				if ($selected_edit_type === 'user_role') {
					$old_raw = (string)$selected_user['user_role'];
					if (!array_key_exists($new_value, $role_labels)) {
						$errors[] = 'Access Level ใหม่ไม่ถูกต้อง';
					} elseif ($new_value === $old_raw) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					}
				}

				if ($selected_edit_type === 'branch_access') {
					$current_branch_ids = getUserAllowedBranchIds($pdo, (string)$selected_user['user_id']);
					$old_raw = implode(',', $current_branch_ids);

					$allowed_branch_map = [];
					foreach ($available_branches as $branch_row) {
						$b_id = trim((string)($branch_row['branch_id'] ?? ''));
						if ($b_id !== '') {
							$allowed_branch_map[$b_id] = true;
						}
					}

					foreach ($selected_new_branch_ids as $selected_branch_id) {
						if (!isset($allowed_branch_map[$selected_branch_id])) {
							$errors[] = 'มี Branch ID ที่เลือกไม่ถูกต้อง';
							break;
						}
					}

					if (!$new_branch_all && empty($selected_new_branch_ids)) {
						$errors[] = 'กรุณาเลือกอย่างน้อย 1 สาขา หรือเลือกแบบไม่จำกัดสาขา';
					}

					$old_all = empty($current_branch_ids);
					$new_compare_ids = $selected_new_branch_ids;
					sort($current_branch_ids);
					sort($new_compare_ids);

					if ($old_all === $new_branch_all && $current_branch_ids === $new_compare_ids) {
						$errors[] = 'ข้อมูลใหม่ต้องไม่ซ้ำกับข้อมูลเดิม';
					}

					$old_display_value = formatBranchSelectionDisplay(getUserAllowedBranchIds($pdo, (string)$selected_user['user_id']), $branch_name_map);
					$new_display_value = $new_branch_all
						? 'ทุกสาขา (ไม่จำกัด)'
						: formatBranchSelectionDisplay($selected_new_branch_ids, $branch_name_map);

					$new_value_payload = json_encode([
						'all' => $new_branch_all,
						'branch_ids' => $new_branch_all ? [] : $selected_new_branch_ids
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}

				if (empty($errors)) {
					if ($old_display_value === '') {
						$old_display_value = getOldDisplayValue($selected_user, $selected_edit_type, $role_labels);
					}
					if ($new_display_value === '') {
						$new_display_value = $selected_edit_type === 'user_role' ? ($role_labels[$new_value] ?? $new_value) : $new_value;
					}
					if ($new_value_payload === null || $new_value_payload === false) {
						$new_value_payload = '';
					}

					$pending_change = [
						'target_id' => (int)$selected_user['id'],
						'target_user_id' => (string)$selected_user['user_id'],
						'edit_type' => $selected_edit_type,
						'edit_type_label' => $edit_type_labels[$selected_edit_type],
						'old_value_raw' => $old_raw,
						'new_value_raw' => (string)$new_value_payload,
						'old_value_display' => $old_display_value,
						'new_value_display' => $new_display_value,
						'saved_at' => time()
					];

					$_SESSION['pending_user_edit'] = $pending_change;
					$success_message = 'บันทึกข้อมูลที่ต้องการแก้ไขแล้ว กรุณาตรวจสอบและยืนยันการแก้ไข';
					$audit_action_detail = 'target_user_id=' . (string)$pending_change['target_user_id']
						. '; edit_type=' . (string)$pending_change['edit_type_label']
						. '; old=' . (string)$pending_change['old_value_display']
						. '; new=' . (string)$pending_change['new_value_display'];
				}
			}
		}

		if ($action === 'confirm_update' && empty($errors)) {
			$admin_password = (string)($_POST['admin_password'] ?? '');
			$pending_change = $_SESSION['pending_user_edit'] ?? null;

			if (!is_array($pending_change)) {
				$errors[] = 'ไม่พบรายการแก้ไขที่รอยืนยัน กรุณากรอกข้อมูลใหม่อีกครั้ง';
			} elseif ($admin_password === '') {
				$errors[] = 'กรุณากรอกรหัสผ่านเจ้าหน้าที่ (Admin)';
			} else {
				$admin_user_id = (string)($_SESSION['user_id'] ?? '');
				$admin_stmt = $pdo->prepare('SELECT id, user_role, password_hash, is_active FROM users WHERE user_id = ? LIMIT 1');
				$admin_stmt->execute([$admin_user_id]);
				$admin_row = $admin_stmt->fetch();

				if (!$admin_row || (string)$admin_row['user_role'] !== 'admin' || (int)$admin_row['is_active'] !== 1) {
					$errors[] = 'บัญชีเจ้าหน้าที่ไม่ถูกต้องหรือไม่มีสิทธิ์ยืนยัน';
				} elseif (!password_verify($admin_password, (string)$admin_row['password_hash'])) {
					$errors[] = 'รหัสผ่านเจ้าหน้าที่ไม่ถูกต้อง';
				}
			}

			if (empty($errors)) {
				$target_id = (int)$pending_change['target_id'];
				$target_user = loadUserById($pdo, $target_id);
				if (!$target_user) {
					$errors[] = 'ไม่พบผู้ใช้งานสำหรับยืนยันการแก้ไข';
				} else {
					$edit_type = (string)$pending_change['edit_type'];
					$new_raw = (string)$pending_change['new_value_raw'];

					$pdo->beginTransaction();
					try {
						if ($edit_type === 'user_id') {
							$old_login = (string)$target_user['user_id'];
							$ref_stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM login_logs WHERE user_id = ?) + (SELECT COUNT(*) FROM user_sessions WHERE user_id = ?) AS ref_count');
							$ref_stmt->execute([$old_login, $old_login]);
							if ((int)$ref_stmt->fetchColumn() > 0) {
								throw new RuntimeException('ไม่สามารถแก้ไข User login ได้ เพราะมีข้อมูลประวัติการใช้งานผูกอยู่ในระบบ');
							}

							$check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id = ? AND id <> ?');
							$check_stmt->execute([$new_raw, $target_id]);
							if ((int)$check_stmt->fetchColumn() > 0) {
								throw new RuntimeException('User login ใหม่นี้มีอยู่แล้วในระบบ');
							}
							$update_stmt = $pdo->prepare('UPDATE users SET user_id = ? WHERE id = ?');
							$update_stmt->execute([$new_raw, $target_id]);
						}

						if ($edit_type === 'full_name') {
							$name_parts = preg_split('/\s+/', trim($new_raw), 2);
							$first_name = $name_parts[0] ?? '';
							$last_name = $name_parts[1] ?? '-';
							$update_stmt = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
							$update_stmt->execute([$first_name, $last_name, $target_id]);
						}

						if ($edit_type === 'phone') {
							$update_stmt = $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?');
							$update_stmt->execute([$new_raw, $target_id]);
						}

						if ($edit_type === 'email') {
							$check_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
							$check_stmt->execute([$new_raw, $target_id]);
							if ((int)$check_stmt->fetchColumn() > 0) {
								throw new RuntimeException('E-mail นี้ถูกใช้งานแล้ว');
							}
							$update_stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
							$update_stmt->execute([$new_raw, $target_id]);
						}

						if ($edit_type === 'user_role') {
							$update_stmt = $pdo->prepare('UPDATE users SET user_role = ? WHERE id = ?');
							$update_stmt->execute([$new_raw, $target_id]);
						}

						if ($edit_type === 'branch_access') {
							$decoded = json_decode($new_raw, true);
							$decoded = is_array($decoded) ? $decoded : [];
							$set_all = !empty($decoded['all']);
							$branch_ids = isset($decoded['branch_ids']) && is_array($decoded['branch_ids']) ? $decoded['branch_ids'] : [];
							$branch_ids = array_values(array_unique(array_map('strval', $branch_ids)));

							if ($set_all) {
								$branch_ids = [];
							}

							$valid_branch_map = [];
							foreach ($available_branches as $branch_row) {
								$branch_id = trim((string)($branch_row['branch_id'] ?? ''));
								if ($branch_id !== '') {
									$valid_branch_map[$branch_id] = true;
								}
							}

							foreach ($branch_ids as $branch_id) {
								if (!isset($valid_branch_map[$branch_id])) {
									throw new RuntimeException('มี Branch ID ที่เลือกไม่ถูกต้อง');
								}
							}

							setUserAllowedBranchIds($pdo, (string)$target_user['user_id'], $branch_ids);
						}

						$pdo->commit();
						$confirmed_change = $pending_change;
						$success_message = 'ยืนยันการแก้ไขข้อมูลสำเร็จ';
						unset($_SESSION['pending_user_edit']);

						$selected_user = loadUserById($pdo, $target_id);
						$search_user_id = $selected_user ? (string)$selected_user['user_id'] : '';
						$selected_edit_type = '';
						$new_value = '';
						$pending_change = null;
						$audit_action_detail = 'target_user_id=' . (string)($confirmed_change['target_user_id'] ?? '-')
							. '; edit_type=' . (string)($confirmed_change['edit_type_label'] ?? '-')
							. '; old=' . (string)($confirmed_change['old_value_display'] ?? '-')
							. '; new=' . (string)($confirmed_change['new_value_display'] ?? '-');
					} catch (Throwable $t) {
						$pdo->rollBack();
						$errors[] = 'ไม่สามารถยืนยันการแก้ไขได้: ' . $t->getMessage();
					}
				}
			}
		}

		if ($audit_should_send) {
			try {
				$completed_at = date('Y-m-d H:i:s');
				$duration_seconds = microtime(true) - $request_started_microtime;
				$status = empty($errors) ? 'Success' : 'Failed';

				if (!empty($errors)) {
					$first_error = trim((string)$errors[0]);
					if ($first_error !== '') {
						$audit_action_detail .= ($audit_action_detail !== '' ? '; ' : '') . 'reason=' . $first_error;
					}
				}

				$actor_name = trim((string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? ''));
				if ($actor_name === '') {
					$actor_name = (string)($_SESSION['user_id'] ?? '-');
				}

				$action_text = $audit_action_label !== '' ? $audit_action_label : 'User Setup Action';
				if ($audit_action_detail !== '') {
					$action_text .= ' | ' . $audit_action_detail;
				}

				sendAccessAuditToDiscord($pdo, 'user_setup', [
					'name' => $actor_name,
					'role' => (string)($_SESSION['user_role'] ?? '-'),
					'started_at' => $request_started_at,
					'completed_at' => $completed_at,
					'duration_seconds' => $duration_seconds,
					'device_type' => getDeviceTypeFromUserAgent($_SERVER['HTTP_USER_AGENT'] ?? ''),
					'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
					'action' => $action_text,
					'status' => $status
				]);
			} catch (Throwable $e) {
				error_log('User setup Discord webhook error: ' . $e->getMessage());
			}
		}
	}

	if ($selected_user === null && $search_user_id !== '' && empty($errors)) {
		$selected_user = loadUserByLogin($pdo, $search_user_id);
	}

	if ($selected_user && $selected_edit_type === '' && isset($_POST['edit_type']) && array_key_exists((string)$_POST['edit_type'], $edit_type_labels)) {
		$selected_edit_type = (string)$_POST['edit_type'];
	}

	if ($selected_user) {
		$selected_user_branch_ids = getUserAllowedBranchIds($pdo, (string)$selected_user['user_id']);
	}

	if ($selected_edit_type === 'branch_access') {
		if (empty($selected_new_branch_ids)) {
			$selected_new_branch_ids = $selected_user_branch_ids;
		}
		if (!isset($_POST['new_branch_all'])) {
			$new_branch_all = empty($selected_user_branch_ids);
		}
	}

	if ($pending_change === null && isset($_SESSION['pending_user_edit']) && is_array($_SESSION['pending_user_edit'])) {
		$pending_change = $_SESSION['pending_user_edit'];
	}

	if ($pending_change && (string)($pending_change['edit_type'] ?? '') === 'branch_access') {
		$pending_branch = json_decode((string)($pending_change['new_value_raw'] ?? ''), true);
		if (is_array($pending_branch)) {
			$new_branch_all = !empty($pending_branch['all']);
			$selected_new_branch_ids = isset($pending_branch['branch_ids']) && is_array($pending_branch['branch_ids'])
				? array_values(array_unique(array_map('strval', $pending_branch['branch_ids'])))
				: [];
		}
	}
} catch (PDOException $e) {
	error_log('Database error in app/admin/edit_user.php: ' . $e->getMessage());
	$errors[] = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Edit User - Office Plus</title>
	<link rel="stylesheet" href="assets/css/temp.css">
	<style>
		body {
			background: #ffffff;
			margin: 0;
			padding: 0;
			font-family: Verdana, Arial, Helvetica, sans-serif;
		}

		.page-wrap {
			max-width: 1050px;
			margin: 0 auto;
			padding: 10px 8px 20px;
		}

		.page-title {
			font-size: 28px;
			font-weight: bold;
			margin: 0 0 10px;
		}

		.form-box,
		.ref-table,
		.summary-table {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 10px;
		}

		.form-box td,
		.ref-table th,
		.ref-table td,
		.summary-table th,
		.summary-table td {
			border: 1px solid #8cacbb;
			padding: 6px 8px;
			font-size: 14px;
		}

		.ref-table th,
		.summary-table th {
			background: #c7d4de;
			text-align: left;
		}

		.form-label {
			width: 230px;
			background: #f5f9fc;
			font-weight: bold;
		}

		input[type="text"],
		input[type="email"],
		input[type="password"],
		select {
			width: 100%;
			max-width: 420px;
			height: 32px;
			padding: 4px 6px;
			border: 1px solid #9aa9b4;
			font-size: 14px;
			box-sizing: border-box;
		}

		.msg-error,
		.msg-success {
			border: 1px solid;
			padding: 8px 10px;
			margin-bottom: 10px;
		}

		.msg-error {
			border-color: #cc3300;
			background: #ffefea;
			color: #a02b00;
		}

		.msg-success {
			border-color: #2f8f2f;
			background: #eaffe9;
			color: #226622;
		}

		.msg-error ul {
			margin: 6px 0 0 18px;
		}

		.action-row {
			margin: 10px 0 14px;
		}

		.action-row button,
		.action-row a {
			font-size: 14px;
		}

		.action-row button {
			padding: 4px 14px;
			border: 1px solid #8cacbb;
			background: #dee7ec;
			cursor: pointer;
		}

		.action-row a {
			display: inline-block;
			margin-left: 10px;
		}

		.block-title {
			font-size: 17px;
			font-weight: bold;
			margin: 14px 0 8px;
		}

		.readonly-value {
			background: #f4f4f4;
		}

		@media (max-width: 900px) {
			.form-box,
			.ref-table,
			.summary-table {
				display: block;
				overflow-x: auto;
			}
		}
	</style>
</head>
<body>
	<div class="page-wrap">
		<div class="page-title">Edit User</div>

		<?php if (!empty($errors)): ?>
			<div class="msg-error">
				<strong>ไม่สามารถดำเนินการได้</strong>
				<ul>
					<?php foreach ($errors as $error): ?>
						<li><?php echo htmlspecialchars((string)$error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ($success_message !== ''): ?>
			<div class="msg-success"><?php echo htmlspecialchars($success_message); ?></div>
		<?php endif; ?>

		<div class="block-title">1) ค้นหาผู้ใช้งาน</div>
		<form method="post" autocomplete="off">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
			<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
			<input type="hidden" name="action" value="search_user">
			<table class="form-box" cellspacing="0" cellpadding="0">
				<tr>
					<td class="form-label"><label for="search_user_id">User login ที่ต้องการแก้ไข</label></td>
					<td>
						<input type="text" id="search_user_id" name="search_user_id" value="<?php echo htmlspecialchars($search_user_id); ?>" required>
					</td>
				</tr>
			</table>
			<div class="action-row">
				<button type="submit">ถัดไป</button>
			</div>
		</form>

		<?php if ($selected_user): ?>
			<div class="block-title">2) ข้อมูลเดิม (สำหรับอ้างอิง)</div>
			<table class="ref-table" cellspacing="0" cellpadding="0">
				<tr>
					<th>User login</th>
					<th>Full Name</th>
					<th>Phone</th>
					<th>E-mail</th>
					<th>Access Level</th>
					<th>Branch Access</th>
				</tr>
				<tr>
					<td><?php echo htmlspecialchars((string)$selected_user['user_id']); ?></td>
					<td><?php echo htmlspecialchars(toFullName($selected_user)); ?></td>
					<td><?php echo htmlspecialchars((string)$selected_user['phone']); ?></td>
					<td><?php echo htmlspecialchars((string)$selected_user['email']); ?></td>
					<td><?php echo htmlspecialchars($role_labels[(string)$selected_user['user_role']] ?? (string)$selected_user['user_role']); ?></td>
					<td><?php echo htmlspecialchars(formatBranchSelectionDisplay($selected_user_branch_ids, $branch_name_map)); ?></td>
				</tr>
			</table>

			<div class="block-title">3) เลือกประเภทการแก้ไข</div>
			<form method="post" autocomplete="off">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
				<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
				<input type="hidden" name="action" value="choose_edit_type">
				<input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars((string)$selected_user['user_id']); ?>">
				<table class="form-box" cellspacing="0" cellpadding="0">
					<tr>
						<td class="form-label"><label for="edit_type">ประเภทการแก้ไข</label></td>
						<td>
							<select id="edit_type" name="edit_type" required>
								<option value="">เลือกประเภท</option>
								<?php foreach ($edit_type_labels as $type_key => $type_label): ?>
									<option value="<?php echo htmlspecialchars($type_key); ?>"<?php echo $selected_edit_type === $type_key ? ' selected' : ''; ?>><?php echo htmlspecialchars($type_label); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<div class="action-row">
					<button type="submit">ถัดไป</button>
				</div>
			</form>
		<?php endif; ?>

		<?php if ($selected_user && $selected_edit_type !== '' && array_key_exists($selected_edit_type, $edit_type_labels)): ?>
			<div class="block-title">4) กรอกข้อมูลใหม่</div>
			<form method="post" autocomplete="off">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
				<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
				<input type="hidden" name="action" value="prepare_update">
				<input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars((string)$selected_user['user_id']); ?>">
				<input type="hidden" name="edit_type" value="<?php echo htmlspecialchars($selected_edit_type); ?>">
				<table class="form-box" cellspacing="0" cellpadding="0">
					<tr>
						<td class="form-label">รายการที่แก้ไข</td>
						<td><?php echo htmlspecialchars($edit_type_labels[$selected_edit_type]); ?></td>
					</tr>
					<tr>
						<td class="form-label">ข้อมูลเดิม</td>
						<td>
							<?php if ($selected_edit_type === 'branch_access'): ?>
								<input class="readonly-value" type="text" value="<?php echo htmlspecialchars(formatBranchSelectionDisplay($selected_user_branch_ids, $branch_name_map)); ?>" readonly>
							<?php else: ?>
								<input class="readonly-value" type="text" value="<?php echo htmlspecialchars(getOldDisplayValue($selected_user, $selected_edit_type, $role_labels)); ?>" readonly>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td class="form-label">ข้อมูลใหม่</td>
						<td>
							<?php if ($selected_edit_type === 'user_role'): ?>
								<select name="new_user_role" required>
									<option value="">เลือกสิทธิ์ใหม่</option>
									<?php foreach ($role_labels as $role_key => $role_name): ?>
										<option value="<?php echo htmlspecialchars($role_key); ?>"<?php echo $new_value === $role_key ? ' selected' : ''; ?>><?php echo htmlspecialchars($role_name); ?></option>
									<?php endforeach; ?>
								</select>
							<?php elseif ($selected_edit_type === 'branch_access'): ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox" name="new_branch_all" value="1"<?php echo $new_branch_all ? ' checked' : ''; ?>>
									ไม่จำกัดสาขา (เข้าใช้งานได้ทุก Branch ID)
								</label>
								<?php if (empty($available_branches)): ?>
									<div style="color:#9c2a00;">ยังไม่มีข้อมูลสาขาในระบบ</div>
								<?php else: ?>
									<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:6px;max-height:220px;overflow:auto;border:1px solid #cdd7df;padding:8px;background:#fff;">
										<?php foreach ($available_branches as $branch_row): ?>
											<?php $branch_id = trim((string)($branch_row['branch_id'] ?? '')); ?>
											<?php if ($branch_id === '') { continue; } ?>
											<label style="display:flex;align-items:flex-start;gap:6px;">
												<input type="checkbox" name="new_branch_ids[]" value="<?php echo htmlspecialchars($branch_id); ?>"<?php echo in_array($branch_id, $selected_new_branch_ids, true) ? ' checked' : ''; ?>>
												<span>
													<strong><?php echo htmlspecialchars($branch_id); ?></strong>
													<?php if (!empty($branch_row['company_name'])): ?>
														- <?php echo htmlspecialchars((string)$branch_row['company_name']); ?>
													<?php endif; ?>
												</span>
											</label>
										<?php endforeach; ?>
									</div>
									<div style="font-size:12px;color:#3f5c78;margin-top:6px;">ติ๊กได้หลายสาขา หรือเลือกไม่จำกัดสาขา</div>
								<?php endif; ?>
							<?php elseif ($selected_edit_type === 'email'): ?>
								<input type="email" name="new_value" value="<?php echo htmlspecialchars($new_value); ?>" required>
							<?php else: ?>
								<input type="text" name="new_value" value="<?php echo htmlspecialchars($new_value); ?>" required>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<div class="action-row">
					<button type="submit">บันทึกข้อมูล</button>
				</div>
			</form>
		<?php endif; ?>

		<?php if ($pending_change): ?>
			<div class="block-title">ข้อมูลที่ทำการเปลี่ยน</div>
			<table class="summary-table" cellspacing="0" cellpadding="0">
				<tr>
					<th>User login</th>
					<th>ประเภทการแก้ไข</th>
					<th>ข้อมูลเดิม</th>
					<th>ข้อมูลใหม่</th>
				</tr>
				<tr>
					<td><?php echo htmlspecialchars((string)$pending_change['target_user_id']); ?></td>
					<td><?php echo htmlspecialchars((string)$pending_change['edit_type_label']); ?></td>
					<td><?php echo htmlspecialchars((string)$pending_change['old_value_display']); ?></td>
					<td><?php echo htmlspecialchars((string)$pending_change['new_value_display']); ?></td>
				</tr>
			</table>

			<form method="post" autocomplete="off">
				<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
				<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
				<input type="hidden" name="action" value="confirm_update">
				<table class="form-box" cellspacing="0" cellpadding="0">
					<tr>
						<td class="form-label"><label for="admin_password">รหัสผ่านเจ้าหน้าที่ (Admin)</label></td>
						<td><input type="password" id="admin_password" name="admin_password" required></td>
					</tr>
				</table>
				<div class="action-row">
					<button type="submit">ยืนยันการแก้ไข</button>
				</div>
			</form>
		<?php endif; ?>

		<?php if ($confirmed_change): ?>
			<div class="block-title">ผลการแก้ไขล่าสุด</div>
			<table class="summary-table" cellspacing="0" cellpadding="0">
				<tr>
					<th>ประเภทการแก้ไข</th>
					<th>ข้อมูลเดิม</th>
					<th>ข้อมูลใหม่</th>
				</tr>
				<tr>
					<td><?php echo htmlspecialchars((string)$confirmed_change['edit_type_label']); ?></td>
					<td><?php echo htmlspecialchars((string)$confirmed_change['old_value_display']); ?></td>
					<td><?php echo htmlspecialchars((string)$confirmed_change['new_value_display']); ?></td>
				</tr>
			</table>
		<?php endif; ?>
	</div>
</body>
</html>
