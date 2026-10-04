<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SKBD & SKB3 Report')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#0A66C2', 50: '#EFF6FF', 100: '#DBEAFE', 600: '#2563EB', 700: '#1D4ED8' },
                        accent: { DEFAULT: '#10B981', 50: '#ECFDF5', 100: '#D1FAE5', 600: '#059669', 700: '#047857' }
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', system-ui, sans-serif; }
        body { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        .sidebar { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-right: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-header { background: linear-gradient(135deg, rgba(10, 102, 194, 0.2), rgba(16, 185, 129, 0.2)); backdrop-filter: blur(10px); }
        .topbar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0, 0, 0, 0.05); }
        .gradient-text { background: linear-gradient(135deg, #0A66C2 0%, #10B981 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-container { background: linear-gradient(135deg, rgba(10, 102, 194, 0.1), rgba(16, 185, 129, 0.1)); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .nav-link { transition: all 0.25s ease; position: relative; }
        .nav-link:hover { background: linear-gradient(to right, rgba(10, 102, 194, 0.16), rgba(16, 185, 129, 0.12)); transform: translateX(4px); }
        .nav-link.active { background: linear-gradient(to right, rgba(10, 102, 194, 0.25), rgba(16, 185, 129, 0.18)); border-left: 3px solid #10B981; }
        .user-avatar { background: linear-gradient(135deg, rgba(10, 102, 194, 0.18), rgba(16, 185, 129, 0.18)); backdrop-filter: blur(10px); }
        .user-dropdown { position: absolute; right: 0; top: 100%; margin-top: 8px; min-width: 220px; background: white; border-radius: 10px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15); border: 1px solid rgba(0, 0, 0, 0.08); opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s ease; z-index: 1000; }
        .user-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .page-transition { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .sidebar { width: 72px; } .sidebar-text { display: none; } }
    </style>
    @stack('styles')
</head>
<body class="h-screen flex overflow-hidden text-gray-900">
    @php
        $isMainAdmin = strtolower((string) auth()->user()?->email) === 'admin@email.com';
    @endphp
    <aside class="sidebar w-64 flex flex-shrink-0 flex-col text-white">
        <div class="sidebar-header h-20 flex items-center border-b border-gray-800 px-6">
            <div class="flex items-center space-x-3">
                <div class="logo-container flex h-12 w-12 items-center justify-center rounded-xl">
                    <div class="text-lg font-bold gradient-text">SR</div>
                </div>
                <div>
                    <h1 class="sidebar-text text-lg font-bold tracking-tight text-white">SKBD Report</h1>
                    <p class="sidebar-text mt-1 text-xs text-gray-400">Administration Panel</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
            @if ($isMainAdmin)
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->is('dashboard') || request()->is('admin') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-home w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Dashboard</span>
                </a>
            @endif
            <div class="px-4 pt-6 pb-2"><div class="sidebar-text text-xs font-medium uppercase tracking-wider text-gray-400">Report</div></div>
            @if ($isMainAdmin)
                <a href="{{ route('reports.skbd') }}" class="nav-link {{ request()->is('report/skbd*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-file-lines w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Report SKBD</span>
                </a>
                <a href="{{ route('reports.skb3') }}" class="nav-link {{ request()->is('report/skb3*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-chart-column w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Report SKB3</span>
                </a>
            @endif
            <a href="{{ route('reports.konsolidasi') }}" class="nav-link {{ request()->is('report/konsolidasi*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                <i class="fas fa-layer-group w-5 text-center text-gray-300"></i>
                <span class="sidebar-text">Report Konsolidasi</span>
            </a>
            @if ($isMainAdmin)
                <a href="{{ route('reports.konsolidasi-v1') }}" class="nav-link {{ request()->is('admin/report/konsolidasi-v1*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-copy w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Report Konsolidasi v.1</span>
                </a>
                <a href="{{ route('reports.konsolidasi-query') }}" class="nav-link {{ request()->is('admin/report/konsolidasi-query*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-code-branch w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Query Konsolidasi</span>
                </a>
                <div class="px-4 pt-6 pb-2"><div class="sidebar-text text-xs font-medium uppercase tracking-wider text-gray-400">Admin</div></div>
                <a href="{{ route('register') }}" class="nav-link {{ request()->is('admin/register*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-4 py-3">
                    <i class="fas fa-user-plus w-5 text-center text-gray-300"></i>
                    <span class="sidebar-text">Register User</span>
                </a>
            @endif
        </nav>

        <div class="border-t border-gray-800 p-4">
            <div class="mb-3 flex items-center space-x-3">
                <div class="user-avatar flex h-8 w-8 items-center justify-center rounded-full">
                    <span class="text-sm font-medium gradient-text">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
                <div class="sidebar-text min-w-0">
                    <div class="truncate text-xs font-medium text-white">{{ auth()->user()->name }}</div>
                    <div class="truncate text-[11px] text-gray-400">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <div class="sidebar-text text-xs text-gray-400"><i class="fas fa-shield-alt mr-1"></i> Session: {{ substr(session()->getId(), 0, 8) }}...</div>
            <div class="sidebar-text mt-2 text-xs text-gray-400">Copyright {{ date('Y') }} SKBD Report</div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
        <header class="topbar flex h-16 items-center justify-between px-6">
            <div class="text-sm text-gray-600">
                <a href="{{ route('dashboard') }}" class="font-medium hover:text-primary-600">Admin</a>
                @hasSection('breadcrumb')
                    <span class="mx-2">/</span>
                    <span class="font-medium text-gray-800">@yield('breadcrumb')</span>
                @endif
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative flex items-center space-x-3">
                    <div class="hidden text-right md:block"><div class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</div></div>
                    <button id="userMenuButton" class="user-avatar flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500" type="button">
                        <span class="text-sm font-medium gradient-text">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="border-b border-gray-200 p-3">
                            <p class="text-sm font-medium text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p>
                            <p class="mt-1 inline-block rounded-full bg-gradient-to-r from-primary-50 to-accent-50 px-2 py-1 text-xs text-primary-600">{{ ucfirst(auth()->user()->role ?? 'user') }}</p>
                        </div>
                        <div class="p-2">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="flex w-full items-center rounded px-3 py-2 text-sm text-red-600 hover:bg-red-50" type="submit">
                                    <i class="fas fa-sign-out-alt mr-2 w-4 text-center text-red-500"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="page-transition flex-1 overflow-y-auto p-6">@yield('content')</main>

        <footer class="border-t border-gray-200 bg-white px-6 py-3 text-xs text-gray-500">
            <div class="flex items-center justify-between">
                <div><i class="fas fa-shield-alt mr-1"></i> Secured by PT. SEKARBUMI TBK - SKBD Report</div>
                <div><span class="mr-4"><i class="fas fa-server mr-1"></i> Server: {{ gethostname() }}</span><span><i class="far fa-clock mr-1"></i> <span id="currentTime">{{ date('H:i:s') }}</span></span></div>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    userDropdown.classList.toggle('show');
                });
                document.addEventListener('click', function (event) {
                    if (!userDropdown.contains(event.target)) userDropdown.classList.remove('show');
                });
            }
            function updateCurrentTime() {
                const timeElement = document.getElementById('currentTime');
                if (!timeElement) return;
                timeElement.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
            setInterval(updateCurrentTime, 1000);
            updateCurrentTime();
        });
    </script>
    @stack('scripts')
</body>
</html>
