<nav class="bg-white border-b shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">

        <a href="/" class="font-bold text-lg">
            LaravelApp
        </a>

        <div class="space-x-4">
            @guest
                <a href="/login" class="text-gray-600 hover:text-black">
                    Login
                </a>
                <a href="/register" class="px-4 py-2 bg-blue-600 text-white rounded">
                    Register
                </a>
            @endguest

            @auth
            
                {{-- Menu Admin (hanya muncul kalau role = admin) --}}
                @if(auth()->user()->role === 'admin')
                    <a href="/admin" class="text-red-600 font-semibold">
                        Admin
                    </a>
                @endif
            
                <span class="text-gray-600">
                    Hi, {{ auth()->user()->name }}
                </span>
            
                <form action="/logout" method="POST" class="inline">
                    @csrf
                    <button class="text-red-600 hover:underline">
                        Logout
                    </button>
                </form>
            
            @endauth

        </div>

    </div>
</nav>
