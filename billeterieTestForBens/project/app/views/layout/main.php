<?php
$currentUser = Auth::user();
$flashError = flash('error');
$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Billetterie') ?> — PulseTicket</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎵</text></svg>">
    <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                        display: ['Sora', 'Inter', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out both',
                        'slide-down': 'slideDown 0.35s ease-out both',
                        'success-pop': 'successPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both',
                    },
                }
            }
        };
    </script>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <nav class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                <a href="<?= url('') ?>" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-500 to-pink-500 flex items-center justify-center shadow-lg shadow-orange-500/30 group-hover:scale-110 transition-transform">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                    </div>
                    <span class="font-display font-bold text-xl tracking-tight text-white">Pulse<span class="text-orange-500">Ticket</span></span>
                </a>

                <div class="hidden md:flex items-center gap-1">
                    <a href="<?= url('') ?>" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-lg hover:bg-white/5">Accueil</a>
                    <a href="<?= url('events') ?>" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-lg hover:bg-white/5">Événements</a>
                    <?php if (Auth::check()): ?>
                        <a href="<?= url('account') ?>" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-lg hover:bg-white/5">Mes billets</a>
                        <a href="<?= url('logout') ?>" class="px-4 py-2 text-sm font-medium text-slate-400 hover:text-white transition-colors rounded-lg hover:bg-white/5">Déconnexion</a>
                        <div class="ml-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/10 text-xs font-medium text-slate-200">
                            <?= e($currentUser['name']) ?>
                        </div>
                    <?php else: ?>
                        <a href="<?= url('login') ?>" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-lg hover:bg-white/5">Connexion</a>
                        <a href="<?= url('register') ?>" class="ml-2 px-5 py-2 text-sm font-semibold bg-gradient-to-r from-orange-500 to-pink-500 text-white rounded-xl hover:shadow-lg hover:shadow-orange-500/30 transition-all hover:scale-[1.03]">S'inscrire</a>
                    <?php endif; ?>
                </div>

                <button class="md:hidden text-white p-2" id="mobileToggle">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
        <div class="md:hidden hidden bg-slate-900/95 backdrop-blur-lg border-t border-white/5" id="mobileMenu">
            <div class="px-4 py-4 space-y-1">
                <a href="<?= url('') ?>" class="block px-4 py-2.5 text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-lg">Accueil</a>
                <a href="<?= url('events') ?>" class="block px-4 py-2.5 text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-lg">Événements</a>
                <?php if (Auth::check()): ?>
                    <a href="<?= url('account') ?>" class="block px-4 py-2.5 text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-lg">Mes billets</a>
                    <a href="<?= url('logout') ?>" class="block px-4 py-2.5 text-sm font-medium text-slate-400 hover:text-white hover:bg-white/5 rounded-lg">Déconnexion</a>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="block px-4 py-2.5 text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 rounded-lg">Connexion</a>
                    <a href="<?= url('register') ?>" class="block px-4 py-2.5 text-sm font-semibold text-orange-500">S'inscrire</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if ($flashError): ?>
        <div class="fixed top-20 left-1/2 -translate-x-1/2 z-[60] px-6 py-3 rounded-xl bg-red-500/90 backdrop-blur-md text-white text-sm font-medium shadow-lg shadow-red-500/20 animate-slide-down">
            <?= e($flashError) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
        <div class="fixed top-20 left-1/2 -translate-x-1/2 z-[60] px-6 py-3 rounded-xl bg-emerald-500/90 backdrop-blur-md text-white text-sm font-medium shadow-lg shadow-emerald-500/20 animate-slide-down">
            <?= e($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <main><?= $content ?></main>

    <footer class="border-t border-white/5 bg-slate-950 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2.5 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-500 to-pink-500 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        </div>
                        <span class="font-display font-bold text-xl text-white">Pulse<span class="text-orange-500">Ticket</span></span>
                    </div>
                    <p class="text-slate-400 text-sm max-w-md leading-relaxed">La billetterie en ligne pour vos festivals, concerts et événements sportifs. Réservation rapide, paiement sécurisé et QR code de contrôle à l'entrée.</p>
                </div>
                <div>
                    <h4 class="font-semibold text-white text-sm mb-3">Navigation</h4>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li><a href="<?= url('') ?>" class="hover:text-orange-500 transition-colors">Accueil</a></li>
                        <li><a href="<?= url('events') ?>" class="hover:text-orange-500 transition-colors">Tous les événements</a></li>
                        <li><a href="<?= url('account') ?>" class="hover:text-orange-500 transition-colors">Mon compte</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-white text-sm mb-3">Informations</h4>
                    <ul class="space-y-2 text-sm text-slate-400">
                        <li>CGV</li>
<li>Politique de confidentialité</li>
<li>Mentions légales</li>
<li>FAQ</li>
                    </ul>
                </div>
            </div>
            <div class="mt-10 pt-6 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-500">© 2026 PulseTicket — Billetterie en ligne.</p>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 border border-white/10">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Système opérationnel
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
