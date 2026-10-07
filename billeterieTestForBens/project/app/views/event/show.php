<?php
/** @var array $event */
/** @var array $categories */
/** @var array $stats */
$catLabels = ['festival' => 'Festival', 'concert' => 'Concert', 'sport' => 'Sport', 'rush' => 'Rush Sale'];
$isQuota = !empty(array_filter($categories, fn($c) => $c['quota_total'] !== null));
$ctaUrl  = $isQuota ? url('event/' . $event['slug'] . '/buy') : url('event/' . $event['slug'] . '/seats');
$ctaLabel = $isQuota ? 'Acheter mes billets' : 'Choisir mes sièges';
$ctaHint  = $isQuota ? 'Choisissez la quantité par catégorie.' : 'Sélectionnez vos places sur le plan interactif.';
?>
<section class="relative h-[55vh] min-h-[400px] overflow-hidden">
    <img src="<?= e($event['image_url']) ?>" alt="<?= e($event['title']) ?>" class="absolute inset-0 w-full h-full object-cover">
    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-950/20"></div>
    <div class="absolute bottom-0 left-0 right-0 pb-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="<?= url('events') ?>" class="inline-flex items-center gap-2 text-sm text-slate-300 hover:text-white transition-colors mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Retour aux événements
            </a>
            <div class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-white bg-orange-500/30 border border-orange-500/40 backdrop-blur-md mb-4">
                <?= e($catLabels[$event['category']] ?? 'Événement') ?>
            </div>
            <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white mb-2 leading-tight"><?= e($event['title']) ?></h1>
            <p class="text-lg text-slate-300 mb-4"><?= e($event['subtitle']) ?></p>
            <div class="flex flex-wrap items-center gap-5 text-sm text-slate-300">
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= format_date($event['event_date']) ?>
                </span>
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2"/></svg>
                    Ouverture <?= format_time($event['door_time']) ?> · Concert <?= format_time($event['show_time']) ?>
                </span>
                <span class="inline-flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                    <?= e($event['venue']) ?>, <?= e($event['city']) ?>
                </span>
            </div>
        </div>
    </div>
</section>

