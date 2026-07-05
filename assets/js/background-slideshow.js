// Background Slideshow Manager
class BackgroundSlideshow {
    constructor() {
        this.backgrounds = [];
        this.currentIndex = 0;
        this.slideInterval = null;
        this.transitionDuration = 1500; // 1.5 วินาที
        this.slideDuration = 5000; // 5 วินาที
        this.init();
    }

    async init() {
        await this.loadBackgroundImages();
        if (this.backgrounds.length > 0) {
            this.createBackgroundElements();
            this.startSlideshow();
        } else {
            this.setDefaultBackground();
        }
    }

    async loadBackgroundImages() {
        try {
            // เรียก API เพื่อดึงรายการรูปภาพ
            const data = await this.requestGet('../auth/get_backgrounds.php');
            
            if (data.success && data.backgrounds.length > 0) {
                this.backgrounds = data.backgrounds.map(bg => ({
                    url: `../assets/images/backgrounds/${bg}`,
                    name: bg
                }));
            } else {
                // ถ้าไม่มีรูปให้ใช้ gradient เริ่มต้น
                this.backgrounds = [];
            }
        } catch (error) {
            console.error('Error loading background images:', error);
            this.backgrounds = [];
        }
    }

    async requestGet(url) {
        if (window.AppAjax && typeof window.AppAjax.getJSON === 'function') {
            return window.AppAjax.getJSON(url);
        }

        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || (data && data.success === false)) {
            throw new Error((data && data.message) || 'Request failed');
        }
        return data;
    }

    async requestMultipart(url, formData) {
        if (window.AppAjax && typeof window.AppAjax.postMultipart === 'function') {
            return window.AppAjax.postMultipart(url, formData);
        }

        const response = await fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (!response.ok || (data && data.success === false)) {
            throw new Error((data && data.message) || 'Request failed');
        }
        return data;
    }

    createBackgroundElements() {
        // สร้าง container สำหรับพื้นหลัง
        const bgContainer = document.createElement('div');
        bgContainer.id = 'background-container';
        bgContainer.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        `;

        // สร้าง div สำหรับแต่ละรูป
        this.backgrounds.forEach((bg, index) => {
            const bgDiv = document.createElement('div');
            bgDiv.className = 'background-slide';
            bgDiv.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-image: url('${bg.url}');
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                opacity: ${index === 0 ? 1 : 0};
                transition: opacity ${this.transitionDuration}ms ease-in-out;
            `;
            bgContainer.appendChild(bgDiv);
        });

        // เพิ่ม overlay
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        `;
        bgContainer.appendChild(overlay);

        // เพิ่มเข้า body
        document.body.insertBefore(bgContainer, document.body.firstChild);
        
        // สร้างตัวบ่งชี้และข้อมูลรูป
        this.createIndicators();
        this.updateImageInfo();
    }

    setDefaultBackground() {
        // ใช้ gradient เริ่มต้นถ้าไม่มีรูป
        document.body.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        document.body.style.backgroundSize = 'cover';
        document.body.style.backgroundAttachment = 'fixed';
    }

    startSlideshow() {
        if (this.backgrounds.length <= 1) return;

        this.slideInterval = setInterval(() => {
            this.nextSlide();
        }, this.slideDuration);
    }

    nextSlide() {
        const slides = document.querySelectorAll('.background-slide');
        if (slides.length <= 1) return;

        const currentSlide = slides[this.currentIndex];
        this.currentIndex = (this.currentIndex + 1) % this.backgrounds.length;
        const nextSlide = slides[this.currentIndex];

        // Fade out current, fade in next
        currentSlide.style.opacity = '0';
        nextSlide.style.opacity = '1';
        
        this.updateIndicators();
        this.updateImageInfo();
    }

    prevSlide() {
        const slides = document.querySelectorAll('.background-slide');
        if (slides.length <= 1) return;

        const currentSlide = slides[this.currentIndex];
        this.currentIndex = (this.currentIndex - 1 + this.backgrounds.length) % this.backgrounds.length;
        const prevSlide = slides[this.currentIndex];

        // Fade out current, fade in prev
        currentSlide.style.opacity = '0';
        prevSlide.style.opacity = '1';
        
        this.updateIndicators();
        this.updateImageInfo();
    }

    goToSlide(index) {
        if (index < 0 || index >= this.backgrounds.length) return;

        const slides = document.querySelectorAll('.background-slide');
        const currentSlide = slides[this.currentIndex];
        const targetSlide = slides[index];

        currentSlide.style.opacity = '0';
        targetSlide.style.opacity = '1';
        
        this.currentIndex = index;
        this.updateIndicators();
        this.updateImageInfo();
    }

    createIndicators() {
        if (this.backgrounds.length <= 1) return;

        let indicatorsContainer = document.getElementById('slideshow-indicators');
        if (!indicatorsContainer) {
            indicatorsContainer = document.createElement('div');
            indicatorsContainer.id = 'slideshow-indicators';
            indicatorsContainer.className = 'slideshow-indicators';
            document.body.appendChild(indicatorsContainer);
        }

        indicatorsContainer.innerHTML = '';
        
        this.backgrounds.forEach((bg, index) => {
            const indicator = document.createElement('div');
            indicator.className = `indicator ${index === 0 ? 'active' : ''}`;
            indicator.addEventListener('click', () => this.goToSlide(index));
            indicator.title = bg.name;
            indicatorsContainer.appendChild(indicator);
        });
    }

    updateIndicators() {
        const indicators = document.querySelectorAll('.indicator');
        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === this.currentIndex);
        });
    }

    updateImageInfo() {
        if (this.backgrounds.length === 0) return;

        let infoElement = document.getElementById('image-info');
        if (!infoElement) {
            infoElement = document.createElement('div');
            infoElement.id = 'image-info';
            infoElement.className = 'image-info';
            document.body.appendChild(infoElement);
        }

        const currentBg = this.backgrounds[this.currentIndex];
        infoElement.textContent = `${this.currentIndex + 1}/${this.backgrounds.length} - ${currentBg.name}`;
    }

    toggleSlideshow() {
        if (this.slideInterval) {
            this.pause();
        } else {
            this.resume();
        }
    }

    pause() {
        if (this.slideInterval) {
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        }
    }

    resume() {
        if (this.backgrounds.length > 1 && !this.slideInterval) {
            this.startSlideshow();
        }
    }

    // เพิ่มรูปใหม่โดยไม่ต้อง reload หน้า
    async addBackground(imageFile) {
        const formData = new FormData();
        formData.append('background', imageFile);

        try {
            const result = await this.requestMultipart('../auth/upload_background.php', formData);
            if (result.success) {
                await this.loadBackgroundImages();
                this.updateBackgroundElements();
                return true;
            }
            return false;
        } catch (error) {
            console.error('Error uploading background:', error);
            return false;
        }
    }

    updateBackgroundElements() {
        const container = document.getElementById('background-container');
        if (container) {
            container.remove();
        }
        
        // ลบ indicators และ info เก่า
        const indicators = document.getElementById('slideshow-indicators');
        const info = document.getElementById('image-info');
        if (indicators) indicators.remove();
        if (info) info.remove();
        
        this.currentIndex = 0;
        this.createBackgroundElements();
        if (!this.slideInterval && this.backgrounds.length > 1) {
            this.startSlideshow();
        }
    }
}

// สร้าง instance และเริ่มต้น
let backgroundSlideshow;

document.addEventListener('DOMContentLoaded', function() {
    backgroundSlideshow = new BackgroundSlideshow();
    
    // หยุดการสไลด์เมื่อ hover ที่หน้าต่างล็อกอิน
    const loginContainer = document.querySelector('.login-container');
    if (loginContainer) {
        loginContainer.addEventListener('mouseenter', () => {
            backgroundSlideshow.pause();
        });
        
        loginContainer.addEventListener('mouseleave', () => {
            backgroundSlideshow.resume();
        });
    }
});

// Export เพื่อให้ใช้ได้จากภายนอก
window.BackgroundSlideshow = BackgroundSlideshow;