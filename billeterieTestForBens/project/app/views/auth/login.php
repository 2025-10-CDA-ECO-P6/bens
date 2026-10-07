<?php /** @var string $pageTitle */ ?>
<div class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto">
    <div class="bg-slate-900 rounded-3xl border border-white/10 p-8 md:p-12">
        <h1 class="font-display text-4xl font-bold text-white mb-2">Connexion</h1>
        <p class="text-slate-400 mb-8">Connectez-vous pour accéder à vos billets.</p>

        <form method="POST" action="<?= url('login') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="vous@email.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Mot de passe</label>
                <input type="password" name="password" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full px-6 py-3.5 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all">
                Se connecter
            </button>
        </form>

        <p class="text-center text-sm text-slate-400 mt-6">
            Pas encore de compte ? <a href="<?= url('register') ?>" class="text-orange-500 font-medium hover:text-orange-400 transition-colors">Créer un compte</a>
        </p>
    </div>
</div>
