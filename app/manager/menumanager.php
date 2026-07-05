<?php
require_once '../../auth/config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!canCurrentUserAccessDashboard($access_pdo, '../app/manager/menumanager')) {
        header("Location: ../../auth/login");
        exit();
    }

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/manager/menumanager'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in menumanager.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลักหัวหน้า - Office Plus</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: #fd7e14;
            margin-bottom: 10px;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .manager-menu {
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
            color: #fd7e14;
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

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .profile-card h2 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .profile-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .profile-label {
            font-weight: 600;
            color: #555;
        }

        .profile-value {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">Office Plus - ระบบหัวหน้า</div>
            <div class="user-info">
                <span class="user-name">สวัสดี, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                <a href="../../auth/logout.php" class="logout-btn">ออกจากระบบ</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>ยินดีต้อนรับสู่ระบบหัวหน้า</h1>
            <p>จัดการทีมงานและกำกับดูแลการทำงานของฝ่ายคุณ</p>
        </div>

        <div class="profile-card">
            <h2>👤 ข้อมูลส่วนตัว</h2>
            <div class="profile-info">
                <div class="profile-item">
                    <span class="profile-label">ชื่อ-นามสกุล:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">อีเมล:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">ตำแหน่ง:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($_SESSION['position']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">ฝ่าย:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($_SESSION['department']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">บริษัท:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($_SESSION['company']); ?></span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">สิทธิ์:</span>
                    <span class="profile-value">หัวหน้า</span>
                </div>
            </div>
        </div>

        <?php
        // ดึงสถิติสำหรับหัวหน้า
        try {
            $pdo = getDBConnection();
            
            // นับจำนวนพนักงานในฝ่ายเดียวกัน
            $team_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department = ? AND user_role = 'employee' AND is_active = 1");
            $team_stmt->execute([$_SESSION['department']]);
            $team_count = $team_stmt->fetchColumn();
            
            // นับจำนวนพนักงานที่ล็อกอินวันนี้ในฝ่ายเดียวกัน
            $today_login_stmt = $pdo->prepare("SELECT COUNT(DISTINCT l.user_id) FROM login_logs l 
                                              JOIN users u ON l.user_id = u.user_id 
                                              WHERE u.department = ? AND DATE(l.login_time) = CURDATE() 
                                              AND l.login_status = 'success'");
            $today_login_stmt->execute([$_SESSION['department']]);
            $today_team_logins = $today_login_stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $team_count = $today_team_logins = 0;
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($team_count); ?></div>
                <div class="label">พนักงานในทีม</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo number_format($today_team_logins); ?></div>
                <div class="label">ล็อกอินวันนี้</div>
            </div>
            <div class="stat-card">
                <div class="number">0</div>
                <div class="label">งานค้างอนุมัติ</div>
            </div>
            <div class="stat-card">
                <div class="number">0</div>
                <div class="label">รายงานใหม่</div>
            </div>
        </div>

        <div class="manager-menu">
            <div class="menu-card" onclick="location.href='team'">
                <div class="icon">👥</div>
                <h3>จัดการทีมงาน</h3>
                <p>ดูข้อมูลและจัดการพนักงานในทีม</p>
            </div>

            <div class="menu-card" onclick="location.href='projects'">
                <div class="icon">📊</div>
                <h3>โครงการ</h3>
                <p>ติดตามและจัดการโครงการของฝ่าย</p>
            </div>

            <div class="menu-card" onclick="location.href='approval'">
                <div class="icon">✅</div>
                <h3>อนุมัติงาน</h3>
                <p>อนุมัติการลาหยุดและคำขอต่างๆ</p>
            </div>

            <div class="menu-card" onclick="location.href='reports'">
                <div class="icon">📈</div>
                <h3>รายงานฝ่าย</h3>
                <p>ดูรายงานผลงานและสถิติของฝ่าย</p>
            </div>

            <div class="menu-card" onclick="location.href='schedule'">
                <div class="icon">📅</div>
                <h3>จัดตารางงาน</h3>
                <p>วางแผนและจัดสรรงานให้ทีม</p>
            </div>

            <div class="menu-card" onclick="location.href='performance'">
                <div class="icon">🎯</div>
                <h3>ประเมินผลงาน</h3>
                <p>ประเมินและติดตามผลงานพนักงาน</p>
            </div>

            <div class="menu-card" onclick="location.href='meetings'">
                <div class="icon">🤝</div>
                <h3>ประชุม</h3>
                <p>จัดการการประชุมและนัดหมาย</p>
            </div>

            <div class="menu-card" onclick="location.href='training'">
                <div class="icon">🎓</div>
                <h3>อบรมพัฒนา</h3>
                <p>วางแผนการอบรมและพัฒนาทีม</p>
            </div>
        </div>
    </div>
</body>
</html>