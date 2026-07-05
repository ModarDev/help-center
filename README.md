# ระบบล็อกอิน Office Plus

ระบบล็อกอินที่มีความปลอดภัยสูงสำหรับองค์กร พร้อมการจัดการสิทธิ์ผู้ใช้งาน 3 ระดับ

## คุณสมบัติหลัก

### 🔐 ระบบความปลอดภัย
- การเข้ารหัสรหัสผ่านด้วย PHP password_hash()
- CSRF Protection
- Session Management ที่ปลอดภัย
- Rate Limiting ป้องกัน Brute Force Attack
- Input Validation และ Sanitization
- Security Headers (XSS Protection, Content Security Policy)
- Login Attempt Monitoring และ Account Lockout

### 👥 การจัดการผู้ใช้งาน
- **แอดมิน (Admin)**: จัดการระบบและผู้ใช้งานทั้งหมด
- **หัวหน้า (Manager)**: จัดการทีมงานและอนุมัติงาน
- **พนักงาน (Employee)**: เข้าถึงงานส่วนตัวและระบบพื้นฐาน

### 🎨 User Interface
- Responsive Design รองรับทุกขนาดหน้าจอ
- ระบบพื้นหลังที่ปรับแต่งได้ - ใส่รูปภาพเองได้
- การสไลด์รูปพื้นหลังอัตโนมัติ (ถ้ามีหลายรูป)
- หน้าต่างล็อกอินอยู่มุมขวาตามที่ร้องขอ
- ฟอนต์ Tahoma
- Animation และ Transition ที่นุ่มนวล
- ตัวควบคุมการสไลด์และตัวบ่งชี้

### 📊 การบันทึกและรายงาน
- บันทึกประวัติการล็อกอิน
- ติดตาม Security Events
- สถิติการใช้งานระบบ
- รายงานการเข้าถึงแยกตามสิทธิ์

## การติดตั้ง

### ความต้องการของระบบ
- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า
- Web Server (Apache/Nginx)
- PDO Extension สำหรับ PHP

### ขั้นตอนการติดตั้ง

1. **อัพโหลดไฟล์**
   ```
   อัพโหลดทุกไฟล์ไปยัง Document Root ของ Web Server
   ```

2. **สร้างฐานข้อมูล**
   ```sql
   -- เข้า MySQL แล้วรันคำสั่ง
   SOURCE database.sql;
   ```