<section class="py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <h2 class="font-display text-2xl font-bold text-white mb-4">À propos de l'événement</h2>
            <div class="prose prose-invert max-w-none">
                <?php foreach (explode("\n\n", $event['description']) as $paragraph): ?>
                    <p class="text-slate-300 leading-relaxed mb-4"><?= nl2br(e($paragraph)) ?></p>
                <?php endforeach; ?>
            </div>

            <?php if ($event['gallery_image_1']): ?>
            <h3 class="font-display text-xl font-bold text-white mt-8 mb-4">Galerie</h3>
            <div class="grid grid-cols-3 gap-3">
                <img src="<?= e($event['gallery_image_1']) ?>" alt="Galerie 1" class="rounded-2xl object-cover h-40 w-full hover:scale-105 transition-transform cursor-pointer">
                <img src="<?= e($event['gallery_image_2']) ?>" alt="Galerie 2" class="rounded-2xl object-cover h-40 w-full hover:scale-105 transition-transform cursor-pointer">
                <img src="<?= e($event['gallery_image_3']) ?>" alt="Galerie 3" class="rounded-2xl object-cover h-40 w-full hover:scale-105 transition-transform cursor-pointer">
            </div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-1">
            <div class="sticky top-24 bg-slate-900 rounded-3xl border border-white/10 p-6">
                <h3 class="font-display text-lg font-bold text-white mb-1">Billets</h3>
                <p class="text-sm text-slate-400 mb-5"><?= e($ctaHint) ?></p>

                <div class="mb-5">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span class="text-slate-400">Disponibilité</span>
                        <span class="font-semibold text-white"><?= number_format($stats['available'], 0, ',', ' ') ?> / <?= number_format($stats['total'], 0, ',', ' ') ?></span>
                    </div>
                    <div class="h-2.5 rounded-full bg-slate-800 overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-orange-500 to-pink-500 transition-all duration-500" style="width: <?= $stats['percent'] ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2"><?= $stats['percent'] ?>% vendus</p>
                </div>

                <div class="space-y-3 mb-6">
                    <?php foreach ($categories as $cat):
                        $remaining = $cat['quota_total'] !== null
                            ? ((int) $cat['quota_total'] - (int) $cat['quota_sold'] - (int) $cat['quota_locked'])
                            : null;
                    ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-800/50 border border-white/5">
                        <div class="flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full" style="background: <?= e($cat['color']) ?>"></span>
                            <div>
                                <span class="text-sm font-medium text-white block"><?= e($cat['name']) ?></span>
                                <?php if ($remaining !== null): ?>
                                    <span class="text-xs text-slate-500"><?= number_format($remaining, 0, ',', ' ') ?> dispo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-orange-400"><?= format_price((float) $cat['price']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$isQuota): ?>
                <div class="space-y-2 mb-6 text-xs text-slate-400">
                    <p class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-slate-700"></span> Disponible</p>
                    <p class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-orange-500"></span> Sélectionné (verrouillé)</p>
                    <p class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-slate-600"></span> Vendu</p>
                </div>
                <?php else: ?>
                <div class="mb-6 px-3 py-2.5 rounded-xl bg-red-500/10 border border-red-500/20">
                    <p class="text-xs text-red-300 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                        Rush sale — verrouillage 10 min par sélection
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($saleOpen): ?>
                <a href="<?= $ctaUrl ?>" class="block w-full text-center px-6 py-4 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-2xl hover:shadow-xl hover:shadow-orange-500/30 transition-all hover:scale-[1.02]">
                    <?= e($ctaLabel) ?>
                </a>
                <?php else: ?>
                <?php
                    $opensAt = new DateTime($event['sale_open_at']);
                    $opensLabel = $opensAt->format('j') . ' ' . ['jan','fév','mar','avr','mai','juin','juil','août','sep','oct','nov','déc'][(int)$opensAt->format('n') - 1] . ' à ' . $opensAt->format('H\hi');
                ?>
                <div class="mb-4 p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-center">
                    <p class="text-xs font-semibold text-red-300 uppercase tracking-wider mb-2">Vente ouvre bientôt</p>
                    <p class="text-sm text-white font-medium mb-3">Ouverture le <?= e($opensLabel) ?></p>
                    <div class="flex items-center justify-center gap-2" data-countdown="<?= e($event['sale_open_at']) ?>">
                        <div class="flex flex-col items-center px-2 py-1.5 rounded-lg bg-slate-950/60 min-w-[44px]">
                            <span class="font-display text-xl font-bold text-white" data-cd-days>--</span>
                            <span class="text-[10px] text-slate-400 uppercase">j</span>
                        </div>
                        <div class="flex flex-col items-center px-2 py-1.5 rounded-lg bg-slate-950/60 min-w-[44px]">
                            <span class="font-display text-xl font-bold text-white" data-cd-hours>--</span>
                            <span class="text-[10px] text-slate-400 uppercase">h</span>
                        </div>
                        <div class="flex flex-col items-center px-2 py-1.5 rounded-lg bg-slate-950/60 min-w-[44px]">
                            <span class="font-display text-xl font-bold text-white" data-cd-mins>--</span>
                            <span class="text-[10px] text-slate-400 uppercase">m</span>
                        </div>
                        <div class="flex flex-col items-center px-2 py-1.5 rounded-lg bg-slate-950/60 min-w-[44px]">
                            <span class="font-display text-xl font-bold text-white" data-cd-secs>--</span>
                            <span class="text-[10px] text-slate-400 uppercase">s</span>
                        </div>
                    </div>
                </div>
                <button type="button" disabled class="block w-full text-center px-6 py-4 bg-slate-700 text-slate-400 font-bold rounded-2xl cursor-not-allowed">
                    Vente non ouverte
                </button>
                <?php endif; ?>
                <p class="text-center text-xs text-slate-500 mt-3">Max <?= (int) $event['max_tickets_per_user'] ?> billets par personne</p>
            </div>
        </div>
    </div>
</section>
