/* =========================================================================
   qr.js — render QR codes for every element with class `ticket-qr`
   Uses the qrcodejs library (QRCode global) loaded in <head> from jsDelivr.
   Each .ticket-qr carries its payload in data-qr-value.
   ========================================================================= */
(function () {
    'use strict';

    function renderAll() {
        var nodes = document.querySelectorAll('.ticket-qr[data-qr-value]');
        nodes.forEach(function (node) {
            if (node.dataset.qrRendered === '1') return;
            var value = node.getAttribute('data-qr-value');
            if (!value) return;
            node.innerHTML = ''; // clear any placeholder
            try {
                new QRCode(node, {
                    text: value,
                    width: 200,
                    height: 200,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
                node.dataset.qrRendered = '1';
            } catch (e) {
                // Fallback: show the raw code if the library failed to load
                node.textContent = value;
            }
        });
    }

    if (typeof QRCode === 'undefined') {
        // Library not yet ready — retry shortly.
        var tries = 0;
        var wait = setInterval(function () {
            tries++;
            if (typeof QRCode !== 'undefined' || tries > 40) {
                clearInterval(wait);
                renderAll();
            }
        }, 100);
    } else {
        renderAll();
    }
})();
