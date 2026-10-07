<?php
/** @var array|null $featured */
/** @var array $others */
/** @var array $allEvents */

$catLabels = ['festival' => 'Festival', 'concert' => 'Concert', 'sport' => 'Sport', 'rush' => 'Rush Sale'];
$catColors = [
    'festival' => 'from-orange-500 to-pink-500',
    'concert' => 'from-cyan-500 to-blue-500',
    'sport' => 'from-emerald-500 to-teal-500',
    'rush' => 'from-red-500 to-orange-500',
];

$featuredIsQuota  = $featured ? \Event::isQuotaEvent((int) $featured['id']) : false;
$featuredSaleOpen = $featured ? \Event::isSaleOpen($featured['sale_open_at']) : true;
$featuredBuyUrl   = $featuredIsQuota
    ? url('event/' . $featured['slug'] . '/buy')
    : url('event/' . $featured['slug'] . '/seats');
?>
<?php if ($featured): ?>
<section class="relative min-h-screen flex items-center overflow-hidden">
    <div class="absolute inset-0 z-0">
        <img src="<?= e($featured['image_url']) ?>" alt="<?= e($featured['title']) ?>" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-slate-950/30"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/40 to-transparent"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12 w-full">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-orange-500/20 border border-orange-500/30 backdrop-blur-md mb-6 animate-fade-in-up">
                <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse"></span>
                <span class="text-xs font-semibold text-orange-300 uppercase tracking-wider">À la une · <?= e($catLabels[$featured['category']] ?? 'Événement') ?></span>
            </div>

            <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-extrabold text-white leading-[1.05] mb-4 animate-fade-in-up animation-delay-100">
                <?= e($featured['title']) ?>
            </h1>
            <p class="text-lg sm:text-xl text-slate-300 mb-8 leading-relaxed animate-fade-in-up animation-delay-200">
                <?= e($featured['subtitle']) ?>
            </p>

            <div class="flex flex-wrap items-center gap-4 mb-8 text-sm text-slate-300 animate-fade-in-up animation-delay-300">
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= format_date($featured['event_date']) ?>
                </span>
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                    <?= e($featured['venue']) ?>, <?= e($featured['city']) ?>
                </span>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 animate-fade-in-up animation-delay-400">
                <?php if ($featuredSaleOpen): ?>
                <a href="<?= $featuredBuyUrl ?>" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-2xl text-lg hover:shadow-2xl hover:shadow-orange-500/40 transition-all hover:scale-[1.03]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24  24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 0v2m0-2h2m-2 0H9m7 4h2a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2h2m7 0V7a2 2 0 00-2-2H9a2 2 0 00-2 2v4"/></svg>
                    Acheter des billets
                </a>
                <?php else: ?>
                <?php
                    $opensAt = new DateTime($featured['sale_open_at']);
                    $opensLabel = $opensAt->format('j') . ' ' . ['jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'][(int)$opensAt->format('n') - 1] . ' à ' . $opensAt->format('H\hi');
                ?>
                <div class="flex flex-col gap-3">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-red-500/20 border border-red-500/40 backdrop-blur-md">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <span class="text-xs font-semibold text-red-200 uppercase tracking-wider">Vente ouvre le <?= e($opensLabel) ?></span>
                    </div>
                    <div class="flex items-center gap-2" data-countdown="<?= e($featured['sale_open_at']) ?>">
                        <div class="flex flex-col items-center px-3 py-2 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 min-w-[56px]">
                            <span class="font-display text-2xl font-bold text-white" data-cd-days>--</span>
                            <span class="text-[10px] text-slate-300 uppercase">jours</span>
                        </div>
                        <div class="flex flex-col items-center px-3 py-2 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 min-w-[56px]">
                            <span class="font-display text-2xl font-bold text-white" data-cd-hours>--</span>
                            <span class="text-[10px] text-slate-300 uppercase">heures</span>
                        </div>
                        <div class="flex flex-col items-center px-3 py-2 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 min-w-[56px]">
                            <span class="font-display text-2xl font-bold text-white" data-cd-mins>--</span>
                            <span class="text-[10px] text-slate-300 uppercase">min</span>
                        </div>
                        <div class="flex flex-col items-center px-3 py-2 rounded-xl bg-white/10 backdrop-blur-md border border-white/10 min-w-[56px]">
                            <span class="font-display text-2xl font-bold text-white" data-cd-secs>--</span>
                            <span class="text-[10px] text-slate-300 uppercase">sec</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <a href="<?= url('event/' . $featured['slug']) ?>" class="inline-flex items-center justify-center gap-2 px-8 py-4 bg-white/10 backdrop-blur-md text-white font-semibold rounded-2xl text-lg border border-white/20 hover:bg-white/15 transition-all">
                    Plus d'infos
                </a>
            </div>
        </div>
    </div>

    <div class="absolute bottom-0 left-0 right-0 z-10 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-2 text-slate-400 text-xs animate-bounce">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                Défiler pour voir plus d'événements
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="flex items-end justify-between mb-12">
        <div>
            <h2 class="font-display text-4xl font-bold text-white mb-2">Événements à venir</h2>
            <p class="text-slate-400">Découvrez notre sélection et réservez vos places.</p>
        </div>
        <a href="<?= url('events') ?>" class="hidden sm:inline-flex items-center gap-2 text-sm font-medium text-orange-500 hover:text-orange-400 transition-colors group">
            Voir tout
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>

    <?php if (!empty($others)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($others as $event):
            $stats = \Event::stats((int) $event['id']);
        ?>
        <a href="<?= url('event/' . $event['slug']) ?>" class="group relative rounded-3xl overflow-hidden bg-slate-900 border border-white/5 hover:border-white/20 transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
            <div class="relative h-64 overflow-hidden">
                <img src="<?= e($event['image_url']) ?>" alt="<?= e($event['title']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/30 to-transparent"></div>
                <div class="absolute top-4 left-4">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-white bg-gradient-to-r <?= e($catColors[$event['category']] ?? 'from-slate-700 to-slate-800') ?> backdrop-blur-md shadow-lg">
                        <?= e($catLabels[$event['category']] ?? 'Événement') ?>
                    </span>
                </div>
                <?php if ($stats['percent'] > 50): ?>
                <div class="absolute top-4 right-4">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-white bg-red-500/80 backdrop-blur-md shadow-lg">
                        Plus que <?= (100 - $stats['percent']) ?>% dispo
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <h3 class="font-display text-xl font-bold text-white mb-1 group-hover:text-orange-400 transition-colors"><?= e($event['title']) ?></h3>
                <p class="text-sm text-slate-400 mb-4"><?= e($event['subtitle']) ?></p>
                <div class="flex items-center justify-between text-sm">
                    <div class="flex flex-col gap-1 text-slate-400">
                        <span class="inline-flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <?= format_date($event['event_date']) ?>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                            <?= e($event['venue']) ?>, <?= e($event['city']) ?>
                        </span>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                    <span class="text-xs text-slate-500"><?= $stats['available'] ?> places dispo</span>
                    <span class="text-sm font-semibold text-orange-500 group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">
                        Réserver
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-slate-400 text-center py-12">Aucun autre événement pour le moment.</p>
    <?php endif; ?>
</section>

<section class="py-16 px-4 sm:px-6 lg:px-8 bg-slate-900/50 border-y border-white/5">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-orange-500/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="font-semibold text-white text-sm mb-1">Billets sécurisés</h3>
                <p class="text-xs text-slate-400">QR code de contrôle à l'entrée</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-cyan-500/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <h3 class="font-semibold text-white text-sm mb-1">Réservation instantanée</h3>
                <p class="text-xs text-slate-400">Verrouillage des sièges en temps réel</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-emerald-500/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <h3 class="font-semibold text-white text-sm mb-1">Paiement sécurisé</h3>
                <p class="text-xs text-slate-400">Transactions chiffrées en Euros</p>
            </div>
            <div class="text-center">
                <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-purple-500/10 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="font-semibold text-white text-sm mb-1">TTL 10 minutes</h3>
                <p class="text-xs text-slate-400">Votre panier réservé pendant 10 min</p>
            </div>
        </div>
    </div>
</section>
