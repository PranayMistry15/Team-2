// Slider (guard when not present)
    const slides = document.querySelectorAll('.hero-slide');
    const indicators = document.querySelectorAll('.slider-indicator');
    let currentSlide = 0;
    const slideInterval = 5000; 

    function showSlide(index) {
        if (!slides.length || !indicators.length) return;
        slides.forEach(slide => slide.classList.remove('active'));
        indicators.forEach(indicator => indicator.classList.remove('active'));
        if (slides[index] && indicators[index]) {
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
        }
    }

    function nextSlide() {
        if (!slides.length) return;
        currentSlide = (currentSlide + 1) % slides.length;
        showSlide(currentSlide);
    }

    if (slides.length && indicators.length) {
        let autoSlide = setInterval(nextSlide, slideInterval);
        indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => {
                currentSlide = index;
                showSlide(currentSlide);
                clearInterval(autoSlide);
                autoSlide = setInterval(nextSlide, slideInterval);
            });
        });
    }

// View Toggle 
class ViewToggle {
    constructor() {
        this.gridBtn = document.querySelector('[data-view="grid"]');
        this.listBtn = document.querySelector('[data-view="list"]');
        this.container = document.querySelector('.products-container');
        this.currentView = localStorage.getItem('productView') || 'grid';
        
        this.init();
    }
    
    init() {
        if (!this.gridBtn || !this.listBtn || !this.container) return;
        
        this.setView(this.currentView);
        
        this.gridBtn.addEventListener('click', () => this.setView('grid'));
        this.listBtn.addEventListener('click', () => this.setView('list'));
    }
    
    setView(view) {
        this.currentView = view;
        localStorage.setItem('productView', view);
        
        this.gridBtn.classList.toggle('active', view === 'grid');
        this.listBtn.classList.toggle('active', view === 'list');
    
        if (view === 'list') {
            this.container.classList.remove('row', 'g-4');
            this.container.classList.add('product-list-view');
            this.container.querySelectorAll('.product-card').forEach(card => {
                card.classList.add('product-card-list');
            });
        } else {
            this.container.classList.add('row', 'g-4');
            this.container.classList.remove('product-list-view');
            this.container.querySelectorAll('.product-card').forEach(card => {
                card.classList.remove('product-card-list');
            });
        }
    }
}

// Login Tabs
class LoginTabs {
    constructor() {
        this.tabs = document.querySelectorAll('.login-tab');
        this.contents = document.querySelectorAll('.login-tab-content');
        this.indicator = document.querySelector('.login-tab-indicator');
        
        this.init();
    }
    
    init() {
        if (!this.tabs.length) return;
        
        this.tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => this.switchTab(index));
        });
        
        this.updateIndicator(0);
    }
    
    switchTab(index) {
        this.tabs.forEach(tab => tab.classList.remove('active'));
        this.tabs[index].classList.add('active');
        this.contents.forEach(content => content.classList.remove('active'));
        this.contents[index].classList.add('active');
        this.updateIndicator(index);
    }
    
    updateIndicator(index) {
        if (!this.indicator) return;
        
        const tab = this.tabs[index];
        const width = tab.offsetWidth;
        const left = tab.offsetLeft;
        
        this.indicator.style.width = `${width}px`;
        this.indicator.style.left = `${left}px`;
    }
}

// Admin Sidebar Toggle
class AdminSidebar {
    constructor() {
        this.sidebar = document.querySelector('.admin-sidebar');
        this.mainContent = document.querySelector('.admin-main');
        this.toggleBtn = document.querySelector('.sidebar-toggle');
        this.mobileToggle = document.querySelector('.mobile-sidebar-toggle');
        
        this.init();
    }
    
    init() {
        if (!this.sidebar) return;
        
        if (this.toggleBtn) {
            this.toggleBtn.addEventListener('click', () => this.toggle());
        }
        
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.toggleMobile());
        }
        
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && 
                this.sidebar.classList.contains('mobile-open') &&
                !this.sidebar.contains(e.target) &&
                e.target !== this.mobileToggle) {
                this.sidebar.classList.remove('mobile-open');
            }
        });
    }
    
    toggle() {
        this.sidebar.classList.toggle('collapsed');
        this.mainContent.classList.toggle('expanded');
    }
    
    toggleMobile() {
        this.sidebar.classList.toggle('mobile-open');
    }
}

// On DOM load 
(function(){
const init = () => {
    try {
        if (typeof HeroSlider === 'function') {
            const heroSliderEl = document.querySelector('.hero-slider');
            if (heroSliderEl) new HeroSlider(heroSliderEl);
        }
    } catch (e) {
        // ignore 
    }

    try { new ViewToggle(); } catch(e){}
    try { new LoginTabs(); } catch(e){}
    try { new AdminSidebar(); } catch(e){}

    // Fallback 
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-close');
        if (btn && btn.closest('.alert')) {
            const alertEl = btn.closest('.alert');
            alertEl.classList.remove('show');
            setTimeout(() => {
                if (alertEl && alertEl.parentNode) {
                    alertEl.parentNode.removeChild(alertEl);
                }
            }, 150);
        }
    });

    // Fallback
    document.querySelectorAll('img[data-fallback]').forEach(img => {
        img.addEventListener('error', () => {
            if (!img.dataset.fallbackApplied) {
                img.src = img.dataset.fallback;
                img.dataset.fallbackApplied = '1';
            }
        }, { once: false });
    });

    // Confirm actions
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-confirm]');
        if (el && el.tagName === 'A') {
            const msg = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        }
    });

    // Toggle order details 
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-toggle-order-details]');
        if (!t) return;
        const id = t.getAttribute('data-order-id');
        if (!id) return;
        const row = document.getElementById('order-' + id);
        if (!row) return;
        e.preventDefault();
        const isHidden = row.style.display === 'none' || getComputedStyle(row).display === 'none';
        row.style.display = isHidden ? 'table-row' : 'none';
    });
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
