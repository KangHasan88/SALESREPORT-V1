<div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
    <div>
        <label for="code" class="mb-2 block text-sm font-semibold text-gray-700">Kode Departemen</label>
        <input id="code"
               name="code"
               type="text"
               value="{{ old('code', $department?->code) }}"
               placeholder="contoh: maintenance_utility"
               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
               required>
        <p class="mt-1 text-xs text-gray-500">Gunakan huruf kecil, angka, dan underscore. Contoh: maintenance_process</p>
        @error('code')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="mb-2 block text-sm font-semibold text-gray-700">Nama Departemen</label>
        <input id="name"
               name="name"
               type="text"
               value="{{ old('name', $department?->name) }}"
               placeholder="contoh: Maintenance Utility"
               class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 placeholder-gray-400 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
               required>
        @error('name')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
    <label for="is_active" class="flex cursor-pointer items-center gap-3">
        <input id="is_active"
               name="is_active"
               type="checkbox"
               value="1"
               class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
               @checked(old('is_active', $department?->is_active ?? true))>
        <span>
            <span class="block text-sm font-semibold text-gray-800">Aktif</span>
            <span class="block text-xs text-gray-500">Departemen aktif bisa dipilih saat tambah/edit user.</span>
        </span>
    </label>
</div>
