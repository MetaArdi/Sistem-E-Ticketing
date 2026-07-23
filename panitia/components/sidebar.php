<!-- Premium Sidebar (Panitia) -->
<aside id="sidebar" class="w-72 bg-slate-50 border-r border-slate-200 flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-all duration-300 ease-in-out z-50 shadow-lg md:shadow-none">
    
    <div class="h-20 flex items-center justify-between px-6 border-b border-slate-200 shrink-0">
        <a href="../index.php" class="flex items-center gap-3 group">
            <?php if (isset($global_site_logo) && $global_site_logo): ?>
            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
            <?php else: ?>
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
            <span class="text-xl font-extrabold text-white tracking-tight">Panitia Panel</span>
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
                    Dashboard
                </a>
            </li>
            <li>
                <a href="manage_events.php" class="<?= $am == 'events' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Event Saya
                </a>
            </li>
            <li>
                <a href="manage_validators.php" class="<?= $am == 'validators' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    Kelola Validator
                </a>
            </li>
            <li>
                <a href="laporan_sales.php" class="<?= $am == 'sales' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    Laporan Penjualan
                </a>
            </li>
            <li>
                <a href="profile.php#withdrawal" class="<?= $am == 'withdrawal' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    Tarik Tunai & Bank
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
