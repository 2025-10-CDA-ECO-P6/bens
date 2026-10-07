<?php
/** @var array $event */
/** @var array $seats */
/** @var float $total */
/** @var int $maxTickets */
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
    <div class="mb-8">
        <a href="<?= url('event/' . $event['slug'] . '/seats') ?>" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Retour au plan des sièges
        </a>
        <h1 class="font-display text-3xl font-bold text-white">Paiement</h1>
        <p class="text-slate-400 mt-1">Plus que quelques étapes avant de recevoir vos billets.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6">
        <!-- Form -->
        <div class="space-y-6">
            <!-- Customer info -->
            <div class="bg-slate-900 rounded-3xl border border-white/10 p-6">
                <h2 class="font-display text-lg font-bold text-white mb-5 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-sm font-bold">1</span>
                    Vos informations
                </h2>
                <form id="checkoutForm" method="POST" action="<?= url('checkout/process') ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Prénom</label>
                            <input type="text" name="first_name" required value="<?= e(old('first_name', Auth::check() ? explode(' ', Auth::name())[0] : '')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="Jean">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Nom</label>
                            <input type="text" name="last_name" required value="<?= e(old('last_name', Auth::check() ? explode(' ', Auth::name())[1] ?? '' : '')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="Dupont">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" required value="<?= e(old('email', Auth::email())) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="jean.dupont@email.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Téléphone <span class="text-slate-500">(optionnel)</span></label>
                        <input type="tel" name="phone" value="<?= e(old('phone')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="06 12 34 56 78">
                    </div>

                    <!-- Payment -->
                    <h2 class="font-display text-lg font-bold text-white pt-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-sm font-bold">2</span>
                        Paiement par carte
                    </h2>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Numéro de carte</label>
                        <input type="text" name="card_number" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors font-mono" placeholder="4242 4242 4242 4242" maxlength="19" id="cardNumber">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Expiration</label>
                            <input type="text" name="card_expiry" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors font-mono" placeholder="MM/AA" maxlength="5" id="cardExpiry">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">CVC</label>
                            <input type="text" name="card_cvc" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors font-mono" placeholder="123" maxlength="4" id="cardCvc">
                        </div>
                    </div>

                    <button type="submit" class="w-full px-6 py-4 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-2xl hover:shadow-xl hover:shadow-orange-500/30 transition-all hover:scale-[1.01] flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Payer <?= format_price($total) ?>
                    </button>

                    <p class="text-xs text-slate-500 text-center flex items-center justify-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Paiement sécurisé — vos billets seront générés après confirmation
                    </p>
                </form>
            </div>
        </div>

        <!-- Summary -->
        <div class="self-start">
            <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 sticky top-24">
                <h3 class="font-display text-lg font-bold text-white mb-1"><?= e($event['title']) ?></h3>
                <p class="text-xs text-slate-400 mb-4"><?= format_date($event['event_date']) ?> · <?= e($event['venue']) ?></p>

                <div class="space-y-2 mb-4">
                    <?php foreach ($seats as $seat): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background: <?= e($seat['color']) ?>"></span>
                            <div>
                                <p class="text-sm font-medium text-white"><?= e($seat['category_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= e(\Seat::seatLabel($seat)) ?></p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-orange-400"><?= format_price((float) $seat['price']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($quotaItems as $item): ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full" style="background: <?= e($item['color']) ?>"></span>
                            <div>
                                <p class="text-sm font-medium text-white"><?= e($item['category_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= (int) $item['quantity'] ?> × <?= format_price((float) $item['unit_price']) ?></p>
                            </div>
                        </div>
                        <span class="text-sm font-semibold text-orange-400"><?= format_price((float) $item['unit_price'] * (int) $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-white/10 pt-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Sous-total</span>
                        <span class="text-white"><?= format_price($total) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-400">Frais de service</span>
                        <span class="text-white">0,00 €</span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-white/5">
                        <span class="font-bold text-white">Total</span>
                        <span class="font-display text-xl font-bold text-orange-400"><?= format_price($total) ?></span>
                    </div>
                </div>

                <div class="mt-4 px-3 py-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                    <p class="text-xs text-emerald-300 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Billets avec QR code de contrôle à l'entrée
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="<?= asset('js/checkout.js') ?>"></script>
