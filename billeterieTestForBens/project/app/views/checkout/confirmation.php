<?php
/** @var array $order */
/** @var array $items */
/** @var array $tickets */
/** @var array $event */
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
    <!-- Success header -->
    <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-emerald-500/20 border-2 border-emerald-500/30 mb-6 animate-success-pop">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white mb-3">Paiement confirmé !</h1>
        <p class="text-slate-400 text-lg">Vos billets ont été générés avec succès. Un email de confirmation a été envoyé à <?= e($order['customer_email']) ?>.</p>
        <div class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-full bg-slate-800 border border-white/10">
            <span class="text-sm text-slate-400">Référence commande :</span>
            <span class="font-mono font-bold text-orange-400"><?= e($order['order_reference']) ?></span>
        </div>
    </div>

    <!-- Tickets -->
    <?php if (!empty($tickets)): ?>
    <div class="mb-8">
        <h2 class="font-display text-2xl font-bold text-white mb-5">Vos billets (<?= count($tickets) ?>)</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($tickets as $i => $ticket): ?>
            <div class="relative bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl border border-white/10 overflow-hidden group hover:border-orange-500/30 transition-all">
                <!-- Perforation -->
                <div class="absolute left-0 right-0 top-[60%] border-t border-dashed border-white/10"></div>
                <div class="absolute -left-3 top-[60%] w-6 h-6 rounded-full bg-slate-950"></div>
                <div class="absolute -right-3 top-[60%] w-6 h-6 rounded-full bg-slate-950"></div>

                <!-- Top section -->
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Billet #<?= $i + 1 ?></p>
                            <h3 class="font-display font-bold text-white text-lg leading-tight"><?= e($ticket['event_title']) ?></h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Actif
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Date</p>
                            <p class="text-white font-medium"><?= format_date($ticket['event_date']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Heure</p>
                            <p class="text-white font-medium"><?= format_time($ticket['show_time']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Lieu</p>
                            <p class="text-white font-medium"><?= e($ticket['venue']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5">Siège</p>
                            <p class="text-white font-medium text-xs"><?= e($ticket['seat_label'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                </div>

                <!-- Bottom section -->
                <div class="p-6 pt-8 flex items-center gap-4">
                    <!-- QR Code placeholder -->
                    <div class="flex-shrink-0">
                        <div class="w-24 h-24 rounded-2xl bg-white p-2 flex items-center justify-center">
                            <div class="ticket-qr w-full h-full" data-qr-value="<?= e($ticket['ticket_code']) ?>"></div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-500 mb-0.5">Code du billet</p>
                        <p class="font-mono text-sm text-white truncate"><?= e($ticket['ticket_code']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order summary -->
    <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 mb-8">
        <h3 class="font-display text-lg font-bold text-white mb-4">Récapitulatif de la commande</h3>
        <div class="space-y-2">
            <?php foreach ($items as $item): ?>
            <div class="flex items-center justify-between py-2 text-sm">
                <div>
                    <span class="text-white font-medium"><?= e($item['category_name']) ?></span>
                    <?php if ((int) $item['quantity'] > 1): ?>
                        <span class="text-slate-500 ml-2"><?= (int) $item['quantity'] ?> × <?= format_price((float) $item['unit_price']) ?></span>
                    <?php else: ?>
                        <span class="text-slate-500 ml-2"><?= e($item['seat_label']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="text-white"><?= format_price((float) $item['unit_price'] * (int) $item['quantity']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-white/10 mt-4 pt-4 flex justify-between">
            <span class="font-bold text-white">Total payé</span>
            <span class="font-display text-xl font-bold text-orange-400"><?= format_price((float) $order['total_amount']) ?></span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="<?= url('account') ?>" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white/10 text-white font-semibold rounded-xl hover:bg-white/15 transition-all">
            Mes billets
        </a>
        <a href="<?= url('events') ?>" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all">
            Autres événements
        </a>
    </div>
</section>

<script src="<?= asset('js/qr.js') ?>"></script>
