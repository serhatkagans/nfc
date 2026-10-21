/**
 * NFC Kartvizit - Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // ==========================================
    // Flash Messages Auto-Dismiss
    // ==========================================
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(function (flash) {
        // Auto dismiss after 5 seconds
        const timer = setTimeout(function () {
            dismissFlash(flash);
        }, 5000);

        // Close button
        const closeBtn = flash.querySelector('.flash__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                clearTimeout(timer);
                dismissFlash(flash);
            });
        }
    });

    function dismissFlash(el) {
        el.classList.add('dismiss');
        setTimeout(function () {
            el.remove();
        }, 300);
    }

    // ==========================================
    // Mobile Menu Toggle
    // ==========================================
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const navMenu = document.querySelector('.header__nav');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function () {
            menuToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking a link
        navMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });

        // Close menu on outside click
        document.addEventListener('click', function (e) {
            if (!menuToggle.contains(e.target) && !navMenu.contains(e.target)) {
                menuToggle.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
    }

    // ==========================================
    // Profile Image Preview on Upload
    // ==========================================
    const avatarInput = document.getElementById('profile-image-input');
    const avatarPreview = document.getElementById('profile-image-preview');

    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                showToast('Lütfen geçerli bir görsel dosyası seçin (JPG, PNG, GIF, WEBP)', 'error');
                return;
            }

            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                showToast('Dosya boyutu en fazla 2MB olabilir', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                avatarPreview.src = event.target.result;
                // If we were showing initials, replace the container
                const initialsEl = document.querySelector('.avatar-upload .avatar__initials');
                if (initialsEl) {
                    const img = document.createElement('img');
                    img.id = 'profile-image-preview';
                    img.src = event.target.result;
                    img.alt = 'Profil';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '50%';
                    initialsEl.parentNode.replaceChild(img, initialsEl);
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // ==========================================
    // Copy Profile URL to Clipboard
    // ==========================================
    const copyBtns = document.querySelectorAll('[data-copy]');
    copyBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = btn.getAttribute('data-copy');
            copyToClipboard(text).then(function () {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Kopyalandı';
                btn.classList.add('btn--success');
                setTimeout(function () {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn--success');
                }, 2000);
            }).catch(function () {
                showToast('Kopyalama başarısız oldu', 'error');
            });
        });
    });

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        // Fallback
        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            }
            document.body.removeChild(textarea);
        });
    }

    // ==========================================
    // Form Validation (Client-Side)
    // ==========================================
    const forms = document.querySelectorAll('[data-validate]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let isValid = true;

            // Clear previous errors
            form.querySelectorAll('.form-error').forEach(function (el) { el.remove(); });
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

            // Required fields
            form.querySelectorAll('[required]').forEach(function (input) {
                if (!input.value.trim()) {
                    isValid = false;
                    showFieldError(input, 'Bu alan zorunludur');
                }
            });

            // Email validation
            form.querySelectorAll('input[type="email"]').forEach(function (input) {
                if (input.value.trim() && !isValidEmail(input.value)) {
                    isValid = false;
                    showFieldError(input, 'Geçerli bir e-posta adresi girin');
                }
            });

            // Password minimum length
            var passwordInput = form.querySelector('input[name="password"]');
            if (passwordInput && passwordInput.value && passwordInput.value.length < 6) {
                isValid = false;
                showFieldError(passwordInput, 'Şifre en az 6 karakter olmalıdır');
            }

            // Password match
            var passwordConfirm = form.querySelector('input[name="password_confirm"]');
            if (passwordInput && passwordConfirm && passwordConfirm.value) {
                if (passwordInput.value !== passwordConfirm.value) {
                    isValid = false;
                    showFieldError(passwordConfirm, 'Şifreler eşleşmiyor');
                }
            }

            // Username validation
            var usernameInput = form.querySelector('input[name="username"]');
            if (usernameInput && usernameInput.value) {
                var usernameRegex = /^[a-zA-Z0-9_-]+$/;
                if (!usernameRegex.test(usernameInput.value)) {
                    isValid = false;
                    showFieldError(usernameInput, 'Kullanıcı adı sadece harf, rakam, tire ve alt çizgi içerebilir');
                }
                if (usernameInput.value.length < 3) {
                    isValid = false;
                    showFieldError(usernameInput, 'Kullanıcı adı en az 3 karakter olmalıdır');
                }
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    function showFieldError(input, message) {
        input.classList.add('is-invalid');
        var existing = input.parentNode.querySelector('.form-error');
        if (!existing) {
            var errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            errorEl.textContent = message;
            input.parentNode.appendChild(errorEl);
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ==========================================
    // Character Counter for Bio
    // ==========================================
    const bioTextarea = document.querySelector('textarea[data-maxlength]');
    if (bioTextarea) {
        const maxLength = parseInt(bioTextarea.getAttribute('data-maxlength'));
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        bioTextarea.parentNode.appendChild(counter);

        function updateCounter() {
            const len = bioTextarea.value.length;
            counter.textContent = len + ' / ' + maxLength;
            counter.classList.remove('warn', 'over');
            if (len > maxLength) {
                counter.classList.add('over');
            } else if (len > maxLength * 0.85) {
                counter.classList.add('warn');
            }
        }

        bioTextarea.addEventListener('input', updateCounter);
        updateCounter();
    }

    // ==========================================
    // Smooth Scroll for Anchor Links
    // ==========================================
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href');
            if (targetId === '#') return;
            var target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ==========================================
    // Toast Notification Helper
    // ==========================================
    function showToast(message, type) {
        type = type || 'info';
        var container = document.querySelector('.flash-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'flash-container';
            document.body.appendChild(container);
        }

        var icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-exclamation',
            info: 'fa-circle-info',
            warning: 'fa-triangle-exclamation'
        };

        var flash = document.createElement('div');
        flash.className = 'flash flash--' + type;
        flash.innerHTML =
            '<i class="fas ' + (icons[type] || icons.info) + '"></i>' +
            '<div class="flash__content">' + message + '</div>' +
            '<span class="flash__close"><i class="fas fa-times"></i></span>';

        container.appendChild(flash);

        var timer = setTimeout(function () { dismissFlash(flash); }, 5000);

        flash.querySelector('.flash__close').addEventListener('click', function () {
            clearTimeout(timer);
            dismissFlash(flash);
        });
    }

    // Make showToast globally accessible
    window.showToast = showToast;

    // ==========================================
    // Password Toggle Visibility
    // ==========================================
    document.querySelectorAll('.toggle-password').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var input = this.closest('.form-group').querySelector('input');
            if (input.type === 'password') {
                input.type = 'text';
                this.querySelector('i').classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                this.querySelector('i').classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ==========================================
    // Theme Toggle (Dark / Light)
    // ==========================================
    var themeToggles = document.querySelectorAll('[data-theme-toggle]');

    function isLightTheme() {
        return document.documentElement.getAttribute('data-theme') === 'light';
    }

    function updateThemeIcons() {
        var light = isLightTheme();
        themeToggles.forEach(function (btn) {
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = light ? 'fas fa-sun' : 'fas fa-moon';
            }
            btn.setAttribute('aria-label', light ? 'Koyu temaya geç' : 'Açık temaya geç');
        });
    }

    updateThemeIcons();

    themeToggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (isLightTheme()) {
                document.documentElement.removeAttribute('data-theme');
                try { localStorage.setItem('theme', 'dark'); } catch (e) {}
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                try { localStorage.setItem('theme', 'light'); } catch (e) {}
            }
            updateThemeIcons();
        });
    });

    // ==========================================
    // Profile Theme Selector (dashboard)
    // ==========================================
    var themeOptions = document.querySelectorAll('.theme-option');
    if (themeOptions.length) {
        themeOptions.forEach(function (option) {
            option.addEventListener('click', function () {
                // Sadece aynı gruptaki seçenekleri sıfırla (tema / NFC hedefi ayrı gruplar)
                var group = option.closest('.theme-grid') || document;
                group.querySelectorAll('.theme-option').forEach(function (o) { o.classList.remove('selected'); });
                option.classList.add('selected');
                var radio = option.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    // ==========================================
    // Intersection Observer for Animations
    // ==========================================
    if ('IntersectionObserver' in window) {
        var animateElements = document.querySelectorAll('.animate-on-scroll');
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        animateElements.forEach(function (el) {
            el.style.opacity = '0';
            observer.observe(el);
        });
    }

});
