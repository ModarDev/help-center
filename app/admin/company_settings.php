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
$current_module = isset($_GET['module']) ? trim((string)$_GET['module']) : 'setup';
if (!in_array($current_module, $allowed_modules, true)) {
	$current_module = 'setup';
}

$menu_back_href = 'menuadmin.php?module=' . urlencode($current_module);

$company_types = [
	'บริษัท',
	'ห้างหุ่นส่วน',
	'บุคคลธรรมดา'
];

$default_form = [
	'company_type' => 'บริษัทจำกัด',
	'business_name' => '',
	'tax_id' => '',
	'trade_registration_no' => '',
	'branch_code' => '',
	'branch_name' => '',
	'address_no' => '',
	'moo' => '',
	'subdistrict' => '',
	'district' => '',
	'road' => '',
	'province' => '',
	'postal_code' => '',
	'office_phone' => '',
	'email' => '',
	'header_logo_path' => ''
];

$form = $default_form;
$errors = [];
$success_message = '';
$has_settings = false;

function onlyDigits($value) {
	return preg_replace('/\D+/', '', (string)$value);
}

function cleanText($value) {
	return trim((string)$value);
}

function cleanAddressNo($value) {
	$address_no = trim((string)$value);
	return preg_replace('/[^0-9\/-]/', '', $address_no);
}

function loadCompanySettings(PDO $pdo) {
	$stmt = $pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1');
	return $stmt->fetch() ?: null;
}

try {
	$pdo = getDBConnection();
	$pdo->exec(
		"CREATE TABLE IF NOT EXISTS company_settings (
			id TINYINT UNSIGNED NOT NULL DEFAULT 1,
			company_type VARCHAR(50) NOT NULL,
			business_name VARCHAR(255) NOT NULL,
			tax_id VARCHAR(20) NOT NULL,
			trade_registration_no VARCHAR(20) NOT NULL,
			branch_code VARCHAR(20) NOT NULL,
			branch_name VARCHAR(255) NOT NULL,
			address_no VARCHAR(20) NOT NULL,
			moo VARCHAR(20) NOT NULL,
			subdistrict VARCHAR(100) NOT NULL,
			district VARCHAR(100) NOT NULL,
			road VARCHAR(150) NOT NULL,
			province VARCHAR(100) NOT NULL,
			postal_code VARCHAR(10) NOT NULL,
			office_phone VARCHAR(20) NOT NULL,
			email VARCHAR(150) NOT NULL,
			header_logo_path VARCHAR(255) DEFAULT NULL,
			created_by VARCHAR(50) DEFAULT NULL,
			updated_by VARCHAR(50) DEFAULT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
	);

	$settings_row = loadCompanySettings($pdo);
	if ($settings_row) {
		$has_settings = true;
		foreach ($default_form as $field => $value) {
			$form[$field] = isset($settings_row[$field]) ? (string)$settings_row[$field] : $value;
		}
	}
}
catch (PDOException $e) {
	error_log('Database setup error in app/admin/company_settings.php: ' . $e->getMessage());
	$errors[] = 'ไม่สามารถเตรียมข้อมูลตั้งค่ากิจการได้';
}

