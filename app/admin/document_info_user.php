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

enforceCurrentUserDashboardMenuAccessAny(['user-setup', 'users'], ['sidebar', 'top_nav', 'footer']);

$allowed_modules = ['sales', 'purchases', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'banking-gl', 'setup'];
$current_module = isset($_POST['module'])
    ? trim((string)$_POST['module'])
    : (isset($_GET['module']) ? trim((string)$_GET['module']) : 'setup');
if (!in_array($current_module, $allowed_modules, true)) {
    $current_module = 'setup';
}

$back_to_menu_href = 'menuadmin.php?module=' . urlencode($current_module);

function buildPageUrl(string $module, array $params = []): string {
    $base = 'document_info_user.php?module=' . rawurlencode($module);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $base .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    return $base;
}

function generateAlphaNum(int $length = 15): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($chars) - 1;
    $token = '';

    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, $max)];
    }

    return $token;
}

function generateDocNumbers(): array {
    return [
        'request_no' => 'FAP-' . generateAlphaNum(15),
        'request_ref_no' => generateAlphaNum(15),
        'document_no' => 'APP-' . generateAlphaNum(15),
        'document_ref_no' => generateAlphaNum(15)
    ];
}

function isTextOnly(string $value): bool {
    return (bool)preg_match('/^[\p{L}\s\-\.]+$/u', $value);
}

$custom_companies = [];

$role_labels = [
    'normal' => 'ผู้ใช้งานธรรมดา',
    'admin' => 'แอดมิน',
    'manager' => 'หัวหน้างาน',
    'executive' => 'ผู้บริหาร',
    'other' => 'อื่นๆ'
];

$errors = [];
$success_message = '';

if (!isset($_SESSION['document_flash']) || !is_array($_SESSION['document_flash'])) {
    $_SESSION['document_flash'] = [];
}

if (!empty($_SESSION['document_flash']['success'])) {
    $success_message = (string)$_SESSION['document_flash']['success'];
}
if (!empty($_SESSION['document_flash']['errors']) && is_array($_SESSION['document_flash']['errors'])) {
    $errors = array_map('strval', $_SESSION['document_flash']['errors']);
}
$_SESSION['document_flash'] = [];

$search_query = trim((string)($_GET['q'] ?? ''));
$selected_id = isset($_GET['selected_id']) ? (int)$_GET['selected_id'] : 0;
$mode = isset($_GET['mode']) && $_GET['mode'] === 'edit' ? 'edit' : 'view';

$numbers = generateDocNumbers();
$form = [
    'first_name' => '',
    'last_name' => '',
    'employee_code' => '',
    'company_key' => '',
    'company_name' => '',
    'company_detail' => '',
    'contact_phone' => '',
    'email' => '',
    'system_name' => '',
    'usage_level' => '',
    'access_levels' => [],
    'access_other' => '',
    'request_no' => $numbers['request_no'],
    'request_ref_no' => $numbers['request_ref_no'],
    'document_no' => $numbers['document_no'],
    'document_ref_no' => $numbers['document_ref_no'],
    'transaction_date' => date('Y-m-d'),
    'status' => 'active'
];

$selected_doc = null;
$documents = [];
$active_branch_id = '';

function ensureTableColumn(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
    $stmt->execute([$column]);
    $exists = $stmt->fetch();

    if (!$exists) {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $column . ' ' . $definition);
    }
}

