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
} catch (Throwable $e) {
    error_log('Role access check failed in page_branch_crud.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}

$current_module = isset($_GET['module']) ? trim((string)$_GET['module']) : 'branch';
$csrf_token = generateCSRFToken();

$company_types = [
    'บุคคลธรรมดา',
    'หจก',
    'บริษัท',
    'บริษักจำกัดมหาขน'
];

$default_form = [
    'branch_row_id' => 0,
    'branch_id' => '',
    'company_name' => '',
    'company_type' => $company_types[0],
    'address_line' => '',
    'amphoe' => '',
    'room_no' => '',
    'subdistrict' => '',
    'district_area' => '',
    'road' => '',
    'province' => '',
    'tax_id' => '',
    'branch_no' => '',
    'office_phone' => '',
    'email' => '',
    'logo_path' => '',
    'data_year' => date('Y')
];

$form = $default_form;
$errors = [];
$success_message = '';
$branch_rows = [];
$is_edit_mode = false;
$generated_branch_id = '';

function cleanTextValue($value) {
    return trim((string)$value);
}

function cleanDigitsValue($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function normalizeYearValue($value) {
    $digits = cleanDigitsValue($value);
    if (strlen($digits) >= 4) {
        return substr($digits, 0, 4);
    }
    return $digits;
}

function ensureBranchesTable(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS branches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            branch_id VARCHAR(30) NOT NULL UNIQUE,
            company_name VARCHAR(255) NOT NULL,
            company_type VARCHAR(100) NOT NULL,
            address_line VARCHAR(255) NOT NULL,
            amphoe VARCHAR(100) NOT NULL,
            room_no VARCHAR(100) NOT NULL,
            subdistrict VARCHAR(100) NOT NULL,
            district_area VARCHAR(100) NOT NULL,
            road VARCHAR(150) NOT NULL,
            province VARCHAR(100) NOT NULL,
            tax_id VARCHAR(20) NOT NULL,
            branch_no VARCHAR(50) NOT NULL,
            office_phone VARCHAR(20) NOT NULL,
            email VARCHAR(150) NOT NULL,
            logo_path VARCHAR(255) DEFAULT NULL,
            data_year VARCHAR(4) NOT NULL,
            created_by VARCHAR(50) DEFAULT NULL,
            updated_by VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_data_year (data_year),
            INDEX idx_company_name (company_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function loadBranchRow(PDO $pdo, $rowId) {
    $stmt = $pdo->prepare('SELECT * FROM branches WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$rowId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function generateBranchId(PDO $pdo, $dataYear) {
    $year = normalizeYearValue($dataYear);
    if (strlen($year) !== 4) {
        $year = date('Y');
    }

    $prefix = 'BR' . $year . '-';
    $stmt = $pdo->prepare('SELECT branch_id FROM branches WHERE branch_id LIKE :prefix ORDER BY branch_id DESC LIMIT 1');
    $stmt->execute(['prefix' => $prefix . '%']);
    $lastBranchId = (string)$stmt->fetchColumn();

    $next = 1;
    if ($lastBranchId !== '' && preg_match('/-(\d+)$/', $lastBranchId, $matches)) {
        $next = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

try {
    $pdo = getDBConnection();
    ensureBranchesTable($pdo);
} catch (Throwable $e) {
    error_log('Branch table setup error: ' . $e->getMessage());
    $errors[] = 'ไม่สามารถเตรียมตารางข้อมูลสาขาได้';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $deleteId = (int)($_POST['branch_row_id'] ?? 0);
        if ($deleteId > 0 && isset($pdo) && $pdo instanceof PDO) {
            try {
                $row = loadBranchRow($pdo, $deleteId);
                if ($row) {
                    if (!empty($row['logo_path']) && strpos((string)$row['logo_path'], 'assets/images/logo/branches/') === 0) {
                        $logoFile = __DIR__ . '/../../' . ltrim((string)$row['logo_path'], '/');
                        if (is_file($logoFile)) {
                            @unlink($logoFile);
                        }
                    }

                    $stmt = $pdo->prepare('DELETE FROM branches WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $deleteId]);
                }

                header('Location: page_branch_crud.php?module=' . urlencode($current_module) . '&deleted=1');
                exit();
            } catch (Throwable $e) {
                error_log('Branch delete error: ' . $e->getMessage());
                $errors[] = 'ไม่สามารถลบข้อมูลสาขาได้';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branch'])) {
    $form['branch_row_id'] = (int)($_POST['branch_row_id'] ?? 0);
    $is_edit_mode = $form['branch_row_id'] > 0;

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    }

    $form['branch_id'] = cleanTextValue($_POST['branch_id'] ?? '');
    $form['company_name'] = cleanTextValue($_POST['company_name'] ?? '');
    $form['company_type'] = cleanTextValue($_POST['company_type'] ?? '');
    $form['address_line'] = cleanTextValue($_POST['address_line'] ?? '');
    $form['amphoe'] = cleanTextValue($_POST['amphoe'] ?? '');
    $form['room_no'] = cleanTextValue($_POST['room_no'] ?? '');
    $form['subdistrict'] = cleanTextValue($_POST['subdistrict'] ?? '');
    $form['district_area'] = cleanTextValue($_POST['district_area'] ?? '');
    $form['road'] = cleanTextValue($_POST['road'] ?? '');
    $form['province'] = cleanTextValue($_POST['province'] ?? '');
    $form['tax_id'] = cleanDigitsValue($_POST['tax_id'] ?? '');
    $form['branch_no'] = cleanTextValue($_POST['branch_no'] ?? '');
    $form['office_phone'] = cleanDigitsValue($_POST['office_phone'] ?? '');
    $form['email'] = cleanTextValue($_POST['email'] ?? '');
    $form['logo_path'] = cleanTextValue($_POST['current_logo_path'] ?? '');
    $form['data_year'] = normalizeYearValue($_POST['data_year'] ?? '');

    if (!in_array($form['company_type'], $company_types, true)) {
        $errors[] = 'ประเภทบริษัทไม่ถูกต้อง';
    }

    $required_fields = [
        'company_name' => 'ชื่อบริษัท',
        'address_line' => 'ที่อยู่',
        'amphoe' => 'อำเภอ',
        'room_no' => 'ห้องเลขที่',
        'subdistrict' => 'ตำบล',
        'district_area' => 'เขต',
        'road' => 'ถนน',
        'province' => 'จังหวัด',
        'tax_id' => 'หมายเลขผู้เสียภาษี',
        'branch_no' => 'สาขาที่',
        'office_phone' => 'เบอร์โทรสำนักงาน',
        'email' => 'อีเมล์',
        'data_year' => 'ปีข้อมูล'
    ];

    foreach ($required_fields as $field => $label) {
        if ($form[$field] === '') {
            $errors[] = 'กรุณากรอก' . $label;
        }
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'รูปแบบอีเมล์ไม่ถูกต้อง';
    }

    if ($form['data_year'] !== '' && !preg_match('/^\d{4}$/', $form['data_year'])) {
        $errors[] = 'ปีข้อมูลต้องเป็นตัวเลข 4 หลัก';
    }

    $remove_logo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';
    if ($remove_logo) {
        if ($form['logo_path'] !== '' && strpos($form['logo_path'], 'assets/images/logo/branches/') === 0) {
            $old_logo_file = __DIR__ . '/../../' . ltrim($form['logo_path'], '/');
            if (is_file($old_logo_file)) {
                @unlink($old_logo_file);
            }
        }
        $form['logo_path'] = '';
    }

    if (isset($_FILES['branch_logo']) && $_FILES['branch_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['branch_logo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลดโลโก้';
        } else {
            $max_file_size = 5 * 1024 * 1024;
            if ((int)$_FILES['branch_logo']['size'] > $max_file_size) {
                $errors[] = 'ไฟล์โลโก้ต้องมีขนาดไม่เกิน 5MB';
            }

            $tmp_name = (string)$_FILES['branch_logo']['tmp_name'];
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
                $logo_dir = __DIR__ . '/../../assets/images/logo/branches/';
                if (!is_dir($logo_dir) && !mkdir($logo_dir, 0755, true)) {
                    $errors[] = 'ไม่สามารถสร้างโฟลเดอร์เก็บโลโก้สาขาได้';
                } else {
                    $extension = $allowed_mimes[$mime_type];
                    $new_name = 'branch_logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $target_file = $logo_dir . $new_name;

                    if (move_uploaded_file($tmp_name, $target_file)) {
                        if ($form['logo_path'] !== '' && strpos($form['logo_path'], 'assets/images/logo/branches/') === 0) {
                            $old_logo_file = __DIR__ . '/../../' . ltrim($form['logo_path'], '/');
                            if (is_file($old_logo_file)) {
                                @unlink($old_logo_file);
                            }
                        }
                        $form['logo_path'] = 'assets/images/logo/branches/' . $new_name;
                    } else {
                        $errors[] = 'ไม่สามารถบันทึกไฟล์โลโก้สาขาได้';
                    }
                }
            }
        }
    }

    if (empty($errors) && isset($pdo) && $pdo instanceof PDO) {
        try {
            $user_id = (string)($_SESSION['user_id'] ?? 'system');

            if ($is_edit_mode) {
                $stmt = $pdo->prepare(
                    'UPDATE branches SET
                        company_name = :company_name,
                        company_type = :company_type,
                        address_line = :address_line,
                        amphoe = :amphoe,
                        room_no = :room_no,
                        subdistrict = :subdistrict,
                        district_area = :district_area,
                        road = :road,
                        province = :province,
                        tax_id = :tax_id,
                        branch_no = :branch_no,
                        office_phone = :office_phone,
                        email = :email,
                        logo_path = :logo_path,
                        data_year = :data_year,
                        updated_by = :updated_by,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id LIMIT 1'
                );

                $stmt->execute([
                    'company_name' => $form['company_name'],
                    'company_type' => $form['company_type'],
                    'address_line' => $form['address_line'],
                    'amphoe' => $form['amphoe'],
                    'room_no' => $form['room_no'],
                    'subdistrict' => $form['subdistrict'],
                    'district_area' => $form['district_area'],
                    'road' => $form['road'],
                    'province' => $form['province'],
                    'tax_id' => $form['tax_id'],
                    'branch_no' => $form['branch_no'],
                    'office_phone' => $form['office_phone'],
                    'email' => $form['email'],
                    'logo_path' => $form['logo_path'] !== '' ? $form['logo_path'] : null,
                    'data_year' => $form['data_year'],
                    'updated_by' => $user_id,
                    'id' => (int)$form['branch_row_id']
                ]);

                $generated_branch_id = $form['branch_id'];
            } else {
                $generated_branch_id = generateBranchId($pdo, $form['data_year']);

                $stmt = $pdo->prepare(
                    'INSERT INTO branches (
                        branch_id, company_name, company_type, address_line, amphoe, room_no,
                        subdistrict, district_area, road, province, tax_id, branch_no,
                        office_phone, email, logo_path, data_year, created_by, updated_by
                    ) VALUES (
                        :branch_id, :company_name, :company_type, :address_line, :amphoe, :room_no,
                        :subdistrict, :district_area, :road, :province, :tax_id, :branch_no,
                        :office_phone, :email, :logo_path, :data_year, :created_by, :updated_by
                    )'
                );

                $stmt->execute([
                    'branch_id' => $generated_branch_id,
                    'company_name' => $form['company_name'],
                    'company_type' => $form['company_type'],
                    'address_line' => $form['address_line'],
                    'amphoe' => $form['amphoe'],
                    'room_no' => $form['room_no'],
                    'subdistrict' => $form['subdistrict'],
                    'district_area' => $form['district_area'],
                    'road' => $form['road'],
                    'province' => $form['province'],
                    'tax_id' => $form['tax_id'],
                    'branch_no' => $form['branch_no'],
                    'office_phone' => $form['office_phone'],
                    'email' => $form['email'],
                    'logo_path' => $form['logo_path'] !== '' ? $form['logo_path'] : null,
                    'data_year' => $form['data_year'],
                    'created_by' => $user_id,
                    'updated_by' => $user_id
                ]);
            }

            header('Location: page_branch_crud.php?module=' . urlencode($current_module) . '&saved=1&branch_id=' . urlencode($generated_branch_id));
            exit();
        } catch (Throwable $e) {
            error_log('Branch save error: ' . $e->getMessage());
            $errors[] = 'ไม่สามารถบันทึกข้อมูลสาขาได้';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit']) && isset($pdo) && $pdo instanceof PDO) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        try {
            $edit_row = loadBranchRow($pdo, $edit_id);
            if ($edit_row) {
                $is_edit_mode = true;
                $form = [
                    'branch_row_id' => (int)$edit_row['id'],
                    'branch_id' => (string)$edit_row['branch_id'],
                    'company_name' => (string)$edit_row['company_name'],
                    'company_type' => (string)$edit_row['company_type'],
                    'address_line' => (string)$edit_row['address_line'],
                    'amphoe' => (string)$edit_row['amphoe'],
                    'room_no' => (string)$edit_row['room_no'],
                    'subdistrict' => (string)$edit_row['subdistrict'],
                    'district_area' => (string)$edit_row['district_area'],
                    'road' => (string)$edit_row['road'],
                    'province' => (string)$edit_row['province'],
                    'tax_id' => (string)$edit_row['tax_id'],
                    'branch_no' => (string)$edit_row['branch_no'],
                    'office_phone' => (string)$edit_row['office_phone'],
                    'email' => (string)$edit_row['email'],
                    'logo_path' => (string)($edit_row['logo_path'] ?? ''),
                    'data_year' => (string)$edit_row['data_year']
                ];
            }
        } catch (Throwable $e) {
            error_log('Branch edit load error: ' . $e->getMessage());
            $errors[] = 'ไม่สามารถโหลดข้อมูลสาขาที่ต้องการแก้ไขได้';
        }
    }
}

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $generated_branch_id = cleanTextValue($_GET['branch_id'] ?? '');
    $success_message = 'บันทึกข้อมูลสาขาเรียบร้อยแล้ว';
    if ($generated_branch_id !== '') {
        $success_message .= ' (Branch ID: ' . $generated_branch_id . ')';
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $success_message = 'ลบข้อมูลสาขาเรียบร้อยแล้ว';
}

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $list_stmt = $pdo->query('SELECT * FROM branches ORDER BY id DESC');
        $branch_rows = $list_stmt ? $list_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('Branch list load error: ' . $e->getMessage());
        $errors[] = 'ไม่สามารถโหลดรายการสาขาได้';
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branch Management</title>
    <style>
        body {
            margin: 0;
            padding: 12px;
            background: #ffffff;
            font-family: Verdana, Arial, sans-serif;
            color: #102744;
        }

        .page-title {
            margin: 0 0 12px;
            font-size: 24px;
            font-weight: 700;
        }

        .card {
            border: 1px solid #8cacbb;
            background: #ffffff;
            margin-bottom: 14px;
        }

        .card-head {
            padding: 8px 10px;
            border-bottom: 1px solid #8cacbb;
            background: #f5fbff;
            font-size: 15px;
            font-weight: 700;
        }

        .card-body {
            padding: 10px;
        }

        .msg-error,
        .msg-success {
            margin-bottom: 10px;
            border: 1px solid;
            padding: 8px 10px;
            font-size: 14px;
        }

        .msg-error {
            border-color: #cc3300;
            background: #ffefea;
            color: #9f2d00;
        }

        .msg-success {
            border-color: #2d8f2d;
            background: #ebffeb;
            color: #1f641f;
        }

        .msg-error ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(240px, 1fr));
            gap: 10px 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .field label {
            font-size: 13px;
            font-weight: 700;
        }

        .field input,
        .field select {
            min-height: 34px;
            border: 1px solid #9aa9b4;
            padding: 5px 7px;
            font-size: 14px;
            box-sizing: border-box;
            width: 100%;
        }

        .field.readonly input {
            background: #f4f7fa;
        }

        .logo-preview {
            margin-top: 6px;
        }

        .logo-preview img {
            max-width: 220px;
            max-height: 90px;
            border: 1px solid #c4d4e0;
            padding: 3px;
            background: #fff;
        }

        .actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            border: 1px solid #8cacbb;
            background: #dee7ec;
            color: #0f2b45;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 14px;
            cursor: pointer;
        }

        .btn.primary {
            background: #0078d4;
            border-color: #0068b8;
            color: #fff;
        }

        .btn.danger {
            background: #c62828;
            border-color: #a71919;
            color: #fff;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th,
        td {
            border: 1px solid #8cacbb;
            padding: 6px 8px;
            font-size: 13px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f5fbff;
            font-weight: 700;
            white-space: nowrap;
        }

        .row-actions {
            display: flex;
            gap: 6px;
        }

        .row-actions form {
            margin: 0;
        }

        .muted {
            color: #587491;
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <h1 class="page-title">Branch Management</h1>

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

    <div class="card">
        <div class="card-head"><?php echo $is_edit_mode ? 'แก้ไขข้อมูลสาขา' : 'เพิ่มข้อมูลสาขา'; ?></div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="branch_row_id" value="<?php echo (int)$form['branch_row_id']; ?>">
                <input type="hidden" name="current_logo_path" value="<?php echo htmlspecialchars($form['logo_path']); ?>">

                <div class="form-grid">
                    <div class="field readonly">
                        <label for="branch_id">Branch ID (สร้างอัตโนมัติหลังบันทึก)</label>
                        <input type="text" id="branch_id" name="branch_id" value="<?php echo htmlspecialchars($form['branch_id']); ?>" readonly>
                    </div>

                    <div class="field">
                        <label for="company_name">ชื่อบริษัท</label>
                        <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($form['company_name']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="company_type">ประเภทบริษัท</label>
                        <select id="company_type" name="company_type" required>
                            <?php foreach ($company_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"<?php echo $form['company_type'] === $type ? ' selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="address_line">ที่อยู่</label>
                        <input type="text" id="address_line" name="address_line" value="<?php echo htmlspecialchars($form['address_line']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="amphoe">อำเภอ</label>
                        <input type="text" id="amphoe" name="amphoe" value="<?php echo htmlspecialchars($form['amphoe']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="room_no">ห้องเลขที่</label>
                        <input type="text" id="room_no" name="room_no" value="<?php echo htmlspecialchars($form['room_no']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="subdistrict">ตำบล</label>
                        <input type="text" id="subdistrict" name="subdistrict" value="<?php echo htmlspecialchars($form['subdistrict']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="district_area">เขต</label>
                        <input type="text" id="district_area" name="district_area" value="<?php echo htmlspecialchars($form['district_area']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="road">ถนน</label>
                        <input type="text" id="road" name="road" value="<?php echo htmlspecialchars($form['road']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="province">จังหวัด</label>
                        <input type="text" id="province" name="province" value="<?php echo htmlspecialchars($form['province']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="tax_id">หมายเลขผู้เสียภาษี</label>
                        <input type="text" id="tax_id" name="tax_id" inputmode="numeric" value="<?php echo htmlspecialchars($form['tax_id']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="branch_no">สาขาที่</label>
                        <input type="text" id="branch_no" name="branch_no" value="<?php echo htmlspecialchars($form['branch_no']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="office_phone">เบอร์โทรสำนักงาน</label>
                        <input type="text" id="office_phone" name="office_phone" inputmode="numeric" value="<?php echo htmlspecialchars($form['office_phone']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="email">อีเมล์</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form['email']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="data_year">ปีข้อมูล</label>
                        <input type="text" id="data_year" name="data_year" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="<?php echo htmlspecialchars($form['data_year']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="branch_logo">อัปโหลด Logo</label>
                        <input type="file" id="branch_logo" name="branch_logo" accept=".jpg,.jpeg,.png,.gif,.webp">
                        <?php if ($form['logo_path'] !== ''): ?>
                            <div class="logo-preview">
                                <img src="../../<?php echo htmlspecialchars($form['logo_path']); ?>" alt="Branch logo">
                            </div>
                            <label class="muted">
                                <input type="checkbox" name="remove_logo" value="1"> ลบโลโก้ปัจจุบัน
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn primary" name="save_branch" value="1">บันทึกข้อมูลสาขา</button>
                    <?php if ($is_edit_mode): ?>
                        <a class="btn" href="page_branch_crud.php?module=<?php echo urlencode($current_module); ?>">ยกเลิกการแก้ไข</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">รายการสาขา</div>
        <div class="card-body table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Branch ID</th>
                        <th>ชื่อบริษัท</th>
                        <th>ประเภทบริษัท</th>
                        <th>สาขาที่</th>
                        <th>อำเภอ</th>
                        <th>จังหวัด</th>
                        <th>ปีข้อมูล</th>
                        <th>เบอร์โทร</th>
                        <th>อีเมล์</th>
                        <th>โลโก้</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($branch_rows)): ?>
                        <tr>
                            <td colspan="11">ยังไม่มีข้อมูลสาขา</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($branch_rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$row['branch_id']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['company_name']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['company_type']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['branch_no']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['amphoe']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['province']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['data_year']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['office_phone']); ?></td>
                                <td><?php echo htmlspecialchars((string)$row['email']); ?></td>
                                <td>
                                    <?php if (!empty($row['logo_path'])): ?>
                                        <img src="../../<?php echo htmlspecialchars((string)$row['logo_path']); ?>" alt="logo" style="max-width:90px;max-height:40px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn" href="page_branch_crud.php?module=<?php echo urlencode($current_module); ?>&edit=<?php echo (int)$row['id']; ?>">แก้ไข</a>
                                        <form method="post" onsubmit="return confirm('ยืนยันการลบข้อมูลสาขานี้ใช่หรือไม่?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <input type="hidden" name="branch_row_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn danger" name="delete_branch" value="1">ลบ</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>