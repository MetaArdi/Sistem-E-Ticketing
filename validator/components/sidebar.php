<?php
if(!isset($active_menu)) $active_menu = '';
?>
<!-- Premium Sidebar (Light Theme) -->
<aside id="sidebar" class="w-72 bg-slate-50 border-r border-slate-200 flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition-all duration-300 ease-in-out z-50 shadow-lg md:shadow-none">
    
    <div class="h-20 flex items-center justify-between px-6 border-b border-slate-200 shrink-0">
        <a href="../index.php" class="flex items-center gap-3 group">
            <?php if (isset($global_site_logo) && $global_site_logo): ?>
            <img src="<?= $global_site_logo ?>" alt="Logo" class="w-40 md:w-48 h-auto object-contain">
            <?php else: ?>
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white font-bold text-sm shadow-md">H</div>
            <span class="text-xl font-extrabold text-slate-800 tracking-tight">HaloTiket</span>
            <?php endif; ?>
        </a>
        <!-- Close Button (Mobile Only) -->
        <button id="closeSidebar" class="md:hidden text-slate-400 hover:text-slate-600 p-2">
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="scan.php" class="<?= $am == 'scan' ? $active_class : $inactive_class ?> flex items-center px-4 py-3 rounded-xl transition-all duration-300 text-sm gap-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                    Scan QR Tiket
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="p-4 border-t border-slate-200 shrink-0">
        <a href="../auth/logout.php" onclick="return confirm('Yakin ingin keluar?')" class="flex items-center justify-center w-full px-4 py-3 text-sm font-bold text-red-500 bg-red-50 hover:bg-red-100 rounded-xl transition-colors border border-red-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Keluar Sistem
        </a>
    </div>
</aside>