try {
    $pdo = getDBConnection();

    if (shouldRequireBranchSelection($pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($pdo, $active_branch_id)) {
            $redirect_path = '../app/admin/document_info_user?module=' . rawurlencode($current_module);
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode($redirect_path));
            exit();
        }
        $active_branch_id = getCurrentBranchId();
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS document_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id VARCHAR(30) NULL,
            request_no VARCHAR(25) NOT NULL UNIQUE,
            request_ref_no VARCHAR(20) NOT NULL,
            document_no VARCHAR(25) NOT NULL UNIQUE,
            document_ref_no VARCHAR(20) NOT NULL,
            transaction_date DATE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            employee_code VARCHAR(30) NOT NULL,
            company_name VARCHAR(255) NOT NULL,
            company_detail TEXT NULL,
            contact_phone VARCHAR(20) NOT NULL,
            email VARCHAR(150) NOT NULL,
            system_name VARCHAR(150) NOT NULL,
            usage_level VARCHAR(150) NOT NULL,
            access_levels TEXT NOT NULL,
            access_other VARCHAR(255) NULL,
            status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
            created_by VARCHAR(50) NULL,
            updated_by VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS document_request_companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            branch_id VARCHAR(30) NULL,
            reg_type VARCHAR(50) NOT NULL,
            company_name VARCHAR(255) NOT NULL UNIQUE,
            registration_no VARCHAR(50) NULL,
            phone VARCHAR(30) NULL,
            address_no VARCHAR(100) NULL,
            district VARCHAR(100) NULL,
            subdistrict VARCHAR(100) NULL,
            road VARCHAR(100) NULL,
            soi VARCHAR(100) NULL,
            moo VARCHAR(50) NULL,
            province VARCHAR(100) NULL,
            postcode VARCHAR(20) NULL,
            branch_no VARCHAR(100) NULL,
            company_detail TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    ensureTableColumn($pdo, 'document_requests', 'branch_id', 'VARCHAR(30) NULL');
    ensureTableColumn($pdo, 'document_request_companies', 'branch_id', 'VARCHAR(30) NULL');

    try {
        $pdo->exec('CREATE INDEX idx_document_requests_branch_id ON document_requests (branch_id)');
    } catch (Throwable $e) {
        // Index may already exist.
    }

    try {
        $pdo->exec('CREATE INDEX idx_document_request_companies_branch_id ON document_request_companies (branch_id)');
    } catch (Throwable $e) {
        // Index may already exist.
    }

    try {
        $index_stmt = $pdo->query("SHOW INDEX FROM document_request_companies WHERE Key_name = 'company_name'");
        $has_single_unique_company = false;
        if ($index_stmt) {
            foreach ($index_stmt->fetchAll() as $index_row) {
                if ((int)($index_row['Non_unique'] ?? 1) === 0) {
                    $has_single_unique_company = true;
                    break;
                }
            }
        }

        if ($has_single_unique_company) {
            $pdo->exec('ALTER TABLE document_request_companies DROP INDEX company_name');
        }
    } catch (Throwable $e) {
        // Ignore if index name differs or already adjusted.
    }

    try {
        $pdo->exec('CREATE UNIQUE INDEX uniq_company_branch ON document_request_companies (company_name, branch_id)');
    } catch (Throwable $e) {
        // Ignore if unique index already exists.
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'add_company_ajax') {
            header('Content-Type: application/json; charset=utf-8');

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $reg_type = sanitizeInput($_POST['reg_type'] ?? '');
            $company_modal_name = sanitizeInput($_POST['company_modal_name'] ?? '');
            $registration_no = preg_replace('/\D+/', '', (string)($_POST['company_reg_no'] ?? '')) ?? '';
            $company_phone = preg_replace('/\D+/', '', (string)($_POST['company_phone'] ?? '')) ?? '';
            $address_no = sanitizeInput($_POST['address_no'] ?? '');
            $district = sanitizeInput($_POST['district'] ?? '');
            $subdistrict = sanitizeInput($_POST['subdistrict'] ?? '');
            $road = sanitizeInput($_POST['road'] ?? '');
            $soi = sanitizeInput($_POST['soi'] ?? '');
            $moo = sanitizeInput($_POST['moo'] ?? '');
            $province = sanitizeInput($_POST['province'] ?? '');
            $postcode = preg_replace('/\D+/', '', (string)($_POST['postcode'] ?? '')) ?? '';
            $branch_no = sanitizeInput($_POST['branch_no'] ?? '');

            $allowed_reg_types = ['หจก', 'บริษัท', 'บริษัทจำกัดมหาชน'];
            if (!in_array($reg_type, $allowed_reg_types, true)) {
                echo json_encode(['ok' => false, 'message' => 'ประเภทการจดทะเบียนไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if ($company_modal_name === '') {
                echo json_encode(['ok' => false, 'message' => 'กรุณากรอกชื่อบริษัท'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $display_name = trim($reg_type . ' ' . $company_modal_name);
            $detail_parts = [
                'ทะเบียน: ' . $registration_no,
                'โทร: ' . $company_phone,
                'ที่อยู่: ' . $address_no,
                'อำเภอ: ' . $district,
                'ตำบล: ' . $subdistrict,
                'ถนน: ' . $road,
                'ซอย: ' . $soi,
                'หมู่: ' . $moo,
                'จังหวัด: ' . $province,
                'รหัสไปรษณีย์: ' . $postcode,
                'สาขา: ' . $branch_no
            ];
            $company_detail = implode(' | ', $detail_parts);

            $exists_stmt = $pdo->prepare('SELECT id, company_name, company_detail FROM document_request_companies WHERE company_name = ? AND branch_id = ? LIMIT 1');
            $exists_stmt->execute([$display_name, $active_branch_id]);
            $existing = $exists_stmt->fetch();

            if ($existing) {
                echo json_encode([
                    'ok' => true,
                    'message' => 'บริษัทนี้มีอยู่แล้วในรายการ',
                    'option_key' => 'CUSTOM_DB_' . (int)$existing['id'],
                    'company_name' => (string)$existing['company_name'],
                    'company_detail' => (string)($existing['company_detail'] ?? '')
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }

            $insert_company_stmt = $pdo->prepare(
                'INSERT INTO document_request_companies (
                    branch_id, reg_type, company_name, registration_no, phone, address_no,
                    district, subdistrict, road, soi, moo, province, postcode,
                    branch_no, company_detail
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $insert_company_stmt->execute([
                $active_branch_id,
                $reg_type,
                $display_name,
                $registration_no,
                $company_phone,
                $address_no,
                $district,
                $subdistrict,
                $road,
                $soi,
                $moo,
                $province,
                $postcode,
                $branch_no,
                $company_detail
            ]);

            $new_company_id = (int)$pdo->lastInsertId();

            echo json_encode([
                'ok' => true,
                'message' => 'เพิ่มบริษัทเรียบร้อยแล้ว',
                'option_key' => 'CUSTOM_DB_' . $new_company_id,
                'company_name' => $display_name,
                'company_detail' => $company_detail
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Invalid CSRF token';
        }

        $post_selected_id = (int)($_POST['selected_id'] ?? 0);
        $search_query = trim((string)($_POST['q'] ?? $search_query));

        if (in_array($action, ['create_doc', 'update_doc'], true)) {
            $form['first_name'] = sanitizeInput($_POST['first_name'] ?? '');
            $form['last_name'] = sanitizeInput($_POST['last_name'] ?? '');
            $form['employee_code'] = preg_replace('/\D+/', '', (string)($_POST['employee_code'] ?? '')) ?? '';
            $form['company_key'] = sanitizeInput($_POST['company_key'] ?? '');
            $form['company_name'] = sanitizeInput($_POST['company_name'] ?? '');
            $form['company_detail'] = sanitizeInput($_POST['company_detail'] ?? '');
            $form['contact_phone'] = preg_replace('/\D+/', '', (string)($_POST['contact_phone'] ?? '')) ?? '';
            $form['email'] = sanitizeInput($_POST['email'] ?? '');
            $form['system_name'] = sanitizeInput($_POST['system_name'] ?? '');
            $form['usage_level'] = sanitizeInput($_POST['usage_level'] ?? '');
            $form['access_other'] = sanitizeInput($_POST['access_other'] ?? '');
            $form['transaction_date'] = sanitizeInput($_POST['transaction_date'] ?? '');

            $posted_access = $_POST['access_levels'] ?? [];
            $posted_access = is_array($posted_access) ? $posted_access : [];
            $allowed_access = ['normal', 'admin', 'manager', 'executive', 'other'];
            $form['access_levels'] = [];
            foreach ($posted_access as $access) {
                $access_value = (string)$access;
                if (in_array($access_value, $allowed_access, true)) {
                    $form['access_levels'][] = $access_value;
                }
            }

            if ($action === 'create_doc') {
                $form['request_no'] = isset($_POST['request_no']) ? strtoupper(trim((string)$_POST['request_no'])) : $form['request_no'];
                $form['request_ref_no'] = isset($_POST['request_ref_no']) ? strtoupper(trim((string)$_POST['request_ref_no'])) : $form['request_ref_no'];
                $form['document_no'] = isset($_POST['document_no']) ? strtoupper(trim((string)$_POST['document_no'])) : $form['document_no'];
                $form['document_ref_no'] = isset($_POST['document_ref_no']) ? strtoupper(trim((string)$_POST['document_ref_no'])) : $form['document_ref_no'];
            }

            if ($form['first_name'] === '' || !isTextOnly($form['first_name'])) {
                $errors[] = 'กรุณากรอกชื่อเป็นตัวอักษรเท่านั้น';
            }

            if ($form['last_name'] === '' || !isTextOnly($form['last_name'])) {
                $errors[] = 'กรุณากรอกนามสกุลเป็นตัวอักษรเท่านั้น';
            }

            if ($form['employee_code'] === '') {
                $errors[] = 'กรุณากรอกรหัสพนักงานเป็นตัวเลข';
            }

            if ($form['company_name'] === '') {
                $errors[] = 'กรุณาเลือกหรือเพิ่มสังกัดบริษัท';
            }

            if ($form['contact_phone'] === '' || !preg_match('/^[0-9]{9,15}$/', $form['contact_phone'])) {
                $errors[] = 'กรุณากรอกเบอร์โทรติดต่อเป็นตัวเลข 9-15 หลัก';
            }

            if ($form['email'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'รูปแบบอีเมล์ไม่ถูกต้อง';
            }

            if ($form['system_name'] === '') {
                $errors[] = 'กรุณากรอกชื่อระบบ';
            }

            if ($form['usage_level'] === '') {
                $errors[] = 'กรุณากรอกระดับการใช้งาน';
            }

            if (count($form['access_levels']) === 0) {
                $errors[] = 'กรุณาเลือกระดับสิทธิ์การเข้าถึงอย่างน้อย 1 รายการ';
            }

            if (in_array('other', $form['access_levels'], true) && $form['access_other'] === '') {
                $errors[] = 'กรุณาระบุรายละเอียดสิทธิ์อื่นๆ';
            }

            if ($form['transaction_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['transaction_date'])) {
                $errors[] = 'กรุณาเลือกวันที่ทำรายการ';
            }

            if (empty($errors) && $action === 'create_doc') {
                if (!preg_match('/^FAP-[A-Z0-9]{15}$/', $form['request_no'])) {
                    $form['request_no'] = 'FAP-' . generateAlphaNum(15);
                }
                if (!preg_match('/^[A-Z0-9]{15}$/', $form['request_ref_no'])) {
                    $form['request_ref_no'] = generateAlphaNum(15);
                }
                if (!preg_match('/^APP-[A-Z0-9]{15}$/', $form['document_no'])) {
                    $form['document_no'] = 'APP-' . generateAlphaNum(15);
                }
                if (!preg_match('/^[A-Z0-9]{15}$/', $form['document_ref_no'])) {
                    $form['document_ref_no'] = generateAlphaNum(15);
                }

                $duplicate_stmt = $pdo->prepare('SELECT COUNT(*) FROM document_requests WHERE branch_id = ? AND (request_no = ? OR document_no = ?)');
                $duplicate_stmt->execute([$active_branch_id, $form['request_no'], $form['document_no']]);
                if ((int)$duplicate_stmt->fetchColumn() > 0) {
                    $errors[] = 'เกิดเลขที่เอกสารซ้ำในระบบ กรุณาบันทึกใหม่อีกครั้ง';
                }
            }

            if (empty($errors) && $action === 'create_doc') {
                $insert_stmt = $pdo->prepare(
                    'INSERT INTO document_requests (
                        branch_id, request_no, request_ref_no, document_no, document_ref_no, transaction_date,
                        first_name, last_name, employee_code, company_name, company_detail,
                        contact_phone, email, system_name, usage_level, access_levels, access_other,
                        status, created_by, updated_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $insert_stmt->execute([
                    $active_branch_id,
                    $form['request_no'],
                    $form['request_ref_no'],
                    $form['document_no'],
                    $form['document_ref_no'],
                    $form['transaction_date'],
                    $form['first_name'],
                    $form['last_name'],
                    $form['employee_code'],
                    $form['company_name'],
                    $form['company_detail'],
                    $form['contact_phone'],
                    $form['email'],
                    $form['system_name'],
                    $form['usage_level'],
                    json_encode($form['access_levels'], JSON_UNESCAPED_UNICODE),
                    $form['access_other'],
                    'active',
                    (string)($_SESSION['user_id'] ?? ''),
                    (string)($_SESSION['user_id'] ?? '')
                ]);

                $new_id = (int)$pdo->lastInsertId();
                $_SESSION['document_flash'] = ['success' => 'บันทึกเอกสารใหม่เรียบร้อยแล้ว'];
                header('Location: ' . buildPageUrl($current_module, ['selected_id' => $new_id, 'q' => $search_query]));
                exit();
            }

            if (empty($errors) && $action === 'update_doc') {
                if ($post_selected_id <= 0) {
                    $errors[] = 'ไม่พบเอกสารที่ต้องการแก้ไข';
                } else {
                    $current_stmt = $pdo->prepare('SELECT id, request_no, request_ref_no, document_no, document_ref_no, status FROM document_requests WHERE id = ? AND branch_id = ? LIMIT 1');
                    $current_stmt->execute([$post_selected_id, $active_branch_id]);
                    $current_row = $current_stmt->fetch();

                    if (!$current_row) {
                        $errors[] = 'ไม่พบเอกสารที่ต้องการแก้ไข';
                    } elseif ((string)$current_row['status'] === 'cancelled') {
                        $errors[] = 'เอกสารที่ยกเลิกแล้วไม่สามารถแก้ไขได้';
                    } else {
                        $update_stmt = $pdo->prepare(
                            'UPDATE document_requests SET
                                transaction_date = ?,
                                first_name = ?,
                                last_name = ?,
                                employee_code = ?,
                                company_name = ?,
                                company_detail = ?,
                                contact_phone = ?,
                                email = ?,
                                system_name = ?,
                                usage_level = ?,
                                access_levels = ?,
                                access_other = ?,
                                updated_by = ?
                                      WHERE id = ? AND branch_id = ?'
                        );

                        $update_stmt->execute([
                            $form['transaction_date'],
                            $form['first_name'],
                            $form['last_name'],
                            $form['employee_code'],
                            $form['company_name'],
                            $form['company_detail'],
                            $form['contact_phone'],
                            $form['email'],
                            $form['system_name'],
                            $form['usage_level'],
                            json_encode($form['access_levels'], JSON_UNESCAPED_UNICODE),
                            $form['access_other'],
                            (string)($_SESSION['user_id'] ?? ''),
                            $post_selected_id,
                            $active_branch_id
                        ]);

                        $_SESSION['document_flash'] = ['success' => 'แก้ไขเอกสารเรียบร้อยแล้ว'];
                        header('Location: ' . buildPageUrl($current_module, ['selected_id' => $post_selected_id, 'q' => $search_query]));
                        exit();
                    }
                }
            }
        }

        if ($action === 'cancel_doc' && empty($errors)) {
            if ($post_selected_id <= 0) {
                $errors[] = 'ไม่พบเอกสารที่ต้องการยกเลิก';
            } else {
                $cancel_stmt = $pdo->prepare('UPDATE document_requests SET status = "cancelled", updated_by = ? WHERE id = ? AND branch_id = ?');
                $cancel_stmt->execute([(string)($_SESSION['user_id'] ?? ''), $post_selected_id, $active_branch_id]);

                $_SESSION['document_flash'] = ['success' => 'ยกเลิกเอกสารเรียบร้อยแล้ว'];
                header('Location: ' . buildPageUrl($current_module, ['selected_id' => $post_selected_id, 'q' => $search_query]));
                exit();
            }
        }

        if ($action === 'delete_doc' && empty($errors)) {
            if ($post_selected_id <= 0) {
                $errors[] = 'ไม่พบเอกสารที่ต้องการลบ';
            } else {
                $delete_stmt = $pdo->prepare('DELETE FROM document_requests WHERE id = ? AND branch_id = ?');
                $delete_stmt->execute([$post_selected_id, $active_branch_id]);

                $_SESSION['document_flash'] = ['success' => 'ลบเอกสารเรียบร้อยแล้ว'];
                header('Location: ' . buildPageUrl($current_module, ['q' => $search_query]));
                exit();
            }
        }
    }

    if ($selected_id > 0) {
        $selected_stmt = $pdo->prepare('SELECT * FROM document_requests WHERE id = ? AND branch_id = ? LIMIT 1');
        $selected_stmt->execute([$selected_id, $active_branch_id]);
        $selected_doc = $selected_stmt->fetch();

        if ($selected_doc) {
            $decoded_access = json_decode((string)$selected_doc['access_levels'], true);
            $decoded_access = is_array($decoded_access) ? $decoded_access : [];

            $form['first_name'] = (string)$selected_doc['first_name'];
            $form['last_name'] = (string)$selected_doc['last_name'];
            $form['employee_code'] = (string)$selected_doc['employee_code'];
            $form['company_name'] = (string)$selected_doc['company_name'];
            $form['contact_phone'] = (string)$selected_doc['contact_phone'];
            $form['email'] = (string)$selected_doc['email'];
            $form['system_name'] = (string)$selected_doc['system_name'];
            $form['usage_level'] = (string)$selected_doc['usage_level'];
            $form['access_levels'] = $decoded_access;
            $form['access_other'] = (string)$selected_doc['access_other'];
            $form['request_no'] = (string)$selected_doc['request_no'];
            $form['request_ref_no'] = (string)$selected_doc['request_ref_no'];
            $form['document_no'] = (string)$selected_doc['document_no'];
            $form['document_ref_no'] = (string)$selected_doc['document_ref_no'];
            $form['transaction_date'] = (string)$selected_doc['transaction_date'];
            $form['status'] = (string)$selected_doc['status'];
            $form['company_detail'] = (string)($selected_doc['company_detail'] ?? '');
        } else {
            $selected_id = 0;
            $mode = 'view';
        }
    }

    $search_sql = 'branch_id = :branch_id';
    $query_params = ['branch_id' => $active_branch_id];
    if ($search_query !== '') {
        $search_like = '%' . $search_query . '%';
        $search_sql .= ' AND (
            request_no LIKE :q_request_no
            OR request_ref_no LIKE :q_request_ref_no
            OR document_no LIKE :q_document_no
            OR document_ref_no LIKE :q_document_ref_no
            OR first_name LIKE :q_first_name
            OR last_name LIKE :q_last_name
            OR CONCAT(first_name, " ", last_name) LIKE :q_full_name
            OR employee_code LIKE :q_employee_code
            OR system_name LIKE :q_system_name
        )';

        $query_params = array_merge($query_params, [
            'q_request_no' => $search_like,
            'q_request_ref_no' => $search_like,
            'q_document_no' => $search_like,
            'q_document_ref_no' => $search_like,
            'q_first_name' => $search_like,
            'q_last_name' => $search_like,
            'q_full_name' => $search_like,
            'q_employee_code' => $search_like,
            'q_system_name' => $search_like,
        ]);
    }

    $list_stmt = $pdo->prepare(
        'SELECT id, request_no, document_no, first_name, last_name, employee_code, system_name, transaction_date, status, created_at
         FROM document_requests
         WHERE ' . $search_sql . '
         ORDER BY created_at DESC
         LIMIT 200'
    );

    foreach ($query_params as $k => $v) {
        $list_stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
    }

    $list_stmt->execute();
    $documents = $list_stmt->fetchAll();

    $company_stmt = $pdo->prepare('SELECT id, company_name, company_detail FROM document_request_companies WHERE branch_id = ? ORDER BY company_name ASC');
    $company_stmt->execute([$active_branch_id]);
    $custom_companies = $company_stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Database error in document_info_user.php: ' . $e->getMessage());
    if (empty($errors)) {
        $errors[] = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
    }
}

$is_edit_mode = $selected_doc && $mode === 'edit';
$is_view_locked = $selected_doc && !$is_edit_mode;

$company_options = [];
foreach ($custom_companies as $company) {
    $company_options['CUSTOM_DB_' . (int)$company['id']] = [
        'name' => (string)$company['company_name'],
        'detail' => (string)($company['company_detail'] ?? '')
    ];
}

$has_selected_company = false;
foreach ($company_options as $option) {
    if (($option['name'] ?? '') === $form['company_name']) {
        $has_selected_company = true;
        break;
    }
}

if ($form['company_name'] !== '' && !$has_selected_company) {
    $company_options['CUSTOM_SELECTED'] = [
        'name' => $form['company_name'],
        'detail' => $form['company_detail']
    ];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารรายละเอียดการเข้าใช้งานระบบ</title>
    <link rel="stylesheet" href="assets/css/temp.css">
    <style>
        body {
            background: #ffffff;
            margin: 0;
            padding: 0;
            font-family: Verdana, Arial, Helvetica, sans-serif;
        }

        .page-wrap {
            width: 100%;
            margin: 0;
            padding: 10px;
            box-sizing: border-box;
        }

        .page-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 10px;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(520px, 1.3fr) minmax(360px, 1fr);
            gap: 12px;
            align-items: start;
        }

        .panel {
            border: 1px solid #8cacbb;
            background: #ffffff;
        }

        .panel-head {
            background: #dee7ec;
            padding: 6px 8px;
            font-weight: bold;
            border-bottom: 1px solid #8cacbb;
        }

        .panel-body {
            padding: 8px;
        }

        .group-box {
            border: 1px solid #8cacbb;
            margin-bottom: 12px;
            padding: 10px;
        }

        .group-box legend {
            font-size: 14px;
            font-weight: bold;
            padding: 0 4px;
        }

        .form-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .form-grid td {
            padding: 4px 5px;
            vertical-align: top;
        }

        .label-cell {
            width: 220px;
            font-weight: bold;
            white-space: nowrap;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 5px 6px;
            border: 1px solid #9aa9b4;
            box-sizing: border-box;
            font-size: 14px;
            background: #fff;
        }

        input[readonly],
        textarea[readonly] {
            background: #f2f5f8;
        }

        .inline-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .inline-row button {
            padding: 4px 10px;
            border: 1px solid #8cacbb;
            background: #dee7ec;
            cursor: pointer;
        }

        .access-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .access-list label {
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }

        .other-wrap.hidden {
            display: none;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .actions button,
        .actions a {
            font-size: 14px;
        }

        .actions button,
        .actions a,
        .search-row a {
            display: inline-block;
            padding: 5px 12px;
            border: 1px solid #8cacbb;
            background: #dee7ec;
            color: #000;
            text-decoration: none;
            line-height: 20px;
        }

        .actions button {
            cursor: pointer;
        }

        .actions a:hover,
        .search-row a:hover {
            background: #d0dbe3;
            text-decoration: none;
        }

        .state-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            background: #e6f4ea;
            color: #1f6f3f;
            border: 1px solid #93c8a3;
        }

        .state-badge.cancelled {
            background: #ffe9e9;
            color: #a22b2b;
            border-color: #d89a9a;
        }

        .message {
            border: 1px solid;
            padding: 8px 10px;
            margin-bottom: 10px;
        }

        .message.error {
            border-color: #cc3300;
            background: #ffefea;
            color: #a02b00;
        }

        .message.success {
            border-color: #2f8f2f;
            background: #eaffe9;
            color: #226622;
        }

        .message ul {
            margin: 6px 0 0 18px;
        }

        .search-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .search-row input[type="text"] {
            flex: 1;
        }

        .search-row button {
            padding: 5px 10px;
            border: 1px solid #8cacbb;
            background: #dee7ec;
            cursor: pointer;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
        }

        .doc-table th,
        .doc-table td {
            border: 1px solid #8cacbb;
            padding: 5px 6px;
            font-size: 13px;
            vertical-align: top;
        }

        .doc-table th {
            background: #c7d4de;
            text-align: left;
        }

        .doc-table tr.active-row {
            background: #f3f9ff;
        }

        .doc-link {
            color: #0b4f8c;
            text-decoration: none;
            font-weight: bold;
        }

        .doc-link:hover {
            text-decoration: underline;
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.4);
            padding: 10px;
            z-index: 1000;
        }

        .modal.show {
            display: flex;
        }

        .modal-box {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border: 1px solid #8cacbb;
            padding: 10px;
            max-height: 90vh;
            overflow: auto;
        }

        .modal-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        @media (max-width: 1150px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
       

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>ไม่สามารถดำเนินการได้</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars((string)$error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message !== ''): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="layout">
            <div class="panel">
                <div class="panel-head">
                    ฟอร์มเอกสาร
                    <?php if ($selected_doc): ?>
                        <span class="state-badge<?php echo $form['status'] === 'cancelled' ? ' cancelled' : ''; ?>"><?php echo $form['status'] === 'cancelled' ? 'ยกเลิกแล้ว' : 'ใช้งานอยู่'; ?></span>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <form method="post" autocomplete="off" id="doc-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="selected_id" value="<?php echo htmlspecialchars((string)$selected_id); ?>">
                        <input type="hidden" id="company_name" name="company_name" value="<?php echo htmlspecialchars($form['company_name']); ?>">
                        <input type="hidden" id="company_detail" name="company_detail" value="<?php echo htmlspecialchars($form['company_detail']); ?>">

                        <fieldset class="group-box">
                            <legend>ข้อมูลรายละเอียดผู้ขอใช้งาน</legend>
                            <table class="form-grid" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="label-cell"><label for="first_name">ชื่อ</label></td>
                                    <td><input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form['first_name']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="last_name">นามสกุล</label></td>
                                    <td><input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form['last_name']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="employee_code">รหัสพนักงาน</label></td>
                                    <td><input type="text" id="employee_code" name="employee_code" inputmode="numeric" pattern="[0-9]*" value="<?php echo htmlspecialchars($form['employee_code']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="company_key">สังกัดบริษัท</label></td>
                                    <td>
                                        <div class="inline-row">
                                            <select id="company_key" name="company_key"<?php echo $is_view_locked ? ' disabled' : ''; ?> required>
                                                <option value="">เลือกบริษัท</option>
                                                <?php if (empty($company_options)): ?>
                                                    <option value="" disabled>ยังไม่มีข้อมูลบริษัท กรุณากดเพิ่มบริษัท</option>
                                                <?php endif; ?>
                                                <?php foreach ($company_options as $company_key => $company_item): ?>
                                                    <?php
                                                        $company_name = (string)($company_item['name'] ?? '');
                                                        $company_detail = (string)($company_item['detail'] ?? '');
                                                        $selected_option = ($form['company_name'] !== '' && $form['company_name'] === $company_name);
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars((string)$company_key); ?>" data-company-name="<?php echo htmlspecialchars($company_name); ?>" data-company-detail="<?php echo htmlspecialchars($company_detail); ?>"<?php echo $selected_option ? ' selected' : ''; ?>><?php echo htmlspecialchars($company_name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" id="open-company-modal"<?php echo $is_view_locked ? ' disabled' : ''; ?>>เพิ่มบริษัท</button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="contact_phone">เบอร์โทรติดต่อ</label></td>
                                    <td><input type="text" id="contact_phone" name="contact_phone" inputmode="numeric" pattern="[0-9]*" value="<?php echo htmlspecialchars($form['contact_phone']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="email">อีเมล์</label></td>
                                    <td><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form['email']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                            </table>
                        </fieldset>

                        <fieldset class="group-box">
                            <legend>ระบบที่เข้าใช้งาน</legend>
                            <table class="form-grid" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="label-cell"><label for="system_name">ชื่อระบบ</label></td>
                                    <td><input type="text" id="system_name" name="system_name" value="<?php echo htmlspecialchars($form['system_name']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="usage_level">ระดับการใช้งาน</label></td>
                                    <td><input type="text" id="usage_level" name="usage_level" value="<?php echo htmlspecialchars($form['usage_level']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">ระดับสิทธิ์การเข้าถึง</td>
                                    <td>
                                        <div class="access-list">
                                            <?php foreach ($role_labels as $role_key => $role_label): ?>
                                                <label>
                                                    <input type="checkbox" name="access_levels[]" value="<?php echo htmlspecialchars($role_key); ?>"<?php echo in_array($role_key, $form['access_levels'], true) ? ' checked' : ''; ?><?php echo $is_view_locked ? ' disabled' : ''; ?><?php echo $role_key === 'other' ? ' id="access_other_chk"' : ''; ?>>
                                                    <?php echo htmlspecialchars($role_label); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div id="access_other_wrap" class="other-wrap<?php echo in_array('other', $form['access_levels'], true) ? '' : ' hidden'; ?>">
                                            <input type="text" id="access_other" name="access_other" placeholder="ระบุสิทธิ์อื่นๆ" value="<?php echo htmlspecialchars($form['access_other']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?>>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </fieldset>

                        <fieldset class="group-box">
                            <legend>เลขที่เอกสาร</legend>
                            <table class="form-grid" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="label-cell"><label for="request_no">เลขที่คำร้องขอ</label></td>
                                    <td><input type="text" id="request_no" name="request_no" value="<?php echo htmlspecialchars($form['request_no']); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="request_ref_no">หมายเลขอ้างอิงคำร้อง</label></td>
                                    <td><input type="text" id="request_ref_no" name="request_ref_no" value="<?php echo htmlspecialchars($form['request_ref_no']); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="document_no">หมายเลขที่เอกสาร</label></td>
                                    <td><input type="text" id="document_no" name="document_no" value="<?php echo htmlspecialchars($form['document_no']); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="document_ref_no">หมายเลขอ้างอิงเอกสาร</label></td>
                                    <td><input type="text" id="document_ref_no" name="document_ref_no" value="<?php echo htmlspecialchars($form['document_ref_no']); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td class="label-cell"><label for="transaction_date">วันที่ทำรายการ</label></td>
                                    <td><input type="date" id="transaction_date" name="transaction_date" value="<?php echo htmlspecialchars($form['transaction_date']); ?>"<?php echo $is_view_locked ? ' readonly' : ''; ?> required></td>
                                </tr>
                            </table>
                        </fieldset>

                        <div class="actions">
                            <?php if (!$selected_doc): ?>
                                <button type="submit" name="action" value="create_doc">บันทึกเอกสารใหม่</button>
                            <?php elseif ($is_edit_mode): ?>
                                <button type="submit" name="action" value="update_doc">บันทึกการแก้ไข</button>
                                <a href="<?php echo htmlspecialchars(buildPageUrl($current_module, ['selected_id' => $selected_id, 'q' => $search_query])); ?>">ยกเลิกแก้ไข</a>
                            <?php else: ?>
                                <?php if ($form['status'] !== 'cancelled'): ?>
                                    <a href="<?php echo htmlspecialchars(buildPageUrl($current_module, ['selected_id' => $selected_id, 'mode' => 'edit', 'q' => $search_query])); ?>">แก้ไข</a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($selected_doc): ?>
                                <button type="button" onclick='openDocumentPopup(<?php echo json_encode('document_view_popup.php?module=' . rawurlencode($current_module) . '&document_no=' . rawurlencode((string)$form['document_no']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);'>ดูเอกสาร</button>
                                <?php if ($form['status'] !== 'cancelled'): ?>
                                    <button type="submit" name="action" value="cancel_doc" onclick="return confirm('ยืนยันการยกเลิกเอกสารนี้?');">ยกเลิกเอกสาร</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete_doc" onclick="return confirm('ยืนยันการลบเอกสารนี้? การลบไม่สามารถย้อนกลับได้');">ลบเอกสาร</button>
                                <a href="<?php echo htmlspecialchars(buildPageUrl($current_module, ['q' => $search_query])); ?>">เอกสารใหม่</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">รายการเอกสาร</div>
                <div class="panel-body">
                    <form method="get" class="search-row" autocomplete="off">
                        <input type="hidden" name="module" value="<?php echo htmlspecialchars($current_module); ?>">
                        <input type="text" name="q" placeholder="ค้นหาเลขเอกสาร, ผู้ขอ, รหัสพนักงาน, ระบบ" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit">ค้นหา</button>
                        <?php if ($search_query !== ''): ?>
                            <a href="<?php echo htmlspecialchars(buildPageUrl($current_module)); ?>">ล้าง</a>
                        <?php endif; ?>
                    </form>

                    <table class="doc-table" cellspacing="0" cellpadding="0">
                        <tr>
                            <th>เลขที่คำร้อง</th>
                            <th>ผู้ขอใช้งาน</th>
                            <th>ระบบ</th>
                            <th>วันที่</th>
                            <th>สถานะ</th>
                            <th>ดูเอกสาร</th>
                        </tr>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="6">ไม่พบข้อมูลเอกสาร</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                                <?php
                                    $is_active_row = ((int)$doc['id'] === (int)$selected_id);
                                    $row_url = buildPageUrl($current_module, ['selected_id' => (int)$doc['id'], 'q' => $search_query]);
                                    $full_name = trim((string)$doc['first_name'] . ' ' . (string)$doc['last_name']);
                                    $popup_url = 'document_view_popup.php?module=' . rawurlencode($current_module) . '&document_no=' . rawurlencode((string)$doc['document_no']);
                                ?>
                                <tr class="<?php echo $is_active_row ? 'active-row' : ''; ?>">
                                    <td><a class="doc-link" href="<?php echo htmlspecialchars($row_url); ?>"><?php echo htmlspecialchars((string)$doc['request_no']); ?></a></td>
                                    <td><?php echo htmlspecialchars($full_name); ?><br><small><?php echo htmlspecialchars((string)$doc['employee_code']); ?></small></td>
                                    <td><?php echo htmlspecialchars((string)$doc['system_name']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$doc['transaction_date']); ?></td>
                                    <td>
                                        <span class="state-badge<?php echo (string)$doc['status'] === 'cancelled' ? ' cancelled' : ''; ?>">
                                            <?php echo (string)$doc['status'] === 'cancelled' ? 'ยกเลิกแล้ว' : 'ใช้งานอยู่'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" onclick='openDocumentPopup(<?php echo json_encode($popup_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);'>ดูเอกสาร</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="company-modal" class="modal" aria-hidden="true">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="company-modal-title">
            <div class="modal-title" id="company-modal-title">เพิ่มข้อมูลบริษัท</div>
            <table class="form-grid" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="label-cell"><label for="reg_type">ประเภทการจดทะเบียน</label></td>
                    <td>
                        <select id="reg_type">
                            <option value="หจก">หจก</option>
                            <option value="บริษัท">บริษัท</option>
                            <option value="บริษัทจำกัดมหาชน">บริษัทจำกัดมหาชน</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="company_modal_name">ชื่อบริษัท</label></td>
                    <td><input type="text" id="company_modal_name"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="company_reg_no">หมายเลขทะเบียนนิติบุคคล</label></td>
                    <td><input type="text" id="company_reg_no" inputmode="numeric" pattern="[0-9]*"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="company_phone">เบอร์โทรบริษัท</label></td>
                    <td><input type="text" id="company_phone" inputmode="numeric" pattern="[0-9]*"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="address_no">ที่อยู่</label></td>
                    <td><input type="text" id="address_no" inputmode="numeric" pattern="[0-9/\- ]*"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="district">อำเภอ</label></td>
                    <td><input type="text" id="district"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="subdistrict">ตำบล</label></td>
                    <td><input type="text" id="subdistrict"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="road">ถนน</label></td>
                    <td><input type="text" id="road"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="soi">ซอย</label></td>
                    <td><input type="text" id="soi"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="moo">หมู่ที่</label></td>
                    <td><input type="text" id="moo"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="province">จังหวัด</label></td>
                    <td><input type="text" id="province"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="postcode">รหัสไปรษณีย์</label></td>
                    <td><input type="text" id="postcode" inputmode="numeric" pattern="[0-9]*"></td>
                </tr>
                <tr>
                    <td class="label-cell"><label for="branch_no">สาขาที่</label></td>
                    <td><input type="text" id="branch_no"></td>
                </tr>
            </table>
            <div class="actions">
                <button type="button" id="save-company-btn">บันทึกบริษัท</button>
                <button type="button" id="close-company-modal">ปิด</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ajax.js"></script>
    <script>
        function openDocumentPopup(url) {
            var popup = window.open(url, 'document_info_popup', 'width=980,height=760,resizable=yes,scrollbars=yes');
            if (popup) {
                popup.focus();
            }
        }

        (function () {
            var form = document.getElementById('doc-form');
            var companySelect = document.getElementById('company_key');
            var companyNameHidden = document.getElementById('company_name');
            var companyDetailHidden = document.getElementById('company_detail');
            var csrfInput = document.querySelector('input[name="csrf_token"]');
            var moduleInput = document.querySelector('input[name="module"]');
            var otherCheckbox = document.getElementById('access_other_chk');
            var otherWrap = document.getElementById('access_other_wrap');
            var otherInput = document.getElementById('access_other');

            function syncCompanyName() {
                if (!companySelect || !companyNameHidden) {
                    return;
                }
                var selectedOption = companySelect.options[companySelect.selectedIndex];
                if (!selectedOption) {
                    companyNameHidden.value = '';
                    if (companyDetailHidden) {
                        companyDetailHidden.value = '';
                    }
                    return;
                }
                companyNameHidden.value = selectedOption.getAttribute('data-company-name') || selectedOption.textContent || '';
                if (companyDetailHidden) {
                    companyDetailHidden.value = selectedOption.getAttribute('data-company-detail') || '';
                }
            }

            function toggleOtherAccess() {
                if (!otherCheckbox || !otherWrap) {
                    return;
                }
                var checked = otherCheckbox.checked;
                otherWrap.classList.toggle('hidden', !checked);
                if (!checked && otherInput) {
                    otherInput.value = '';
                }
            }

            if (companySelect) {
                companySelect.addEventListener('change', syncCompanyName);
                syncCompanyName();
            }

            if (form) {
                form.addEventListener('submit', syncCompanyName);
            }

            if (otherCheckbox) {
                otherCheckbox.addEventListener('change', toggleOtherAccess);
                toggleOtherAccess();
            }

            var modal = document.getElementById('company-modal');
            var openBtn = document.getElementById('open-company-modal');
            var closeBtn = document.getElementById('close-company-modal');
            var saveBtn = document.getElementById('save-company-btn');

            var regType = document.getElementById('reg_type');
            var companyName = document.getElementById('company_modal_name');
            var companyRegNo = document.getElementById('company_reg_no');
            var companyPhone = document.getElementById('company_phone');
            var addressNo = document.getElementById('address_no');
            var district = document.getElementById('district');
            var subdistrict = document.getElementById('subdistrict');
            var road = document.getElementById('road');
            var soi = document.getElementById('soi');
            var moo = document.getElementById('moo');
            var province = document.getElementById('province');
            var postcode = document.getElementById('postcode');
            var branchNo = document.getElementById('branch_no');

            function openModal() {
                if (!modal) {
                    return;
                }
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeModal() {
                if (!modal) {
                    return;
                }
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
            }

            function addCompanyOption() {
                if (!companySelect || !companyName || !regType) {
                    return;
                }

                var nameValue = companyName.value.trim();
                if (nameValue === '') {
                    alert('กรุณากรอกชื่อบริษัท');
                    return;
                }

                var detailParts = [
                    'ทะเบียน: ' + (companyRegNo ? companyRegNo.value.trim() : ''),
                    'โทร: ' + (companyPhone ? companyPhone.value.trim() : ''),
                    'ที่อยู่: ' + (addressNo ? addressNo.value.trim() : ''),
                    'อำเภอ: ' + (district ? district.value.trim() : ''),
                    'ตำบล: ' + (subdistrict ? subdistrict.value.trim() : ''),
                    'ถนน: ' + (road ? road.value.trim() : ''),
                    'ซอย: ' + (soi ? soi.value.trim() : ''),
                    'หมู่: ' + (moo ? moo.value.trim() : ''),
                    'จังหวัด: ' + (province ? province.value.trim() : ''),
                    'รหัสไปรษณีย์: ' + (postcode ? postcode.value.trim() : ''),
                    'สาขา: ' + (branchNo ? branchNo.value.trim() : '')
                ];

                var formData = new FormData();
                formData.append('action', 'add_company_ajax');
                formData.append('csrf_token', csrfInput ? csrfInput.value : '');
                formData.append('module', moduleInput ? moduleInput.value : 'setup');
                formData.append('reg_type', regType.value);
                formData.append('company_modal_name', nameValue);
                formData.append('company_reg_no', companyRegNo ? companyRegNo.value.trim() : '');
                formData.append('company_phone', companyPhone ? companyPhone.value.trim() : '');
                formData.append('address_no', addressNo ? addressNo.value.trim() : '');
                formData.append('district', district ? district.value.trim() : '');
                formData.append('subdistrict', subdistrict ? subdistrict.value.trim() : '');
                formData.append('road', road ? road.value.trim() : '');
                formData.append('soi', soi ? soi.value.trim() : '');
                formData.append('moo', moo ? moo.value.trim() : '');
                formData.append('province', province ? province.value.trim() : '');
                formData.append('postcode', postcode ? postcode.value.trim() : '');
                formData.append('branch_no', branchNo ? branchNo.value.trim() : '');

                if (!window.AppAjax || typeof window.AppAjax.postMultipart !== 'function') {
                    alert('ไม่พบระบบ AppAjax กลาง');
                    return;
                }

                window.AppAjax.postMultipart(window.location.href, formData, { requireSuccess: false })
                .then(function (result) {
                    if (!result || !result.ok) {
                        alert(result && result.message ? result.message : 'ไม่สามารถเพิ่มบริษัทได้');
                        return;
                    }

                    var optionKey = String(result.option_key || ('CUSTOM_' + Date.now()));
                    var displayName = String(result.company_name || (regType.value + ' ' + nameValue));
                    var detailText = String(result.company_detail || detailParts.join(' | '));

                    var option = companySelect.querySelector('option[value="' + optionKey.replace(/"/g, '\\"') + '"]');
                    if (!option) {
                        option = document.createElement('option');
                        option.value = optionKey;
                        companySelect.appendChild(option);
                    }

                    option.textContent = displayName;
                    option.setAttribute('data-company-name', displayName);
                    option.setAttribute('data-company-detail', detailText);
                    companySelect.value = optionKey;

                    if (companyNameHidden) {
                        companyNameHidden.value = displayName;
                    }
                    if (companyDetailHidden) {
                        companyDetailHidden.value = detailText;
                    }

                    closeModal();
                })
                .catch(function () {
                    alert('ไม่สามารถเชื่อมต่อเพื่อเพิ่มบริษัทได้');
                });
            }

            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            if (saveBtn) {
                saveBtn.addEventListener('click', addCompanyOption);
            }
            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }
        })();
    </script>
</body>
</html>
