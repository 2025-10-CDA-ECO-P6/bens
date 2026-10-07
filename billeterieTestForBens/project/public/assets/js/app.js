/* =========================================================================
   app.js — global UI behaviour (navbar, mobile menu, flash toasts)
   ========================================================================= */
(function () {
    'use strict';

    // ---- Navbar: add solid background once the user scrolls ----------------
    var navbar = document.getElementById('navbar');
    if (navbar) {
        var onScroll = function () {
            if (window.scrollY > 24) navbar.classList.add('scrolled');
            else navbar.classList.remove('scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ---- Mobile menu toggle ------------------------------------------------
    var mobileToggle = document.getElementById('mobileToggle');
    var mobileMenu = document.getElementById('mobileMenu');
    if (mobileToggle && mobileMenu) {
        mobileToggle.addEventListener('click', function () {
            mobileMenu.classList.toggle('hidden');
        });
        // Close the menu when a link is tapped
        mobileMenu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () { mobileMenu.classList.add('hidden'); });
        });
    }

    // ---- Flash toasts: auto-dismiss after a few seconds --------------------
    document.querySelectorAll('[id^="flash-"], .animate-slide-down').forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = el.style.transform.replace('translate(-50%, 0)', 'translate(-50%, -16px)');
            setTimeout(function () { el.remove(); }, 400);
        }, 4500);
    });

    // ---- Generic countdown: any element with data-countdown="ISO" ----------
    // Updates [data-cd-days/hours/mins/secs] children every second. When the
    // target is reached, reload the page so the now-open CTA appears.
    (function () {
        var nodes = document.querySelectorAll('[data-countdown]');
        if (nodes.length === 0) return;

        function pad(n) { return String(n).padStart(2, '0'); }

        function tick() {
            nodes.forEach(function (el) {
                var target = el.getAttribute('data-countdown');
                if (!target) return;
                var diff = Math.max(0, (new Date(target).getTime() - Date.now()) / 1000);
                var d = Math.floor(diff / 86400);
                var h = Math.floor((diff % 86400) / 3600);
                var m = Math.floor((diff % 3600) / 60);
                var s = Math.floor(diff % 60);
                var dEl = el.querySelector('[data-cd-days]');
                var hEl = el.querySelector('[data-cd-hours]');
                var mEl = el.querySelector('[data-cd-mins]');
                var sEl = el.querySelector('[data-cd-secs]');
                if (dEl) dEl.textContent = d;
                if (hEl) hEl.textContent = pad(h);
                if (mEl) mEl.textContent = pad(m);
                if (sEl) sEl.textContent = pad(s);
                if (diff <= 0) {
                    // Sale just opened — reload to reveal the active CTA.
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            });
        }
        tick();
        setInterval(tick, 1000);
    })();
})();
