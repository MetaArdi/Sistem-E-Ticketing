<!-- Premium Sidebar -->
<aside id="sidebar" class="w-72 bg-slate-50 border-r border-slate-200 flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-all duration-300 ease-in-out z-50 shadow-lg md:shadow-none">
    
    <div class="h-20 flex items-center justify-between px-6 border-b border-slate-200 shrink-0">
        <a href="../index.php" class="flex items-center gap-3 group">
            <?php if (isset($global_site_logo) && $global_site_logo): ?>
            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
            <?php else: ?>
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
            <span class="text-xl font-extrabold text-white tracking-tight">HaloTiket</span>
            <?php endif; ?>
        </a>
        <!-- Close Button (Mobile Only) -->
        <button id="closeSidebar" class="md:hidden text-slate-400 hover:text-white p-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>
    
    <nav class="flex-1 overflow-y-auto py-6 px-4 scrollbar-hide">
        <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Menu Utama</p>
        <ul class="space-y-2">
            <?php 
                $active_class = "text-primary bg-primary/10 font-bold";
                $inactive_class = "text-gray-600 font-medium hover:text-primary hover:bg-primary/5";
                $am = $active_menu ?? 'overview';
            ?>
            <li>
                <a href="index.php" class="<?= $am == 'overview' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                    Overview
                </a>
            </li>
            <li>
                <a href="manage_users.php" class="<?= $am == 'users' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    Kelola User
                </a>
            </li>
            <li>
                <a href="manage_events.php" class="<?= $am == 'events' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Kelola Event
                </a>
            </li>
            <li>
                <a href="manage_transactions.php" class="<?= $am == 'transactions' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    Transaksi
                </a>
            </li>
            <li>
                <a href="manage_categories.php" class="<?= $am == 'categories' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                    Kategori Event
                </a>
            </li>
        </ul>
        <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 mt-8">Pengaturan</p>
        <ul class="space-y-2">
            <li>
                <a href="settings.php" class="<?= $am == 'settings' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    Pengaturan Sistem
                </a>
            </li>
        </ul>
        <p class="px-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3 mt-8">Sistem</p>
        <ul class="space-y-2">
            <li>
                <a href="api_settings.php" class="<?= $am == 'api' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    API Config
                </a>
            </li>
            <li>
                <a href="system_logs.php" class="<?= $am == 'logs' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Logs Sistem
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="p-4 border-t border-slate-200 shrink-0">
        <a href="../auth/logout.php" class="flex items-center justify-center w-full px-4 py-3 text-sm font-bold text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition-colors border border-red-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Logout
        </a>
    </div>
</aside>