3. **แก้ไขการตั้งค่า**
   ```php
   // แก้ไขไฟล์ auth/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'office_login_system');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **ตั้งค่าสิทธิ์โฟลเดอร์**
   ```bash
   chmod 755 auth/
   chmod 755 admin/
   chmod 755 employee/
   chmod 755 manager/
   chmod 755 assets/
   ```

## บัญชีทดสอบ

ระบบมาพร้อมบัญชีทดสอบ 3 บัญชี (รหัสผ่านทั้งหมดคือ: `password123`)

| สิทธิ์ | รหัสผู้ใช้ | รหัสผ่าน | หน้าที่เข้าใช้ |
|--------|-----------|----------|---------------|
| แอดมิน | admin001 | password123 | admin/menuadmin.php |
| หัวหน้า | mgr001 | password123 | manager/menumanager.php |
| พนักงาน | emp001 | password123 | employee/menuemployee.php |

## วิธีการใช้งาน

### การล็อกอิน
1. เข้าที่ `index.php` (จะ redirect ไป `auth/login`)
2. กรอกรหัสผู้ใช้งาน → กดถัดไป
3. ระบบจะแสดงข้อมูลผู้ใช้งาน
4. กรอกรหัสผ่าน → กดเข้าสู่ระบบ
5. ระบบจะนำไปยังหน้าที่เหมาะสมตามสิทธิ์

### การสมัครสมาชิก
1. ในหน้าล็อกอิน คลิก "สมัครสมาชิก"
2. กรอกข้อมูลทั้งหมดให้ครบถ้วน
3. เลือกสิทธิ์การใช้งาน (แอดมิน/หัวหน้า/พนักงาน)
4. กดสมัครสมาชิก
5. ระบบจะกลับไปหน้าล็อกอินอัตโนมัติ

### การออกจากระบบ
- คลิกปุ่ม "ออกจากระบบ" ที่มุมขวาบนของหน้าจอ

## การจัดการรูปพื้นหลัง

### สำหรับแอดมิน
1. ล็อกอินด้วยบัญชีแอดมิน
2. คลิกปุ่ม "จัดการพื้นหลัง" ที่มุมขวาบน (ปรากฏเมื่อ hover เมาส์)
3. ลากไฟล์รูปภาพหรือคลิกเพื่อเลือกไฟล์
4. รูปจะถูกอัพโหลดและแสดงในระบบทันที
5. สามารถลบรูปที่ไม่ต้องการได้โดยคลิกปุ่ม ×

### การเพิ่มรูปแบบ Manual (FTP/File Manager)
1. อัพโหลดไฟล์รูปภาพลงโฟลเดอร์ `assets/images/backgrounds/`
2. ไฟล์ที่รองรับ: JPG, JPEG, PNG, GIF, WebP
3. ขนาดแนะนำ: 1920x1080 หรือสูงกว่า (อัตราส่วน 16:9)
4. ขนาดไฟล์สูงสุด: 5MB
5. รีเฟรชหน้าเว็บเพื่อดูรูปพื้นหลังใหม่

### การทำงานของระบบพื้นหลัง
- ถ้ามีรูปเดียว: แสดงรูปนั้นตลอดเวลา
- ถ้ามีหลายรูป: สไลด์อัตโนมัติทุก 5 วินาที
- ปุ่มควบคุม: ลูกศรซ้าย-ขวา สำหรับเปลี่ยนรูปด้วยตนเอง
- ตัวบ่งชี้: จุดกลมด้านล่าง คลิกเพื่อไปยังรูปที่ต้องการ
- หยุดชั่วคราว: เมื่อ hover เมาส์ที่หน้าต่างล็อกอิน
- ปุ่มหยุด/เล่น: สำหรับแอดมิน ควบคุมการสไลด์

## โครงสร้างไฟล์

```
MyOfficeplus.apmofficial.com/
├── index.php                 # หน้าหลัก (redirect ไป auth/)
├── database.sql             # Script สร้างฐานข้อมูล
├── auth/                    # ระบบ Authentication
│   ├── config.php          # การตั้งค่าระบบ
│   ├── login.php           # หน้าล็อกอิน
│   ├── check_user.php      # ตรวจสอบรหัสผู้ใช้
│   ├── authenticate.php    # ตรวจสอบรหัสผ่าน
│   ├── register.php        # สมัครสมาชิก
│   ├── logout.php          # ออกจากระบบ
│   └── security.php        # ฟังก์ชันความปลอดภัย
├── admin/                   # ระบบแอดมิน
│   └── menuadmin.php       # หน้าหลักแอดมิน
├── manager/                 # ระบบหัวหน้า
│   └── menumanager.php     # หน้าหลักหัวหน้า
├── employee/                # ระบบพนักงาน
│   └── menuemployee.php    # หน้าหลักพนักงาน
├── assets/                  # ไฟล์ Static
    ├── css/
    │   └── login.css       # CSS หน้าล็อกอิน
    ├── js/
    │   ├── login.js        # JavaScript หน้าล็อกอิน
    │   └── background-slideshow.js # ระบบสไลด์พื้นหลัง
    └── images/
        └── backgrounds/    # รูปพื้นหลัง (ใส่รูปเองได้)
```

## ฐานข้อมูล

### ตาราง users
เก็บข้อมูลผู้ใช้งานทั้งหมด
- `user_id`: รหัสผู้ใช้งาน (Unique)
- `first_name`, `last_name`: ชื่อ-นามสกุล
- `phone`: เบอร์โทรศัพท์
- `email`: อีเมล
- `position`: ตำแหน่ง
- `department`: ฝ่าย
- `company`: บริษัท
- `user_role`: สิทธิ์ (admin/manager/employee)
- `password_hash`: รหัสผ่านที่เข้ารหัส

### ตาราง user_sessions
เก็บข้อมูล Session ที่ใช้งานอยู่

### ตาราง login_logs
เก็บประวัติการล็อกอิน

### ตาราง security_logs
เก็บ Security Events

### ตาราง rate_limit
ใช้สำหรับ Rate Limiting

## การปรับแต่ง

### ตั้งค่าเมนูกลางตาม Role (ใหม่)

ระบบสามารถแยกการตั้งค่า Top Navigation + Sidebar ออกจากหน้า Dashboard ได้แล้ว โดยใช้ไฟล์กลาง:

- `app/config/dashboard_menu_config.php`

ไฟล์นี้รองรับ:

- กำหนดเมนูตาม role จากโค้ด (Top nav, Sidebar, Footer)
- ดึงชื่อ role จากตาราง `roles` เพื่อให้ label ตรงกับฐานข้อมูล
- ดึงหน้า Dashboard หลักของ role จาก `roles.dashboard_path` เพื่อทำปุ่ม Home ให้ถูก role
- ให้หลายหน้าเรียกใช้ config เดียวกัน (เช่น `app/SYSTEM/index.php`, `app/admin/menuadmin.php`)

### วิธีเพิ่ม Role ใหม่

1. เพิ่ม role ในฐานข้อมูลตาราง `roles` พร้อม `dashboard_path`
2. เพิ่มกฎสิทธิ์เมนูของ role นั้นใน `getDashboardRoleMenuRules()` ภายใน `app/config/dashboard_menu_config.php`
3. (ถ้าต้องการ) เพิ่ม label เริ่มต้นใน `getDashboardRoleLabels()`
4. หน้า dashboard ที่ include ไฟล์ config นี้ จะใช้สิทธิ์เมนู role ใหม่ได้ทันที

> หมายเหตุ: ถ้า role มีในฐานข้อมูล แต่ยังไม่มีกฎเมนูในโค้ด ระบบจะ fallback ไปสิทธิ์แบบ `default`

### เปลี่ยนระยะเวลา Session
```php
// ในไฟล์ auth/config.php
define('SESSION_TIMEOUT', 3600); // วินาที (3600 = 1 ชั่วโมง)
```

### เปลี่ยนจำนวนครั้งที่ล็อกอินผิดได้
```php
// ในไฟล์ auth/config.php
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // วินาที (900 = 15 นาที)
```

### เปลี่ยนการตั้งค่าฐานข้อมูล
```php
// ในไฟล์ auth/config.php
define('DB_HOST', 'your_host');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

