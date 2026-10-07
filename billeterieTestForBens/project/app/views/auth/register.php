<?php /** @var string $pageTitle */ ?>
<div class="pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto">
    <div class="bg-slate-900 rounded-3xl border border-white/10 p-8 md:p-12">
        <h1 class="font-display text-4xl font-bold text-white mb-2">Créer un compte</h1>
        <p class="text-slate-400 mb-8">Rejoignez PulseTicket pour acheter et gérer vos billets.</p>

        <form method="POST" action="<?= url('register') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Prénom</label>
                    <input type="text" name="first_name" required value="<?= e(old('first_name')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="Jean">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Nom</label>
                    <input type="text" name="last_name" required value="<?= e(old('last_name')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="Dupont">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input type="email" name="email" required value="<?= e(old('email')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="vous@email.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Téléphone <span class="text-slate-500">(optionnel)</span></label>
                <input type="tel" name="phone" value="<?= e(old('phone')) ?>" class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="06 12 34 56 78">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Mot de passe</label>
                <input type="password" name="password" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="6 caractères minimum">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirmer le mot de passe</label>
                <input type="password" name="password_confirm" required class="w-full px-4 py-3 bg-slate-800 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-orange-500 transition-colors" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full px-6 py-3.5 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all">
                Créer mon compte
            </button>
        </form>

        <p class="text-center text-sm text-slate-400 mt-6">
            Déjà inscrit ? <a href="<?= url('login') ?>" class="text-orange-500 font-medium hover:text-orange-400 transition-colors">Se connecter</a>
        </p>
    </div>
</div>
