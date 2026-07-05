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
$current_module = isset($_GET['module']) ? trim((string)$_GET['module']) : 'setup';
if (!in_array($current_module, $allowed_modules, true)) {
    $current_module = 'setup';
}

$document_no = strtoupper(trim((string)($_GET['document_no'] ?? '')));
$document = null;
$error_message = '';
$company_settings = null;

$role_labels = [
    'normal' => 'ผู้ใช้งานธรรมดา',
    'admin' => 'แอดมิน',
    'manager' => 'หัวหน้างาน',
    'executive' => 'ผู้บริหาร',
    'other' => 'อื่นๆ'
];

if ($document_no === '') {
    $error_message = 'ไม่พบเลขที่เอกสาร';
} else {
    try {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare('SELECT * FROM document_requests WHERE document_no = ? LIMIT 1');
        $stmt->execute([$document_no]);
        $document = $stmt->fetch();

        if (!$document) {
            $error_message = 'ไม่พบข้อมูลเอกสารเลขที่ ' . $document_no;
        } else {
            try {
                $company_stmt = $pdo->query('SELECT * FROM company_settings WHERE id = 1 LIMIT 1');
                $company_settings = $company_stmt->fetch() ?: null;
            } catch (PDOException $inner_e) {
                error_log('Company settings load warning in app/admin/document_view_popup.php: ' . $inner_e->getMessage());
            }
        }
    } catch (PDOException $e) {
        error_log('Database error in app/admin/document_view_popup.php: ' . $e->getMessage());
        $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูลเอกสาร';
    }
}

$access_values = [];
$access_labels = [];
if ($document) {
    $decoded_access = json_decode((string)($document['access_levels'] ?? '[]'), true);
    $access_values = is_array($decoded_access) ? $decoded_access : [];
    foreach ($access_values as $access_key) {
        $key = (string)$access_key;
        $access_labels[] = $role_labels[$key] ?? $key;
    }
}

$header_business_name = (string)($company_settings['business_name'] ?? ($document['company_name'] ?? ''));
$header_company_type = (string)($company_settings['company_type'] ?? '');
$header_tax_id = (string)($company_settings['tax_id'] ?? '');
$header_trade_no = (string)($company_settings['trade_registration_no'] ?? '');
$header_branch_code = (string)($company_settings['branch_code'] ?? '');
$header_branch_name = (string)($company_settings['branch_name'] ?? '');
$header_phone = (string)($company_settings['office_phone'] ?? '');
$header_email = (string)($company_settings['email'] ?? '');
$header_logo_path = (string)($company_settings['header_logo_path'] ?? '');

$header_address_parts = [];
if (!empty($company_settings['address_no'])) {
    $header_address_parts[] = 'เลขที่ ' . (string)$company_settings['address_no'];
}
if (!empty($company_settings['moo'])) {
    $header_address_parts[] = 'หมู่ ' . (string)$company_settings['moo'];
}
if (!empty($company_settings['road'])) {
    $header_address_parts[] = 'ถนน' . (string)$company_settings['road'];
}
if (!empty($company_settings['subdistrict'])) {
    $header_address_parts[] = 'ต.' . (string)$company_settings['subdistrict'];
}
if (!empty($company_settings['district'])) {
    $header_address_parts[] = 'อ.' . (string)$company_settings['district'];
}
if (!empty($company_settings['province'])) {
    $header_address_parts[] = 'จ.' . (string)$company_settings['province'];
}
if (!empty($company_settings['postal_code'])) {
    $header_address_parts[] = (string)$company_settings['postal_code'];
}

$header_address = implode(' ', $header_address_parts);

$to_full_name = trim((string)($document['first_name'] ?? '') . ' ' . (string)($document['last_name'] ?? ''));
$access_text = implode(', ', $access_labels);
$request_ref_no = (string)($document['request_ref_no'] ?? '');
$document_ref_no = (string)($document['document_ref_no'] ?? '');
$qr_svg_markup = '';
$barcode_svg_markup = '';
$document_detail_items = [];

