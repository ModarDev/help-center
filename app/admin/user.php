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

enforceCurrentUserDashboardMenuAccess('users', ['top_nav', 'footer']);

$allowed_modules = ['sales', 'purchases', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'banking-gl', 'setup'];
$current_module = isset($_GET['module']) ? trim((string)$_GET['module']) : 'setup';
if (!in_array($current_module, $allowed_modules, true)) {
	$current_module = 'setup';
}
$menu_back_href = 'menuadmin.php?module=' . urlencode($current_module);

$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';

function normalizeRoleKey(string $value): string {
	$key = strtolower(trim($value));
	$key = preg_replace('/[^a-z0-9_-]+/', '-', $key) ?? '';
	$key = trim($key, '-_');
	return substr($key, 0, 50);
}

$form = [
	'user_id' => '',
	'full_name' => '',
	'phone' => '',
	'email' => '',
	'user_role' => '',
	'position' => 'Staff',
	'department' => 'General',
	'company' => 'Office Plus',
	'is_active' => '1'
];

$role_form = [
	'role_key' => '',
	'role_name' => '',
	'dashboard_path' => '../app/employee/menuemployee',
	'is_active' => '1'
];

$errors = [];
$success_message = '';
$users = [];

$role_labels = [
	'admin' => 'System Administrator',
	'manager' => 'Manager',
	'employee' => 'Employee'
];

