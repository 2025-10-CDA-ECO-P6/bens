/* =========================================================================
   account.js — switch between "Mes billets" and "Historique des commandes"
   ========================================================================= */
(function () {
    'use strict';

    var tabBtns = document.querySelectorAll('.tab-btn');
    if (tabBtns.length === 0) return;

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.dataset.tab;

            tabBtns.forEach(function (b) {
                b.classList.remove('active', 'text-white', 'bg-slate-800');
                b.classList.add('text-slate-400');
                b.classList.remove('font-semibold');
                b.classList.add('font-medium');
            });
            btn.classList.add('active', 'text-white', 'bg-slate-800', 'font-semibold');
            btn.classList.remove('text-slate-400', 'font-medium');

            document.querySelectorAll('.tab-content').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.id !== 'tab-' + target);
            });
        });
    });
})();
