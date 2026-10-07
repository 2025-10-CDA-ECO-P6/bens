<?php
/** @var array $events */
$catLabels = ['festival' => 'Festival', 'concert' => 'Concert', 'sport' => 'Sport', 'rush' => 'Rush Sale'];
$catColors = [
    'festival' => 'from-orange-500 to-pink-500',
    'concert' => 'from-cyan-500 to-blue-500',
    'sport' => 'from-emerald-500 to-teal-500',
    'rush' => 'from-red-500 to-orange-500',
];
?>
<section class="pt-28 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <div class="mb-12">
        <h1 class="font-display text-4xl sm:text-5xl font-bold text-white mb-3">Tous les événements</h1>
        <p class="text-slate-400 text-lg">Festivals, concerts et matchs — trouvez votre prochaine expérience.</p>
    </div>

    <?php if (!empty($events)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($events as $event):
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
            </div>
            <div class="p-6">
                <h3 class="font-display text-xl font-bold text-white mb-1 group-hover:text-orange-400 transition-colors"><?= e($event['title']) ?></h3>
                <p class="text-sm text-slate-400 mb-4"><?= e($event['subtitle']) ?></p>
                <div class="space-y-1.5 text-sm text-slate-400 mb-4">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?= format_date($event['event_date']) ?> · <?= format_time($event['show_time']) ?>
                    </span>
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                        <?= e($event['venue']) ?>, <?= e($event['city']) ?>
                    </span>
                </div>
                <div class="pt-4 border-t border-white/5 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-500 block"><?= $stats['available'] ?> places</span>
                        <div class="mt-1 w-24 h-1.5 rounded-full bg-slate-800 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-orange-500 to-pink-500" style="width: <?= $stats['percent'] ?>%"></div>
                        </div>
                    </div>
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
    <div class="text-center py-20">
        <p class="text-slate-400 text-lg">Aucun événement disponible pour le moment.</p>
    </div>
    <?php endif; ?>
</section>