if ($document) {
    $qrcode_autoload = __DIR__ . '/Extension/php-qrcode/vendor/autoload.php';
    $barcode_autoload = __DIR__ . '/Extension/php-barcode-generator/vendor/autoload.php';

    if (is_file($qrcode_autoload)) {
        require_once $qrcode_autoload;
    }
    if (is_file($barcode_autoload)) {
        require_once $barcode_autoload;
    }

    try {
        if (
            $request_ref_no !== ''
            && class_exists('chillerlan\\QRCode\\QRCode')
            && class_exists('chillerlan\\QRCode\\QROptions')
            && class_exists('chillerlan\\QRCode\\Output\\QRMarkupSVG')
        ) {
            $qr_options = new \chillerlan\QRCode\QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
                'scale' => 3,
                'outputBase64' => false,
                'svgAddXmlHeader' => false,
            ]);

            $qr_svg_markup = (string)(new \chillerlan\QRCode\QRCode($qr_options))->render($request_ref_no);
        }
    } catch (Throwable $e) {
        error_log('QR Code generation warning in app/admin/document_view_popup.php: ' . $e->getMessage());
    }

    try {
        if ($document_ref_no !== '' && class_exists('Picqer\\Barcode\\BarcodeGeneratorSVG')) {
            $barcode_generator = new \Picqer\Barcode\BarcodeGeneratorSVG();
            $barcode_svg_markup = (string)$barcode_generator->getBarcode($document_ref_no, $barcode_generator::TYPE_CODE_128, 1.4, 34);
        }
    } catch (Throwable $e) {
        error_log('Barcode generation warning in app/admin/document_view_popup.php: ' . $e->getMessage());
    }

    $document_detail_items = [
        ['label' => 'เลขที่คำร้องขอ', 'value' => (string)$document['request_no']],
        ['label' => 'หมายเลขที่เอกสาร', 'value' => (string)$document['document_no']],
        ['label' => 'ผู้ขอใช้งาน', 'value' => $to_full_name],
        ['label' => 'รหัสพนักงาน', 'value' => (string)$document['employee_code']],
        ['label' => 'สังกัดบริษัท', 'value' => (string)$document['company_name']],
        ['label' => 'เบอร์โทรติดต่อ', 'value' => (string)$document['contact_phone']],
        ['label' => 'อีเมล์', 'value' => (string)$document['email']],
        ['label' => 'ชื่อระบบ', 'value' => (string)$document['system_name']],
        ['label' => 'ระดับการใช้งาน', 'value' => (string)$document['usage_level']],
        ['label' => 'ระดับสิทธิ์การเข้าถึง', 'value' => $access_text],
        ['label' => 'สิทธิ์อื่นๆ', 'value' => (string)($document['access_other'] ?? '')],
        ['label' => 'ผู้สร้าง', 'value' => (string)($document['created_by'] ?? '')],
        ['label' => 'ผู้แก้ไขล่าสุด', 'value' => (string)($document['updated_by'] ?? '')],
        ['label' => 'สร้างเมื่อ', 'value' => (string)($document['created_at'] ?? '')],
        ['label' => 'แก้ไขล่าสุด', 'value' => (string)($document['updated_at'] ?? '')],
        ['label' => 'รายละเอียดบริษัท', 'value' => (string)($document['company_detail'] ?? ''), 'full' => true],
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ดูเอกสาร</title>
    <style>
        @page {
            size: A4;
            margin: 7mm;
        }

        body {
            margin: 0;
            background: #eceff3;
            color: #1f2933;
            font-family: "TH Sarabun New", "Sarabun", Tahoma, sans-serif;
            font-size: 12px;
            line-height: 1.3;
        }

        .document-page {
            width: 190mm;
            min-height: 277mm;
            margin: 6px auto;
            padding: 7mm 8mm 6mm;
            box-sizing: border-box;
            background: #fff;
            box-shadow: none;
            display: flex;
            flex-direction: column;
        }

        .document-content {
            flex: 1;
        }

        .letter-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 5mm;
            border-bottom: 0;
            padding-bottom: 2mm;
        }

        .header-company {
            width: 66%;
            font-size: 11px;
        }

        .company-name {
            margin: 0 0 1mm;
            font-size: 15px;
            line-height: 1.2;
        }

        .header-title {
            width: 34%;
            text-align: right;
        }

        .header-logo {
            max-height: 50px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 1mm;
        }

        .document-title {
            margin: 0;
            font-size: 14px;
            line-height: 1.25;
            font-weight: 700;
        }

        .letter-meta {
            margin-top: 2mm;
            font-size: 11px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            padding: 0.6mm 0;
            border-bottom: 0;
        }

        .meta-left {
            width: 68%;
        }

        .meta-right {
            width: 30%;
            text-align: right;
        }

        .intro-text {
            margin: 2mm 0 2.2mm;
            font-size: 11px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.5mm 2.2mm;
        }

        .info-card {
            border: 0;
            background: transparent;
            border-radius: 0;
            padding: 0.8mm 0;
        }

        .info-card.full {
            grid-column: 1 / -1;
        }

        .info-label {
            color: #4b6178;
            font-size: 10px;
            line-height: 1.2;
        }

        .info-value {
            margin-top: 0.4mm;
            color: #13212f;
            font-size: 11px;
            line-height: 1.25;
            word-break: break-word;
        }

        .closing-note {
            margin: 2.5mm 0 0;
            font-size: 11px;
        }

        .signature-section {
            margin-top: 4mm;
            font-size: 10px;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 2.5mm;
        }

        .signature-card {
            min-height: 30mm;
            page-break-inside: avoid;
        }

        .signature-role-title {
            margin: 0 0 3mm;
            font-weight: 700;
            text-align: center;
            color: #1f2933;
        }

        .signature-sign-line {
            margin-bottom: 2mm;
            white-space: nowrap;
        }

        .signature-field {
            margin-bottom: 1.4mm;
            white-space: nowrap;
        }

        .terms-section {
            margin-top: 2.2mm;
            font-size: 9.5px;
            line-height: 1.25;
            color: #243447;
        }

        .terms-title {
            margin: 0 0 1mm;
            font-size: 10px;
            font-weight: 700;
        }

        .terms-list {
            margin: 0;
            padding-left: 4.2mm;
        }

        .terms-list li {
            margin-bottom: 0.7mm;
        }

        .credentials-section {
            margin-top: 1.8mm;
            display: flex;
            justify-content: flex-start;
        }

        .credentials-box {
            position: relative;
            width: 74mm;
            min-height: 18mm;
            border: 1px solid #64748b;
            padding: 2.2mm 2.8mm;
            box-sizing: border-box;
            overflow: hidden;
        }

        .credentials-title {
            margin: 0 0 1.2mm;
            font-size: 9.5px;
            font-weight: 700;
            position: relative;
            z-index: 1;
            color: #1f2933;
        }

        .credentials-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.8px;
            position: relative;
            z-index: 1;
        }

        .credentials-table td {
            padding: 0.7mm 0;
        }

        .credentials-label {
            width: 24mm;
            white-space: nowrap;
            font-weight: 700;
        }

        .credentials-line {
            border-bottom: 1px solid #475569;
            height: 4mm;
        }

        .credentials-watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: rgba(71, 85, 105, 0.16);
            transform: rotate(-20deg);
            pointer-events: none;
            user-select: none;
        }

        .credentials-watermark.top {
            inset: -20% 0 auto 0;
            transform: rotate(-20deg);
        }

        .credentials-watermark.bottom {
            inset: auto 0 -20% 0;
            transform: rotate(-20deg);
        }

        .credentials-watermark.left {
            inset: 0 auto 0 -26%;
            transform: rotate(-20deg);
        }

        .credentials-watermark.right {
            inset: 0 -26% 0 auto;
            transform: rotate(-20deg);
        }

        .document-footer {
            margin-top: 3.5mm;
            padding-top: 1.8mm;
            border-top: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 6mm;
        }

        .footer-box {
            width: 48%;
            font-size: 10px;
        }

        .footer-box-left {
            text-align: left;
        }

        .footer-box-right {
            text-align: right;
        }

        .footer-title {
            margin-bottom: 2px;
            font-weight: 700;
        }

        .footer-qr-svg {
            width: 56px;
            height: 56px;
            border: 0;
            padding: 0;
            background: transparent;
        }

        .footer-qr-svg svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .footer-barcode-svg {
            width: 160px;
            max-width: 100%;
            height: 28px;
            background: transparent;
            margin-left: auto;
        }

        .footer-barcode-svg svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .footer-placeholder {
            color: #666;
            font-style: italic;
        }

        .footer-ref {
            margin-top: 1px;
            font-size: 9px;
            color: #333;
            word-break: break-all;
        }

        #action-buttons {
            margin: 12px 0 20px;
        }

        @media print {
            body {
                background: #fff;
            }

            .document-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .info-card {
                break-inside: avoid;
                padding: 0.5mm 0;
            }

            .info-grid {
                gap: 1mm 1.8mm;
            }

            .closing-note {
                margin-top: 2mm;
            }

            .signature-section {
                margin-top: 3mm;
            }

            .signature-grid {
                gap: 2mm;
            }

            .signature-card {
                min-height: 27mm;
            }

            .terms-section {
                margin-top: 1.6mm;
                font-size: 9px;
            }

            .terms-list li {
                margin-bottom: 0.5mm;
            }

            .credentials-section {
                margin-top: 1.2mm;
            }

            .credentials-box {
                width: 70mm;
                min-height: 16mm;
                padding: 1.8mm 2.2mm;
            }

            .credentials-title {
                margin-bottom: 0.8mm;
                font-size: 9px;
            }

            .credentials-table {
                font-size: 9px;
            }

            .credentials-label {
                width: 22mm;
            }

            .credentials-line {
                height: 3.5mm;
            }

            .credentials-watermark {
                font-size: 12px;
            }

            .document-footer {
                margin-top: 2.5mm;
                padding-top: 1.2mm;
            }

            .footer-qr-svg {
                width: 52px;
                height: 52px;
            }

            .footer-barcode-svg {
                width: 150px;
                height: 24px;
            }

            #action-buttons {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php if ($error_message !== ''): ?>
        <p><?php echo htmlspecialchars($error_message); ?></p>
    <?php else: ?>
        <div class="document-page">
            <div class="document-content">
                <div class="letter-header">
                    <div class="header-company">
                        <h2 class="company-name"><?php echo htmlspecialchars(trim($header_company_type . ' ' . $header_business_name)); ?></h2>
                        <?php if ($header_address !== ''): ?>
                            <div><?php echo htmlspecialchars($header_address); ?></div>
                        <?php endif; ?>
                        <?php if ($header_tax_id !== ''): ?>
                            <div>เลขประจำตัวผู้เสียภาษี: <?php echo htmlspecialchars($header_tax_id); ?></div>
                        <?php endif; ?>
                        <?php if ($header_trade_no !== ''): ?>
                            <div>เลขทะเบียนการค้า: <?php echo htmlspecialchars($header_trade_no); ?></div>
                        <?php endif; ?>
                        <?php if ($header_branch_code !== '' || $header_branch_name !== ''): ?>
                            <div>สาขา: <?php echo htmlspecialchars($header_branch_code); ?> <?php echo htmlspecialchars($header_branch_name); ?></div>
                        <?php endif; ?>
                        <?php if ($header_phone !== ''): ?>
                            <div>โทรศัพท์: <?php echo htmlspecialchars($header_phone); ?></div>
                        <?php endif; ?>
                        <?php if ($header_email !== ''): ?>
                            <div>อีเมล์: <?php echo htmlspecialchars($header_email); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="header-title">
                        <?php if ($header_logo_path !== ''): ?>
                            <img src="../../<?php echo htmlspecialchars($header_logo_path); ?>" alt="Header Logo" class="header-logo">
                        <?php endif; ?>
                        <h1 class="document-title">หนังสือแจ้งสิทธิ์การเข้าใช้งานระบบ</h1>
                    </div>
                </div>

                <div class="letter-meta">
                    <div class="meta-row">
                        <div class="meta-left">เรียน: <?php echo htmlspecialchars($to_full_name); ?></div>
                        <div class="meta-right">วันที่: <?php echo htmlspecialchars((string)$document['transaction_date']); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-left">รหัสพนักงาน: <?php echo htmlspecialchars((string)$document['employee_code']); ?></div>
                        <div class="meta-right">สถานะ: <?php echo htmlspecialchars((string)$document['status']); ?></div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-left">เรื่อง: แจ้งข้อมูลการเข้าถึงระบบ</div>
                        <div class="meta-right"></div>
                    </div>
                </div>

                <p class="intro-text">
                    ตามที่ท่านได้ยื่นคำร้องเพื่อเข้าใช้งานระบบ ทางผู้ดูแลได้บันทึกข้อมูลเอกสารและสิทธิ์การเข้าใช้งานเรียบร้อยแล้ว
                    รายละเอียดข้อมูลปรากฏตามรายการด้านล่างนี้
                </p>

                <div class="info-grid">
                    <?php foreach ($document_detail_items as $detail_item): ?>
                        <?php
                            $detail_label = (string)($detail_item['label'] ?? '');
                            $detail_value = trim((string)($detail_item['value'] ?? ''));
                            $detail_full = !empty($detail_item['full']);
                            if ($detail_value === '') {
                                $detail_value = '-';
                            }
                        ?>
                        <div class="info-card<?php echo $detail_full ? ' full' : ''; ?>">
                            <div class="info-label"><?php echo htmlspecialchars($detail_label); ?></div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($detail_value)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="closing-note">
                    จึงเรียนมาเพื่อทราบ<br>
                    ขอแสดงความนับถือ
                </p>

                <div class="signature-section">
                    <div class="signature-grid">
                        <div class="signature-card">
                            <p class="signature-role-title">เจ้าหน้าที่ออกเอกสาร</p>
                            <div class="signature-sign-line">ลงลายมือชื่อ .......................................</div>
                            <div class="signature-field">ชื่อ ......................................................</div>
                            <div class="signature-field">ตำแหน่ง ................................................</div>
                            <div class="signature-field">วันที่ .....................................................</div>
                        </div>

                        <div class="signature-card">
                            <p class="signature-role-title">พนักงานผู้ขอใช้</p>
                            <div class="signature-sign-line">ลงลายมือชื่อ .......................................</div>
                            <div class="signature-field">ชื่อ ......................................................</div>
                            <div class="signature-field">ตำแหน่ง ................................................</div>
                            <div class="signature-field">วันที่ .....................................................</div>
                        </div>

                        <div class="signature-card">
                            <p class="signature-role-title">ผู้อนุมัติ</p>
                            <div class="signature-sign-line">ลงลายมือชื่อ .......................................</div>
                            <div class="signature-field">ชื่อ ......................................................</div>
                            <div class="signature-field">ตำแหน่ง ................................................</div>
                            <div class="signature-field">วันที่ .....................................................</div>
                        </div>
                    </div>
                </div>

                <div class="terms-section">
                    <p class="terms-title">ข้อกำหนดการใช้งาน</p>
                    <ol class="terms-list">
                        <li>เอกสารฉบับนี้ใช้สำหรับกำหนดสิทธิ์การเข้าถึงระบบตามรายการที่ระบุเท่านั้น และห้ามนำไปใช้ผิดวัตถุประสงค์</li>
                        <li>ผู้ขอใช้ต้องเก็บรักษาบัญชีผู้ใช้งานและรหัสผ่านเป็นความลับ และรับผิดชอบการใช้งานภายใต้บัญชีของตน</li>
                        <li>ห้ามเผยแพร่ข้อมูลภายในระบบให้บุคคลที่ไม่มีสิทธิ์ หากพบความผิดปกติให้แจ้งผู้ดูแลระบบทันที</li>
                        <li>สิทธิ์การใช้งานอาจถูกปรับเปลี่ยน ระงับ หรือยกเลิกได้ตามนโยบายบริษัทโดยไม่ต้องแจ้งล่วงหน้า</li>
                    </ol>
                </div>

                <div class="credentials-section">
                    <div class="credentials-box">
                        <div class="credentials-watermark top">CONFIDENTIAL</div>
                        <div class="credentials-watermark left">CONFIDENTIAL</div>
                        <div class="credentials-watermark">CONFIDENTIAL</div>
                        <div class="credentials-watermark right">CONFIDENTIAL</div>
                        <div class="credentials-watermark bottom">CONFIDENTIAL</div>
                        <p class="credentials-title">ข้อมูลสำหรับการเข้าใช้งานระบบ (ห้ามเผยแพร่โดยเด็ดขาด)</p>
                        <table class="credentials-table" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="credentials-label">USERNAME :</td>
                                <td class="credentials-line"></td>
                            </tr>
                            <tr>
                                <td class="credentials-label">PASSWORD :</td>
                                <td class="credentials-line"></td>
                            </tr>
                            
                        </table>
                        <div style="margin-top: 4mm; font-size: 9px; color: #475569;">
                            * หากไม่สามารถเข้าใช้งานได้ กรุณาติดต่อผู้ดูแลระบบเพื่อขอรับข้อมูลการเข้าใช้งานใหม่อีกครั้ง 
                    </div>
                </div>
            </div>

            <div class="document-footer">
                <div class="footer-box footer-box-left">
                    <div class="footer-title">หมายเลขอ้างอิงคำร้อง (QR Code)</div>
                    <?php if ($qr_svg_markup !== ''): ?>
                        <div class="footer-qr-svg"><?php echo $qr_svg_markup; ?></div>
                    <?php else: ?>
                        <div class="footer-placeholder">ไม่สามารถสร้าง QR Code ได้</div>
                    <?php endif; ?>
                    <div class="footer-ref"><?php echo htmlspecialchars($request_ref_no); ?></div>
                </div>

                <div class="footer-box footer-box-right">
                    <div class="footer-title">หมายเลขอ้างอิงเอกสาร (Barcode)</div>
                    <?php if ($barcode_svg_markup !== ''): ?>
                        <div class="footer-barcode-svg"><?php echo $barcode_svg_markup; ?></div>
                    <?php else: ?>
                        <div class="footer-placeholder">ไม่สามารถสร้าง Barcode ได้</div>
                    <?php endif; ?>
                    <div class="footer-ref"><?php echo htmlspecialchars($document_ref_no); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <p align="center" id="action-buttons">
        <button type="button" onclick="printDocument();">พิมพ์</button>
        <button type="button" onclick="window.close();">ปิดหน้าต่าง</button>
    </p>

    <script>
        (function () {
            var actionButtons = document.getElementById('action-buttons');

            function hideActionButtons() {
                if (actionButtons) {
                    actionButtons.hidden = true;
                }
            }

            function showActionButtons() {
                if (actionButtons) {
                    actionButtons.hidden = false;
                }
            }

            window.addEventListener('beforeprint', hideActionButtons);
            window.addEventListener('afterprint', showActionButtons);

            window.printDocument = function () {
                hideActionButtons();
                window.print();
                setTimeout(showActionButtons, 300);
            };
        })();
    </script>
</body>
</html>
