/**
 * public/assets/app.js
 * JavaScript propio de la aplicación CEBAC.
 */
(function () {
    'use strict';

    const $$ = (selector, contexto = document) => contexto.querySelectorAll(selector);
    const $ = (selector, contexto = document) => contexto.querySelector(selector);

    /* ==========================================================
       Tabs genéricos
       ========================================================== */
    function initTabs() {
        const tabs = $$('.tab');
        if (!tabs.length) return;
        tabs.forEach((tab) => {
            tab.addEventListener('click', function () {
                $$('.tab, .tab-panel').forEach((el) => el.classList.remove('active'));
                this.classList.add('active');
                const panel = document.querySelector('[data-panel="' + this.dataset.tab + '"]');
                if (panel) panel.classList.add('active');
            });
        });
    }

    /* ==========================================================
       Confirmaciones genéricas
       ========================================================== */
    function initConfirmaciones() {
        $$('a[data-confirm], button[data-confirm]').forEach((el) => {
            el.addEventListener('click', function (event) {
                if (!confirm(this.dataset.confirm)) {
                    event.preventDefault();
                }
            });
        });
    }

    /* ==========================================================
       ENTER → siguiente campo
       - Enter en input/select → siguiente campo
       - Enter en textarea → siguiente campo
       - Shift+Enter en textarea → salto de línea
       ========================================================== */
    function initEnterNavegacion() {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;

            const target = e.target;
            if (!target) return;

            const tag = target.tagName.toLowerCase();
            if (tag === 'button' || tag === 'a') return;
            if (target.type === 'submit' || target.type === 'button') return;

            // En textarea: solo navegar si NO se presiona Shift
            if (tag === 'textarea' && e.shiftKey) {
                return; // permitir salto de línea
            }

            const form = target.closest('form');
            if (!form) return;

            const campos = Array.from(form.querySelectorAll(
                'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])'
            )).filter(function (el) {
                return el.offsetParent !== null && !el.hidden;
            });

            const index = campos.indexOf(target);
            if (index === -1) return;
            if (index === campos.length - 1) return;

            e.preventDefault();
            const siguiente = campos[index + 1];
            siguiente.focus();
            if (typeof siguiente.select === 'function' && siguiente.tagName.toLowerCase() === 'input') {
                try { siguiente.select(); } catch (err) {}
            }
        });
    }

    /* ==========================================================
       MODAL VENTANA
       ========================================================== */
    window.initModalVentana = function () {
        if (window._modalVentanaInit) return;
        window._modalVentanaInit = true;

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.modal-accion');
            if (!btn) return;
            const modal = btn.closest('.modal-ventana');
            if (!modal) return;
            e.preventDefault();
            e.stopPropagation();

            if (btn.classList.contains('minimizar')) {
                modal.classList.toggle('modal-minimizado');
                modal.classList.remove('modal-maximizado');
                const icono = btn.querySelector('i');
                if (modal.classList.contains('modal-minimizado')) {
                    if (icono) icono.className = 'bi bi-square';
                    btn.title = 'Restaurar';
                } else {
                    if (icono) icono.className = 'bi bi-dash-lg';
                    btn.title = 'Minimizar';
                }
                return;
            }

            if (btn.classList.contains('maximizar')) {
                modal.classList.toggle('modal-maximizado');
                modal.classList.remove('modal-minimizado');
                const icono = btn.querySelector('i');
                if (modal.classList.contains('modal-maximizado')) {
                    if (icono) icono.className = 'bi bi-fullscreen-exit';
                    btn.title = 'Restaurar';
                } else {
                    if (icono) icono.className = 'bi bi-fullscreen';
                    btn.title = 'Maximizar';
                }
                return;
            }

            if (btn.classList.contains('cerrar')) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                } else {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
                return;
            }
        });

        document.addEventListener('click', function (e) {
            const header = e.target.closest('.modal-header');
            if (!header) return;
            const modal = header.closest('.modal-ventana');
            if (!modal) return;
            if (!modal.classList.contains('modal-minimizado')) return;
            if (e.target.closest('.modal-accion')) return;

            modal.classList.remove('modal-minimizado');
            const btnMin = modal.querySelector('.modal-accion.minimizar');
            if (btnMin) {
                const icono = btnMin.querySelector('i');
                if (icono) icono.className = 'bi bi-dash-lg';
                btnMin.title = 'Minimizar';
            }
        });

        document.addEventListener('hidden.bs.modal', function (e) {
            const modal = e.target;
            if (!modal.classList || !modal.classList.contains('modal-ventana')) return;
            modal.classList.remove('modal-minimizado', 'modal-maximizado');
            const btnMin = modal.querySelector('.modal-accion.minimizar');
            const btnMax = modal.querySelector('.modal-accion.maximizar');
            if (btnMin) {
                const icono = btnMin.querySelector('i');
                if (icono) icono.className = 'bi bi-dash-lg';
                btnMin.title = 'Minimizar';
            }
            if (btnMax) {
                const icono = btnMax.querySelector('i');
                if (icono) icono.className = 'bi bi-fullscreen';
                btnMax.title = 'Maximizar';
            }
        });
    };
    
    /* ==========================================================
       Ejecutar <script> de HTML cargado por AJAX
       ========================================================== */
    window.ejecutarScripts = function (container) {
        var scripts = container.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            var newScript = document.createElement('script');
            if (oldScript.src) {
                newScript.src = oldScript.src;
            } else {
                newScript.textContent = oldScript.textContent;
            }
            document.body.appendChild(newScript);
            newScript.remove();
        });
    };
    /* ==========================================================
       Init
       ========================================================== */
    function init() {
        initTabs();
        initConfirmaciones();
        initEnterNavegacion();
        initModalVentana();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();