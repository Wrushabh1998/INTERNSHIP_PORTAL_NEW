/**
 * InternTrack Pro — Main JavaScript
 * Handles: Dark Mode, Sidebar, Toast, Modal, Dropdown, Tabs, Session Timeout
 */

(function () {
    'use strict';

    // ── Theme (Dark / Light) ──────────────────────────────────────────────
    const ThemeManager = {
        KEY: 'interntrack_theme',
        init() {
            const saved = localStorage.getItem(this.KEY) || 'light';
            this.apply(saved);
            document.querySelectorAll('[data-theme-toggle]').forEach(el => {
                el.addEventListener('click', () => this.toggle());
            });
        },
        apply(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.querySelectorAll('[data-theme-toggle]').forEach(el => {
                if (el.classList.contains('theme-toggle')) {
                    el.classList.toggle('dark', theme === 'dark');
                }
                const icon = el.querySelector('.theme-icon');
                if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
            });
            localStorage.setItem(this.KEY, theme);
        },
        toggle() {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            this.apply(current === 'dark' ? 'light' : 'dark');
        }
    };

    // ── Sidebar ───────────────────────────────────────────────────────────
    const Sidebar = {
        KEY: 'sidebar_collapsed',
        sidebar: null,
        main: null,
        backdrop: null,
        init() {
            this.sidebar  = document.querySelector('.sidebar');
            this.main     = document.querySelector('.main-content');
            this.backdrop = document.querySelector('.sidebar-backdrop');
            if (!this.sidebar) return;

            // Desktop: restore collapsed state
            const collapsed = localStorage.getItem(this.KEY) === 'true';
            if (collapsed && window.innerWidth > 768) this.collapse();

            // Toggle button
            document.querySelectorAll('[data-sidebar-toggle]').forEach(btn => {
                btn.addEventListener('click', () => this.toggle());
            });

            // Backdrop (mobile close)
            if (this.backdrop) {
                this.backdrop.addEventListener('click', () => this.closeMobile());
            }

            // Resize handler
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) this.closeMobile();
            });
        },
        toggle() {
            if (window.innerWidth <= 768) {
                this.sidebar.classList.toggle('mobile-open');
                if (this.backdrop) this.backdrop.classList.toggle('show', this.sidebar.classList.contains('mobile-open'));
            } else {
                const isCollapsed = this.sidebar.classList.contains('collapsed');
                if (isCollapsed) { this.expand(); } else { this.collapse(); }
            }
        },
        collapse() {
            this.sidebar.classList.add('collapsed');
            if (this.main) this.main.classList.add('sidebar-collapsed');
            localStorage.setItem(this.KEY, 'true');
        },
        expand() {
            this.sidebar.classList.remove('collapsed');
            if (this.main) this.main.classList.remove('sidebar-collapsed');
            localStorage.setItem(this.KEY, 'false');
        },
        closeMobile() {
            this.sidebar.classList.remove('mobile-open');
            if (this.backdrop) this.backdrop.classList.remove('show');
        }
    };

    // ── Toast Notifications ────────────────────────────────────────────────
    const Toast = {
        container: null,
        icons: {
            success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️'
        },
        init() {
            this.container = document.getElementById('toast-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toast-container';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        show(type, title, message = '', duration = 4000) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <span class="toast-icon">${this.icons[type] || 'ℹ️'}</span>
                <div class="toast-body">
                    <div class="toast-title">${title}</div>
                    ${message ? `<div class="toast-msg">${message}</div>` : ''}
                </div>
                <button class="toast-close" aria-label="Close">✕</button>
            `;
            this.container.appendChild(toast);
            toast.querySelector('.toast-close').addEventListener('click', () => this.remove(toast));
            if (duration > 0) setTimeout(() => this.remove(toast), duration);
        },
        remove(toast) {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }
    };

    // ── Modal ──────────────────────────────────────────────────────────────
    const Modal = {
        init() {
            document.querySelectorAll('[data-modal-open]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.getAttribute('data-modal-open');
                    this.open(id);
                });
            });
            document.querySelectorAll('[data-modal-close], .modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    const overlay = btn.closest('.modal-overlay');
                    if (overlay) this.closeEl(overlay);
                });
            });
            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', e => {
                    if (e.target === overlay) this.closeEl(overlay);
                });
            });
            // ESC key
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal-overlay.open').forEach(o => this.closeEl(o));
                }
            });
        },
        open(id) {
            const overlay = document.getElementById(id);
            if (overlay) { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
        },
        close(id) {
            const overlay = document.getElementById(id);
            if (overlay) this.closeEl(overlay);
        },
        closeEl(overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    };

    // ── Dropdown ───────────────────────────────────────────────────────────
    const Dropdown = {
        init() {
            document.querySelectorAll('[data-dropdown]').forEach(trigger => {
                const targetId = trigger.getAttribute('data-dropdown');
                const menu     = document.getElementById(targetId);
                if (!menu) return;
                trigger.addEventListener('click', e => {
                    e.stopPropagation();
                    const open = menu.classList.contains('open');
                    document.querySelectorAll('.dropdown-menu.open, .notif-panel.open').forEach(m => m.classList.remove('open'));
                    if (!open) menu.classList.add('open');
                });
            });
            document.addEventListener('click', () => {
                document.querySelectorAll('.dropdown-menu.open, .notif-panel.open').forEach(m => m.classList.remove('open'));
            });
        }
    };

    // ── Tabs ───────────────────────────────────────────────────────────────
    const Tabs = {
        init() {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const group   = btn.closest('[data-tabs]') || btn.closest('.card') || btn.parentElement.parentElement;
                    const targetId = btn.getAttribute('data-tab');
                    group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    document.querySelectorAll('.tab-content').forEach(pane => {
                        pane.classList.toggle('active', pane.id === targetId);
                    });
                });
            });
        }
    };

    // ── Confirm Dialog ─────────────────────────────────────────────────────
    window.confirmAction = function (message, onConfirm) {
        const overlay = document.getElementById('confirm-modal');
        if (!overlay) {
            // Inline fallback
            if (window.confirm(message)) onConfirm();
            return;
        }
        document.getElementById('confirm-message').textContent = message;
        overlay.classList.add('open');
        const confirmBtn = document.getElementById('confirm-ok');
        const cancelBtn  = document.getElementById('confirm-cancel');
        const close = () => { overlay.classList.remove('open'); document.body.style.overflow = ''; };
        confirmBtn.onclick = () => { close(); onConfirm(); };
        cancelBtn.onclick  = close;
        overlay.onclick    = e => { if (e.target === overlay) close(); };
    };

    // ── Session Timeout Warning ────────────────────────────────────────────
    const SessionWatcher = {
        TIMEOUT: 1800, // 30 min in seconds — must match PHP SESSION_TIMEOUT
        WARNING:  120, // warn 2 min before
        timer: null,
        init() {
            if (!document.getElementById('session-warning-modal')) return;
            this.reset();
            ['click','keydown','mousemove','touchstart'].forEach(ev => {
                document.addEventListener(ev, () => this.reset(), { passive: true });
            });
        },
        reset() {
            clearTimeout(this.timer);
            const warningDelay = (this.TIMEOUT - this.WARNING) * 1000;
            this.timer = setTimeout(() => this.warn(), warningDelay);
        },
        warn() {
            Modal.open('session-warning-modal');
            let remaining = this.WARNING;
            const counter = document.getElementById('session-countdown');
            const interval = setInterval(() => {
                remaining--;
                if (counter) counter.textContent = remaining;
                if (remaining <= 0) {
                    clearInterval(interval);
                    window.location.href = '/INTERNSHIP_PORTAL_NEW/logout.php?timeout=1';
                }
            }, 1000);
            document.getElementById('session-extend')?.addEventListener('click', () => {
                clearInterval(interval);
                Modal.close('session-warning-modal');
                // Ping server to extend
                fetch('/INTERNSHIP_PORTAL_NEW/ajax/ping.php').catch(() => {});
                this.reset();
            });
        }
    };

    // ── Notification Badge (polling) ───────────────────────────────────────
    const NotifBadge = {
        init() {
            const badge = document.getElementById('notif-count');
            if (!badge) return;
            this.fetch();
            setInterval(() => this.fetch(), 30000); // every 30s
        },
        fetch() {
            fetch('/INTERNSHIP_PORTAL_NEW/ajax/notifications.php?action=count')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notif-count');
                    if (!badge) return;
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }
    };

    // ── Page Loader ────────────────────────────────────────────────────────
    function hideLoader() {
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.classList.add('hidden');
            setTimeout(() => loader.remove(), 500);
        }
    }

    // ── AJAX Form Helper ───────────────────────────────────────────────────
    window.submitForm = function (formId, url, onSuccess) {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn  = form.querySelector('[type=submit]');
            const orig = btn ? btn.innerHTML : '';
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner spinner-sm"></span> Processing…'; }
            const data = new FormData(form);
            fetch(url, { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Toast.show('success', 'Success', res.message);
                        if (onSuccess) onSuccess(res);
                    } else {
                        Toast.show('error', 'Error', res.message);
                    }
                })
                .catch(() => Toast.show('error', 'Network Error', 'Request failed. Try again.'))
                .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = orig; } });
        });
    };

    // ── Progress Bar Animate ───────────────────────────────────────────────
    function animateProgressBars() {
        document.querySelectorAll('.progress-bar[data-value]').forEach(bar => {
            const val = parseInt(bar.getAttribute('data-value'), 10);
            bar.style.width = '0%';
            setTimeout(() => { bar.style.width = val + '%'; }, 100);
        });
    }

    // ── Auto-dismiss alerts ────────────────────────────────────────────────
    function autoDismissAlerts() {
        document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
            const delay = parseInt(alert.getAttribute('data-auto-dismiss'), 10) || 5000;
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }, delay);
        });
    }

    // ── Delete confirmation ────────────────────────────────────────────────
    function initDeleteButtons() {
        document.querySelectorAll('[data-confirm-delete]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const msg = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item? This cannot be undone.';
                const href = this.href || this.getAttribute('data-href');
                confirmAction(msg, () => {
                    if (href) window.location.href = href;
                });
            });
        });
    }

    // ── Copy to Clipboard ─────────────────────────────────────────────────
    window.copyToClipboard = function (text, label = 'Copied!') {
        navigator.clipboard.writeText(text).then(() => {
            Toast.show('success', label);
        });
    };

    // ── Number counter animation ───────────────────────────────────────────
    function animateCounters() {
        document.querySelectorAll('[data-counter]').forEach(el => {
            const target = parseInt(el.getAttribute('data-counter'), 10);
            let current = 0;
            const step = Math.ceil(target / 40);
            const timer = setInterval(() => {
                current = Math.min(current + step, target);
                el.textContent = current.toLocaleString();
                if (current >= target) clearInterval(timer);
            }, 25);
        });
    }

    // ── Init All ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        ThemeManager.init();
        Sidebar.init();
        Toast.init();
        Modal.init();
        Dropdown.init();
        Tabs.init();
        SessionWatcher.init();
        NotifBadge.init();
        hideLoader();
        animateProgressBars();
        autoDismissAlerts();
        initDeleteButtons();
        animateCounters();
    });

    window.addEventListener('load', hideLoader);

    // Expose globals
    window.Toast   = Toast;
    window.Modal   = Modal;
    window.Sidebar = Sidebar;
    window.ThemeManager = ThemeManager;

})();
