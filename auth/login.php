<?php
require_once 'config.php';

// ถ้าล็อกอินแล้วให้ redirect ไปยังหน้าที่เหมาะสม
if (isLoggedIn()) {
    try {
        $pdo = getDBConnection();
        $redirectPath = getDashboardByRole($pdo, (string)($_SESSION['user_role'] ?? ''));

        if (shouldRequireBranchSelection($pdo)) {
            $active_branch_id = getCurrentBranchId();
            if ($active_branch_id === '' || !setCurrentBranchContext($pdo, $active_branch_id)) {
                header('Location: branch_selector_popup.php?redirect=' . rawurlencode($redirectPath));
                exit();
            }
        }
    } catch (Throwable $e) {
        $redirectPath = getDefaultDashboardByRole((string)($_SESSION['user_role'] ?? ''));
    }

    header("Location: " . $redirectPath);
    exit();
}

// สร้าง CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>เข้าสู่ระบบ - Office Plus</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <style>
        .logo-container {
            position: relative;
            z-index: 3;
        }
        
        .logo-container img {
            display: block;
            width: auto;
            max-width: 100%;
            height: auto;
            max-height: 100%;
            object-fit: contain;
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            filter: none !important;
            transition: none !important;
            transform: none !important;
            opacity: 1 !important;
            outline: none !important;
            animation: none !important;
            pointer-events: none;
        }
    </style>
