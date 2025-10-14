<?php
// templates/header.php (Universal Header Structure)
// NOTE: ဤ file ကို Calling Page ကနေ include မလုပ်ခင် core/functions.php ကို ခေါ်ထားရပါမည်။

global $lang, $current_lang, $page_title_key;


$user_display_name = $_SESSION['user_name'] ?? 'Guest';

?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>"
      data-theme="<?php echo htmlspecialchars($_SESSION['theme'] ?? 'light'); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo page_title($page_title_key, $lang); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css"> 
    
    <script>
        // Theme Setup & Toggle Logic (functions.php မှ ခေါ်ရန်)
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
        }

        function toggleTheme() {
            const current = document.documentElement.getAttribute('data-theme');
            const newTheme = (current === 'dark') ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeButton();
        }

        function updateThemeButton() {
            const icon = document.getElementById('themeIcon');
            const label = document.getElementById('themeLabel');
            const current = document.documentElement.getAttribute('data-theme');
            if (icon && label) {
                if (current === 'dark') {
                    icon.textContent = '🌙';
                    label.textContent = 'Dark';
                } else {
                    icon.textContent = '☀️';
                    label.textContent = 'Light';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', updateThemeButton);
        
        // Language Toggle Logic
       function toggleLanguage(currentLang) {
            const newLang = currentLang === 'en' ? 'my' : 'en';
            // 💡 Link Fix: BASE_URL ကို သုံးပြီး switch_lang.php ကို တိုက်ရိုက်ခေါ်ခြင်း
            const redirectUrl = "<?php echo BASE_URL; ?>/switch_lang.php?lang=" + newLang + "&redirect=" + encodeURIComponent(window.location.href); 
            window.location.href = redirectUrl;
        }

        // Date & Time Logic
        function updateDateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour12: true }); 
            const dateString = now.toLocaleDateString('en-GB'); 
            const dtElement = document.getElementById('datetime');
            if(dtElement) dtElement.textContent = `${dateString} ${timeString}`;
        }
        setInterval(updateDateTime, 1000);
        document.addEventListener('DOMContentLoaded', updateDateTime);
    </script>
</head>

<body class="min-h-screen flex flex-col font-sans" style="background-color: var(--color-bg-main); padding-top: 60px;">
    
    <header class="top-bar fixed w-full z-50 shadow-md" style="background-color: var(--color-bg-card); height: 60px;">
        <div class="flex items-center justify-between w-full p-2">
            <div class="flex items-center gap-2">
                <a href="<?php echo BASE_URL; ?>/index.php">
                     <img src="<?php echo BASE_URL; ?>/assets/images/UNIFLOW.png" alt="UNIFLOW Logo" style="height: 120px; width: auto;">
                </a>
            </div>

            <div class="flex items-center gap-4">

                <div class="flex items-center gap-2" style="color: var(--color-text);">
                    <i class="fa fa-user-circle text-2xl"></i>
                    <span class="font-medium"><?php echo $user_display_name; ?></span>
                    <span id="datetime" class="text-sm"></span>
                </div>

                <button class="lang-toggle" onclick="toggleLanguage('<?php echo $current_lang; ?>')">
                    <?php if ($current_lang === 'en'): ?>
                        <span class="fi fi-gb"></span> English
                    <?php else: ?>
                        <span class="fi fi-mm"></span> မြန်မာ
                    <?php endif; ?>
                </button>

                <button class="theme-btn" onclick="toggleTheme()">
                    <span id="themeIcon"></span>
                    <span id="themeLabel"></span>
                </button>
            </div>
        </div>
    </header>

    <div class="flex flex-1">
        <main class="flex-1 p-8">
            <?php display_session_messages($lang); ?>