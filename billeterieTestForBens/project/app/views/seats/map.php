<?php
/** @var array $event */
/** @var array $categories */
/** @var array $grouped */
/** @var array $stats */
/** @var string $sessionId */
/** @var array $lockedSeats */
?>
<section class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="<?= url('event/' . $event['slug']) ?>" class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition-colors mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <?= e($event['title']) ?>
        </a>
        <h1 class="font-display text-3xl sm:text-4xl font-bold text-white">Plan des sièges</h1>
        <p class="text-slate-400 mt-1">Cliquez sur les sièges disponibles pour les sélectionner. Verrouillage de 10 minutes.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
        <!-- Seat Map -->
        <div class="bg-slate-900 rounded-3xl border border-white/10 p-6 overflow-x-auto">
            <!-- Stage -->
            <div class="text-center mb-8">
                <div class="inline-block w-full max-w-md py-4 rounded-2xl bg-gradient-to-r from-orange-500/20 via-pink-500/20 to-orange-500/20 border border-orange-500/30">
                    <p class="font-display font-bold text-orange-300 text-lg tracking-widest uppercase">Scène</p>
                </div>
                <div class="mx-auto mt-3 w-0 h-0 border-l-[20px] border-l-transparent border-t-[30px] border-t-slate-800 border-r-[20px] border-r-transparent"></div>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center justify-center gap-4 mb-6 text-xs">
                <span class="flex items-center gap-2 text-slate-400"><span class="w-4 h-4 rounded bg-slate-700"></span> Disponible</span>
                <span class="flex items-center gap-2 text-slate-400"><span class="w-4 h-4 rounded bg-orange-500"></span> Sélectionné</span>
                <span class="flex items-center gap-2 text-slate-400"><span class="w-4 h-4 rounded bg-slate-600"></span> Vendu</span>
                <span class="flex items-center gap-2 text-slate-400"><span class="w-4 h-4 rounded border-2 border-yellow-500 bg-slate-900"></span> En attente (autre session)</span>
            </div>

            <!-- Categories -->
            <?php foreach ($grouped as $catName => $catData): ?>
                <div class="mb-8 last:mb-0">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-4 h-4 rounded-full" style="background: <?= e($catData['color']) ?>"></span>
                        <h3 class="font-semibold text-white text-sm"><?= e($catName) ?> — <?= format_price($catData['price']) ?></h3>
                    </div>

                    <?php foreach ($catData['sections'] as $sectionName => $rows): ?>
                        <div class="mb-6">
                            <p class="text-xs text-slate-500 uppercase tracking-wider mb-2"><?= e($sectionName) ?></p>
                            <div class="space-y-1.5">
                                <?php foreach ($rows as $rowLabel => $rowSeats): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-slate-500 w-6 text-center font-mono"><?= e($rowLabel) ?></span>
                                        <div class="flex gap-1 flex-wrap">
                                            <?php foreach ($rowSeats as $seat):
                                                $isLockedByMe = in_array((int) $seat['id'], array_map(fn($s) => (int) $s['id'], $lockedSeats));
                                            ?>
                                                <button
                                                    type="button"
                                                    class="seat-btn w-7 h-7 rounded text-[10px] font-bold transition-all duration-150 flex items-center justify-center
                                                    <?php if ($seat['status'] === 'sold'): ?>
                                                        bg-slate-600 text-slate-400 cursor-not-allowed
                                                    <?php elseif ($isLockedByMe): ?>
                                                        bg-orange-500 text-white shadow-lg shadow-orange-500/30 selected
                                                    <?php elseif ($seat['status'] === 'locked'): ?>
                                                        bg-slate-900 border-2 border-yellow-500/50 text-yellow-500/50 cursor-not-allowed
                                                    <?php else: ?>
                                                        bg-slate-700 text-slate-300 hover:bg-slate-600 hover:scale-110 cursor-pointer
                                                    <?php endif; ?>"
                                                    data-seat-id="<?= (int) $seat['id'] ?>"
                                                    data-seat-label="<?= e(\Seat::seatLabel($seat)) ?>"
                                                    data-seat-price="<?= (float) $seat['price'] ?>"
                                                    data-seat-category="<?= e($seat['category_name']) ?>"
                                                    data-seat-color="<?= e($seat['color']) ?>"
                                                    data-seat-status="<?= e($seat['status']) ?>"
                                                    <?php if ($seat['status'] === 'sold' || ($seat['status'] === 'locked' && !$isLockedByMe)): ?>disabled<?php endif; ?>
                                                ><?= (int) $seat['seat_number'] ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Cart Sidebar -->
        <div class="lg:sticky lg:top-24 self-start">
            <div class="bg-slate-900 rounded-3xl border border-white/10 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-display text-lg font-bold text-white">Ma sélection</h3>
                    <span class="px-3 py-1 rounded-full bg-orange-500/20 text-orange-300 text-xs font-bold" id="seatCount">0 siège</span>
                </div>

                <!-- Timer -->
                <div id="lockTimer" class="hidden mb-4 px-4 py-3 rounded-xl bg-orange-500/10 border border-orange-500/20">
                    <div class="flex items-center gap-2 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                        <span class="text-orange-300 font-mono font-bold" id="timerText">10:00</span>
                        <span class="text-slate-400 text-xs">restantes</span>
                    </div>
                </div>

                <!-- Selected seats list -->
                <div id="selectedSeats" class="space-y-2 mb-4 min-h-[60px]">
                    <p class="text-sm text-slate-500 text-center py-4" id="emptyState">Aucun siège sélectionné. Cliquez sur le plan pour commencer.</p>
                </div>

                <!-- Total -->
                <div class="border-t border-white/10 pt-4 mb-5">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-sm">Total</span>
                        <span class="font-display text-2xl font-bold text-white" id="totalAmount">0,00 €</span>
                    </div>
                </div>

                <button id="checkoutBtn" disabled class="w-full px-6 py-4 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-2xl transition-all disabled:opacity-40 disabled:cursor-not-allowed enabled:hover:shadow-xl enabled:hover:shadow-orange-500/30 enabled:hover:scale-[1.02]">
                    Procéder au paiement
                </button>

                <button id="clearSelection" class="hidden w-full mt-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition-colors">
                    Tout désélectionner
                </button>

                <p class="text-xs text-slate-500 mt-4 text-center">Maximum <?= (int) $event['max_tickets_per_user'] ?> billets par personne</p>
            </div>
        </div>
    </div>
</section>

<script>
    window.EVENT_ID = <?= (int) $event['id'] ?>;
    window.MAX_TICKETS = <?= (int) $event['max_tickets_per_user'] ?>;
    window.SEAT_LOCK_URL = '<?= url('api/seats/lock') ?>';
    window.SEAT_UNLOCK_URL = '<?= url('api/seats/unlock') ?>';
    window.CHECKOUT_URL = '<?= url('checkout') ?>';
    window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<script src="<?= asset('js/seatmap.js') ?>"></script>