</head>
<body>
    
    <!-- Logo at top left corner -->
    <div class="logo-container" id="logo-container">
        <img src="../assets/images/logo/logo3.png" alt="Office Plus Logo" id="logo-image">
    </div>

    <div class="login-container">
        <!-- ขั้นตอนที่ 1: กรอกรหัสผู้ใช้งาน -->
        <div id="step1" class="login-step">
            <div class="login-header">
                <h1>เข้าสู่ระบบ</h1>
                <p>กรุณากรอกรหัสผู้ใช้งานเพื่อเข้าสู่ระบบ</p>
            </div>

            <form id="userIdForm">
                <div class="form-group">
                    <label for="userId">รหัสผู้ใช้งาน</label>
                    <input type="text" id="userId" name="user_id" placeholder="รหัสผู้ใช้งาน" required>
                </div>

                <button type="submit" class="btn btn-primary">ถัดไป</button>
            </form>
        </div>

        <!-- ขั้นตอนที่ 2: กรอกรหัสผ่าน -->
        <div id="step2" class="login-step" style="display: none;">
            <div class="login-header">
                <h1>ยืนยันตัวตน</h1>
                <p>กรุณากรอกรหัสผ่านเพื่อเข้าสู่ระบบ</p>
            </div>

            <fieldset class="user-info">
                <legend id="displayName">ชื่อผู้ใช้</legend>
                <p><strong>บริษัท:</strong> <span id="displayCompany"></span></p>
                <p><strong>ฝ่าย:</strong> <span id="displayDepartment"></span></p>
                <p><strong>ตำแหน่ง:</strong> <span id="displayPosition"></span></p>
            </fieldset>

            <form id="passwordForm">
                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-success">เข้าสู่ระบบ</button>
                <button type="button" class="btn btn-secondary back-button">ย้อนกลับ</button>
            </form>
        </div>
 <div class="register-link">
                <p>แจ้งปัญหาการใช้งานหรือติดต่อสอบถามเพิ่มเติมได้ที่ </p><br>
                <p>© 2026 CKT GROUP Co., Ltd. All rights reserved. Developed by CKT GROUP TECH Team.</p>
            </div>
    </div>

    <script src="../assets/js/ajax.js"></script>
    <script src="../assets/js/login.js"></script>
    
    <!-- ตัวบ่งชี้การสไลด์ -->
    <div class="slideshow-indicators" id="slideshow-indicators"></div>
    
    <!-- ปุ่มจัดการพื้นหลัง (เฉพาะแอดมิน) -->
    <?php if (isLoggedIn() && $_SESSION['user_role'] === 'admin'): ?>
    <div class="background-manager">
        <button class="bg-manager-btn" onclick="openBackgroundManager()">จัดการพื้นหลัง</button>
    </div>
    
    <!-- Modal จัดการพื้นหลัง -->
    <div class="bg-modal" id="bg-modal">
        <div class="bg-modal-content">
            <h2>จัดการรูปพื้นหลัง</h2>
            
            <div class="upload-area" id="upload-area">
                <p>📁 คลิกหรือลากไฟล์รูปภาพมาที่นี่</p>
                <p style="font-size: 12px; color: #666;">รองรับไฟล์: JPG, PNG, GIF, WebP (สูงสุด 5MB)</p>
                <input type="file" id="file-input" multiple accept="image/*" style="display: none;">
            </div>
            
            <div class="bg-grid" id="bg-grid">
                <!-- รูปภาพจะแสดงที่นี่ -->
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeBackgroundManager()">ปิด</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function ajaxGetJson(url) {
            if (!window.AppAjax || typeof window.AppAjax.getJSON !== 'function') {
                return Promise.reject(new Error('ไม่พบระบบ AppAjax กลาง'));
            }
            return window.AppAjax.getJSON(url);
        }

        function ajaxPostForm(url, payload) {
            if (!window.AppAjax || typeof window.AppAjax.postForm !== 'function') {
                return Promise.reject(new Error('ไม่พบระบบ AppAjax กลาง'));
            }
            return window.AppAjax.postForm(url, payload);
        }

        function ajaxPostMultipart(url, formData) {
            if (!window.AppAjax || typeof window.AppAjax.postMultipart !== 'function') {
                return Promise.reject(new Error('ไม่พบระบบ AppAjax กลาง'));
            }
            return window.AppAjax.postMultipart(url, formData);
        }

        // ฟังก์ชันสำหรับจัดการพื้นหลัง (เฉพาะแอดมิน)
        function openBackgroundManager() {
            document.getElementById('bg-modal').style.display = 'block';
            loadBackgroundGrid();
        }

        function closeBackgroundManager() {
            document.getElementById('bg-modal').style.display = 'none';
        }

        function loadBackgroundGrid() {
            // โหลดรูปภาพที่มีอยู่
            ajaxGetJson('get_backgrounds.php')
                .then(data => {
                    const grid = document.getElementById('bg-grid');
                    grid.innerHTML = '';
                    
                    if (data.success && data.backgrounds.length > 0) {
                        data.backgrounds.forEach(bg => {
                            const item = document.createElement('div');
                            item.className = 'bg-item';
                            item.innerHTML = `
                                <img src="../assets/images/backgrounds/${bg}" alt="${bg}">
                                <button class="delete-btn" onclick="deleteBackground('${bg}')" title="ลบรูปภาพ">×</button>
                            `;
                            grid.appendChild(item);
                        });
                    } else {
                        grid.innerHTML = '<p style="text-align: center; color: #666;">ยังไม่มีรูปพื้นหลัง</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading backgrounds:', error);
                    const grid = document.getElementById('bg-grid');
                    if (grid) {
                        grid.innerHTML = '<p style="text-align: center; color: #d32f2f;">ไม่สามารถโหลดรูปพื้นหลังได้</p>';
                    }
                });
        }

        function deleteBackground(filename) {
            if (confirm('ต้องการลบรูปภาพนี้หรือไม่?')) {
                ajaxPostForm('delete_background.php', { filename: filename })
                .then(data => {
                    loadBackgroundGrid();
                    if (typeof backgroundSlideshow !== 'undefined') {
                        backgroundSlideshow.updateBackgroundElements();
                    }
                })
                .catch(error => {
                    alert(error && error.message ? error.message : 'ไม่สามารถลบรูปภาพได้');
                });
            }
        }

        // จัดการการอัพโหลดไฟล์
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('file-input');

            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());
                
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    const files = e.dataTransfer.files;
                    handleFileUpload(files);
                });
                
                fileInput.addEventListener('change', (e) => {
                    handleFileUpload(e.target.files);
                });
            }
        });

        function handleFileUpload(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    uploadFile(file);
                }
            });
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('background', file);

            ajaxPostMultipart('upload_background.php', formData)
            .then(data => {
                if (data.success) {
                    loadBackgroundGrid();
                    if (typeof backgroundSlideshow !== 'undefined') {
                        backgroundSlideshow.updateBackgroundElements();
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('เกิดข้อผิดพลาดในการเข้าใช้งานระบบกรุณาลองใหม่อีกครั้งหรือติดต่อเจ้า้าหน้าที่');
            });
        }

        // ปิด modal เมื่อคลิกพื้นหลัง
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('bg-modal')) {
                closeBackgroundManager();
            }
        });

        // Logo Management
        function initLogoManager() {
            loadLogo();
            
            // เฉพาะแอดมินถึงจะคลิกได้
            <?php if (isLoggedIn() && $_SESSION['user_role'] === 'admin'): ?>
            const logoPlaceholder = document.getElementById('logo-placeholder');
            if (logoPlaceholder) {
                logoPlaceholder.addEventListener('click', () => {
                    const input = document.createElement('input');
                    input.type = 'file';
                    input.accept = 'image/*';
                    input.onchange = (e) => uploadLogo(e.target.files[0]);
                    input.click();
                });
            }
            <?php endif; ?>
        }

        function loadLogo() {
            ajaxGetJson('get_logo.php')
                .then(data => {
                    const logoContainer = document.getElementById('logo-container');
                    const logoImage = document.getElementById('logo-image');
                    const logoPlaceholder = document.getElementById('logo-placeholder');

                    if (!logoContainer || !logoImage) {
                        return;
                    }

                    const logoUrl = data.logo_url || (data.logo ? `../assets/images/logo/${data.logo}` : '');
                    
                    if (logoUrl) {
                        logoImage.src = logoUrl;
                        logoImage.style.display = 'block';
                        logoContainer.classList.add('has-logo');
                        logoContainer.classList.remove('no-logo');
                    } else {
                        logoImage.style.display = 'none';
                        logoContainer.classList.remove('has-logo');
                        <?php if (isLoggedIn() && $_SESSION['user_role'] === 'admin'): ?>
                        logoContainer.classList.add('no-logo');
                        <?php endif; ?>
                    }
                })
                .catch(error => {
                    console.error('Error loading logo:', error);
                });
        }

        function uploadLogo(file) {
            if (!file) return;
            
            if (!file.type.startsWith('image/')) {
                alert('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
                return;
            }

            const formData = new FormData();
            formData.append('logo', file);

            ajaxPostMultipart('upload_logo.php', formData)
            .then(data => {
                if (data.success) {
                    loadLogo();
                    alert('✅ อัพโหลด Logo สำเร็จ');
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ เกิดข้อผิดพลาดในการอัพโหลด Logo');
            });
        }

        // เรียกใช้เมื่อหน้าเว็บโหลดเสร็จ
        document.addEventListener('DOMContentLoaded', function() {
            initLogoManager();
        });
    </script>
</body>
</html>