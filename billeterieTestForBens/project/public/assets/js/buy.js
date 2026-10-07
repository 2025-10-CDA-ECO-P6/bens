/* =========================================================================
   buy.js — quota-based purchase (large venue / rush sale)
   -------------------------------------------------------------------------
   - Quantity stepper per category.
   - "Ajouter au panier" → POST /api/inventory/lock {category_id, quantity}
     (atomic decrement of the category counter).
   - Remove from cart → POST /api/inventory/unlock {category_id}
   - 10-minute countdown; at 0 the cart is emptied (server keeps the counter
     until unlock, so we also call unlock on expiry).
   - Polls /api/inventory/status/{eventId} to refresh live availability.
   ========================================================================= */
(function () {
    'use strict';

    if (typeof window.EVENT_ID === 'undefined') return;

    var EVENT_ID      = window.EVENT_ID;
    var MAX_TICKETS  = window.MAX_TICKETS || 4;
    var LOCK_URL      = window.INV_LOCK_URL;
    var UNLOCK_URL    = window.INV_UNLOCK_URL;
    var STATUS_URL    = window.INV_STATUS_URL;
    var CHECKOUT_URL  = window.CHECKOUT_URL;
    var CSRF_TOKEN    = window.CSRF_TOKEN || '';

    var cartEl       = document.getElementById('cartItems');
    var cartEmpty    = document.getElementById('cartEmpty');
    var cartCountEl  = document.getElementById('cartCount');
    var cartTotalEl  = document.getElementById('cartTotal');
    var checkoutBtn  = document.getElementById('checkoutBtn');
    var clearBtn     = document.getElementById('clearCart');
    var timerEl      = document.getElementById('lockTimer');
    var timerTextEl  = document.getElementById('timerText');

    // cart: Map<categoryId, {category_id, category_name, unit_price, color, quantity}>
    var cart = new Map();
    var lockExpiresAt = null;
    var timerInterval = null;
    var pollInterval  = null;
    var busy = false;

    // ---- helpers ----------------------------------------------------------
    function formatPrice(n) {
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }
    function formatTime(sec) {
        var m = Math.floor(sec / 60), s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }
    async function api(url, body) {
        var opts = { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
        if (body !== undefined) opts.body = JSON.stringify(body);
        if (CSRF_TOKEN) opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
        var res = await fetch(url, opts);
        try { return { ok: res.ok, status: res.status, data: await res.json() }; }
        catch (e) { return { ok: false, status: res.status, data: {} }; }
    }

    // ---- cart rendering ---------------------------------------------------
    function totalQuantity() {
        var t = 0; cart.forEach(function (i) { t += i.quantity; }); return t;
    }
    function totalPrice() {
        var t = 0; cart.forEach(function (i) { t += i.quantity * i.unit_price; }); return t;
    }
    function renderCart() {
        if (cartEmpty) cartEmpty.style.display = cart.size === 0 ? '' : 'none';
        cartEl.querySelectorAll('[data-cart-row]').forEach(function (n) { n.remove(); });
        cart.forEach(function (i) {
            var row = document.createElement('div');
            row.setAttribute('data-cart-row', i.category_id);
            row.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5';
            row.innerHTML =
                '<div class="flex items-center gap-2 min-w-0">' +
                  '<span class="w-3 h-3 rounded-full flex-shrink-0" style="background:' + i.color + '"></span>' +
                  '<div class="min-w-0"><p class="text-sm font-medium text-white truncate">' + i.category_name + '</p>' +
                  '<p class="text-xs text-slate-400">' + i.quantity + ' × ' + formatPrice(i.unit_price) + '</p></div>' +
                '</div>' +
                '<button type="button" class="text-slate-500 hover:text-red-400 transition-colors" data-remove="' + i.category_id + '" aria-label="Retirer">' +
                  '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
                '</button>';
            cartEl.appendChild(row);
        });
        var qty = totalQuantity();
        if (cartCountEl) cartCountEl.textContent = qty + (qty > 1 ? ' billets' : ' billet');
        if (cartTotalEl) cartTotalEl.textContent = formatPrice(totalPrice());
        if (checkoutBtn) checkoutBtn.disabled = cart.size === 0;
        if (clearBtn) clearBtn.classList.toggle('hidden', cart.size === 0);
        updateAddButtons();
    }

    function updateAddButtons() {
        document.querySelectorAll('.add-btn').forEach(function (btn) {
            var catId = parseInt(btn.dataset.cat, 10);
            var input = document.querySelector('.qty-input[data-cat="' + catId + '"]');
            var qty = input ? parseInt(input.value, 10) || 0 : 0;
            var remaining = parseInt(document.querySelector('[data-remaining="' + catId + '"]').textContent.replace(/\s/g, ''), 10) || 0;
            var left = MAX_TICKETS - totalQuantity();
            // can't add if: qty 0, no remaining, or would exceed max per user
            btn.disabled = (qty <= 0) || (remaining < qty) || (qty > left);
        });
    }

    // ---- timer ------------------------------------------------------------
    function startTimer() {
        if (timerInterval) return;
        if (timerEl) timerEl.classList.remove('hidden');
        timerInterval = setInterval(function () {
            if (!lockExpiresAt) return;
            var remaining = Math.max(0, Math.floor((lockExpiresAt - Date.now()) / 1000));
            if (timerTextEl) timerTextEl.textContent = formatTime(remaining);
            if (remaining <= 0) {
                stopTimer();
                expireAll();
            }
        }, 1000);
    }
    function stopTimer() { if (timerInterval) { clearInterval(timerInterval); timerInterval = null; } }
    function bumpTimer(ttl) {
        var exp = Date.now() + ttl * 1000;
        if (!lockExpiresAt || exp > lockExpiresAt) lockExpiresAt = exp;
        startTimer();
    }

    // ---- actions ----------------------------------------------------------
    async function addToCart(catId) {
        if (busy) return;
        var input = document.querySelector('.qty-input[data-cat="' + catId + '"]');
        var qty = input ? parseInt(input.value, 10) || 0 : 0;
        if (qty <= 0) return;
        if (totalQuantity() + qty > MAX_TICKETS) {
            alert('Maximum ' + MAX_TICKETS + ' billets par personne.');
            return;
        }
        busy = true;
        var r = await api(LOCK_URL, { category_id: catId, quantity: qty });
        busy = false;
        if (!r.ok || !r.data.success) {
            alert((r.data && r.data.error) ? r.data.error : 'Plus assez de billets disponibles.');
            return;
        }
        var it = r.data.item;
        // If category already in cart, stack quantities
        if (cart.has(catId)) {
            cart.get(catId).quantity += it.quantity;
        } else {
            cart.set(catId, it);
        }
        bumpTimer(r.data.ttl || 600);
        if (input) input.value = 0;
        renderCart();
    }

    async function removeFromCart(catId) {
        if (busy) return;
        if (!cart.has(catId)) return;
        busy = true;
        await api(UNLOCK_URL, { category_id: catId });
        busy = false;
        cart.delete(catId);
        if (cart.size === 0) { stopTimer(); lockExpiresAt = null; if (timerEl) timerEl.classList.add('hidden'); }
        renderCart();
    }

    async function clearCart() {
        if (cart.size === 0) return;
        busy = true;
        await api(UNLOCK_URL, {});
        busy = false;
        cart.clear();
        stopTimer(); lockExpiresAt = null;
        if (timerEl) timerEl.classList.add('hidden');
        renderCart();
    }

    async function expireAll() {
        // Release everything server-side, clear locally.
        try { await api(UNLOCK_URL, {}); } catch (e) {}
        cart.clear();
        stopTimer(); lockExpiresAt = null;
        if (timerEl) timerEl.classList.add('hidden');
        renderCart();
        alert('Votre délai de réservation a expiré. Votre panier a été vidé.');
    }

    // ---- availability polling --------------------------------------------
    async function pollStatus() {
        try {
            var res = await fetch(STATUS_URL, { credentials: 'same-origin' });
            var data = await res.json();
            if (!data || !data.success || !data.availability) return;
            Object.keys(data.availability).forEach(function (idStr) {
                var avail = data.availability[idStr];
                var el = document.querySelector('[data-remaining="' + idStr + '"]');
                if (el) el.textContent = avail.toLocaleString('fr-FR');
            });
            updateAddButtons();
        } catch (e) {}
    }

    // ---- wire up ----------------------------------------------------------
    // Init cart from server-rendered state (e.g. page reload mid-session)
    (window.INITIAL_CART || []).forEach(function (i) {
        cart.set(i.category_id, i);
        bumpTimer(600);
    });

    // Quantity steppers
    document.querySelectorAll('.qty-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var catId = btn.dataset.cat;
            var action = btn.dataset.action;
            var input = document.querySelector('.qty-input[data-cat="' + catId + '"]');
            if (!input) return;
            var val = parseInt(input.value, 10) || 0;
            var remaining = parseInt(document.querySelector('[data-remaining="' + catId + '"]').textContent.replace(/\s/g, ''), 10) || 0;
            var left = MAX_TICKETS - totalQuantity();
            if (action === 'inc') {
                val = Math.min(val + 1, remaining, left);
            } else {
                val = Math.max(0, val - 1);
            }
            input.value = val;
            updateAddButtons();
        });
    });
    document.querySelectorAll('.qty-input').forEach(function (input) {
        input.addEventListener('input', updateAddButtons);
    });

    // Add buttons
    document.querySelectorAll('.add-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { addToCart(parseInt(btn.dataset.cat, 10)); });
    });

    // Remove (delegation)
    cartEl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-remove]');
        if (b) { e.preventDefault(); removeFromCart(parseInt(b.dataset.remove, 10)); }
    });

    if (checkoutBtn) checkoutBtn.addEventListener('click', function () {
        if (cart.size === 0) return;
        window.location.href = CHECKOUT_URL;
    });
    if (clearBtn) clearBtn.addEventListener('click', clearCart);

    // Release on leave (best-effort)
    window.addEventListener('beforeunload', function () {
        if (cart.size === 0) return;
        try {
            navigator.sendBeacon(UNLOCK_URL, new Blob([JSON.stringify({})], { type: 'application/json' }));
        } catch (e) {}
    });

    renderCart();
    pollStatus();
    pollInterval = setInterval(pollStatus, 8000);
})();