try {
	$pdo = getDBConnection();
	$role_labels = getRoleOptions($pdo, true);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
			$errors[] = 'Invalid CSRF token';
		}

		$action = (string)($_POST['action'] ?? 'add_user');

		if ($action === 'add_role') {
			$role_form['role_key'] = trim((string)($_POST['role_key'] ?? ''));
			$role_form['role_name'] = trim((string)($_POST['role_name'] ?? ''));
			$role_form['dashboard_path'] = trim((string)($_POST['dashboard_path'] ?? $role_form['dashboard_path']));
			$role_form['is_active'] = isset($_POST['role_is_active']) ? '1' : '0';

			$normalized_role_key = normalizeRoleKey($role_form['role_key']);
			if ($normalized_role_key === '') {
				$errors[] = 'กรุณากรอก Role Key โดยใช้ a-z, 0-9, _ หรือ -';
			}
			if ($role_form['role_name'] === '') {
				$errors[] = 'กรุณากรอกชื่อ Role';
			}
			if ($role_form['dashboard_path'] === '') {
				$errors[] = 'กรุณากรอก Dashboard Path';
			} elseif (!preg_match('#^\.\./app/[a-zA-Z0-9/_-]+$#', $role_form['dashboard_path'])) {
				$errors[] = 'Dashboard Path ต้องขึ้นต้นด้วย ../app/';
			}

			if (empty($errors)) {
				if (!hasRolesTable($pdo)) {
					$errors[] = 'ยังไม่พบตาราง roles กรุณารัน SQL โครงสร้างล่าสุดก่อน';
				} else {
					$check_key_stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE role_key = ?');
					$check_key_stmt->execute([$normalized_role_key]);
					if ((int)$check_key_stmt->fetchColumn() > 0) {
						$errors[] = 'Role Key นี้มีอยู่ในระบบแล้ว';
					}

					$check_name_stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE role_name = ?');
					$check_name_stmt->execute([$role_form['role_name']]);
					if ((int)$check_name_stmt->fetchColumn() > 0) {
						$errors[] = 'ชื่อ Role นี้มีอยู่ในระบบแล้ว';
					}

					if (empty($errors)) {
						$insert_role_stmt = $pdo->prepare(
							'INSERT INTO roles (role_key, role_name, dashboard_path, is_active) VALUES (?, ?, ?, ?)'
						);
						$insert_role_stmt->execute([
							$normalized_role_key,
							$role_form['role_name'],
							$role_form['dashboard_path'],
							$role_form['is_active'] === '1' ? 1 : 0
						]);

						$role_labels = getRoleOptions($pdo, true);
						$success_message = 'เพิ่ม Role ใหม่สำเร็จ';
						$role_form = [
							'role_key' => '',
							'role_name' => '',
							'dashboard_path' => '../app/employee/menuemployee',
							'is_active' => '1'
						];
					}
				}
			}
		} else {
			foreach ($form as $field => $default_value) {
				$form[$field] = sanitizeInput($_POST[$field] ?? $default_value);
			}

			$password = $_POST['password'] ?? '';
			$confirm_password = $_POST['confirm_password'] ?? '';

			if ($form['user_id'] === '') {
				$errors[] = 'กรุณากรอก User Login';
			}

			if ($form['full_name'] === '') {
				$errors[] = 'กรุณากรอก Full Name';
			}

			if ($form['phone'] !== '' && !preg_match('/^[0-9]{10}$/', preg_replace('/\D/', '', $form['phone']))) {
				$errors[] = 'รูปแบบ Telephone No. ไม่ถูกต้อง';
			}

			if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
				$errors[] = 'รูปแบบ Email Address ไม่ถูกต้อง';
			}

			if ($form['user_role'] === '' || !array_key_exists($form['user_role'], $role_labels)) {
				$errors[] = 'Access Level ไม่ถูกต้อง';
			}

			if ($password === '') {
				$errors[] = 'กรุณากรอก Password';
			}

			if ($password !== $confirm_password) {
				$errors[] = 'Password และ Confirm Password ไม่ตรงกัน';
			}

			if (strlen($password) > 0 && strlen($password) < 6) {
				$errors[] = 'Password ต้องมีอย่างน้อย 6 ตัวอักษร';
			}

			$name_parts = preg_split('/\s+/', trim($form['full_name']), 2);
			$first_name = $name_parts[0] ?? '';
			$last_name = $name_parts[1] ?? '-';

			if (empty($errors)) {
				$check_user = $pdo->prepare('SELECT COUNT(*) FROM users WHERE user_id = ?');
				$check_user->execute([$form['user_id']]);
				if ((int)$check_user->fetchColumn() > 0) {
					$errors[] = 'User Login นี้มีอยู่ในระบบแล้ว';
				}

				$check_email = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
				$check_email->execute([$form['email']]);
				if ((int)$check_email->fetchColumn() > 0) {
					$errors[] = 'Email นี้มีอยู่ในระบบแล้ว';
				}

				if (empty($errors)) {
					$password_hash = password_hash($password, PASSWORD_DEFAULT);
					$is_active = $form['is_active'] === '1' ? 1 : 0;

					$insert_stmt = $pdo->prepare(
						'INSERT INTO users (user_id, first_name, last_name, phone, email, position, department, company, user_role, password_hash, is_active)
						 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
					);

					$insert_stmt->execute([
						$form['user_id'],
						$first_name,
						$last_name,
						$form['phone'],
						$form['email'],
						$form['position'],
						$form['department'],
						$form['company'],
						$form['user_role'],
						$password_hash,
						$is_active
					]);

					$success_message = 'เพิ่มผู้ใช้งานสำเร็จ';
					foreach ($form as $field => $default_value) {
						$form[$field] = $field === 'is_active' ? '1' : '';
					}
					$form['position'] = 'Staff';
					$form['department'] = 'General';
					$form['company'] = 'Office Plus';
				}
			}
		}
	}

	$sql = "SELECT u.user_id,
				   u.first_name,
				   u.last_name,
				   u.phone,
				   u.email,
				   u.user_role,
				   r.role_name,
				   u.is_active,
				   MAX(CASE WHEN l.login_status = 'success' THEN l.login_time END) AS last_visit
			FROM users u
			LEFT JOIN roles r ON r.role_key = u.user_role
			LEFT JOIN login_logs l ON l.user_id = u.user_id";

	if (!$show_inactive) {
		$sql .= " WHERE u.is_active = 1";
	}

	$sql .= " GROUP BY u.id, u.user_id, u.first_name, u.last_name, u.phone, u.email, u.user_role, r.role_name, u.is_active
			   ORDER BY u.id DESC";

	$stmt = $pdo->query($sql);
	$users = $stmt->fetchAll();

	if (hasRolesTable($pdo)) {
		$role_stmt = $pdo->query('SELECT role_key, role_name, dashboard_path, is_active, updated_at FROM roles ORDER BY role_name ASC');
		$roles = $role_stmt ? $role_stmt->fetchAll() : [];
	} else {
		foreach ($role_labels as $role_key => $role_name) {
			$roles[] = [
				'role_key' => $role_key,
				'role_name' => $role_name,
				'dashboard_path' => getDefaultDashboardByRole($role_key),
				'is_active' => 1,
				'updated_at' => null
			];
		}
	}
}
catch (PDOException $e) {
	error_log('Database error loading users list in app/admin/user.php: ' . $e->getMessage());
	$errors[] = 'ไม่สามารถโหลดรายการผู้ใช้งานได้';
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Users - Office Plus</title>
	<link rel="stylesheet" href="assets/css/temp.css">
	<style>
		body {
			background: #ffffff;
			margin: 0;
			padding: 0;
			font-family: Verdana, Arial, Helvetica, sans-serif;
		}

		.page-wrap {
			background: #ffffff;
			padding: 0 10px 16px;
		}

		.page-content {
			max-width: 1360px;
			margin: 0 auto;
			display: flex;
			gap: 14px;
			align-items: flex-start;
		}

		.form-panel {
			flex: 0 0 430px;
		}

		.list-panel {
			flex: 1 1 auto;
			min-width: 0;
		}

		.page-heading {
			font-size: 32px;
			font-weight: bold;
			margin: 6px 0 8px;
		}

		.user-table,
		.form-table {
			border-collapse: collapse;
			width: 100%;
			max-width: none;
			margin: 0;
			background: #ffffff;
		}

		.user-table th,
		.user-table td,
		.form-table th,
		.form-table td {
			border: 1px solid #8cacbb;
			padding: 4px 6px;
			font-size: 14px;
		}

		.user-table th {
			background: #c7d4de;
			font-weight: bold;
		}

		.form-table {
			max-width: none;
		}

		.form-table th {
			width: 210px;
			text-align: left;
			background: #ffffff;
			font-weight: normal;
		}

		.form-table input[type="text"],
		.form-table input[type="password"],
		.form-table input[type="email"],
		.form-table select {
			width: 100%;
			height: 30px;
			padding: 4px 6px;
			font-size: 14px;
			border: 1px solid #9aa9b4;
			background: #fff;
			box-sizing: border-box;
		}

		.form-table input[type="checkbox"] {
			width: auto;
			height: auto;
			padding: 0;
			margin-right: 6px;
			vertical-align: middle;
		}

		.form-table label {
			display: inline-flex;
			align-items: center;
		}

		.filter-row td {
			background: #ffffff;
		}

		.action-cell {
			text-align: center;
		}

		.msg-error,
		.msg-success {
			max-width: 1360px;
			margin: 8px auto;
			border: 1px solid;
			padding: 8px 10px;
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

		.form-actions {
			text-align: center;
			margin-top: 12px;
		}

		.form-actions button {
			font-size: 14px;
			padding: 4px 14px;
			border: 1px solid #8cacbb;
			background: #dee7ec;
			cursor: pointer;
		}

		.form-actions a {
			display: inline-block;
			margin-top: 6px;
		}

		.section-title {
			font-size: 18px;
			font-weight: bold;
			margin: 0 0 8px;
		}

		.hint-text {
			font-size: 12px;
			color: #586773;
			margin: 6px 0 0;
		}

		.panel-gap {
			margin-top: 18px;
		}

		.user-table-wrap {
			overflow-x: auto;
		}

		@media (max-width: 900px) {
			.page-content {
				flex-direction: column;
			}

			.form-panel,
			.list-panel {
				flex: 1 1 auto;
				width: 100%;
			}

			.user-table,
			.form-table {
				display: table;
				min-width: 720px;
			}

			.form-panel {
				overflow-x: auto;
			}

			.form-table th,
			.form-table td {
				white-space: nowrap;
			}
		}
	</style>
</head>
<body>
	<div class="page-wrap">
<br>
		<?php if (!empty($errors)): ?>
			<div class="msg-error">
				<strong>ไม่สามารถบันทึกข้อมูลได้</strong>
				<ul>
					<?php foreach ($errors as $error): ?>
						<li><?php echo htmlspecialchars($error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ($success_message !== ''): ?>
			<div class="msg-success"><?php echo htmlspecialchars($success_message); ?></div>
		<?php endif; ?>

		<div class="page-content">
			<div class="form-panel">
				<div class="section-title">เพิ่มผู้ใช้งาน</div>
				<form method="post" autocomplete="off">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
					<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
					<input type="hidden" name="action" value="add_user">

					<table class="form-table" cellspacing="0" cellpadding="0">
						<tr>
							<th><label for="user_id">User Login:</label></th>
							<td><input type="text" id="user_id" name="user_id" value="<?php echo htmlspecialchars($form['user_id']); ?>" required></td>
						</tr>
						<tr>
							<th><label for="password">Password:</label></th>
							<td><input type="password" id="password" name="password" minlength="6" required></td>
						</tr>
						<tr>
							<th><label for="confirm_password">Confirm Password:</label></th>
							<td><input type="password" id="confirm_password" name="confirm_password" minlength="6" required></td>
						</tr>
						<tr>
							<th><label for="full_name">Full Name:</label></th>
							<td><input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($form['full_name']); ?>" required></td>
						</tr>
						<tr>
							<th><label for="phone">Telephone No.:</label></th>
							<td><input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($form['phone']); ?>" required></td>
						</tr>
						<tr>
							<th><label for="email">Email Address:</label></th>
							<td><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form['email']); ?>" required></td>
						</tr>
						<tr>
							<th><label for="user_role">Access Level:</label></th>
							<td>
								<select id="user_role" name="user_role" required>
									<option value="">เลือกสิทธิ์</option>
									<?php foreach ($role_labels as $role_key => $role_label): ?>
										<option value="<?php echo htmlspecialchars((string)$role_key); ?>"<?php echo $form['user_role'] === $role_key ? ' selected' : ''; ?>><?php echo htmlspecialchars((string)$role_label); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Inactive:</th>
							<td>
								<label>
									<input type="checkbox" name="is_active" value="1"<?php echo $form['is_active'] === '1' ? ' checked' : ''; ?>>
									Active
								</label>
							</td>
						</tr>
					</table>

					<input type="hidden" name="position" value="<?php echo htmlspecialchars($form['position']); ?>">
					<input type="hidden" name="department" value="<?php echo htmlspecialchars($form['department']); ?>">
					<input type="hidden" name="company" value="<?php echo htmlspecialchars($form['company']); ?>">

					<div class="form-actions">
						<button type="submit">Add new</button><br>
					</div>
				</form>

				<div class="panel-gap"></div>
				<div class="section-title">เพิ่ม Role ใหม่</div>
				<form method="post" autocomplete="off">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
					<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
					<input type="hidden" name="action" value="add_role">

					<table class="form-table" cellspacing="0" cellpadding="0">
						<tr>
							<th><label for="role_key">Role Key:</label></th>
							<td><input type="text" id="role_key" name="role_key" value="<?php echo htmlspecialchars($role_form['role_key']); ?>" placeholder="เช่น supervisor" required></td>
						</tr>
						<tr>
							<th><label for="role_name">Role Name:</label></th>
							<td><input type="text" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role_form['role_name']); ?>" placeholder="เช่น Supervisor" required></td>
						</tr>
						<tr>
							<th><label for="dashboard_path">Dashboard Path:</label></th>
							<td>
								<input type="text" id="dashboard_path" name="dashboard_path" value="<?php echo htmlspecialchars($role_form['dashboard_path']); ?>" placeholder="../app/employee/menuemployee" required>
								<p class="hint-text">ตัวอย่าง: ../app/admin/menuadmin หรือ ../app/employee/menuemployee</p>
							</td>
						</tr>
						<tr>
							<th>สถานะ:</th>
							<td>
								<label>
									<input type="checkbox" name="role_is_active" value="1"<?php echo $role_form['is_active'] === '1' ? ' checked' : ''; ?>>
									เปิดใช้งาน
								</label>
							</td>
						</tr>
					</table>

					<div class="form-actions">
						<button type="submit">Add role</button>
					</div>
				</form>
			</div>

			<div class="list-panel">
				<div class="section-title">รายการผู้ใช้งาน</div>
				<div class="user-table-wrap">
					<table class="user-table" cellspacing="0" cellpadding="0">
						<tr>
							<th>User login</th>
							<th>Full Name</th>
							<th>Phone</th>
							<th>E-mail</th>
							<th>Last Visit</th>
							<th>Access Level</th>
							<th>Inactive</th>
							<th></th>
						</tr>
						<?php if (empty($users)): ?>
							<tr>
								<td colspan="8">No records</td>
							</tr>
						<?php else: ?>
							<?php foreach ($users as $user): ?>
								<?php $edit_user_href = 'edit_user.php?module=' . rawurlencode($current_module) . '&user_id=' . rawurlencode((string)$user['user_id']); ?>
								<tr>
									<td><?php echo htmlspecialchars((string)$user['user_id']); ?></td>
									<td><?php echo htmlspecialchars(trim((string)$user['first_name'] . ' ' . (string)$user['last_name'])); ?></td>
									<td><?php echo htmlspecialchars((string)($user['phone'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars($user['last_visit'] ? date('m/d/Y h:i a', strtotime((string)$user['last_visit'])) : '-'); ?></td>
									<td><?php echo htmlspecialchars((string)($user['role_name'] ?? $role_labels[$user['user_role']] ?? $user['user_role'])); ?></td>
									<td class="action-cell"><?php echo ((int)$user['is_active'] === 1) ? '' : 'x'; ?></td>
									<td class="action-cell"><a href="<?php echo htmlspecialchars($edit_user_href); ?>" title="แก้ไขผู้ใช้งาน">✎</a></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						<tr class="filter-row">
							<td colspan="8">
								<form method="get" style="display:inline;">
									<input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
									<label>
										<input type="checkbox" name="show_inactive" value="1"<?php echo $show_inactive ? ' checked' : ''; ?> onchange="this.form.submit()">
										Show also Inactive
									</label>
								</form>
							</td>
						</tr>
					</table>
				</div>

				<div class="panel-gap"></div>
				<div class="section-title">รายการ Role</div>
				<div class="user-table-wrap">
					<table class="user-table" cellspacing="0" cellpadding="0">
						<tr>
							<th>Role Key</th>
							<th>Role Name</th>
							<th>Dashboard Path</th>
							<th>Active</th>
							<th>Updated</th>
						</tr>
						<?php if (empty($roles)): ?>
							<tr>
								<td colspan="5">No role records</td>
							</tr>
						<?php else: ?>
							<?php foreach ($roles as $role): ?>
								<tr>
									<td><?php echo htmlspecialchars((string)$role['role_key']); ?></td>
									<td><?php echo htmlspecialchars((string)$role['role_name']); ?></td>
									<td><?php echo htmlspecialchars((string)$role['dashboard_path']); ?></td>
									<td class="action-cell"><?php echo ((int)$role['is_active'] === 1) ? 'Y' : 'N'; ?></td>
									<td><?php echo htmlspecialchars(!empty($role['updated_at']) ? date('m/d/Y h:i a', strtotime((string)$role['updated_at'])) : '-'); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</table>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
