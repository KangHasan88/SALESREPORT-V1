<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3;url={{ route('login') }}">
    <title>Logout Berhasil | SIPERMATA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              primary: '#0A66C2',
              accent: '#10B981'
            },
            fontFamily: {
              sans: ['Inter', 'system-ui', 'sans-serif']
            }
          }
        }
      }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: Inter, system-ui, sans-serif; }
        body {
            background:
                radial-gradient(circle at 20% 20%, rgba(10, 102, 194, 0.12), transparent 28%),
                radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.12), transparent 26%),
                linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
        }
    </style>
</head>
<body class="min-h-screen">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-xl rounded-2xl border border-white/70 bg-white/95 p-8 text-center shadow-2xl shadow-blue-900/10">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <i class="fas fa-circle-check text-3xl"></i>
            </div>

            <h1 class="mt-6 text-2xl font-bold text-slate-900">Logout Berhasil</h1>
            <p class="mt-3 text-base leading-7 text-slate-600">
                Terima Kasih sudah menggunakan Aplikasi ini.
            </p>

            <div class="mt-7 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                Anda akan diarahkan ke halaman login dalam
                <span id="countdown" class="font-semibold text-primary">3</span>
                detik.
            </div>

            <a href="{{ route('login') }}"
               class="mt-6 inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Masuk Lagi
            </a>
        </section>
    </main>

    <script>
        let count = 3;
        const countdown = document.getElementById('countdown');
        const timer = setInterval(() => {
            count -= 1;
            if (countdown) countdown.textContent = Math.max(count, 0);
            if (count <= 0) {
                clearInterval(timer);
                window.location.href = @json(route('login'));
            }
        }, 1000);
    </script>
</body>
</html>