$is_edit_mode = isset($_GET['mode']) && $_GET['mode'] === 'edit';
if (!$has_settings) {
	$is_edit_mode = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
	$is_edit_mode = true;

	if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
		$errors[] = 'Invalid CSRF token';
	}

	$form['company_type'] = cleanText($_POST['company_type'] ?? '');
	$form['business_name'] = cleanText($_POST['business_name'] ?? '');
	$form['tax_id'] = onlyDigits($_POST['tax_id'] ?? '');
	$form['trade_registration_no'] = onlyDigits($_POST['trade_registration_no'] ?? '');
	$form['branch_code'] = onlyDigits($_POST['branch_code'] ?? '');
	$form['branch_name'] = cleanText($_POST['branch_name'] ?? '');
	$form['address_no'] = cleanAddressNo($_POST['address_no'] ?? '');
	$form['moo'] = onlyDigits($_POST['moo'] ?? '');
	$form['subdistrict'] = cleanText($_POST['subdistrict'] ?? '');
	$form['district'] = cleanText($_POST['district'] ?? '');
	$form['road'] = cleanText($_POST['road'] ?? '');
	$form['province'] = cleanText($_POST['province'] ?? '');
	$form['postal_code'] = onlyDigits($_POST['postal_code'] ?? '');
	$form['office_phone'] = onlyDigits($_POST['office_phone'] ?? '');
	$form['email'] = cleanText($_POST['email'] ?? '');
	$form['header_logo_path'] = cleanText($_POST['current_logo_path'] ?? '');

	if (!in_array($form['company_type'], $company_types, true)) {
		$errors[] = 'ประเภทบริษัทไม่ถูกต้อง';
	}

	if ($form['business_name'] === '') {
		$errors[] = 'กรุณากรอกชื่อกิจการ';
	}

	$digit_fields = [
		'tax_id' => 'เลขประจำตัวผู้เสียภาษี',
		'trade_registration_no' => 'เลขทะเบียนการค้า',
		'branch_code' => 'รหัสสาขา',
		'moo' => 'หมู่',
		'postal_code' => 'รหัสไปรษณีย์',
		'office_phone' => 'เบอร์สำนักงาน'
	];

	foreach ($digit_fields as $field => $label) {
		if ($form[$field] === '') {
			$errors[] = 'กรุณากรอก' . $label;
		}
	}

	if ($form['address_no'] === '') {
		$errors[] = 'กรุณากรอกที่อยู่';
	}
	elseif (!preg_match('/^[0-9]+([\/-][0-9]+)*$/', $form['address_no'])) {
		$errors[] = 'รูปแบบที่อยู่ไม่ถูกต้อง (ตัวอย่าง: 99 หรือ 99/9)';
	}

	if ($form['branch_name'] === '') {
		$errors[] = 'กรุณากรอกชื่อสาขา';
	}
	if ($form['subdistrict'] === '') {
		$errors[] = 'กรุณากรอกตำบล';
	}
	if ($form['district'] === '') {
		$errors[] = 'กรุณากรอกอำเภอ';
	}
	if ($form['road'] === '') {
		$errors[] = 'กรุณากรอกถนน';
	}
	if ($form['province'] === '') {
		$errors[] = 'กรุณากรอกจังหวัด';
	}

	if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'รูปแบบอีเมล์ไม่ถูกต้อง';
	}

	$remove_logo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';
	if ($remove_logo) {
		if ($form['header_logo_path'] !== '' && strpos($form['header_logo_path'], 'assets/images/logo/company-settings/') === 0) {
			$old_logo_file = '../../' . $form['header_logo_path'];
			if (is_file($old_logo_file)) {
				@unlink($old_logo_file);
			}
		}
		$form['header_logo_path'] = '';
	}

	if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
		if ($_FILES['header_logo']['error'] !== UPLOAD_ERR_OK) {
			$errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพหัวเอกสาร';
		}
		else {
			$max_file_size = 5 * 1024 * 1024;
			if ((int)$_FILES['header_logo']['size'] > $max_file_size) {
				$errors[] = 'ไฟล์รูปภาพต้องมีขนาดไม่เกิน 5MB';
			}

			$tmp_name = (string)$_FILES['header_logo']['tmp_name'];
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = $finfo ? (string)finfo_file($finfo, $tmp_name) : '';
			if ($finfo) {
				finfo_close($finfo);
			}

			$allowed_mimes = [
				'image/jpeg' => 'jpg',
				'image/png' => 'png',
				'image/gif' => 'gif',
				'image/webp' => 'webp'
			];

			if (!isset($allowed_mimes[$mime_type])) {
				$errors[] = 'รองรับเฉพาะไฟล์ JPG, PNG, GIF หรือ WebP';
			}

			if (empty($errors)) {
				$logo_dir = '../../assets/images/logo/company-settings/';
				if (!is_dir($logo_dir) && !mkdir($logo_dir, 0755, true)) {
					$errors[] = 'ไม่สามารถสร้างโฟลเดอร์เก็บรูปหัวเอกสารได้';
				}
				else {
					$extension = $allowed_mimes[$mime_type];
					$new_name = 'header_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
					$target_file = $logo_dir . $new_name;

					if (move_uploaded_file($tmp_name, $target_file)) {
						if ($form['header_logo_path'] !== '' && strpos($form['header_logo_path'], 'assets/images/logo/company-settings/') === 0) {
							$old_logo_file = '../../' . $form['header_logo_path'];
							if (is_file($old_logo_file)) {
								@unlink($old_logo_file);
							}
						}
						$form['header_logo_path'] = 'assets/images/logo/company-settings/' . $new_name;
					}
					else {
						$errors[] = 'ไม่สามารถบันทึกไฟล์รูปหัวเอกสารได้';
					}
				}
			}
		}
	}

	if (empty($errors)) {
		try {
			$pdo = getDBConnection();
			$stmt = $pdo->prepare(
				'INSERT INTO company_settings (
					id, company_type, business_name, tax_id, trade_registration_no, branch_code,
					branch_name, address_no, moo, subdistrict, district, road, province,
					postal_code, office_phone, email, header_logo_path, created_by, updated_by
				) VALUES (
					1, :company_type, :business_name, :tax_id, :trade_registration_no, :branch_code,
					:branch_name, :address_no, :moo, :subdistrict, :district, :road, :province,
					:postal_code, :office_phone, :email, :header_logo_path, :created_by, :updated_by
				)
				ON DUPLICATE KEY UPDATE
					company_type = VALUES(company_type),
					business_name = VALUES(business_name),
					tax_id = VALUES(tax_id),
					trade_registration_no = VALUES(trade_registration_no),
					branch_code = VALUES(branch_code),
					branch_name = VALUES(branch_name),
					address_no = VALUES(address_no),
					moo = VALUES(moo),
					subdistrict = VALUES(subdistrict),
					district = VALUES(district),
					road = VALUES(road),
					province = VALUES(province),
					postal_code = VALUES(postal_code),
					office_phone = VALUES(office_phone),
					email = VALUES(email),
					header_logo_path = VALUES(header_logo_path),
					updated_by = VALUES(updated_by),
					updated_at = CURRENT_TIMESTAMP'
			);

			$user_id = (string)($_SESSION['user_id'] ?? 'system');
			$stmt->execute([
				'company_type' => $form['company_type'],
				'business_name' => $form['business_name'],
				'tax_id' => $form['tax_id'],
				'trade_registration_no' => $form['trade_registration_no'],
				'branch_code' => $form['branch_code'],
				'branch_name' => $form['branch_name'],
				'address_no' => $form['address_no'],
				'moo' => $form['moo'],
				'subdistrict' => $form['subdistrict'],
				'district' => $form['district'],
				'road' => $form['road'],
				'province' => $form['province'],
				'postal_code' => $form['postal_code'],
				'office_phone' => $form['office_phone'],
				'email' => $form['email'],
				'header_logo_path' => $form['header_logo_path'] !== '' ? $form['header_logo_path'] : null,
				'created_by' => $user_id,
				'updated_by' => $user_id
			]);

			header('Location: company_settings.php?module=' . urlencode($current_module) . '&saved=1');
			exit();
		}
		catch (PDOException $e) {
			error_log('Database save error in app/admin/company_settings.php: ' . $e->getMessage());
			$errors[] = 'ไม่สามารถบันทึกข้อมูลตั้งค่ากิจการได้';
		}
	}
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
	$success_message = 'บันทึกข้อมูลตั้งค่ากิจการเรียบร้อยแล้ว';
	$is_edit_mode = false;

	try {
		$pdo = getDBConnection();
		$settings_row = loadCompanySettings($pdo);
		if ($settings_row) {
			$has_settings = true;
			foreach ($default_form as $field => $value) {
				$form[$field] = isset($settings_row[$field]) ? (string)$settings_row[$field] : $value;
			}
		}
	}
	catch (PDOException $e) {
		error_log('Database reload error in app/admin/company_settings.php: ' . $e->getMessage());
	}
}

