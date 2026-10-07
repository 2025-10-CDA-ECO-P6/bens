/* =========================================================================
   seatmap.js — interactive seat selection with server-side locking
   -------------------------------------------------------------------------
   Behaviour:
   - Click an available seat → POST /api/seats/lock → on success it is
     added to the cart and a 10-minute countdown starts.
   - Click a seat already selected by this session → POST /api/seats/unlock
     → removed from the cart.
   - Countdown reaching 0 → all selected seats are unlocked and the cart
     is emptied (the server also expires locks independently).
   - Periodically polls /api/seats/status/{eventId} so seats bought/locked
     by other users appear correctly without a page reload.
   - "Procéder au paiement" → redirects to /checkout (server reads the
     locks held for this PHP session).
   ========================================================================= */
(function () {
    'use strict';

    if (typeof window.EVENT_ID === 'undefined') return;

    var EVENT_ID      = window.EVENT_ID;
    var MAX_TICKETS   = window.MAX_TICKETS || 4;
    var LOCK_URL      = window.SEAT_LOCK_URL;
    var UNLOCK_URL    = window.SEAT_UNLOCK_URL;
    var STATUS_URL    = '/api/seats/status/' + EVENT_ID;
    var CHECKOUT_URL  = window.CHECKOUT_URL;
    var CSRF_TOKEN    = window.CSRF_TOKEN || '';

    // ---- DOM refs ----------------------------------------------------------
    var selectedSeatsEl = document.getElementById('selectedSeats');
    var emptyState      = document.getElementById('emptyState');
    var seatCountEl     = document.getElementById('seatCount');
    var totalEl         = document.getElementById('totalAmount');
    var checkoutBtn    = document.getElementById('checkoutBtn');
    var clearBtn       = document.getElementById('clearSelection');
    var timerEl        = document.getElementById('lockTimer');
    var timerTextEl    = document.getElementById('timerText');

    // ---- State -------------------------------------------------------------
    // Map of seatId -> {id, label, category, price, color, el}
    var selection = new Map();
    var lockExpiresAt = null;   // epoch ms when the first lock expires
    var timerInterval = null;
    var pollInterval  = null;
    var busy = false;           // prevent double-clicks during in-flight requests

    // ---- Helpers -----------------------------------------------------------
    function formatPrice(n) {
        return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    function formatTime(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    async function api(url, body) {
        var opts = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        };
        if (body !== undefined) opts.body = JSON.stringify(body);
        if (CSRF_TOKEN) opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
        var res = await fetch(url, opts);
        var data;
        try { data = await res.json(); } catch (e) { data = {}; }
        return { ok: res.ok, status: res.status, data: data };
    }

    // ---- Cart rendering ----------------------------------------------------
    function renderCart() {
        // Empty state
        if (emptyState) emptyState.style.display = selection.size === 0 ? '' : 'none';

        // Remove old seat rows
        selectedSeatsEl.querySelectorAll('[data-cart-row]').forEach(function (n) { n.remove(); });

        var total = 0;
        selection.forEach(function (s) {
            total += s.price;
            var row = document.createElement('div');
            row.setAttribute('data-cart-row', s.id);
            row.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5';
            row.innerHTML =
                '<div class="flex items-center gap-2 min-w-0">' +
                  '<span class="w-3 h-3 rounded-full flex-shrink-0" style="background:' + s.color + '"></span>' +
                  '<div class="min-w-0">' +
                    '<p class="text-sm font-medium text-white truncate">' + s.category + '</p>' +
                    '<p class="text-xs text-slate-400 truncate">' + s.label + '</p>' +
                  '</div>' +
                '</div>' +
                '<div class="flex items-center gap-2 flex-shrink-0">' +
                  '<span class="text-sm font-semibold text-orange-400">' + formatPrice(s.price) + '</span>' +
                  '<button type="button" class="text-slate-500 hover:text-red-400 transition-colors" data-remove="' + s.id + '" aria-label="Retirer">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
                  '</button>' +
                '</div>';
            selectedSeatsEl.appendChild(row);
        });

        if (seatCountEl) {
            seatCountEl.textContent = selection.size + (selection.size > 1 ? ' sièges' : ' siège');
        }
        if (totalEl) totalEl.textContent = formatPrice(total);

        if (checkoutBtn) checkoutBtn.disabled = selection.size === 0;
        if (clearBtn)    clearBtn.classList.toggle('hidden', selection.size === 0);
    }

    // ---- Timer -------------------------------------------------------------
    function startTimer() {
        if (timerInterval) return; // already running
        if (timerEl) timerEl.classList.remove('hidden');
        timerInterval = setInterval(function () {
            if (!lockExpiresAt) return;
            var remaining = Math.max(0, Math.floor((lockExpiresAt - Date.now()) / 1000));
            if (timerTextEl) timerTextEl.textContent = formatTime(remaining);
            if (remaining <= 0) {
                stopTimer();
                // Locks expired — clear local selection; server side expires too.
                selection.forEach(function (s) { markSeat(s.el, 'available'); });
                selection.clear();
                renderCart();
                if (timerEl) timerEl.classList.add('hidden');
                alert('Votre délai de réservation a expiré. Les sièges sélectionnés ont été libérés.');
            }
        }, 1000);
    }

    function stopTimer() {
        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    }

    function bumpTimer(ttlSeconds) {
        var newExpiry = Date.now() + ttlSeconds * 1000;
        if (!lockExpiresAt || newExpiry > lockExpiresAt) lockExpiresAt = newExpiry;
        startTimer();
    }

    // ---- Seat visual state -------------------------------------------------
    function markSeat(el, state) {
        if (!el) return;
        el.classList.remove('selected', 'bg-orange-500', 'text-white', 'shadow-lg', 'shadow-orange-500/30',
            'bg-slate-700', 'text-slate-300', 'hover:bg-slate-600', 'hover:scale-110', 'cursor-pointer',
            'bg-slate-600', 'text-slate-400', 'cursor-not-allowed',
            'bg-slate-900', 'border-2', 'border-yellow-500/50', 'text-yellow-500/50');
        el.removeAttribute('disabled');
        switch (state) {
            case 'selected':
                el.classList.add('selected', 'bg-orange-500', 'text-white', 'shadow-lg', 'shadow-orange-500/30');
                el.dataset.seatStatus = 'locked-mine';
                break;
            case 'locked-other':
                el.classList.add('bg-slate-900', 'border-2', 'border-yellow-500/50', 'text-yellow-500/50', 'cursor-not-allowed');
                el.setAttribute('disabled', '');
                el.dataset.seatStatus = 'locked-other';
                break;
            case 'sold':
                el.classList.add('bg-slate-600', 'text-slate-400', 'cursor-not-allowed');
                el.setAttribute('disabled', '');
                el.dataset.seatStatus = 'sold';
                break;
            default: // available
                el.classList.add('bg-slate-700', 'text-slate-300', 'hover:bg-slate-600', 'hover:scale-110', 'cursor-pointer');
                el.dataset.seatStatus = 'available';
        }
    }

    // ---- Lock / unlock actions --------------------------------------------
    async function selectSeat(el) {
        if (busy) return;
        var id = parseInt(el.dataset.seatId, 10);
        if (selection.has(id)) return; // already selected
        if (selection.size >= MAX_TICKETS) {
            alert('Maximum ' + MAX_TICKETS + ' billets par personne pour cet événement.');
            return;
        }
        busy = true;
        el.style.opacity = '0.5';
        var r = await api(LOCK_URL, { seat_id: id });
        el.style.opacity = '';
        busy = false;
        if (!r.ok || !r.data.success) {
            var msg = (r.data && r.data.error) ? r.data.error : 'Siège indisponible.';
            alert(msg);
            markSeat(el, 'sold');
            return;
        }
        var s = r.data.seat;
        selection.set(s.id, {
            id: s.id, label: s.label, category: s.category,
            price: parseFloat(s.price), color: s.color || el.dataset.seatColor, el: el
        });
        markSeat(el, 'selected');
        bumpTimer(r.data.ttl || 600);
        renderCart();
    }

    async function unselectSeat(id) {
        if (busy) return;
        var s = selection.get(id);
        if (!s) return;
        busy = true;
        await api(UNLOCK_URL, { seat_id: id });
        busy = false;
        selection.delete(id);
        markSeat(s.el, 'available');
        if (selection.size === 0) {
            stopTimer();
            lockExpiresAt = null;
            if (timerEl) timerEl.classList.add('hidden');
        }
        renderCart();
    }

    async function unlockAll() {
        if (selection.size === 0) return;
        busy = true;
        await api(UNLOCK_URL, {}); // server unlocks all seats for this session
        busy = false;
        selection.forEach(function (s) { markSeat(s.el, 'available'); });
        selection.clear();
        stopTimer();
        lockExpiresAt = null;
        if (timerEl) timerEl.classList.add('hidden');
        renderCart();
    }

    // ---- Status polling ----------------------------------------------------
    async function pollStatus() {
        try {
            var res = await fetch(STATUS_URL, { credentials: 'same-origin' });
            var data = await res.json();
            if (!data || !data.success || !data.seats) return;
            Object.keys(data.seats).forEach(function (idStr) {
                var status = data.seats[idStr];
                var el = document.querySelector('.seat-btn[data-seat-id="' + idStr + '"]');
                if (!el) return;
                var id = parseInt(idStr, 10);
                if (selection.has(id)) return; // don't touch our own seats
                if (status === 'sold')      markSeat(el, 'sold');
                else if (status === 'locked') markSeat(el, 'locked-other');
                else                         markSeat(el, 'available');
            });
        } catch (e) { /* ignore transient errors */ }
    }

    // ---- Wire up -----------------------------------------------------------
    document.querySelectorAll('.seat-btn').forEach(function (el) {
        // Pre-populate selection from seats already locked by this session
        if (el.classList.contains('selected')) {
            selection.set(parseInt(el.dataset.seatId, 10), {
                id: parseInt(el.dataset.seatId, 10),
                label: el.dataset.seatLabel,
                category: el.dataset.seatCategory,
                price: parseFloat(el.dataset.seatPrice),
                color: el.dataset.seatColor,
                el: el
            });
            bumpTimer(600); // assume full TTL on reload
        }
        el.addEventListener('click', function () {
            var id = parseInt(el.dataset.seatId, 10);
            if (selection.has(id)) unselectSeat(id);
            else selectSeat(el);
        });
    });

    // Remove buttons (event delegation on the cart container)
    if (selectedSeatsEl) {
        selectedSeatsEl.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove]');
            if (!btn) return;
            e.preventDefault();
            unselectSeat(parseInt(btn.dataset.remove, 10));
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            if (selection.size === 0) return;
            window.location.href = CHECKOUT_URL;
        });
    }

    if (clearBtn) clearBtn.addEventListener('click', unlockAll);

    // Release locks when the user navigates away (best-effort via sendBeacon).
    window.addEventListener('beforeunload', function () {
        if (selection.size === 0) return;
        try {
            var blob = new Blob([JSON.stringify({})], { type: 'application/json' });
            navigator.sendBeacon(UNLOCK_URL, blob);
        } catch (e) { /* ignore */ }
    });

    renderCart();
    pollStatus();
    pollInterval = setInterval(pollStatus, 8000); // refresh every 8s
})();