## ความปลอดภัย

### การป้องกันที่มีอยู่
- ✅ Password Hashing (bcrypt)
- ✅ CSRF Protection
- ✅ SQL Injection Prevention
- ✅ XSS Protection
- ✅ Session Fixation Prevention
- ✅ Rate Limiting
- ✅ Input Validation
- ✅ Security Headers

### คำแนะนำเพิ่มเติม
1. ใช้ HTTPS ในระบบจริง
2. อัพเดทระบบปฏิบัติการและ PHP เป็นประจำ
3. สำรองข้อมูลเป็นประจำ
4. ตรวจสอบ Log ไฟล์เป็นประจำ
5. ใช้ Firewall ป้องกันการเข้าถึงที่ไม่ต้องการ

## การแก้ไขปัญหา

## CRM Ops Scripts (ใหม่)

### 1) ส่ง Daily Digest งาน Follow-up ค้าง (Sales Manager)

รันคำสั่ง:

```bash
php app/sales_manager/cron_followup_daily_digest.php
```

เงื่อนไข:
- ต้องตั้งค่า `Webhook งาน Follow-up ค้าง` ที่หน้า `app/admin/page_setup_discord.php`
- สคริปต์ส่งแบบรายวันและกันการส่งซ้ำต่อผู้จัดการ/สาขาในวันเดียวกัน

### 2) ตรวจสอบความถูกต้องข้อมูล CRM อัตโนมัติ

รันคำสั่ง:

```bash
php scripts/crm_customer_integrity_check.php
```

สิ่งที่ตรวจ:
- ความครบถ้วน field หลักของลูกค้า
- ความสอดคล้อง owner กับ team/member
- ข้อมูลซ้ำในทีม (phone/line แบบ exact)
- ความถูกต้องของ timeline และ SLA alerts ที่อ้างอิงลูกค้า

แนะนำให้ตั้งรันทุกวันหลัง batch job เพื่อเฝ้าระวังคุณภาพข้อมูล

### ไม่สามารถเชื่อมต่อฐานข้อมูลได้
1. ตรวจสอบการตั้งค่าใน `auth/config.php`
2. ตรวจสอบว่า MySQL Server ทำงาน
3. ตรวจสอบสิทธิ์ผู้ใช้ฐานข้อมูล

### Session หมดอายุเร็วเกินไป
1. เพิ่มค่า `SESSION_TIMEOUT` ใน `auth/config.php`
2. ตรวจสอบการตั้งค่า `session.gc_maxlifetime` ใน php.ini

### รหัสผ่านไม่ตรงกัน
1. ตรวจสอบว่าได้ import `database.sql` แล้ว
2. รหัสผ่านตัวอย่างคือ `password123`

## การพัฒนาต่อ

### ฟีเจอร์ที่สามารถเพิ่มได้
- [ ] Two-Factor Authentication (2FA)
- [ ] Password Reset ผ่านอีเมล
- [ ] API สำหรับ Mobile App
- [ ] Advanced User Management
- [ ] File Upload และ Management
- [ ] Notification System
- [ ] Dashboard Analytics
- [ ] Audit Trail

### การขยายระบบ
- สามารถเพิ่มสิทธิ์ผู้ใช้งานใหม่ได้
- สามารถเพิ่มฟีเจอร์ตามความต้องการได้
- รองรับการทำงานแบบ Multi-tenant

## การสนับสนุน

สำหรับคำถามหรือปัญหาการใช้งาน สามารถติดต่อได้ที่:
- Email: support@officeplus.com
- โทร: 02-xxx-xxxx

## License

MIT License - สามารถใช้งานและแก้ไขได้อย่างอิสระ