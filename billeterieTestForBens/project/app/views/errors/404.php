<?php /** @var string $pageTitle */ ?>
<div class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto">
    <div class="text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-500/20 border-2 border-red-500/30 mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <h1 class="font-display text-4xl font-bold text-white mb-3">404</h1>
        <p class="text-slate-400 mb-8">La page que vous cherchez n'existe pas ou a été déplacée.</p>
        <a href="<?= url('') ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all">
            Retour à l'accueil
        </a>
    </div>
</div>
