@extends('layouts.admin')

@section('title', 'Verifikasi PB')

@section('content')
<div class="p-6" x-data="pbVerificationPage()" x-init="init()">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Verifikasi PB</h1>
        <p class="text-sm text-gray-600 mt-1">Konfirmasi kebutuhan permintaan barang sebelum masuk Approval Level 1.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">Menunggu Verifikasi</p>
            <p class="mt-2 text-2xl font-bold text-blue-600" x-text="items.length"></p>
            <p class="text-sm text-gray-500">PB assigned ke saya</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">History Verifikasi</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600" x-text="historyItems.length"></p>
            <p class="text-sm text-gray-500">Sudah diproses</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900" x-text="activeTab === 'pending' ? 'Daftar PB Perlu Verifikasi' : 'History Verifikasi PB'"></h2>
                <p class="text-sm text-gray-500" x-text="activeTab === 'pending' ? 'Cek kebutuhan, lalu verifikasi atau tolak.' : 'Arsip PB yang sudah diverifikasi atau ditolak di tahap Section Head.'"></p>
            </div>
            <div class="flex items-center gap-2">
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                    <button type="button" @click="activeTab = 'pending'" class="px-4 py-2 rounded-md text-sm font-semibold" :class="activeTab === 'pending' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'">Menunggu</button>
                    <button type="button" @click="activeTab = 'history'" class="px-4 py-2 rounded-md text-sm font-semibold" :class="activeTab === 'history' ? 'bg-white text-blue-700 shadow-sm' : 'text-gray-600'">History</button>
                </div>
                <button type="button" @click="refreshAll()" class="px-4 py-2 rounded-md border border-blue-200 bg-blue-50 text-blue-700 text-sm font-semibold hover:bg-blue-100">
                    Refresh
                </button>
            </div>
        </div>

        <div x-show="activeTab === 'history'" class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input x-model="historySearch" type="search" class="md:col-span-2 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Cari no PB, tujuan, catatan...">
            <input x-model="historyFrom" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <input x-model="historyTo" type="date" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div x-show="activeTab === 'pending'" class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left">No. PB</th>
                        <th class="px-5 py-3 text-left">Tanggal</th>
                        <th class="px-5 py-3 text-left">Tujuan</th>
                        <th class="px-5 py-3 text-left">Item</th>
                        <th class="px-5 py-3 text-left">Nilai</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="item in items" :key="item.id">
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900" x-text="item.nomor_pb"></div>
                                <div class="text-xs text-gray-500 capitalize" x-text="item.jenis_pekerjaan || '-'"></div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <div x-text="formatDate(item.tanggal_permintaan)"></div>
                                <div class="text-xs text-gray-500">Diperlukan: <span x-text="formatDate(item.tanggal_diperlukan)"></span></div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900" x-text="item.tujuan_nama || item.untuk"></div>
                                <div class="text-xs text-gray-500" x-text="item.tujuan_kode || '-'"></div>
                            </td>
                            <td class="px-5 py-4 text-gray-700" x-text="`${item.jumlah_barang || 0} item`"></td>
                            <td class="px-5 py-4 font-semibold text-gray-900" x-text="rupiah(item.total_value || 0)"></td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="verify(item)" class="px-3 py-2 rounded-md bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">Verifikasi</button>
                                    <button type="button" @click="reject(item)" class="px-3 py-2 rounded-md bg-red-50 text-red-700 border border-red-200 text-xs font-semibold hover:bg-red-100">Tolak</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!loading && items.length === 0">
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">Tidak ada PB yang menunggu verifikasi.</td>
                    </tr>
                    <tr x-show="loading">
                        <td colspan="6" class="px-5 py-12 text-center text-gray-500">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div x-show="activeTab === 'history'" class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-5 py-3 text-left">No. PB</th>
                        <th class="px-5 py-3 text-left">Tanggal</th>
                        <th class="px-5 py-3 text-left">Tujuan</th>
                        <th class="px-5 py-3 text-left">Verifikator</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Catatan</th>
                        <th class="px-5 py-3 text-left">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="item in filteredHistory" :key="item.id">
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900" x-text="item.nomor_pb"></div>
                                <div class="text-xs text-gray-500 capitalize" x-text="item.jenis_pekerjaan || '-'"></div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">
                                <div x-text="formatDate(item.tanggal_permintaan)"></div>
                                <div class="text-xs text-gray-500">Diproses: <span x-text="formatDateTime(item.verified_at || item.rejected_at)"></span></div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900" x-text="item.tujuan_nama || item.untuk"></div>
                                <div class="text-xs text-gray-500" x-text="item.tujuan_kode || '-'"></div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900" x-text="item.verifier_name || '-'"></div>
                                <div class="text-xs text-gray-500" x-text="item.verifier_username || '-'"></div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold border" :class="item.verification_status === 'verified' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'" x-text="item.verification_status === 'verified' ? 'Terverifikasi' : 'Ditolak'"></span>
                            </td>
                            <td class="px-5 py-4 text-gray-700 max-w-sm" x-text="item.verification_notes || '-'"></td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-gray-900" x-text="rupiah(item.total_value || 0)"></div>
                                <div class="text-xs text-gray-500" x-text="`${item.jumlah_barang || 0} item`"></div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!historyLoading && filteredHistory.length === 0">
                        <td colspan="7" class="px-5 py-12 text-center text-gray-500">Belum ada history verifikasi sesuai filter.</td>
                    </tr>
                    <tr x-show="historyLoading">
                        <td colspan="7" class="px-5 py-12 text-center text-gray-500">Memuat history...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function pbVerificationPage() {
    return {
        items: [],
        historyItems: [],
        activeTab: 'pending',
        loading: false,
        historyLoading: false,
        historySearch: '',
        historyFrom: '',
        historyTo: '',
        async init() {
            await this.refreshAll();
        },
        async refreshAll() {
            await Promise.all([this.loadData(), this.loadHistory()]);
        },
        get filteredHistory() {
            const search = this.historySearch.trim().toLowerCase();
            return this.historyItems.filter((item) => {
                const date = this.dateKey(item.verified_at || item.rejected_at || item.created_at);
                const text = [
                    item.nomor_pb,
                    item.tujuan_nama,
                    item.tujuan_kode,
                    item.verifier_name,
                    item.verifier_username,
                    item.verification_status,
                    item.verification_notes,
                ].join(' ').toLowerCase();

                return (!this.historyFrom || date >= this.historyFrom)
                    && (!this.historyTo || date <= this.historyTo)
                    && (!search || text.includes(search));
            });
        },
        async loadData() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('pb-verification.data') }}', { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                this.items = json.success ? (json.data || []) : [];
            } finally {
                this.loading = false;
            }
        },
        async loadHistory() {
            this.historyLoading = true;
            try {
                const res = await fetch('{{ route('pb-verification.history') }}', { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                this.historyItems = json.success ? (json.data || []) : [];
            } finally {
                this.historyLoading = false;
            }
        },
        async verify(item) {
            const notes = prompt('Catatan verifikasi (opsional):') || '';
            await this.post('{{ url('/pb-verification') }}/' + item.id + '/verify', { notes });
        },
        async reject(item) {
            const alasan = prompt('Alasan ditolak:');
            if (!alasan) return;
            await this.post('{{ url('/pb-verification') }}/' + item.id + '/reject', { alasan });
        },
        async post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(body),
            });
            const json = await res.json();
            alert(json.message || (json.success ? 'Berhasil' : 'Gagal'));
            if (json.success) {
                await this.refreshAll();
                this.activeTab = 'history';
            }
        },
        formatDate(value) {
            if (!value) return '-';
            return new Date(value).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        formatDateTime(value) {
            if (!value) return '-';
            return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        dateKey(value) {
            if (!value) return '';
            const date = new Date(value);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        rupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        },
    };
}
</script>
@endsection
