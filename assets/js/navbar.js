// Navbar JavaScript สำหรับ Mobile และ Dropdown functionality

document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const navbarMenu = document.querySelector('.navbar-menu');
    const dropdowns = document.querySelectorAll('.dropdown');

    // Toggle Mobile Menu
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navbarMenu.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });
    }

    // Mobile Dropdown Toggle
    dropdowns.forEach(dropdown => {
        const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
        
        if (dropdownToggle) {
            dropdownToggle.addEventListener('click', function(e) {
                // ใน mobile mode ให้ toggle dropdown
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                    
                    // ปิด dropdowns อื่นๆ
                    dropdowns.forEach(otherDropdown => {
                        if (otherDropdown !== dropdown) {
                            otherDropdown.classList.remove('active');
                        }
                    });
                }
            });
        }
    });

    // ปิด mobile menu เมื่อคลิกข้างนอก
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.navbar') && navbarMenu.classList.contains('active')) {
            mobileToggle.classList.remove('active');
            navbarMenu.classList.remove('active');
            document.body.classList.remove('menu-open');
        }
    });

    // ปิด dropdowns เมื่อ resize หน้าจอ
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // ปิด mobile menu และ reset dropdowns เมื่อหน้าจอใหญ่ขึ้น
            mobileToggle.classList.remove('active');
            navbarMenu.classList.remove('active');
            document.body.classList.remove('menu-open');
            
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // Smooth scroll สำหรับ internal links (ถ้ามี)
    const internalLinks = document.querySelectorAll('a[href^="#"]');
    internalLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // ปิด mobile menu หลังจากคลิกลิงก์
                    if (navbarMenu.classList.contains('active')) {
                        mobileToggle.classList.remove('active');
                        navbarMenu.classList.remove('active');
                        document.body.classList.remove('menu-open');
                    }
                }
            }
        });
    });

    // เพิ่ม keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // ESC key ปิด mobile menu
        if (e.key === 'Escape') {
            if (navbarMenu.classList.contains('active')) {
                mobileToggle.classList.remove('active');
                navbarMenu.classList.remove('active');
                document.body.classList.remove('menu-open');
            }
        }
    });

    // Active link highlight (ถ้าต้องการ)
    const currentPage = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-link, .dropdown-link');
    
    navLinks.forEach(link => {
        const linkPath = new URL(link.href, window.location.origin).pathname;
        if (linkPath === currentPage) {
            link.classList.add('active-page');
        }
    });
});

// เพิ่ม CSS สำหรับ active page (ถ้าต้องการ)
const style = document.createElement('style');
style.textContent = `
    .active-page {
        background-color: #dc2626 !important;
        color: white !important;
    }
    
    body.menu-open {
        overflow: hidden;
    }
    
    @media (max-width: 768px) {
        body.menu-open::before {
            content: '';
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: calc(100vh - 70px);
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
    }
`;
document.head.appendChild(style);