<?php
require_once '../../auth/config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!canCurrentUserAccessDashboard($access_pdo, '../app/employee/menuemployee')) {
        header("Location: ../../auth/login");
        exit();
    }

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/employee/menuemployee'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in menuemployee.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลักพนักงาน - Office Plus</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            color: #333;
            font-weight: 500;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .welcome-card h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-card p {
            color: #666;
            font-size: 16px;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .profile-card h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .profile-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .profile-label {
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .profile-value {
            color: #333;
            font-size: 16px;
            padding: 8px 0;
        }

        .employee-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .menu-card .icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #28a745;
        }

        .menu-card h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .menu-card p {
            color: #666;
            font-size: 14px;
        }

        .quick-actions {
            margin-top: 30px;
        }

        .quick-actions h2 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">Office Plus - ระบบพนักงาน</div>
            <div class="user-info">
                <span class="user-name">สวัสดี, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                <a href="../../auth/logout.php" class="logout-btn">ออกจากระบบ</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>ยินดีต้อนรับเข้าสู่ระบบ</h1>
            <p>พื้นที่การทำงานส่วนบุคคลของคุณ</p>
        </div>

        <div class="profile-card">
            <h2>📋 ข้อมูลส่วนตัว</h2>
            <div class="profile-grid">
                <div class="profile-item">
                    <div class="profile-label">ชื่อ-นามสกุล</div>
                    <div class="profile-value"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">อีเมล</div>
                    <div class="profile-value"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">ตำแหน่ง</div>
                    <div class="profile-value"><?php echo htmlspecialchars($_SESSION['position']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">ฝ่าย</div>
                    <div class="profile-value"><?php echo htmlspecialchars($_SESSION['department']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">บริษัท</div>
                    <div class="profile-value"><?php echo htmlspecialchars($_SESSION['company']); ?></div>
                </div>
                <div class="profile-item">
                    <div class="profile-label">สิทธิ์การใช้งาน</div>
                    <div class="profile-value">พนักงาน</div>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <h2>🚀 เมนูการทำงาน</h2>
            <div class="employee-menu">
                <div class="menu-card" onclick="location.href='../sell/page_customer_management.php?module=customer'">
                    <div class="icon">🤝</div>
                    <h3>CRM ลูกค้าของฉัน</h3>
                    <p>เพิ่ม แก้ไข และติดตามลูกค้าที่คุณรับผิดชอบในทีมขาย</p>
                </div>

                <div class="menu-card" onclick="location.href='../sell/page_customer_list.php?module=customer'">
                    <div class="icon">📋</div>
                    <h3>รายการลูกค้า CRM</h3>
                    <p>ค้นหาและดูสถานะลูกค้าของคุณ พร้อมส่งออกข้อมูลได้</p>
                </div>

                <div class="menu-card" onclick="location.href='../sell/join_group_sales.php?module=groupsjoin'">
                    <div class="icon">👥</div>
                    <h3>เข้าร่วมทีมขาย</h3>
                    <p>กรอกรหัสเชิญเพื่อเข้าร่วมทีมขายในสาขาปัจจุบัน</p>
                </div>

                <div class="menu-card" onclick="location.href='tasks'">
                    <div class="icon">📝</div>
                    <h3>งานของฉัน</h3>
                    <p>ดูงานที่ได้รับมอบหมายและอัพเดทสถานะ</p>
                </div>

                <div class="menu-card" onclick="location.href='timesheet'">
                    <div class="icon">⏰</div>
                    <h3>บันทึกเวลาทำงาน</h3>
                    <p>ลงเวลาเข้า-ออกงานและดูประวัติการทำงาน</p>
                </div>

                <div class="menu-card" onclick="location.href='documents'">
                    <div class="icon">📄</div>
                    <h3>เอกสารงาน</h3>
                    <p>เข้าถึงเอกสารและไฟล์งานที่เกี่ยวข้อง</p>
                </div>

                <div class="menu-card" onclick="location.href='leave'">
                    <div class="icon">🏖️</div>
                    <h3>ขอลาหยุด</h3>
                    <p>ส่งคำขอลาหยุดและตรวจสอบสถานะ</p>
                </div>

                <div class="menu-card" onclick="location.href='profile'">
                    <div class="icon">👤</div>
                    <h3>จัดการโปรไฟล์</h3>
                    <p>แก้ไขข้อมูลส่วนตัวและเปลี่ยนรหัสผ่าน</p>
                </div>

                <div class="menu-card" onclick="location.href='announcements'">
                    <div class="icon">📢</div>
                    <h3>ประกาศ</h3>
                    <p>ดูประกาศและข่าวสารจากบริษัท</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>