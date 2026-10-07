<?php
/** @var array $event */
/** @var array $categories */
/** @var array $stats */
/** @var array $cart */
$maxPerUser = (int) $event['max_tickets_per_user'];
$inCart = 0;
foreach ($cart as $item) $inCart += (int) $item['quantity'];
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="<?= url('event/' . $event['slug']) ?>" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <?= e($event['title']) ?>
        </a>
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">Acheter mes billets</h1>
        <p class="text-slate-400 mt-1">Choisissez la quantité par catégorie. Verrouillage de 10 minutes à chaque ajout.</p>
    </div>

    <!-- Rush banner -->
    <div class="mb-6 px-4 py-3 rounded-2xl bg-red-500/10 border border-red-500/20 flex items-center gap-3">
        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
        <span class="text-sm text-red-300 font-semibold uppercase tracking-wider">Rush Sale</span>
        <span class="text-sm text-slate-400">— événement à très forte demande. Chaque sélection est verrouillée 10 min.</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
        <!-- Categories -->
        <div class="space-y-4">
            <?php foreach ($categories as $cat):
                $remaining = (int) $cat['quota_total'] - (int) $cat['quota_sold'] - (int) $cat['quota_locked'];
                $pctSold = $cat['quota_total'] > 0 ? round(((int)$cat['quota_sold'] / (int)$cat['quota_total']) * 100) : 0;
            ?>
            <div class="bg-slate-900 rounded-3xl border border-white/10 p-6" data-category-card="<?= (int) $cat['id'] ?>">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <span class="w-4 h-4 rounded-full flex-shrink-0" style="background: <?= e($cat['color']) ?>"></span>
                        <div>
                            <h3 class="font-display text-lg font-bold text-white"><?= e($cat['name']) ?></h3>
                            <p class="text-xs text-slate-500 mt-0.5"><?= number_format((int) $cat['quota_total'], 0, ',', ' ') ?> billets au total</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-display text-2xl font-bold text-orange-400"><?= format_price((float) $cat['price']) ?></p>
                        <p class="text-xs text-slate-500">l'unité</p>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="text-slate-400">Disponibles</span>
                        <span class="font-semibold text-white" data-remaining="<?= (int) $cat['id'] ?>"><?= number_format($remaining, 0, ',', ' ') ?></span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-800 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-orange-500 to-pink-500 transition-all duration-500" style="width: <?= $pctSold ?>%"></div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center bg-slate-800 rounded-xl border border-white/10 overflow-hidden">
                        <button type="button" class="qty-btn w-10 h-11 text-xl text-slate-300 hover:text-white hover:bg-white/5 transition-colors" data-action="dec" data-cat="<?= (int) $cat['id'] ?>" aria-label="Diminuer">−</button>
                        <input type="number" min="0" max="<?= $maxPerUser ?>" value="0" class="qty-input w-14 h-11 bg-transparent text-center text-white font-bold focus:outline-none border-x border-white/10 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" data-cat="<?= (int) $cat['id'] ?>" data-price="<?= (float) $cat['price'] ?>" data-name="<?= e($cat['name']) ?>" data-color="<?= e($cat['color']) ?>">
                        <button type="button" class="qty-btn w-10 h-11 text-xl text-slate-300 hover:text-white hover:bg-white/5 transition-colors" data-action="inc" data-cat="<?= (int) $cat['id'] ?>" aria-label="Augmenter">+</button>
                    </div>
                    <button type="button" class="add-btn flex-1 px-5 py-3 bg-white/5 hover:bg-orange-500/20 text-white font-semibold rounded-xl border border-white/10 hover:border-orange-500/40 transition-all disabled:opacity-40 disabled:cursor-not-allowed" data-cat="<?= (int) $cat['id'] ?>">
                        Ajouter au panier
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Cart sidebar -->
        <div class="lg:sticky lg:top-24 self-start">
            <div class="bg-slate-900 rounded-3xl border border-white/10 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-display text-lg font-bold text-white">Mon panier</h3>
                    <span class="px-3 py-1 rounded-full bg-orange-500/20 text-orange-300 text-xs font-bold" id="cartCount"><?= $inCart ?> billet<?= $inCart > 1 ? 's' : '' ?></span>
                </div>

                <div id="lockTimer" class="hidden mb-4 px-4 py-3 rounded-xl bg-orange-500/10 border border-orange-500/20">
                    <div class="flex items-center gap-2 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                        <span class="text-orange-300 font-mono font-bold" id="timerText">10:00</span>
                        <span class="text-slate-400 text-xs">restantes</span>
                    </div>
                </div>

                <div id="cartItems" class="space-y-2 mb-4 min-h-[60px]">
                    <?php if (empty($cart)): ?>
                    <p class="text-sm text-slate-500 text-center py-4" id="cartEmpty">Aucun billet sélectionné. Choisissez une quantité ci-contre.</p>
                    <?php else: foreach ($cart as $item): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5" data-cart-row="<?= (int) $item['category_id'] ?>">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" style="background: <?= e($item['color']) ?>"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-white truncate"><?= e($item['category_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= (int) $item['quantity'] ?> × <?= format_price($item['unit_price']) ?></p>
                            </div>
                        </div>
                        <button type="button" class="text-slate-500 hover:text-red-400 transition-colors" data-remove="<?= (int) $item['category_id'] ?>" aria-label="Retirer">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="border-t border-white/10 pt-4 mb-5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-sm">Total</span>
                        <span class="font-display text-2xl font-bold text-white" id="cartTotal">0,00 €</span>
                    </div>
                </div>

                <button id="checkoutBtn" disabled class="w-full px-6 py-4 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-2xl transition-all disabled:opacity-40 disabled:cursor-not-allowed enabled:hover:shadow-xl enabled:hover:shadow-orange-500/30 enabled:hover:scale-[1.02]">
                    Procéder au paiement
                </button>

                <button id="clearCart" class="hidden w-full mt-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    Vider le panier
                </button>

                <p class="text-xs text-slate-500 mt-4 text-center">Maximum <?= $maxPerUser ?> billets par personne</p>
            </div>
        </div>
    </div>
</section>

<script>
    window.EVENT_ID     = <?= (int) $event['id'] ?>;
    window.MAX_TICKETS  = <?= $maxPerUser ?>;
    window.INV_LOCK_URL    = '<?= url('api/inventory/lock') ?>';
    window.INV_UNLOCK_URL  = '<?= url('api/inventory/unlock') ?>';
    window.INV_STATUS_URL  = '<?= url('api/inventory/status/' . (int) $event['id']) ?>';
    window.CHECKOUT_URL   = '<?= url('checkout') ?>';
    window.CSRF_TOKEN      = '<?= csrf_token() ?>';
    window.INITIAL_CART = <?= json_encode(array_map(fn($i) => [
        'category_id' => (int)$i['category_id'],
        'category_name' => $i['category_name'],
        'unit_price' => (float)$i['unit_price'],
        'color' => $i['color'],
        'quantity' => (int)$i['quantity'],
    ], $cart)) ?>;
</script>
<script src="<?= asset('js/buy.js') ?>"></script>
