

// Slider 
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

class DashboardSections {
    constructor() {
        this.links = document.querySelectorAll('[data-dashboard-link]');
        this.panels = document.querySelectorAll('[data-dashboard-panel]');
        this.init();
    }

    init() {
        if (!this.links.length || !this.panels.length) return;

        this.links.forEach((link) => {
            link.addEventListener('click', (e) => {
                const target = link.getAttribute('data-dashboard-link');
                if (!target) return;
                e.preventDefault();
                this.showPanel(target, true, false);
            });
        });

        const initial = (window.location.hash || '#overview').replace(/^#/, '') || 'overview';
        this.showPanel(initial, false);

        window.addEventListener('hashchange', () => {
            const hash = (window.location.hash || '#overview').replace(/^#/, '') || 'overview';
            this.showPanel(hash, false);
        });
    }

    showPanel(target, updateHash) {
        const panel = document.getElementById(target) || document.getElementById('overview');
        const activeId = panel ? panel.id : 'overview';

        this.panels.forEach((item) => {
            item.classList.toggle('is-active', item.id === activeId);
        });

        this.links.forEach((link) => {
            link.classList.toggle('active', link.getAttribute('data-dashboard-link') === activeId);
        });

        if (updateHash) {
            const nextUrl = `${window.location.pathname}#${activeId}`;
            window.history.replaceState(null, '', nextUrl);
        }
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
    }

    try { new ViewToggle(); } catch(e){}
    try { new LoginTabs(); } catch(e){}
    try { new AdminSidebar(); } catch(e){}
    try { new DashboardSections(); } catch(e){}

    // fallback for bootstrap because sometimes the close buttons break
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

    // just a fallback I added
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
        const scrollY = window.scrollY;
        const isHidden = row.style.display === 'none' || getComputedStyle(row).display === 'none';
        row.style.display = isHidden ? 'table-row' : 'none';
        t.blur();
        window.scrollTo({ top: scrollY, behavior: 'auto' });
    });

    // Password visibility 
    document.querySelectorAll('[data-password-toggle]').forEach(btn => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        btn.addEventListener('mousedown', (e) => e.preventDefault()); 

        btn.addEventListener('click', () => {
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
            btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
            const pos = input.value.length;
            input.focus({ preventScroll: true });
            try { input.setSelectionRange(pos, pos); } catch (e) {}
        });
    });

    // Password requirements
    const pwdInput = document.getElementById('reg_password');
    const reqContainer = document.getElementById('pwd_help');
    if (pwdInput && reqContainer) {
        const rules = {
            len: v => v.length >= 6,
            upper: v => /[A-Z]/.test(v),
            lower: v => /[a-z]/.test(v),
            digit: v => /\d/.test(v),
        };
        const update = () => {
            const val = pwdInput.value || '';
            reqContainer.querySelectorAll('[data-pwd-rule]').forEach(el => {
                const key = el.getAttribute('data-pwd-rule');
                const ok = rules[key] ? rules[key](val) : false;
                el.classList.toggle('text-success', ok);
                el.classList.toggle('text-muted', !ok);
                const icon = el.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-circle', !ok);
                    icon.classList.toggle('fa-check-circle', ok);
                }
            });
        };
        pwdInput.addEventListener('input', update);
        update();
    }

    // Toggle admin signup code field
    const accountTypeInput = document.getElementById('account_type');
    const adminCodeWrap = document.getElementById('admin-signup-code-wrap');
    const adminCodeInput = document.getElementById('admin_signup_code');
    if (accountTypeInput && adminCodeWrap) {
        const updateAdminCodeVisibility = () => {
            const isAdmin = accountTypeInput.value === 'admin';
            adminCodeWrap.hidden = !isAdmin;
            if (adminCodeInput) {
                adminCodeInput.required = isAdmin;
                if (!isAdmin) {
                    adminCodeInput.value = '';
                }
            }
        };
        accountTypeInput.addEventListener('change', updateAdminCodeVisibility);
        updateAdminCodeVisibility();
    }
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
