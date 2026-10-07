/* =========================================================================
   checkout.js — friendly card-number / expiry formatting on the checkout form
   ========================================================================= */
(function () {
    'use strict';

    var cardNumber = document.getElementById('cardNumber');
    var cardExpiry = document.getElementById('cardExpiry');
    var cardCvc    = document.getElementById('cardCvc');

    if (cardNumber) {
        cardNumber.addEventListener('input', function () {
            // keep only digits, group in 4s, cap at 19 digits
            var digits = this.value.replace(/\D/g, '').slice(0, 19);
            this.value = digits.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    if (cardExpiry) {
        cardExpiry.addEventListener('input', function () {
            var digits = this.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length >= 3) {
                this.value = digits.slice(0, 2) + '/' + digits.slice(2);
            } else {
                this.value = digits;
            }
        });
    }

    if (cardCvc) {
        cardCvc.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    }

    // Disable the submit button while a request is in flight so the user
    // doesn't double-submit the (simulated) payment.
    var form = document.getElementById('checkoutForm');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-wait');
                var original = btn.innerHTML;
                btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Traitement du paiement…';
                // Re-enable after 15s as a safety net if the redirect somehow fails.
                setTimeout(function () { btn.disabled = false; btn.innerHTML = original; }, 15000);
            }
        });
    }
})();
