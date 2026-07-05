// ฟังก์ชันสำหรับการจัดการฟอร์มล็อกอิน
class LoginSystem {
    constructor() {
        this.currentStep = 1;
        this.userData = null;
        this.init();
    }

    init() {
        this.bindEvents();
        this.showStep(1);
    }

    bindEvents() {
        // Event listeners สำหรับฟอร์มล็อกอิน
        const userIdForm = document.getElementById('userIdForm');
        const passwordForm = document.getElementById('passwordForm');
        
        if (userIdForm) {
            userIdForm.addEventListener('submit', (e) => this.handleUserIdSubmit(e));
        }

        if (passwordForm) {
            passwordForm.addEventListener('submit', (e) => this.handlePasswordSubmit(e));
        }

        // ปุ่มย้อนกลับ
        const backButtons = document.querySelectorAll('.back-button');
        backButtons.forEach(btn => {
            btn.addEventListener('click', () => this.goBack());
        });
    }

    async handleUserIdSubmit(e) {
        e.preventDefault();
        
        const userId = document.getElementById('userId').value;
        if (!userId.trim()) {
            this.showAlert('กรุณากรอกรหัสผู้ใช้งาน', 'danger');
            return;
        }

        try {
            const result = await this.postForm('check_user.php', {
                user_id: userId,
                csrf_token: this.getCSRFToken()
            });
            
            if (result.success) {
                this.userData = result.user;
                this.showUserInfo();
                this.showStep(2);
            } else {
                this.showAlert(result.message || 'ไม่พบรหัสผู้ใช้งานในระบบ', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert(error && error.message ? error.message : 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    }

    async handlePasswordSubmit(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        if (!password.trim()) {
            this.showAlert('กรุณากรอกรหัสผ่าน', 'danger');
            return;
        }

        try {
            const result = await this.postForm('authenticate.php', {
                user_id: this.userData.user_id,
                password: password,
                csrf_token: this.getCSRFToken()
            });
            
            if (result.success) {
                if (result.requires_branch_selection) {
                    this.showAlert('เข้าสู่ระบบสำเร็จ กรุณาเลือกสาขาเพื่อเริ่มใช้งาน', 'success');
                    this.openBranchSelectorPopup(result);
                } else {
                    this.showAlert('เข้าสู่ระบบสำเร็จ กำลังนำทางไปยังหน้าหลัก...', 'success');
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                }
            } else {
                this.showAlert(result.message || 'รหัสผ่านไม่ถูกต้อง', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert(error && error.message ? error.message : 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    }

    async postForm(url, payload) {
        if (!window.AppAjax || typeof window.AppAjax.postForm !== 'function') {
            throw new Error('ไม่พบระบบ AppAjax กลาง');
        }
        return window.AppAjax.postForm(url, payload);
    }

    openBranchSelectorPopup(result) {
        const popupUrl = result.branch_selector_url || '';
        const defaultRedirect = result.redirect || 'login';

        if (!popupUrl) {
            window.location.href = defaultRedirect;
            return;
        }

        const popup = window.open(
            popupUrl,
            'branch-selector-popup',
            'width=760,height=700,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes'
        );

        if (!popup) {
            this.showAlert('เบราว์เซอร์บล็อกป๊อปอัพ ระบบกำลังเปิดหน้าเลือกสาขาแบบเต็มหน้า', 'danger');
            window.location.href = popupUrl;
            return;
        }

        const onMessage = (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }

            const payload = event.data || {};
            if (payload.type !== 'branch-selected') {
                return;
            }

            window.removeEventListener('message', onMessage);
            try {
                popup.close();
            } catch (e) {
                // Ignore close errors.
            }

            window.location.href = payload.redirect || defaultRedirect;
        };

        window.addEventListener('message', onMessage);
    }

    showStep(step) {
        this.currentStep = step;
        
        // ซ่อนทุก step
        const steps = document.querySelectorAll('.login-step');
        steps.forEach(stepEl => stepEl.style.display = 'none');
        
        // แสดง step ที่ต้องการ
        const currentStepEl = document.getElementById(`step${step}`);
        if (currentStepEl) {
            currentStepEl.style.display = 'block';
        }

        // Clear alerts
        this.clearAlerts();

        // Focus ช่องแรก
        this.focusFirstInput();
    }

    showUserInfo() {
        if (this.userData) {
            document.getElementById('displayName').textContent = `${this.userData.first_name} ${this.userData.last_name}`;
            document.getElementById('displayCompany').textContent = this.userData.company;
            document.getElementById('displayDepartment').textContent = this.userData.department;
            document.getElementById('displayPosition').textContent = this.userData.position;
        }
    }

    goBack() {
        if (this.currentStep === 2) {
            this.showStep(1);
            this.userData = null;
        } else {
            this.showStep(1);
        }
    }

    showAlert(message, type) {
        this.clearAlerts();
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        const container = document.querySelector('.login-container');
        container.insertBefore(alertDiv, container.firstChild);
        
        // Auto hide after 5 seconds for success messages
        if (type === 'success') {
            setTimeout(() => {
                this.clearAlerts();
            }, 5000);
        }
    }

    clearAlerts() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => alert.remove());
    }

    focusFirstInput() {
        setTimeout(() => {
            const visibleStep = document.querySelector('.login-step[style="display: block;"]');
            if (visibleStep) {
                const firstInput = visibleStep.querySelector('input[type="text"], input[type="password"], input[type="email"]');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        }, 100);
    }

    getCSRFToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenMeta ? tokenMeta.getAttribute('content') : '';
    }
}

// เริ่มต้นระบบเมื่อหน้าเว็บโหลดเสร็จ
document.addEventListener('DOMContentLoaded', function() {
    new LoginSystem();
});

// Utility functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[0-9]{10}$/;
    return re.test(phone.replace(/\D/g, ''));
}