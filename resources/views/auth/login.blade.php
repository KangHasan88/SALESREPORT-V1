<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sales Report Apps</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              primary: {
                DEFAULT: '#0A66C2',
                50: '#EFF6FF',
                100: '#DBEAFE',
                200: '#BFDBFE',
                300: '#93C5FD',
                400: '#60A5FA',
                500: '#3B82F6',
                600: '#2563EB',
                700: '#1D4ED8',
                800: '#1E40AF',
                900: '#1E3A8A',
              },
              accent: {
                DEFAULT: '#10B981',
                50: '#ECFDF5',
                100: '#D1FAE5',
                200: '#A7F3D0',
                300: '#6EE7B7',
                400: '#34D399',
                500: '#10B981',
                600: '#059669',
                700: '#047857',
                800: '#065F46',
                900: '#064E3B',
              }
            },
            fontFamily: {
              'sans': ['Inter', 'system-ui', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        
        .left-panel {
            background: linear-gradient(135deg, #0A66C2 0%, #10B981 100%);
            position: relative;
            overflow: hidden;
        }
        
        .dark .left-panel {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        }
        
        .left-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.1) 0%, transparent 50%);
        }
        
        .login-card {
            background: white;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.1),
                0 2px 4px -1px rgba(0, 0, 0, 0.06),
                0 20px 40px rgba(10, 102, 194, 0.08);
            border-radius: 16px;
        }
        
        .dark .login-card {
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.3),
                0 2px 4px -1px rgba(0, 0, 0, 0.2),
                0 20px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #0A66C2 0%, #10B981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .input-field {
            transition: all 0.3s ease;
            border: 2px solid #E5E7EB;
            background: white;
        }
        
        .dark .input-field {
            border: 2px solid #374151;
            background: rgba(31, 41, 55, 0.5);
        }
        
        .input-field:focus {
            border-color: #0A66C2;
            box-shadow: 0 0 0 3px rgba(10, 102, 194, 0.1);
        }
        
        .dark .input-field:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #0A66C2 0%, #10B981 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(10, 102, 194, 0.3);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .password-hint {
            display: none;
            position: absolute;
            background: white;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 280px;
            z-index: 10;
            top: 100%;
            right: 0;
            margin-top: 8px;
        }
        
        .dark .password-hint {
            background: #1e293b;
            border: 1px solid #475569;
        }
        
        .password-hint::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 20px;
            width: 16px;
            height: 16px;
            background: inherit;
            border-left: 1px solid inherit;
            border-top: 1px solid inherit;
            transform: rotate(45deg);
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.7);
            transform: scale(0);
            animation: ripple 0.6s linear;
        }
        
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        .logo-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .dark .logo-container {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .dark .feature-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .dark .feature-card:hover {
            background: rgba(15, 23, 42, 0.8);
        }
        
        @media (max-width: 1024px) {
            .login-container {
                flex-direction: column;
            }
            
            .left-panel, .right-panel {
                width: 100% !important;
            }
            
            .left-panel {
                min-height: 300px;
                padding: 2rem !important;
            }
            
            .logo-container {
                width: 120px !important;
                height: 120px !important;
            }
        }
        
        @media (max-width: 640px) {
            .login-card {
                padding: 1.5rem !important;
            }
            
            .left-panel {
                padding: 1.5rem !important;
            }
        }
    </style>
</head>
<body class="transition-colors duration-300">
    <!-- Main Container -->
    <div class="login-container flex min-h-screen">
        <!-- Left Panel with Logo and App Info -->
        <div class="left-panel hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-12 text-white relative">
            <!-- App Logo and Title -->
            <div class="relative z-10 text-center mb-12">
                <h1 class="text-4xl font-bold mb-2">Sales Report Apps</h1>
                <p class="text-xl opacity-90">SKBD & SKB3</p>
            </div>
            
            <!-- App Features -->
            <div class="relative z-10 w-full max-w-md">
                <div class="grid grid-cols-1 gap-4 mb-8">
                    <div class="feature-card p-4 rounded-xl">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-cogs text-accent-300 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="font-semibold text-lg mb-1">Sales Order Report</h3>
                                <p class="text-sm opacity-80">Monitoring transaksi Sales Order SKBD dan SKB3 dari ERP secara read-only</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="feature-card p-4 rounded-xl">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-chart-line text-accent-300 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="font-semibold text-lg mb-1">Daily Achievement</h3>
                                <p class="text-sm opacity-80">Pantau pencapaian harian, channel, salesman, nilai SO, dan total KG</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="feature-card p-4 rounded-xl">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="fas fa-shield-alt text-accent-300 text-lg"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="font-semibold text-lg mb-1">Export Professional</h3>
                                <p class="text-sm opacity-80">Download XLSX sesuai filter dengan format data yang rapi untuk review</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel for Login Form -->
        <div class="right-panel w-full lg:w-1/2 flex items-center justify-center p-4 lg:p-8">
            <!-- Dark mode toggle -->
            <button id="darkModeToggle" class="absolute top-6 right-6 p-3 rounded-full bg-white dark:bg-slate-800 shadow-lg hover:shadow-xl transition-all duration-300 z-10">
                <i id="darkIcon" class="fas fa-moon text-gray-700 dark:text-yellow-300"></i>
            </button>
            
            <div class="w-full max-w-md">
                <!-- Status Message -->
                <div id="sessionStatus" class="mb-6 p-4 rounded-lg bg-gradient-to-r from-primary-50 to-accent-50 dark:from-primary-900/20 dark:to-accent-900/20 border border-primary-100 dark:border-primary-800 {{ session('status') ? '' : 'hidden' }}">
                    <div class="flex items-center">
                        <i class="fas fa-circle-check text-accent-600 dark:text-accent-300 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-primary-800 dark:text-primary-200">Status Sesi</h4>
                            <p id="statusMessage" class="text-sm text-primary-700 dark:text-primary-300">{{ session('status') }}</p>
                        </div>
                        <button id="closeStatus" class="ml-auto text-primary-600 hover:text-primary-800 dark:text-primary-300">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Login Card -->
                <div class="login-card p-8">
                    <!-- Header -->
                    <div class="text-center mb-10">
                        <div class="lg:hidden mb-6 flex flex-col items-center">
                            <div class="text-center">
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Sales Report Apps</h1>
                                <p class="text-gray-600 dark:text-gray-300 text-sm">SKBD & SKB3</p>
                            </div>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-2">Selamat Datang</h1>
                        <p class="text-gray-600 dark:text-gray-300">Masuk ke Sales Report Apps</p>
                    </div>
                    
                    <form method="POST" action="{{ route('login') }}" id="loginForm">
                        @csrf

                        <!-- Username -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <label for="username" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <i class="fas fa-user mr-2"></i> Username
                                </label>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Wajib</span>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input id="username"
                                       class="input-field pl-10 pr-4 py-3 w-full rounded-xl focus:outline-none focus:ring-0 transition-all duration-300 text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500"
                                       type="text"
                                       name="username"
                                       value="{{ old('username') }}"
                                       required
                                       autofocus
                                       autocomplete="username"
                                       placeholder="username">
                            </div>
                            <div id="usernameError" class="mt-2 text-sm text-red-600 dark:text-red-400 {{ $errors->has('username') ? '' : 'hidden' }}">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <span id="usernameErrorText">@error('username'){{ $message }}@enderror</span>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    <i class="fas fa-key mr-2"></i> Kata Sandi
                                </label>
                                <div class="flex items-center">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mr-2">Wajib</span>
                                    <!-- Password Hint Button -->
                                    <button type="button" id="passwordHintBtn" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors" aria-label="Tampilkan petunjuk kata sandi">
                                        <i class="fas fa-question-circle"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input id="password" 
                                       class="input-field pl-10 pr-12 py-3 w-full rounded-xl focus:outline-none focus:ring-0 transition-all duration-300 text-gray-800 dark:text-white placeholder-gray-400 dark:placeholder-gray-500" 
                                       type="password" 
                                       name="password" 
                                       required 
                                       autocomplete="current-password"
                                       placeholder="*********">
                                <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-3 flex items-center" aria-label="Tampilkan kata sandi">
                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"></i>
                                </button>
                            </div>
                            
                            <!-- Password Hint Popup -->
                            <div id="passwordHint" class="password-hint">
                                <h4 class="font-semibold text-gray-800 dark:text-white mb-2 flex items-center">
                                    <i class="fas fa-info-circle text-primary-600 mr-2"></i>
                                    Persyaratan Kata Sandi
                                </h4>
                                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-accent-500 mr-2 mt-0.5 text-xs"></i>
                                        <span>Minimal 8 karakter</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-accent-500 mr-2 mt-0.5 text-xs"></i>
                                        <span>Setidaknya satu huruf besar</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-accent-500 mr-2 mt-0.5 text-xs"></i>
                                        <span>Setidaknya satu angka</span>
                                    </li>
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-accent-500 mr-2 mt-0.5 text-xs"></i>
                                        <span>Setidaknya satu karakter khusus</span>
                                    </li>
                                </ul>
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-shield-alt mr-1"></i>
                                        Untuk keamanan, jangan pernah berbagi kata sandi Anda
                                    </p>
                                </div>
                            </div>
                            
                            <div id="passwordError" class="mt-2 text-sm text-red-600 dark:text-red-400 hidden">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <span id="passwordErrorText"></span>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center mb-8">
                            <label for="remember_me" class="flex items-center cursor-pointer">
                                <div class="relative">
                                    <input id="remember_me"
                                           type="checkbox"
                                           class="sr-only" 
                                           name="remember">
                                    <div class="toggle-dot block bg-gray-200 dark:bg-gray-700 w-10 h-6 rounded-full transition-colors duration-300"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform duration-300"></div>
                                </div>
                                <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">Ingat Saya</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="mb-6">
                            <button type="submit" id="submitBtn" class="submit-btn w-full py-3.5 px-4 rounded-xl text-white font-semibold shadow-md">
                                <span id="btnText">Masuk</span>
                                <i id="btnIcon" class="fas fa-sign-in-alt ml-2"></i>
                                <div class="loading hidden absolute inset-0 flex items-center justify-center bg-gradient-to-r from-primary-700 to-accent-700 rounded-xl">
                                    <i class="fas fa-spinner fa-spin mr-2 text-white"></i>
                                    <span class="text-white">Mengautentikasi...</span>
                                </div>
                            </button>
                        </div>
                        
                    </form>
                </div>
                
                <!-- Footer -->
                <div class="mt-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Dilindungi oleh PT. SEKARBUMI TBK
                        <br>
                        <span class="text-xs mt-1 block">@ 2026 Sales Report Apps</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkIcon = document.getElementById('darkIcon');
        const htmlElement = document.documentElement;
        
        // Check for saved theme or prefer-color-scheme
        const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        if (savedTheme === 'dark') {
            htmlElement.classList.add('dark');
            darkIcon.className = 'fas fa-sun text-yellow-300';
        } else {
            htmlElement.classList.remove('dark');
            darkIcon.className = 'fas fa-moon text-gray-700';
        }
        
        darkModeToggle.addEventListener('click', () => {
            htmlElement.classList.toggle('dark');
            
            if (htmlElement.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                darkIcon.className = 'fas fa-sun text-yellow-300';
            } else {
                localStorage.setItem('theme', 'light');
                darkIcon.className = 'fas fa-moon text-gray-700';
            }
        });
        
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordIcon = togglePassword.querySelector('i');
        
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            passwordIcon.className = type === 'password' ? 'fas fa-eye text-gray-400 hover:text-gray-600 dark:hover:text-gray-300' : 'fas fa-eye-slash text-gray-400 hover:text-gray-600 dark:hover:text-gray-300';
        });
        
        // Password hint toggle
        const passwordHintBtn = document.getElementById('passwordHintBtn');
        const passwordHint = document.getElementById('passwordHint');
        
        passwordHintBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            passwordHint.style.display = passwordHint.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close password hint when clicking outside
        document.addEventListener('click', (e) => {
            if (passwordHint.style.display === 'block' && !passwordHint.contains(e.target) && e.target !== passwordHintBtn) {
                passwordHint.style.display = 'none';
            }
        });
        
        // Remember me checkbox styling
        const rememberCheckbox = document.getElementById('remember_me');
        const rememberToggleDot = document.querySelector('.toggle-dot');
        const rememberDot = document.querySelector('.dot');
        
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', () => {
                if (rememberCheckbox.checked) {
                    rememberDot.style.transform = 'translateX(16px)';
                    rememberToggleDot.style.background = 'linear-gradient(135deg, #0A66C2 0%, #10B981 100%)';
                } else {
                    rememberDot.style.transform = 'translateX(0)';
                    rememberToggleDot.style.background = '';
                }
            });
            
            // Initialize checkbox state
            if (rememberCheckbox.checked) {
                rememberDot.style.transform = 'translateX(16px)';
                rememberToggleDot.style.background = 'linear-gradient(135deg, #0A66C2 0%, #10B981 100%)';
            }
        }
        
        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');
        
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                // Simulate form validation
                const username = document.getElementById('username')?.value;
                const password = document.getElementById('password')?.value;
                let valid = true;

                // Reset errors
                const usernameError = document.getElementById('usernameError');
                const passwordError = document.getElementById('passwordError');

                if (usernameError) usernameError.classList.add('hidden');
                if (passwordError) passwordError.classList.add('hidden');

                // Username validation
                if (!username || username.trim().length < 2) {
                    const usernameErrorText = document.getElementById('usernameErrorText');
                    if (usernameErrorText) {
                        usernameErrorText.textContent = 'Harap masukkan username';
                    }
                    if (usernameError) {
                        usernameError.classList.remove('hidden');
                    }
                    valid = false;
                }
                
                // Password validation
                if (!password || password.length < 6) {
                    const passwordErrorText = document.getElementById('passwordErrorText');
                    if (passwordErrorText) {
                        passwordErrorText.textContent = 'Kata sandi harus minimal 6 karakter';
                    }
                    if (passwordError) {
                        passwordError.classList.remove('hidden');
                    }
                    valid = false;
                }
                
                if (!valid) {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                if (btnText) btnText.classList.add('hidden');
                if (btnIcon) btnIcon.classList.add('hidden');
                
                const loadingDiv = submitBtn.querySelector('.loading');
                if (loadingDiv) loadingDiv.classList.remove('hidden');
                
                if (submitBtn) submitBtn.disabled = true;
                
                // Add ripple effect
                const ripple = document.createElement('div');
                ripple.className = 'ripple';
                const rect = submitBtn.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                submitBtn.appendChild(ripple);
                
                // Remove ripple after animation
                setTimeout(() => {
                    ripple.remove();
                }, 600);
                
                // In a real app, the form would submit here
                // For demo, we'll simulate a delay then reset
                setTimeout(() => {
                    if (btnText) btnText.classList.remove('hidden');
                    if (btnIcon) btnIcon.classList.remove('hidden');
                    if (loadingDiv) loadingDiv.classList.add('hidden');
                    if (submitBtn) submitBtn.disabled = false;
                }, 2000);
            });
        }
        
        // Close status message
        const closeStatus = document.getElementById('closeStatus');
        const sessionStatus = document.getElementById('sessionStatus');
        
        if (closeStatus && sessionStatus) {
            closeStatus.addEventListener('click', () => {
                sessionStatus.classList.add('hidden');
            });
        }
        
        // Simulate session status message (would come from server)
        // This simulates the x-auth-session-status component
        setTimeout(() => {
            // Uncomment to show a sample status message
            // showStatusMessage('Sesi dipulihkan. Harap masuk untuk melanjutkan.');
        }, 1000);
        
        function showStatusMessage(message) {
            const statusMessage = document.getElementById('statusMessage');
            if (statusMessage && sessionStatus) {
                statusMessage.textContent = message;
                sessionStatus.classList.remove('hidden');
            }
        }
        
        // Input focus effects
        const inputs = document.querySelectorAll('.input-field');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-primary-200', 'dark:ring-primary-900');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-primary-200', 'dark:ring-primary-900');
            });
        });
        
        // Test logo path
        
        // Force reload logo images if they fail to load
        setTimeout(() => {
            const logos = document.querySelectorAll('img[id$="Logo"]');
            logos.forEach(logo => {
                if (logo.complete && logo.naturalHeight === 0) {
                    // Try to reload with cache busting
                }
            });
        }, 1000);
    </script>
</body>
</html>
