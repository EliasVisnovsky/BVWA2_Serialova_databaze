<?php
declare(strict_types=1);

// Include database connection, authentication, and layout templates
require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

// If the user is already authenticated, redirect them directly to the home page
if (prihlaseny_uzivatel($pdo)) { header('Location: index.php'); exit; }

// Render page header with the login title and active state
hlavicka('Přihlášení', null, 'login');
?>

<!-- Alpine.js application container: The 'mode' variable switches between login and registration views -->
<div x-data="{ mode: 'login' }" class="flex w-full h-screen overflow-hidden">
    
    <!-- LEFT PANEL: Forms container (Takes up half the width on desktop, scrollable if form content is too long) -->
    <div class="w-full md:w-1/2 flex items-center justify-center p-10 pt-32 md:pt-20 overflow-y-auto relative z-10" style="scrollbar-width: thin;">
        <div class="w-full max-w-[420px]">
            
            <!-- ---------------- LOGIN FORM ---------------- -->
            <div x-show="mode === 'login'" x-transition.opacity.duration.400ms>
                <h2 class="text-4xl md:text-[40px] font-black uppercase mb-10 tracking-tight">Login</h2>
                
                <div id="loginInfo" class="hidden p-4 mb-6 font-black uppercase text-sm text-center border-2 rounded"></div>
                
                <form action="login.php" method="POST">
                    <div class="mb-5">
                        <label for="login_user" class="form-label">Username</label>
                        <input type="text" name="login" id="login_user" class="brutal-input" required placeholder="Enter your username">
                    </div>
                    <div class="mb-5">
                        <label for="login_pass" class="form-label">Password</label>
                        <input type="password" id="login_pass" name="password" class="brutal-input" required placeholder="Enter your password">
                    </div>
                    <button type="submit" class="brutal-btn mt-4">Log In</button>
                </form>
                
                <div class="mt-8 text-sm text-white/50 text-center">
                    Don't have an account? <a @click="mode = 'register'" class="text-white font-bold cursor-pointer uppercase tracking-wider border-b border-transparent hover:border-white transition-colors">Sign up</a>
                </div>
            </div>

            <!-- ---------------- REGISTRATION FORM ---------------- -->
            <div x-show="mode === 'register'" x-transition.opacity.duration.400ms x-cloak>
                <h2 class="text-4xl md:text-[40px] font-black uppercase mb-10 tracking-tight">Registration</h2>
                
                <form id="registerFormEl" action="register.php" method="POST" enctype="multipart/form-data" novalidate>
                    <div id="registerAlert" class="hidden p-4 mb-6 font-black uppercase text-sm text-center border-2 border-red-500 bg-red-500/10 text-red-500 rounded"></div>
                    
                    <div class="flex gap-4 mb-5">
                        <div class="flex-1"><label class="form-label">First Name</label><input type="text" name="name" id="name" class="brutal-input" required placeholder="John"></div>
                        <div class="flex-1"><label class="form-label">Last Name</label><input type="text" name="surname" id="surname" class="brutal-input" required placeholder="Doe"></div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" id="email" class="brutal-input" required placeholder="name@example.com">
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" id="phone_number" class="brutal-input" required placeholder="+420 123 456 789">
                    </div>
                    
                    <div class="flex gap-4 mb-5">
                        <div class="flex-1">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="gender" class="brutal-input" required>
                                <option value="male" class="bg-epilogCard text-white">Male</option>
                                <option value="female" class="bg-epilogCard text-white">Female</option>
                                <option value="other" class="bg-epilogCard text-white">Other</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="pfp" id="pfp" accept="image/jpeg,image/png,image/webp" class="brutal-input py-2.5 cursor-pointer text-white/50" required>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Username</label>
                        <input type="text" name="reg_login" id="reg_login" class="brutal-input" required placeholder="username123">
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="reg_pass" class="brutal-input" required placeholder="Min. 8 characters">
                        <span class="text-xs text-white/30 font-medium block mt-2">8-64 characters, at least 1 letter and 1 digit.</span>
                    </div>
                    
                    <button type="submit" class="brutal-btn submit-btn mt-4">Create Account</button>
                </form>
                
                <div class="mt-8 text-sm text-white/50 text-center">
                    Already have an account? <a @click="mode = 'login'" class="text-white font-bold cursor-pointer uppercase tracking-wider border-b border-transparent hover:border-white transition-colors">Log in</a>
                </div>
            </div>

        </div>
    </div>

    <!-- RIGHT PANEL: Animated GIF background stretching across the entire right half -->
    <div class="hidden md:block w-1/2 h-full bg-[url('images/login.gif')] bg-cover bg-center bg-no-repeat border-l border-white/5 relative">
        <div class="absolute inset-0 bg-gradient-to-r from-epilogBg to-epilogBg/30"></div>
    </div>

</div>

<!-- JavaScript handler for catching backend authentication error codes from URL parameters -->
<script>
    (function () {
        const kod = new URLSearchParams(location.search).get('login');
        const zpravy = { chyba: ['Wrong username or password.', 'error'], nutne: ['Please log in.', 'error'], odhlaseno: ['Logged out.', 'success'] };
        if (!kod || !zpravy[kod]) return;
        const box = document.getElementById('loginInfo');
        box.textContent = zpravy[kod][0];
        box.className = `p-4 mb-6 font-black uppercase text-sm text-center border-2 rounded ${zpravy[kod][1] === 'error' ? 'border-red-500 bg-red-500/10 text-red-500' : 'border-white/30 bg-white/10 text-white'}`;
        box.classList.remove('hidden');
        history.replaceState(null, '', location.pathname);
    })();
</script>
<!-- Client-side form validation scripts -->
<script src="js/validace.js"></script>
<script src="js/registrace.js"></script>