$readonly_attr = $is_edit_mode ? '' : ' readonly';
$disabled_attr = $is_edit_mode ? '' : ' disabled';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ตั้งค่ากิจการ - Office Plus</title>
	<link rel="stylesheet" href="assets/css/temp.css">
	<style>
		body {
			margin: 0;
			padding: 0;
			background: #fff;
			font-family: Verdana, Arial, Helvetica, sans-serif;
		}

		.settings-wrap {
			width: 100%;
			max-width: none;
			margin: 0;
			padding: 10px 12px 16px;
			background: #fff;
			box-sizing: border-box;
		}

		.settings-title {
			font-size: 24px;
			font-weight: bold;
			margin: 0 0 10px;
		}

		.msg-error,
		.msg-success {
			margin: 8px 0;
			border: 1px solid;
			padding: 8px 10px;
			font-size: 14px;
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
			margin: 8px 0 0 18px;
			padding: 0;
		}

		.settings-table {
			width: 100%;
			border-collapse: collapse;
			background: #fff;
		}

		.settings-table th,
		.settings-table td {
			border: 1px solid #8cacbb;
			padding: 6px 8px;
			font-size: 14px;
			vertical-align: middle;
		}

		.settings-table th {
			width: 260px;
			text-align: left;
			background: #f7fbfe;
			font-weight: normal;
		}

		.settings-table input[type="text"],
		.settings-table input[type="email"],
		.settings-table select,
		.settings-table input[type="file"] {
			width: 100%;
			max-width: none;
			box-sizing: border-box;
			min-height: 30px;
			padding: 4px 6px;
			border: 1px solid #9aa9b4;
			font-size: 14px;
			background: #fff;
		}

		.settings-table input[readonly],
		.settings-table select:disabled,
		.settings-table input[type="file"]:disabled {
			background: #f4f6f8;
			color: #666;
		}

		.logo-preview {
			margin-bottom: 8px;
		}

		.logo-preview img {
			max-width: 320px;
			max-height: 120px;
			border: 1px solid #cdd8e0;
			background: #fff;
			padding: 4px;
		}

		.logo-actions {
			margin-top: 8px;
			font-size: 13px;
		}

		.logo-actions label {
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}

		.form-actions {
			margin-top: 12px;
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.form-actions button,
		.btn-link {
			border: 1px solid #8cacbb;
			background: #dee7ec;
			color: #000;
			text-decoration: none;
			font-size: 14px;
			padding: 5px 14px;
			cursor: pointer;
			display: inline-block;
		}

		.form-actions button:disabled,
		.btn-link.btn-disabled {
			opacity: 0.55;
			cursor: not-allowed;
			pointer-events: none;
		}

		.helper-note {
			margin-top: 12px;
			font-size: 13px;
			color: #2c4d65;
		}

		@media (max-width: 860px) {
			.settings-table,
			.settings-table tbody,
			.settings-table tr,
			.settings-table th,
			.settings-table td {
				display: block;
				width: 100%;
				box-sizing: border-box;
			}

			.settings-table th {
				border-bottom: 0;
			}

			.settings-table td {
				border-top: 0;
				margin-bottom: 8px;
			}
		}
	</style>
</head>
<body>
	<div class="settings-wrap">
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

		<form method="post" enctype="multipart/form-data" autocomplete="off">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
			<input type="hidden" name="current_logo_path" value="<?php echo htmlspecialchars($form['header_logo_path']); ?>">

			<table class="settings-table" cellspacing="0" cellpadding="0">
				<tr>
					<th><label for="company_type">ประเภทบริษัท</label></th>
					<td>
						<select id="company_type" name="company_type"<?php echo $disabled_attr; ?> required>
							<?php foreach ($company_types as $type): ?>
								<option value="<?php echo htmlspecialchars($type); ?>"<?php echo $form['company_type'] === $type ? ' selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="business_name">ชื่อกิจการ</label></th>
					<td><input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($form['business_name']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="tax_id">เลขประจำตัวผู้เสียภาษี</label></th>
					<td><input type="text" id="tax_id" name="tax_id" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['tax_id']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="trade_registration_no">เลขทะเบียนการค้า</label></th>
					<td><input type="text" id="trade_registration_no" name="trade_registration_no" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['trade_registration_no']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="branch_code">รหัสสาขา</label></th>
					<td><input type="text" id="branch_code" name="branch_code" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['branch_code']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="branch_name">ชื่อสาขา</label></th>
					<td><input type="text" id="branch_name" name="branch_name" value="<?php echo htmlspecialchars($form['branch_name']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="address_no">ที่อยู่</label></th>
					<td><input type="text" id="address_no" name="address_no" inputmode="text" pattern="[0-9]+([/-][0-9]+)*" title="ตัวอย่าง: 99 หรือ 99/9" value="<?php echo htmlspecialchars($form['address_no']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="moo">หมู่</label></th>
					<td><input type="text" id="moo" name="moo" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['moo']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="subdistrict">ตำบล</label></th>
					<td><input type="text" id="subdistrict" name="subdistrict" value="<?php echo htmlspecialchars($form['subdistrict']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="district">อำเภอ</label></th>
					<td><input type="text" id="district" name="district" value="<?php echo htmlspecialchars($form['district']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="road">ถนน</label></th>
					<td><input type="text" id="road" name="road" value="<?php echo htmlspecialchars($form['road']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="province">จังหวัด</label></th>
					<td><input type="text" id="province" name="province" value="<?php echo htmlspecialchars($form['province']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="postal_code">รหัสไปรษณีย์</label></th>
					<td><input type="text" id="postal_code" name="postal_code" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['postal_code']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="office_phone">เบอร์สำนักงาน</label></th>
					<td><input type="text" id="office_phone" name="office_phone" inputmode="numeric" pattern="[0-9]+" value="<?php echo htmlspecialchars($form['office_phone']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="email">อีเมล์</label></th>
					<td><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form['email']); ?>"<?php echo $readonly_attr; ?> required></td>
				</tr>
				<tr>
					<th><label for="header_logo">รูปภาพหัวเอกสาร</label></th>
					<td>
						<?php if ($form['header_logo_path'] !== ''): ?>
							<div class="logo-preview">
								<img src="../../<?php echo htmlspecialchars($form['header_logo_path']); ?>" alt="Header Logo">
							</div>
						<?php endif; ?>

						<input type="file" id="header_logo" name="header_logo" accept=".jpg,.jpeg,.png,.gif,.webp"<?php echo $disabled_attr; ?>>

						<?php if ($is_edit_mode && $form['header_logo_path'] !== ''): ?>
							<div class="logo-actions">
								<label>
									<input type="checkbox" name="remove_logo" value="1">
									ลบรูปภาพหัวเอกสารปัจจุบัน
								</label>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<div class="form-actions">
				<button type="submit" name="save_settings" value="1"<?php echo $is_edit_mode ? '' : ' disabled'; ?>>บันทึก</button>
				<a class="btn-link" href="company_settings.php?module=<?php echo urlencode($current_module); ?>">ยกเลิก</a>
				<a class="btn-link<?php echo $is_edit_mode ? ' btn-disabled' : ''; ?>" href="company_settings.php?module=<?php echo urlencode($current_module); ?>&mode=edit">แก้ไข</a>
			</div>
		</form>

		<div class="helper-note">
			กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนบันทึก โดยเฉพาะเลขประจำตัวผู้เสียภาษีและที่อยู่ที่ใช้ในการออกใบกำกับภาษี หากมีการเปลี่ยนแปลงข้อมูลเหล่านี้ในอนาคต กรุณาแก้ไขและบันทึกใหม่เพื่อให้ข้อมูลเป็นปัจจุบัน
		</div>
	</div>
</body>
</html>
