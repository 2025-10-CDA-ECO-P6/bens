<?php
/** @var array $orders */
/** @var array $tickets */
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
    <div class="mb-8">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-500 to-pink-500 flex items-center justify-center font-display font-bold text-xl text-white">
                <?= e(strtoupper(substr(Auth::name(), 0, 1))) ?>
            </div>
            <div>
                <h1 class="font-display text-2xl font-bold text-white"><?= e(Auth::name()) ?></h1>
                <p class="text-sm text-slate-400"><?= e(Auth::email()) ?></p>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-slate-900 rounded-2xl p-1.5 border border-white/10">
        <button class="tab-btn active flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-slate-800 transition-all" data-tab="tickets">Mes billets (<?= count($tickets) ?>)</button>
        <button class="tab-btn flex-1 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-400 hover:text-white transition-all" data-tab="orders">Historique des commandes (<?= count($orders) ?>)</button>
    </div>

    <!-- Tickets tab -->
    <div id="tab-tickets" class="tab-content">
        <?php if (empty($tickets)): ?>
        <div class="text-center py-16 bg-slate-900 rounded-3xl border border-white/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto text-slate-600 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 0v2m0-2h2m-2 0H9m7 4h2a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2h2m7 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
            <p class="text-slate-400 mb-4">Vous n'avez pas encore de billets.</p>
            <a href="<?= url('events') ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all">
                Découvrir les événements
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($tickets as $ticket): ?>
            <div class="relative bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl border border-white/10 overflow-hidden hover:border-orange-500/30 transition-all">
                <div class="absolute left-0 right-0 top-[55%] border-t border-dashed border-white/10"></div>
                <div class="absolute -left-3 top-[55%] w-6 h-6 rounded-full bg-slate-950"></div>
                <div class="absolute -right-3 top-[55%] w-6 h-6 rounded-full bg-slate-950"></div>

                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <h3 class="font-display font-bold text-white text-base leading-tight"><?= e($ticket['event_title']) ?></h3>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold flex items-center gap-1 bg-emerald-500/20 text-emerald-300">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            Actif
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <p class="text-xs text-slate-500">Date</p>
                            <p class="text-white font-medium"><?= format_date($ticket['event_date']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Heure</p>
                            <p class="text-white font-medium"><?= format_time($ticket['show_time']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500">Lieu</p>
                            <p class="text-white font-medium text-xs"><?= e($ticket['venue']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="p-5 pt-7 flex items-center gap-4">
                    <div class="flex-shrink-0">
                        <div class="w-20 h-20 rounded-xl bg-white p-1.5 flex items-center justify-center">
                            <div class="ticket-qr w-full h-full" data-qr-value="<?= e($ticket['ticket_code']) ?>"></div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-500 mb-0.5">Code</p>
                        <p class="font-mono text-xs text-white truncate"><?= e($ticket['ticket_code']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Orders tab -->
    <div id="tab-orders" class="tab-content hidden">
        <?php if (empty($orders)): ?>
        <div class="text-center py-16 bg-slate-900 rounded-3xl border border-white/10">
            <p class="text-slate-400">Aucune commande pour le moment.</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($orders as $order): ?>
            <a href="<?= url('account/order/' . $order['id']) ?>" class="flex items-center gap-4 p-5 bg-slate-900 rounded-2xl border border-white/10 hover:border-white/20 transition-all group">
                <img src="<?= e($order['image_url']) ?>" alt="" class="w-16 h-16 rounded-xl object-cover flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-white truncate group-hover:text-orange-400 transition-colors"><?= e($order['event_title']) ?></p>
                    <p class="text-xs text-slate-400"><?= format_date($order['event_date']) ?> · <?= e($order['order_reference']) ?></p>
                </div>
                <div class="text-right">
                    <p class="font-bold text-white"><?= format_price((float) $order['total_amount']) ?></p>
                    <span class="text-xs px-2 py-0.5 rounded-full <?php
                        $statusColors = [
                            'confirmed' => 'bg-emerald-500/20 text-emerald-300',
                            'pending' => 'bg-yellow-500/20 text-yellow-300',
                            'cancelled' => 'bg-red-500/20 text-red-300',
                        ];
                        echo e($statusColors[$order['status']] ?? 'bg-slate-700 text-slate-300');
                    ?>"><?= e($order['status']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= asset('js/qr.js') ?>"></script>
<script src="<?= asset('js/account.js') ?>"></script>
