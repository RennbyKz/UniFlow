<?php
// templates/footer.php (Universal Footer - Overlap Fix)

global $lang;
?>
        </main>
    </div> <footer class="p-4 text-sm w-full" 
            style="color: var(--color-text-card); border-top: 1px solid var(--color-border); background-color: var(--color-bg-footer);">
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 max-w-full mx-auto">
            
            <div class="flex items-center justify-center gap-3 text-center">
                <img src="<?php echo BASE_URL; ?>/assets/images/UNIFLOW.png" alt="UniMatch Logo" 
                     class="h-6 w-auto">
                
                <span>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?></span>
            </div>
            
            <span class="hidden sm:inline-block">|</span>
            <span class="text-center sm:text-left">All rights reserved.</span>
            
        </div>
    </footer>
</body>
</html>