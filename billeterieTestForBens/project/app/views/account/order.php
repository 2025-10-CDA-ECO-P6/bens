<?php
/** @var array $order */
/** @var array $items */
/** @var array $tickets */
/** @var array $event */
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
    <div class="mb-8">
        <a href="<?= url('account') ?>" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Mon compte
        </a>
        <h1 class="font-display text-3xl font-bold text-white">Commande <?= e($order['order_reference']) ?></h1>
        <p class="text-slate-400 mt-1"><?= e($event['title']) ?> · <?= format_date($event['event_date']) ?></p>
    </div>

    <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-slate-500 mb-1">Statut</p>
                <span class="text-sm font-semibold <?= $order['status'] === 'confirmed' ? 'text-emerald-400' : 'text-yellow-400' ?>"><?= e($order['status']) ?></span>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Paiement</p>
                <span class="text-sm font-semibold <?= $order['payment_status'] === 'paid' ? 'text-emerald-400' : 'text-yellow-400' ?>"><?= e($order['payment_status']) ?></span>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Date</p>
                <p class="text-sm text-white"><?= format_date(substr($order['created_at'], 0, 10)) ?></p>
            </div>
            <div>
                <p class="text-xs text-slate-500 mb-1">Total</p>
                <p class="text-sm font-bold text-orange-400"><?= format_price((float) $order['total_amount']) ?></p>
            </div>
        </div>
    </div>

    <?php if (!empty($tickets)): ?>
    <h2 class="font-display text-xl font-bold text-white mb-4">Billets</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <?php foreach ($tickets as $i => $ticket): ?>
        <div class="relative bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl border border-white/10 overflow-hidden">
            <div class="absolute left-0 right-0 top-[60%] border-t border-dashed border-white/10"></div>
            <div class="absolute -left-3 top-[60%] w-6 h-6 rounded-full bg-slate-950"></div>
            <div class="absolute -right-3 top-[60%] w-6 h-6 rounded-full bg-slate-950"></div>
            <div class="p-5">
                <h3 class="font-display font-bold text-white mb-2"><?= e($ticket['event_title']) ?></h3>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><p class="text-xs text-slate-500">Date</p><p class="text-white"><?= format_date($ticket['event_date']) ?></p></div>
                    <div><p class="text-xs text-slate-500">Heure</p><p class="text-white"><?= format_time($ticket['show_time']) ?></p></div>
                    <div><p class="text-xs text-slate-500">Lieu</p><p class="text-white text-xs"><?= e($ticket['venue']) ?></p></div>
                    <div><p class="text-xs text-slate-500">Siège</p><p class="text-white text-xs"><?= e($ticket['seat_label'] ?? '') ?></p></div>
                </div>
            </div>
            <div class="p-5 pt-8 flex items-center gap-4">
                <div class="w-20 h-20 rounded-xl bg-white p-1.5">
                    <div class="ticket-qr w-full h-full" data-qr-value="<?= e($ticket['ticket_code']) ?>"></div>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Code</p>
                    <p class="font-mono text-xs text-white"><?= e($ticket['ticket_code']) ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-slate-900 rounded-3xl border border-white/10 p-6">
        <h3 class="font-semibold text-white mb-4">Détail</h3>
        <?php foreach ($items as $item): ?>
        <div class="flex justify-between py-2 text-sm border-b border-white/5 last:border-0">
            <span class="text-slate-300"><?= e($item['category_name']) ?> — <?= e($item['seat_label']) ?></span>
            <span class="text-white"><?= format_price((float) $item['unit_price']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="flex justify-between pt-4 mt-2">
            <span class="font-bold text-white">Total</span>
            <span class="font-display text-xl font-bold text-orange-400"><?= format_price((float) $order['total_amount']) ?></span>
        </div>
    </div>
</div>

<script src="<?= asset('js/qr.js') ?>"></script>
