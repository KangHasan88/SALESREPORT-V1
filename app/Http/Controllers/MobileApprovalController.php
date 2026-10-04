<?php

namespace App\Http\Controllers;

use App\Services\FirebasePushService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MobileApprovalController extends Controller
{
    private const LEVEL_ONE = 1;
    private const LEVEL_TWO = 2;

    public function config(Request $request)
    {
        $defaults = [
            'theme' => [
                'primary_color' => '#2563EB',
                'accent_color' => '#0F766E',
                'navy_color' => '#111827',
                'background_color' => '#F3F6FA',
                'surface_color' => '#F8FAFC',
                'border_color' => '#E2E8F0',
            ],
            'features' => [
                'show_bottom_nav' => true,
                'history_search_on_dashboard' => false,
                'enable_webview_pages' => true,
            ],
            'labels' => [
                'language' => 'id',
                'app_title' => 'e-Request',
                'login_subtitle' => 'Request, Approval, and Tracking',
            ],
        ];

        $path = storage_path('app/mobile-config.json');
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                $defaults = array_replace_recursive($defaults, $json);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $defaults,
        ]);
    }

    public function appVersion(Request $request)
    {
        $defaults = [
            'version_code' => 2,
            'version_name' => '1.1',
            'apk_url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/downloads/e-request-approval.apk',
            'force_update' => false,
            'release_notes' => [
                'In-app update checker',
                'Detail WO/PB history',
                'Preview foto hasil pekerjaan',
            ],
        ];

        $path = storage_path('app/mobile-app-version.json');
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                $defaults = array_merge($defaults, $json);
                if (!Str::startsWith((string) ($defaults['apk_url'] ?? ''), ['http://', 'https://'])) {
                    $defaults['apk_url'] = rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim((string) $defaults['apk_url'], '/');
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $defaults,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = DB::table('users')
            ->where('username', $data['username'])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau kata sandi salah.',
            ], 401);
        }

        if (Schema::hasColumn('users', 'is_active') && ! (bool) ($user->is_active ?? true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini sedang nonaktif. Hubungi administrator.',
            ], 403);
        }

        if (!$this->isAllowedMobileUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Aplikasi mobile ini hanya untuk Approval L1, Approval L2, Section Head, dan Admin Engineering.',
            ], 403);
        }

        $plainToken = Str::random(80);

        DB::table('mobile_api_tokens')->insert([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'device_name' => $data['device_name'] ?? 'Android',
            'last_used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        DB::table('mobile_api_tokens')->where('id', $auth->token_id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        return response()->json([
            'success' => true,
            'user' => $this->userPayload($auth),
        ]);
    }

    public function dashboard(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $today = now()->toDateString();

        if ($this->isEngineeringMobileUser($auth)) {
            $pbBase = DB::table('trBPB')->where('user_id', $auth->id);
            $woBase = DB::table('trWorkOrder')->where('created_by', $auth->id);

            return response()->json([
                'success' => true,
                'role' => $auth->role,
                'summary' => [
                    'pb_total' => (clone $pbBase)->count(),
                    'pb_pending' => (clone $pbBase)->where('status', 'pending')->count(),
                    'pb_approved' => (clone $pbBase)->where('status', 'approved')->count(),
                    'wo_total' => (clone $woBase)->count(),
                    'wo_submitted' => (clone $woBase)->where('status', 'submitted')->count(),
                    'wo_progress' => (clone $woBase)->where('status', 'approved')->whereIn('progress_status', ['open', 'progress', 'hold'])->count(),
                    'notification_count' => 0,
                ],
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        if ($auth->role === 'section_head') {
            $pbVerification = DB::table('trBPB')
                ->where('status', 'verification')
                ->where('verification_status', 'pending')
                ->where('verification_section_head_id', $auth->id)
                ->count();

            $assigned = DB::table('trWorkOrder')
                ->where('assigned_regu', $auth->name)
                ->where('status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('progress_status')
                        ->orWhereIn('progress_status', ['open', 'progress', 'hold']);
                })
                ->count();

            $doneToday = DB::table('trWorkOrder')
                ->where('assigned_regu', $auth->name)
                ->where('progress_status', 'closed')
                ->whereDate('closed_at', $today)
                ->count();

            return response()->json([
                'success' => true,
                'role' => $auth->role,
                'summary' => [
                    'pb_verification' => $pbVerification,
                    'assigned_wo' => $assigned,
                    'done_today' => $doneToday,
                    'notification_count' => $assigned + $pbVerification,
                ],
                'updated_at' => now()->toIso8601String(),
            ]);
        }

        $queueSummary = $this->mobileApprovalQueueSummary($auth);
        $pbPending = $queueSummary['pb_pending'];
        $woPending = $queueSummary['wo_pending'];

        $pbApprovedToday = DB::table('trBPB')
            ->whereDate('approved_at', $today)
            ->when($auth->role === 'approval', fn ($q) => $q->where('approval_level_1_by', $auth->id))
            ->when($auth->role === 'approval2', fn ($q) => $q->where('approval_level_2_by', $auth->id))
            ->count();

        $woApprovedToday = $auth->role === 'approval'
            ? DB::table('trWorkOrder')->where('approved_by', $auth->id)->whereDate('approved_at', $today)->count()
            : 0;

        $pbRejectedToday = DB::table('trBPB')
            ->where('rejected_by', $auth->id)
            ->whereDate('rejected_at', $today)
            ->count();

        $woRejectedToday = $auth->role === 'approval'
            ? DB::table('trWorkOrder')->where('rejected_by', $auth->id)->whereDate('rejected_at', $today)->count()
            : 0;

        $payload = [
            'success' => true,
            'role' => $auth->role,
            'summary' => [
                'pb_pending' => $pbPending,
                'pb_approved' => $queueSummary['pb_approved'],
                'pb_progress' => $queueSummary['pb_progress'],
                'pb_done' => $queueSummary['pb_done'],
                'pb_rejected' => $queueSummary['pb_rejected'],
                'wo_pending' => $woPending,
                'wo_approved' => $queueSummary['wo_approved'],
                'wo_progress' => $queueSummary['wo_progress'],
                'wo_done' => $queueSummary['wo_done'],
                'wo_rejected' => $queueSummary['wo_rejected'],
                'approved_today' => $pbApprovedToday + $woApprovedToday,
                'rejected_today' => $pbRejectedToday + $woRejectedToday,
                'notification_count' => $pbPending + $woPending,
            ],
            'updated_at' => now()->toIso8601String(),
        ];

        if ($auth->role === 'approval') {
            $payload['budget'] = $this->mobileApprovalBudgetSnapshot();
            $payload['budget_by_section_head'] = $this->mobileBudgetBySectionHead($payload['budget']['total_used'] ?? 0);
        }

        return response()->json($payload);
    }

    public function webDashboard(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if ($auth->role === 'section_head') {
            return $this->mobileSectionHeadDashboard($auth, $request);
        }

        if (!in_array($auth->role, ['approval', 'approval2'], true)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Dashboard approval hanya untuk Approval L1 dan Approval L2.</div>', 403);
        }

        $summary = $this->mobileApprovalQueueSummary($auth);
        $budget = in_array($auth->role, ['approval', 'approval2'], true) ? $this->mobileApprovalBudgetSnapshot() : null;
        $sectionBudgets = in_array($auth->role, ['approval', 'approval2'], true)
            ? $this->mobileBudgetBySectionHead($budget['total_used'] ?? 0)
            : [];

        $metric = function (string $label, int $value, string $class, string $hint, string $type, string $status) {
            $url = $this->mobileTokenUrl(url('/api/mobile/web/detail'), [
                'type' => $type,
                'status' => $status,
            ]);

            return '<a class="queue-metric ' . e($class) . '" href="' . e($url) . '">
                <span>' . e($label) . '</span>
                <strong>' . e((string) $value) . '</strong>
                ' . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '
            </a>';
        };

        $pbLane = '<section class="queue-lane queue-lane-pb">
            <div class="queue-lane-head">
                <span>PB</span>
                <strong>Permintaan Barang</strong>
                <small>Approval & fulfillment</small>
            </div>
            ' . $metric('PB Menunggu', $summary['pb_pending'], 'waiting', 'Butuh keputusan', 'PB', 'pending') . '
            ' . $metric('PB Approved', $summary['pb_approved'], 'approved', 'Disetujui', 'PB', 'approved') . '
            ' . $metric('PB Fulfillment', $summary['pb_progress'], 'progress', 'Proses warehouse', 'PB', 'progress') . '
            ' . $metric('PB Done', $summary['pb_done'], 'done', 'Selesai', 'PB', 'done') . '
            ' . $metric('PB Rejected', $summary['pb_rejected'], 'rejected', 'Ditolak', 'PB', 'rejected') . '
        </section>';

        $woLane = '<section class="queue-lane queue-lane-wo">
            <div class="queue-lane-head">
                <span>WO</span>
                <strong>Work Order</strong>
                <small>Assign & progress</small>
            </div>
            ' . $metric('WO Menunggu', $summary['wo_pending'], 'waiting', $auth->role === 'approval' ? 'Butuh keputusan' : 'Khusus L1', 'WO', 'pending') . '
            ' . $metric('WO Approved', $summary['wo_approved'], 'approved', 'Sudah assign', 'WO', 'approved') . '
            ' . $metric('WO Progress', $summary['wo_progress'], 'progress', 'Sedang dikerjakan', 'WO', 'progress') . '
            ' . $metric('WO Done', $summary['wo_done'], 'done', 'Pekerjaan selesai', 'WO', 'done') . '
            ' . $metric('WO Rejected', $summary['wo_rejected'], 'rejected', 'Ditolak', 'WO', 'rejected') . '
        </section>';

        $factoryPbMetrics = $metric('PB Menunggu', $summary['pb_pending'], 'waiting', 'Butuh keputusan L2', 'PB', 'pending') . '
            ' . $metric('PB Approved', $summary['pb_approved'], 'approved', 'Sudah disetujui', 'PB', 'approved') . '
            ' . $metric('PB Fulfillment', $summary['pb_progress'], 'progress', 'Proses warehouse', 'PB', 'progress') . '
            ' . $metric('PB Done', $summary['pb_done'], 'done', 'Selesai', 'PB', 'done') . '
            ' . $metric('PB Rejected', $summary['pb_rejected'], 'rejected', 'Ditolak', 'PB', 'rejected');

        $budgetHtml = '';
        if ($budget) {
            $sourceBreakdown = $this->mobileApprovalBudgetSourceBreakdown();
            $sourceRow = function (string $source, string $label, string $caption, array $row) use ($auth) {
                $content = '<div><strong>' . e($label) . '</strong><span>' . e((string) $row['count']) . ' ' . e($caption) . '</span></div>
                    <b>' . e($this->mobileRupiah($row['amount'])) . '</b>';

                if ($auth->role !== 'approval') {
                    return '<div class="budget-break-row">' . $content . '</div>';
                }

                $href = $this->mobileTokenUrl(url('/api/mobile/web/detail'), [
                    'type' => 'PB',
                    'status' => 'approved',
                    'source' => $source,
                ]);

                return '<a class="budget-break-row budget-break-link" href="' . e($href) . '">' . $content . '<i aria-hidden="true">&rsaquo;</i></a>';
            };
            $sectionRows = collect($sectionBudgets)->map(function ($row) {
                return '<div class="budget-row">
                    <div><strong>' . e($row['name']) . '</strong><span>' . e((string) $row['pb_count']) . ' PB</span></div>
                    <b>' . e($this->mobileRupiah($row['amount'])) . '</b>
                </div>';
            })->implode('');

            $budgetHtml = '<section class="budget-mobile-card">
                <h2>Budget Snapshot</h2>
                <p>Ringkasan konsumsi budget PB tahun berjalan.</p>
                <input class="budget-toggle" id="budgetBreakdownToggle" type="checkbox" hidden>
                <label class="budget-box success budget-click" for="budgetBreakdownToggle"><span>Final Approved</span><strong>' . e($this->mobileRupiah($budget['total_used'] ?? 0)) . '</strong><small>Tap untuk breakdown sumber PB</small></label>
                <div class="budget-box warning"><span>Masih Menunggu</span><strong>' . e($this->mobileRupiah($budget['waiting_l2'] ?? 0)) . '</strong></div>
                <div class="budget-box danger"><span>Tidak Disetujui</span><strong>' . e($this->mobileRupiah($budget['rejected'] ?? 0)) . '</strong></div>
                <div class="budget-overlay">
                    <label class="budget-backdrop" for="budgetBreakdownToggle"></label>
                    <section class="budget-modal">
                        <div class="budget-modal-head">
                            <div><h2>Breakdown Final Approved</h2><p>Akumulasi PB tahun berjalan.</p></div>
                            <label for="budgetBreakdownToggle" aria-label="Tutup">&times;</label>
                        </div>
                        ' . $sourceRow('with_wo', 'PB dari WO', 'PB dengan referensi Work Order', $sourceBreakdown['with_wo']) . '
                        ' . $sourceRow('without_wo', 'PB tanpa WO', 'PB tanpa referensi Work Order', $sourceBreakdown['without_wo']) . '
                    </section>
                </div>
                <h3>Budget per Section Head</h3>
                <div class="budget-list">' . ($sectionRows ?: '<div class="empty small">Belum ada konsumsi budget.</div>') . '</div>
            </section>';
        }

        $topbar = '';

        if ($auth->role === 'approval2') {
            $body = '<section class="approval-dashboard factory-dashboard">
                <section class="factory-hero">
                    <div>
                        <span>Approval Level 2</span>
                        <h2>PB High Value</h2>
                        <p>Fokus keputusan untuk permintaan barang dengan item di atas Rp10 juta.</p>
                    </div>
                    <strong>' . e((string) $summary['pb_pending']) . '</strong>
                    <small>menunggu</small>
                </section>
                <section class="factory-panel">
                    <div class="factory-panel-head">
                        <div>
                            <h2>Antrian PB</h2>
                            <p>Tap kartu untuk melihat detail data.</p>
                        </div>
                    </div>
                    <div class="factory-metric-grid">' . $factoryPbMetrics . '</div>
                </section>
                ' . $budgetHtml . '
            </section>';

            return $this->mobileWebResponse('Dashboard', $body);
        }

        $lanesClass = 'queue-lanes';
        $lanesHtml = $pbLane . $woLane;
        $queueDescription = $auth->role === 'approval2'
            ? 'Ringkasan PB high value yang membutuhkan keputusan Approval L2.'
            : 'Ringkasan PB dan WO yang membutuhkan perhatian.';

        $body = '<section class="approval-dashboard">
            ' . $topbar . '
            <section class="queue-card">
                <div class="queue-title">
                    <div>
                        <h2>Antrian & Progress</h2>
                        <p>' . e($queueDescription) . '</p>
                    </div>
                </div>
                <div class="' . e($lanesClass) . '">' . $lanesHtml . '</div>
            </section>
            ' . $budgetHtml . '
        </section>';

        return $this->mobileWebResponse('Dashboard', $body);
    }

    private function mobileSectionHeadDashboard(object $auth, Request $request)
    {
        $today = now()->toDateString();
        $period = $this->mobileDashboardPeriod($request);
        $pbVerification = DB::table('trBPB')
            ->where('status', 'verification')
            ->where('verification_status', 'pending')
            ->where('verification_section_head_id', $auth->id)
            ->count();

        $assigned = DB::table('trWorkOrder')
            ->where('assigned_regu', $auth->name)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('progress_status')
                    ->orWhereIn('progress_status', ['open', 'progress', 'hold']);
            })
            ->count();

        $doneToday = DB::table('trWorkOrder')
            ->where('assigned_regu', $auth->name)
            ->where('progress_status', 'closed')
            ->whereDate('closed_at', $today)
            ->count();
        $budget = $this->mobileSectionHeadBudgetSnapshot($auth, $period);

        $metric = function (string $label, int $value, string $class, string $hint, string $url, bool $needsAttention = false) {
            $attentionClass = $needsAttention && $value > 0 ? ' needs-attention' : '';
            $badge = $needsAttention && $value > 0 ? '<em aria-label="Perlu tindak lanjut"></em>' : '';

            return '<a class="queue-metric ' . e($class . $attentionClass) . '" href="' . e($url) . '">
                <span>' . e($label) . '</span>
                <strong>' . e((string) $value) . '</strong>
                <small>' . e($hint) . '</small>
                ' . $badge . '
            </a>';
        };

        $historyUrl = url('/api/mobile/web/history');
        $pbVerificationUrl = url('/api/mobile/web/section/pb-verification');
        $assignedWoUrl = url('/api/mobile/web/section/work-orders');
        $doneTodayUrl = url('/api/mobile/web/section/done-today');
        $stockUrl = url('/api/mobile/web/stock-sparepart');
        $username = (string) ($auth->username ?? $auth->name ?? 'Section Head');
        $tokenInput = request()->query('token') || request()->bearerToken()
            ? '<input type="hidden" name="token" value="' . e((string) (request()->query('token') ?: request()->bearerToken())) . '">'
            : '';
        $monthOptions = '';

        for ($monthIndex = 1; $monthIndex <= 12; $monthIndex++) {
            $monthValue = now()->format('Y') . '-' . str_pad((string) $monthIndex, 2, '0', STR_PAD_LEFT);
            $monthLabel = Carbon::create(now()->year, $monthIndex, 1)->translatedFormat('F');
            $selected = ($period['month'] ?? now()->format('Y-m')) === $monthValue ? ' selected' : '';
            $monthOptions .= '<option value="' . e($monthValue) . '"' . $selected . '>' . e($monthLabel) . '</option>';
        }

        $budgetFilter = '<form id="mobileBudgetFilter" class="mobile-budget-filter" method="GET" action="' . e(url('/api/mobile/web/dashboard')) . '#budget-snapshot" onsubmit="return mobileApplyBudgetFilter(event)">
            ' . $tokenInput . '
            <div>
                <strong>Filter Budget Snapshot</strong>
                <small>Ditampilkan untuk ' . e($period['label'] ?? 'Year to Date') . '.</small>
            </div>
            <label>
                <span>Mode</span>
                <select id="mobileBudgetMode" name="period_mode" onchange="mobileToggleBudgetMonth()">
                    <option value="ytd"' . (($period['mode'] ?? 'ytd') === 'ytd' ? ' selected' : '') . '>Year to Date</option>
                    <option value="month"' . (($period['mode'] ?? 'ytd') === 'month' ? ' selected' : '') . '>Per Bulan</option>
                </select>
            </label>
            <label id="mobileBudgetMonthWrap" class="' . (($period['mode'] ?? 'ytd') === 'month' ? '' : 'is-hidden') . '">
                <span>Bulan</span>
                <select name="period_month">' . $monthOptions . '</select>
            </label>
            <button type="submit">Terapkan</button>
        </form>
        <script>
            function mobileToggleBudgetMonth() {
                var mode = document.getElementById("mobileBudgetMode");
                var month = document.getElementById("mobileBudgetMonthWrap");
                if (!mode || !month) return;
                month.classList.toggle("is-hidden", mode.value !== "month");
            }
            function mobileApplyBudgetFilter(event) {
                if (event && event.preventDefault) event.preventDefault();
                var form = document.getElementById("mobileBudgetFilter");
                var mode = document.getElementById("mobileBudgetMode");
                var month = form ? form.querySelector("select[name=period_month]") : null;
                var button = form ? form.querySelector("button[type=submit]") : null;
                var target = new URL("' . e(url('/api/mobile/web/dashboard')) . '", window.location.origin);
                var current = new URLSearchParams(window.location.search);
                var token = current.get("token") || (form && form.querySelector("input[name=token]") ? form.querySelector("input[name=token]").value : "");
                if (token) target.searchParams.set("token", token);
                target.searchParams.set("period_mode", mode && mode.value === "month" ? "month" : "ytd");
                if (mode && mode.value === "month" && month && month.value) target.searchParams.set("period_month", month.value);
                target.hash = "budget-snapshot";
                if (!window.fetch || !window.DOMParser) {
                    window.location.href = target.toString();
                    return false;
                }
                if (button) {
                    button.disabled = true;
                    button.dataset.originalText = button.textContent;
                    button.textContent = "Memuat...";
                }
                fetch(target.toString(), { credentials: "same-origin" })
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, "text/html");
                        var next = doc.getElementById("budget-snapshot");
                        var currentBudget = document.getElementById("budget-snapshot");
                        if (!next || !currentBudget) {
                            window.location.href = target.toString();
                            return;
                        }
                        currentBudget.outerHTML = next.outerHTML;
                        if (window.history && window.history.replaceState) {
                            window.history.replaceState(null, "", target.pathname + target.search + target.hash);
                        }
                        var section = document.getElementById("budget-snapshot");
                        if (section && section.scrollIntoView) section.scrollIntoView({ block: "start", behavior: "smooth" });
                        mobileToggleBudgetMonth();
                    })
                    .catch(function () {
                        window.location.href = target.toString();
                    })
                    .finally(function () {
                        var freshButton = document.querySelector("#mobileBudgetFilter button[type=submit]");
                        if (freshButton) {
                            freshButton.disabled = false;
                            freshButton.textContent = freshButton.dataset.originalText || "Terapkan";
                        }
                    });
                return false;
            }
            window.addEventListener("load", function () {
                mobileToggleBudgetMonth();
                if (window.location.hash === "#budget-snapshot") {
                    var section = document.getElementById("budget-snapshot");
                    if (section && section.scrollIntoView) section.scrollIntoView({ block: "start" });
                }
            });
            mobileToggleBudgetMonth();
        </script>';

        $body = '<section class="approval-dashboard factory-dashboard">
            <section class="factory-hero">
                <div>
                    <span>Section Head</span>
                    <h2>Pekerjaan Saya</h2>
                    <p>' . e($username) . '</p>
                </div>
                <strong>' . e((string) ($pbVerification + $assigned)) . '</strong>
                <small>aktif</small>
            </section>
            <section class="queue-card">
                <div class="queue-title">
                    <div>
                        <h2>Verifikasi PB & WO</h2>
                        <p>Ringkasan PB yang perlu diverifikasi dan WO yang sedang ditangani.</p>
                    </div>
                </div>
                <div class="factory-metric-grid">
                    ' . $metric('PB Verifikasi', $pbVerification, 'waiting', 'Menunggu konfirmasi', $pbVerificationUrl, true) . '
                    ' . $metric('WO Assigned', $assigned, 'progress', 'Open / progress', $assignedWoUrl, true) . '
                    ' . $metric('Done Hari Ini', $doneToday, 'done', 'Pekerjaan selesai', $doneTodayUrl) . '
                </div>
            </section>
            <section id="budget-snapshot" class="budget-mobile-card section-budget-card">
                <h2>Budget Snapshot</h2>
                <p>Ringkasan budget PB section sesuai periode dipilih.</p>
                ' . $budgetFilter . '
                <div class="budget-box success"><span>Final Approved</span><strong>' . e($this->mobileRupiah($budget['total_used'] ?? 0)) . '</strong><small>' . e((string) ($budget['approved_count'] ?? 0)) . ' PB final</small></div>
                <div class="budget-box warning"><span>Masih Menunggu</span><strong>' . e($this->mobileRupiah($budget['waiting'] ?? 0)) . '</strong><small>' . e((string) ($budget['waiting_count'] ?? 0)) . ' PB dalam proses</small></div>
                <div class="budget-box danger"><span>Tidak Disetujui</span><strong>' . e($this->mobileRupiah($budget['rejected'] ?? 0)) . '</strong><small>' . e((string) ($budget['rejected_count'] ?? 0)) . ' PB ditolak</small></div>
            </section>
        <section class="queue-card section-action-card">
            <a class="primary-action stock-action-button" href="' . e($stockUrl) . '">Stock Sparepart</a>
        </section>
    </section>';

        return $this->mobileWebResponse('Dashboard', $body);
    }

    public function webSectionPbVerification(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if ($auth->role !== 'section_head') {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Halaman PB Verifikasi hanya untuk Section Head.</div>', 403);
        }

        $items = $this->pbListQuery($auth)
            ->limit(80)
            ->get()
            ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                'status' => 'verification',
                'verification_status' => 'pending',
                'history_type' => 'PB',
            ]));

        $cards = $items->map(fn ($item) => $this->mobileHistoryCard((array) $item))->implode('');
        $body = '<section class="history-screen">
            <div class="history-filter-summary">
                <strong>PB Verifikasi</strong>
                <span>' . e((string) $items->count()) . ' PB menunggu konfirmasi</span>
            </div>
            <div class="history-list">' . ($cards ?: '<div class="empty">Belum ada PB yang perlu diverifikasi.</div>') . '</div>
        </section>';

        return $this->mobileWebResponse('PB Verifikasi', $body);
    }

    public function webSectionWorkOrders(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if ($auth->role !== 'section_head') {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Halaman WO Assigned hanya untuk Section Head.</div>', 403);
        }

        $items = $this->sectionWorkOrderQuery($auth)
            ->limit(80)
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'history_type' => 'WO',
                'nomor' => $item->nomor,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'status' => 'approved',
                'progress_status' => $item->progress_status ?: 'open',
                'assigned_at' => $item->assigned_at,
                'approved_at' => $item->assigned_at ?? $item->approved_at ?? $item->created_at,
                'created_at' => $item->created_at,
                'created_by_name' => $item->created_by_name,
                'photos' => $this->workOrderPhotos((int) $item->id),
            ]);

        $cards = $items->map(fn ($item) => $this->mobileHistoryCard((array) $item))->implode('');
        $body = '<section class="history-screen">
            <div class="history-filter-summary">
                <strong>WO Assigned</strong>
                <span>' . e((string) $items->count()) . ' WO open / progress / hold</span>
            </div>
            <div class="history-list">' . ($cards ?: '<div class="empty">Belum ada WO assigned.</div>') . '</div>
        </section>';

        return $this->mobileWebResponse('WO Assigned', $body);
    }

    public function webSectionDoneToday(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if ($auth->role !== 'section_head') {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Halaman Done Hari Ini hanya untuk Section Head.</div>', 403);
        }

        $today = now()->toDateString();
        $items = $this->sectionWorkOrderHistoryRows($auth, 200)
            ->filter(fn ($item) => !empty($item['closed_at']) && Carbon::parse($item['closed_at'])->timezone('Asia/Jakarta')->toDateString() === $today)
            ->values();

        $cards = $items->map(fn ($item) => $this->mobileHistoryCard((array) $item))->implode('');
        $body = '<section class="history-screen">
            <div class="history-filter-summary">
                <strong>Done Hari Ini</strong>
                <span>' . e((string) $items->count()) . ' WO selesai hari ini</span>
            </div>
            <div class="history-list">' . ($cards ?: '<div class="empty">Belum ada WO selesai hari ini.</div>') . '</div>
        </section>';

        return $this->mobileWebResponse('Done Hari Ini', $body);
    }

    public function webDashboardDetail(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if (!in_array($auth->role, ['approval', 'approval2'], true)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Detail dashboard hanya untuk Approval L1 dan Approval L2.</div>', 403);
        }

        $type = strtoupper((string) $request->query('type', 'PB'));
        $status = strtolower((string) $request->query('status', 'pending'));
        if (!in_array($type, ['PB', 'WO'], true)) {
            $type = 'PB';
        }
        if (!in_array($status, ['pending', 'approved', 'progress', 'done', 'rejected'], true)) {
            $status = 'pending';
        }

        $source = strtolower((string) $request->query('source', ''));
        if (!in_array($source, ['with_wo', 'without_wo'], true) || $auth->role !== 'approval' || $type !== 'PB' || $status !== 'approved') {
            $source = '';
        }

        $items = $this->mobileDashboardDetailItems($auth, $type, $status, $source);
        $cards = $items->map(fn ($item) => $this->mobileHistoryCard((array) $item))->implode('');
        $count = $items->count();
        $title = $this->mobileDashboardDetailTitle($type, $status);
        $subtitle = $type === 'PB'
            ? 'Detail Permintaan Barang sesuai status yang dipilih.'
            : 'Detail Work Order sesuai status yang dipilih.';

        if ($source === 'with_wo') {
            $title = 'PB dari WO';
            $subtitle = 'Daftar PB final approved yang memakai referensi Work Order.';
        } elseif ($source === 'without_wo') {
            $title = 'PB tanpa WO';
            $subtitle = 'Daftar PB final approved tanpa referensi Work Order.';
        }

        if ($cards === '') {
            $cards = '<div class="empty">Tidak ada data untuk kategori ini.</div>';
        }

        $body = '<section class="detail-page">
            <p class="page-subtitle">' . e($subtitle) . '</p>
            <section class="history-filter-summary"><strong>' . e($title) . '</strong><span>' . e((string) $count) . ' data</span></section>
            <section class="date-filter-row">
                <label class="field-wrap"><span>Dari</span><input id="dashboardDateFrom" data-date-filter="from" type="date" onchange="filterCards(document.getElementById(\'dashboardDetailSearch\').value)"></label>
                <label class="field-wrap"><span>Sampai</span><input id="dashboardDateTo" data-date-filter="to" type="date" onchange="filterCards(document.getElementById(\'dashboardDetailSearch\').value)"></label>
            </section>
            <section class="toolbar"><input id="dashboardDetailSearch" class="search" type="search" placeholder="Cari nomor, status, judul..." oninput="filterCards(this.value)"></section>
            <section class="list">' . $cards . '</section>
        </section>';

        return $this->mobileWebResponse($title, $body);
    }

    public function notifications(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role === 'section_head') {
            $pb = $this->pbListQuery($auth)
                ->limit(8)
                ->get()
                ->map(fn ($item) => [
                    'id' => 'pb-' . $item->id,
                    'type' => 'PB',
                    'record_id' => (int) $item->id,
                    'nomor' => $item->nomor_pb,
                    'title' => 'PB Menunggu Verifikasi',
                    'message' => trim(($item->tujuan_nama ?? ucfirst($item->untuk ?? '-')) . ' - ' . ($item->jumlah_barang ?? 0) . ' item'),
                    'total_value' => (float) $item->total_value,
                    'created_at' => $item->created_at,
                ]);

            $wo = $this->sectionWorkOrderQuery($auth)
                ->limit(8)
                ->get()
                ->map(fn ($item) => [
                    'id' => 'wo-' . $item->id,
                    'type' => 'WO',
                    'record_id' => (int) $item->id,
                    'nomor' => $item->nomor,
                    'title' => 'WO Assigned',
                    'message' => $item->judul,
                    'total_value' => null,
                    'created_at' => $item->assigned_at ?? $item->approved_at ?? $item->created_at,
                ]);

            $items = $pb->merge($wo)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'count' => $items->count(),
                'items' => $items,
            ]);
        }

        if ($this->isEngineeringMobileUser($auth)) {
            return response()->json([
                'success' => true,
                'count' => 0,
                'items' => [],
            ]);
        }

        $pb = $this->pbListQuery($auth)
            ->limit(8)
            ->get()
            ->map(fn ($item) => [
                'id' => 'pb-' . $item->id,
                'type' => 'PB',
                'record_id' => (int) $item->id,
                'nomor' => $item->nomor_pb,
                'title' => $auth->role === 'approval2' ? 'PB Menunggu Approval L2' : 'PB Menunggu Approval L1',
                'message' => trim(($item->tujuan_nama ?? ucfirst($item->untuk ?? '-')) . ' - ' . ($item->jumlah_barang ?? 0) . ' item'),
                'total_value' => (float) $item->total_value,
                'created_at' => $item->created_at,
            ]);

        $wo = collect();
        if ($auth->role === 'approval') {
            $wo = $this->woBaseQuery()
                ->limit(8)
                ->get()
                ->map(fn ($item) => [
                    'id' => 'wo-' . $item->id,
                    'type' => 'WO',
                    'record_id' => (int) $item->id,
                    'nomor' => $item->nomor,
                    'title' => 'WO Menunggu Approval',
                    'message' => $item->judul,
                    'total_value' => null,
                    'created_at' => $item->submitted_at ?? $item->created_at,
                ]);
        }

        $items = $pb->merge($wo)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function deviceToken(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:30'],
        ]);

        DB::table('mobile_api_tokens')
            ->where('id', '<>', $auth->token_id)
            ->where('fcm_token', $data['fcm_token'])
            ->update([
                'fcm_token' => null,
                'updated_at' => now(),
            ]);

        DB::table('mobile_api_tokens')->where('id', $auth->token_id)->update([
            'fcm_token' => $data['fcm_token'],
            'fcm_registered_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token notifikasi perangkat berhasil disimpan.',
        ]);
    }

    public function history(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($this->isEngineeringMobileUser($auth)) {
            $pb = $this->engineeringPbRows($auth, (int) $request->query('limit', 50))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'PB',
                    'nomor_pb' => $item->nomor_pb,
                    'tujuan_nama' => $item->tujuan_nama,
                    'jenis_pekerjaan' => $item->jenis_pekerjaan,
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'total_value' => (float) $item->total_value,
                    'jumlah_barang' => (int) $item->jumlah_barang,
                ]);

            $wo = $this->engineeringWoRows($auth, (int) $request->query('limit', 50))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'WO',
                    'nomor' => $item->nomor,
                    'judul' => $item->judul,
                    'deskripsi' => $item->deskripsi,
                    'status' => $item->status,
                    'progress_status' => $item->progress_status ?: $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'closed_at' => $item->closed_at,
                    'photos' => [],
                ]);

            return response()->json([
                'success' => true,
                'data' => $pb->merge($wo)
                    ->sortByDesc(fn ($item) => $item['closed_at'] ?? $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'])
                    ->values(),
            ]);
        }

        if ($auth->role === 'section_head') {
            $pb = $this->sectionPbVerificationHistoryRows($auth, (int) $request->query('limit', 50))
                ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                    'status' => $item->verification_status,
                    'verification_status' => $item->verification_status,
                    'verified_at' => $item->verified_at,
                    'rejected_at' => $item->rejected_at,
                    'verification_notes' => $item->verification_notes,
                    'history_type' => 'PB',
                ]));

            $wo = $this->sectionWorkOrderHistoryRows($auth, (int) $request->query('limit', 50));

            return response()->json([
                'success' => true,
                'data' => $pb->merge($wo)
                    ->sortByDesc(fn ($item) => $item['verified_at'] ?? $item['rejected_at'] ?? $item['closed_at'] ?? $item['created_at'])
                    ->values(),
            ]);
        }

        $pb = $this->pbHistoryQuery($auth)
            ->limit((int) $request->query('limit', 50))
            ->get()
            ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                'status' => $item->status,
                'approved_at' => $item->approved_at,
                'rejected_at' => $item->rejected_at,
                'approval_level_1_at' => $item->approval_level_1_at,
                'approval_level_2_at' => $item->approval_level_2_at,
                'rejection_reason' => $item->rejection_reason,
                'history_type' => 'PB',
            ]))
            ->values();

        $wo = collect();
        if ($auth->role === 'approval') {
            $wo = $this->woHistoryQuery($auth)
                ->limit((int) $request->query('limit', 50))
                ->get()
                ->map(fn ($item) => array_merge($this->woPayload($item), [
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'assigned_regu' => $item->assigned_regu,
                    'delegation_notes' => $item->delegation_notes ?? null,
                    'rejection_notes' => $item->rejection_notes,
                    'history_type' => 'WO',
                ]))
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => $pb->merge($wo)
                ->sortByDesc(fn ($item) => $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'])
                ->values(),
        ]);
    }

    public function webHistory(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        if ($this->isEngineeringMobileUser($auth)) {
            $pb = $this->engineeringPbRows($auth, (int) $request->query('limit', 80))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'PB',
                    'nomor_pb' => $item->nomor_pb,
                    'tujuan_nama' => $item->tujuan_nama,
                    'jenis_pekerjaan' => $item->jenis_pekerjaan,
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'total_value' => (float) $item->total_value,
                    'jumlah_barang' => (int) $item->jumlah_barang,
                ]);

            $wo = $this->engineeringWoRows($auth, (int) $request->query('limit', 80))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'WO',
                    'nomor' => $item->nomor,
                    'judul' => $item->judul,
                    'deskripsi' => $item->deskripsi,
                    'status' => $item->status,
                    'progress_status' => $item->progress_status ?: $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'closed_at' => $item->closed_at,
                    'photos' => collect(),
                ]);

            $items = $pb->merge($wo)
                ->sortByDesc(fn ($item) => $item['closed_at'] ?? $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'])
                ->values();
            $typeOptions = '<label class="field-wrap"><span>Tipe</span><select id="typeFilter" name="type"><option value="">Semua Tipe</option><option value="PB">PB</option><option value="WO">WO</option></select></label>';
            $subtitle = 'Riwayat PB dan WO Engineering.';
        } elseif ($auth->role === 'section_head') {
            $pb = $this->sectionPbVerificationHistoryRows($auth, (int) $request->query('limit', 80))
                ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                    'status' => $item->verification_status,
                    'verification_status' => $item->verification_status,
                    'verified_at' => $item->verified_at,
                    'rejected_at' => $item->rejected_at,
                    'verification_notes' => $item->verification_notes,
                    'history_type' => 'PB',
                ]));

            $wo = $this->sectionWorkOrderHistoryRows($auth, (int) $request->query('limit', 80));
            $items = $pb->merge($wo)
                ->sortByDesc(fn ($item) => $item['verified_at'] ?? $item['rejected_at'] ?? $item['closed_at'] ?? $item['created_at'])
                ->values();
            $typeOptions = '<label class="field-wrap"><span>Tipe</span><select id="typeFilter" name="type"><option value="">Semua Tipe</option><option value="PB">PB Verifikasi</option><option value="WO">WO</option></select></label>';
            $subtitle = 'Riwayat verifikasi PB dan pekerjaan WO.';
        } else {
            $pb = $this->pbHistoryQuery($auth)
                ->limit((int) $request->query('limit', 80))
                ->get()
                ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                    'status' => $item->status,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'approval_level_1_at' => $item->approval_level_1_at,
                    'approval_level_2_at' => $item->approval_level_2_at,
                    'rejection_reason' => $item->rejection_reason,
                    'history_type' => 'PB',
                ]));

            $wo = collect();
            if ($auth->role === 'approval') {
                $wo = $this->woHistoryQuery($auth)
                    ->limit((int) $request->query('limit', 80))
                    ->get()
                    ->map(fn ($item) => array_merge($this->woPayload($item), [
                        'approved_at' => $item->approved_at,
                        'rejected_at' => $item->rejected_at,
                        'assigned_regu' => $item->assigned_regu,
                        'delegation_notes' => $item->delegation_notes ?? null,
                        'rejection_notes' => $item->rejection_notes,
                        'history_type' => 'WO',
                    ]));
            }

            $items = $pb->merge($wo)
                ->sortByDesc(fn ($item) => $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'])
                ->values();
            $typeOptions = $auth->role === 'approval'
                ? '<label class="field-wrap"><span>Tipe</span><select id="typeFilter" name="type"><option value="">Semua Tipe</option><option value="PB">PB</option><option value="WO">WO</option></select></label>'
                : '';
            $subtitle = $auth->role === 'approval2' ? 'Riwayat approval PB harga item di atas 10 juta.' : 'Riwayat approval PB dan WO.';
        }

        $normalizeInputDate = function (?string $value) {
            if (!$value) {
                return '';
            }

            try {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return Carbon::parse($value)->format('Y-m-d');
                }

                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $match)) {
                    return Carbon::createFromFormat('d/m/Y', str_pad($match[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($match[2], 2, '0', STR_PAD_LEFT) . '/' . $match[3])->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                return '';
            }

            return '';
        };

        $selectedType = (string) $request->query('type', '');
        $selectedStatus = strtolower(trim((string) $request->query('status', '')));
        $selectedFrom = $normalizeInputDate($request->query('from'));
        $selectedTo = $normalizeInputDate($request->query('to'));
        $search = trim((string) $request->query('q', ''));

        $statusFilters = [
            '' => 'Semua Status',
            'waiting' => 'Menunggu',
            'approved' => 'Approved / Terverifikasi',
            'progress' => 'In Progress',
            'done' => 'Done',
            'rejected' => 'Rejected',
        ];
        if (!array_key_exists($selectedStatus, $statusFilters)) {
            $selectedStatus = '';
        }
        $statusOptionsHtml = collect($statusFilters)->map(function ($label, $value) use ($selectedStatus) {
            return '<option value="' . e($value) . '"' . ($selectedStatus === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        })->implode('');

        $matchesStatusFilter = function (?string $rawStatus, string $filter): bool {
            if ($filter === '') {
                return true;
            }

            $status = strtolower(trim((string) $rawStatus));

            return match ($filter) {
                'waiting' => in_array($status, ['pending', 'submitted', 'open', 'waiting', 'waiting_approval'], true),
                'approved' => in_array($status, ['approved', 'verified'], true),
                'progress' => in_array($status, ['progress', 'in_progress'], true),
                'done' => in_array($status, ['done', 'closed', 'completed'], true),
                'rejected' => in_array($status, ['rejected', 'reject'], true),
                default => true,
            };
        };

        $filteredItems = $items->filter(function ($item) use ($selectedType, $selectedStatus, $selectedFrom, $selectedTo, $search, $matchesStatusFilter) {
            $row = (array) $item;
            $type = $row['history_type'] ?? (isset($row['nomor_pb']) ? 'PB' : 'WO');
            $date = $row['closed_at'] ?? $row['verified_at'] ?? $row['approved_at'] ?? $row['rejected_at'] ?? $row['created_at'] ?? null;
            $dateKey = $date ? Carbon::parse($date)->timezone('Asia/Jakarta')->format('Y-m-d') : '';
            $number = $type === 'PB' ? ($row['nomor_pb'] ?? '') : ($row['nomor'] ?? '');
            $title = $type === 'PB' ? ($row['tujuan_nama'] ?? $row['untuk'] ?? '') : ($row['judul'] ?? '');
            $description = $type === 'PB' ? ($row['jenis_pekerjaan'] ?? '') : ($row['deskripsi'] ?? '');
            $status = $row['progress_status'] ?? $row['status'] ?? '';
            $text = strtolower(trim($type . ' ' . $number . ' ' . $title . ' ' . $description . ' ' . $status));

            return (!$selectedType || $type === $selectedType)
                && $matchesStatusFilter($status, $selectedStatus)
                && (!$selectedFrom || $dateKey >= $selectedFrom)
                && (!$selectedTo || $dateKey <= $selectedTo)
                && (!$search || str_contains($text, strtolower($search)));
        })->values();

        $totalBeforeFilter = $items->count();
        $cards = $filteredItems->map(fn ($item) => $this->mobileHistoryCard((array) $item))->implode('');
        if ($cards === '') {
            $cards = '<div class="empty">Tidak ada history sesuai filter.</div>';
        }

        $filterSummary = $filteredItems->count() . ' dari ' . $totalBeforeFilter . ' history';
        $typeOptionsHtml = $typeOptions ? str_replace('value="' . e($selectedType) . '"', 'value="' . e($selectedType) . '" selected', $typeOptions) : '';
        if ($typeOptionsHtml === $typeOptions && $selectedType) {
            $typeOptionsHtml = str_replace('<option value="' . e($selectedType) . '">', '<option value="' . e($selectedType) . '" selected>', $typeOptions);
        }

        $sectionHeadStockShortcut = $auth->role === 'section_head'
            ? '<section class="stock-shortcut">
                    <a href="' . e(url('/api/mobile/web/stock-sparepart')) . '">
                        <div>
                            <span>Stock Sparepart</span>
                            <strong>Cek ketersediaan barang</strong>
                        </div>
                        <b>Lihat</b>
                    </a>
                </section>'
            : '';

        $body = '
            ' . $sectionHeadStockShortcut . '
            <section class="toolbar">
                <button id="filterToggle" class="filter-toggle" type="button" aria-expanded="false">
                    <span>Filter history</span>
                    <strong>' . e($filterSummary) . '</strong>
                </button>
                <form id="filterPanel" class="filter-panel" method="get" action="' . e(url('/api/mobile/web/history')) . '" hidden>
                    <div class="filter-grid">
                        ' . $typeOptionsHtml . '
                        <label class="field-wrap">
                            <span>Status</span>
                            <select id="statusFilter" name="status" aria-label="Status">
                                ' . $statusOptionsHtml . '
                            </select>
                        </label>
                        <label class="field-wrap">
                            <span>Dari Tanggal</span>
                            <input id="fromDate" name="from" type="date" value="' . e($selectedFrom) . '" aria-label="Dari tanggal">
                        </label>
                        <label class="field-wrap">
                            <span>Sampai Tanggal</span>
                            <input id="toDate" name="to" type="date" value="' . e($selectedTo) . '" aria-label="Sampai tanggal">
                        </label>
                    </div>
                    <input id="searchBox" class="search" name="q" type="search" value="' . e($search) . '" placeholder="Cari nomor, status, atau riwayat">
                    <div class="filter-actions">
                        <button class="reset-filter" type="button" onclick="location.href=\'' . e(url('/api/mobile/web/history')) . '\'">Reset</button>
                        <button class="apply-filter" type="submit">Terapkan</button>
                    </div>
                </form>
            </section>
            <section id="historyList" class="list">' . $cards . '</section>
            <script>
                const filterToggle = document.getElementById("filterToggle");
                const filterPanel = document.getElementById("filterPanel");
                filterToggle.addEventListener("click", () => {
                    const isOpen = filterToggle.getAttribute("aria-expanded") === "true";
                    filterToggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
                    filterPanel.hidden = isOpen;
                });
            </script>
        ';

        return $this->mobileWebResponse('History', $body);
    }

    public function webStockSparepart(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || $auth->role !== 'section_head') {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Halaman stock sparepart hanya untuk Section Head.</div>', 403);
        }

        $search = trim((string) $request->query('q', ''));
        $cards = '<div class="empty">Ketik minimal 2 huruf nama atau kode sparepart.</div>';

        if (mb_strlen($search) >= 2) {
            $rows = collect($this->mobileErpStockRows($search));

            if ($rows->isEmpty()) {
                $cards = '<div class="empty">Stock tidak ditemukan untuk kata kunci tersebut.</div>';
            } else {
                $cards = $rows->map(function ($row) {
                    $qty = (float) ($row->end_qty ?? 0);
                    $status = $qty <= 0 ? 'Habis' : ($qty <= 5 ? 'Menipis' : 'Aman');
                    $statusClass = $qty <= 0 ? 'rejected' : ($status === 'Menipis' ? 'open' : 'done');
                    $name = trim((string) ($row->item_name ?? ''));

                    return '<article class="stock-result-card">
                        <div class="stock-result-main">
                            <div>
                                <span>Nama Sparepart</span>
                                <strong class="stock-result-name">' . e($name !== '' ? $name : '-') . '</strong>
                            </div>
                            <div>
                                <span>Kode</span>
                                <strong class="stock-result-code">' . e($row->code ?? '-') . '</strong>
                            </div>
                        </div>
                        <div class="stock-result-grid">
                            <div><span>Stok</span><strong>' . e($this->mobileQty($qty)) . '</strong></div>
                            <div><span>Satuan</span><strong>' . e($row->uom ?? 'PCS') . '</strong></div>
                            <div><span>Status</span><strong class="status ' . e($statusClass) . '">' . e($status) . '</strong></div>
                        </div>
                    </article>';
                })->implode('');
            }
        }

        $body = '
            <section class="toolbar stock-toolbar">
                <form id="stockSearchForm" method="get" action="' . e(url('/api/mobile/web/stock-sparepart')) . '">
                    <input id="stockSearch" class="search" name="q" type="search" value="' . e($search) . '" placeholder="Cari kode / nama sparepart..." autocomplete="off">
                    <div class="filter-actions">
                        <button class="reset-filter" type="button" onclick="location.href=\'' . e(url('/api/mobile/web/stock-sparepart')) . '\'">Reset</button>
                        <button class="apply-filter" type="submit">Cari</button>
                    </div>
                </form>
            </section>
            <section class="list">' . $cards . '</section>
            <script>
                (function () {
                    var stockInput = document.getElementById("stockSearch");
                    var stockForm = document.getElementById("stockSearchForm");
                    var stockTimer;
                    var duplicateHeads = document.querySelectorAll("section.toolbar + .stock-head, .stock-head + .stock-head");
                    Array.prototype.forEach.call(duplicateHeads, function (node) {
                        if (node && node.parentNode) node.parentNode.removeChild(node);
                    });
                    if (stockInput && stockForm) {
                        stockInput.addEventListener("input", function () {
                            clearTimeout(stockTimer);
                            stockTimer = setTimeout(function () {
                                var value = (stockInput.value || "").replace(/^\s+|\s+$/g, "");
                                if (value.length === 0 || value.length >= 2) stockForm.submit();
                            }, 500);
                        });
                    }
                })();
            </script>
        ';

        return $this->mobileWebResponse('Stock Sparepart', $body);
    }

    private function mobileErpStockRows(string $search): array
    {
        $endQtyExpression = "(
            COALESCE(sa.menge, 0)
            + COALESCE(pur.pur_qty, 0)
            + COALESCE(ret.ret_qty, 0)
            + COALESCE(kpl.kpl_qty, 0)
            - COALESCE(usea.use_qty, 0)
            - COALESCE(nrb.nrb_qty, 0)
            - COALESCE(kmn.kmn_qty, 0)
            - COALESCE(tkl.tkl_qty, 0)
            + COALESCE(tms.tms_qty, 0)
            - COALESCE(los.los_qty, 0)
        )";

        $sparepartScope = $this->sparepartMaterialSql('m');
        $sql = "
            WITH transaksi_periode AS (
                SELECT matnr, lsloc, lgnum, bwart, menge, wrbtr, cpudt, saknr, lvorm, ypotp
                FROM PUBLIC.tb_skb008_2dmseg
                WHERE werks = 1
                  AND cpudt BETWEEN DATE '2026-01-01' AND CURRENT_DATE
            ),
            stok_awal AS (
                SELECT matnr, lgpla AS lsloc, SUBSTRING(lgpla FROM 1 FOR 3) AS lgnum, menge, dmbtr
                FROM PUBLIC.tb_skb111_1mbgni
                WHERE werks = 1
                  AND mjahr = 2026
                  AND lfmon = 1
                  AND ypotp = 'YPO2'
            ),
            lokasi_item AS (
                SELECT matnr, lgnum, lsloc FROM transaksi_periode
                UNION
                SELECT matnr, lgnum, lsloc FROM stok_awal
            ),
            pur_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS pur_qty
                FROM transaksi_periode
                WHERE bwart = '101'
                GROUP BY matnr, lsloc
            ),
            ret_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS ret_qty
                FROM transaksi_periode
                WHERE bwart = '921'
                GROUP BY matnr, lsloc
            ),
            kpl_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS kpl_qty
                FROM transaksi_periode
                WHERE bwart = '931'
                GROUP BY matnr, lsloc
            ),
            use_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS use_qty
                FROM transaksi_periode
                WHERE bwart = '201' AND saknr <> 7755
                GROUP BY matnr, lsloc
            ),
            nrb_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS nrb_qty
                FROM transaksi_periode
                WHERE bwart = '122'
                GROUP BY matnr, lsloc
            ),
            kmn_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS kmn_qty
                FROM transaksi_periode
                WHERE bwart = '941'
                GROUP BY matnr, lsloc
            ),
            tkl_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS tkl_qty
                FROM transaksi_periode
                WHERE bwart = '981'
                GROUP BY matnr, lsloc
            ),
            tms_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS tms_qty
                FROM transaksi_periode
                WHERE bwart = '971'
                GROUP BY matnr, lsloc
            ),
            los_agg AS (
                SELECT matnr, lsloc, SUM(menge) AS los_qty
                FROM transaksi_periode
                WHERE bwart = '201' AND lvorm = 'U' AND saknr = 7755
                GROUP BY matnr, lsloc
            ),
            all_items AS (
                SELECT DISTINCT
                    m.id_items,
                    m.itemno,
                    TRIM(m.code) AS code,
                    m.item_name,
                    m.meins,
                    li.lsloc
                FROM lokasi_item li
                JOIN PUBLIC.tb_skb080_1mmara m ON li.matnr = m.id_items
                WHERE {$sparepartScope}
                  AND (m.code ILIKE ? OR m.item_name ILIKE ? OR m.itemno ILIKE ?)
            )
            SELECT
                ai.code,
                MAX(ai.item_name) AS item_name,
                ai.meins AS uom,
                SUM({$endQtyExpression}) AS end_qty
            FROM all_items ai
            LEFT JOIN stok_awal sa ON sa.matnr = ai.id_items AND sa.lsloc = ai.lsloc
            LEFT JOIN pur_agg pur ON pur.matnr = ai.id_items AND pur.lsloc = ai.lsloc
            LEFT JOIN ret_agg ret ON ret.matnr = ai.id_items AND ret.lsloc = ai.lsloc
            LEFT JOIN kpl_agg kpl ON kpl.matnr = ai.id_items AND kpl.lsloc = ai.lsloc
            LEFT JOIN use_agg usea ON usea.matnr = ai.id_items AND usea.lsloc = ai.lsloc
            LEFT JOIN nrb_agg nrb ON nrb.matnr = ai.id_items AND nrb.lsloc = ai.lsloc
            LEFT JOIN kmn_agg kmn ON kmn.matnr = ai.id_items AND kmn.lsloc = ai.lsloc
            LEFT JOIN tkl_agg tkl ON tkl.matnr = ai.id_items AND tkl.lsloc = ai.lsloc
            LEFT JOIN tms_agg tms ON tms.matnr = ai.id_items AND tms.lsloc = ai.lsloc
            LEFT JOIN los_agg los ON los.matnr = ai.id_items AND los.lsloc = ai.lsloc
            GROUP BY ai.code, ai.meins
            ORDER BY ai.code ASC
            LIMIT 20
        ";

        try {
            $keyword = '%' . $search . '%';

            return DB::connection('pgsql2')->select($sql, [$keyword, $keyword, $keyword]);
        } catch (\Throwable $e) {
            Log::warning('Mobile ERP stock search error: ' . $e->getMessage(), ['q' => $search]);

            return [];
        }
    }

    public function webPbDetail(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses detail PB.</div>', 403);
        }

        if ($this->isEngineeringMobileUser($auth)) {
            $pb = $this->engineeringPbRows($auth, (int) $request->query('limit', 50))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'PB',
                    'nomor_pb' => $item->nomor_pb,
                    'tujuan_nama' => $item->tujuan_nama,
                    'jenis_pekerjaan' => $item->jenis_pekerjaan,
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'total_value' => (float) $item->total_value,
                    'jumlah_barang' => (int) $item->jumlah_barang,
                ]);

            $wo = $this->engineeringWoRows($auth, (int) $request->query('limit', 50))
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'history_type' => 'WO',
                    'nomor' => $item->nomor,
                    'judul' => $item->judul,
                    'deskripsi' => $item->deskripsi,
                    'status' => $item->status,
                    'progress_status' => $item->progress_status ?: $item->status,
                    'created_at' => $item->created_at,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'closed_at' => $item->closed_at,
                    'photos' => [],
                ]);

            return response()->json([
                'success' => true,
                'data' => $pb->merge($wo)
                    ->sortByDesc(fn ($item) => $item['closed_at'] ?? $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'])
                    ->values(),
            ]);
        }

        if ($auth->role === 'section_head') {
            $pb = $this->sectionPbVerificationHistoryRows($auth, 200)->firstWhere('id', $id)
                ?: $this->pbBaseQuery($auth)->where('trBPB.id', $id)->first();
        } elseif ($this->isEngineeringMobileUser($auth)) {
            $pb = $this->engineeringPbRows($auth, 200)->firstWhere('id', $id);
        } else {
            $pb = $this->pbHistoryQuery($auth)->where('trBPB.id', $id)->first()
                ?: $this->pbListQuery($auth)->where('trBPB.id', $id)->first();
        }

        if (!$pb) {
            return $this->mobileWebResponse('PB tidak ditemukan', '<div class="empty">Data PB tidak ditemukan.</div>', 404);
        }

        $pbTujuan = $pb->tujuan_nama ?? $pb->tujuan ?? $pb->untuk ?? '-';
        $pbIsBackdate = (bool) ($pb->is_backdate ?? false);
        $pbBackdateBlock = $pbIsBackdate
            ? '<div class="note backdate-note"><span>PB Backdate</span>' . e($pb->backdate_reason ?: '-') . '<small>Diinput: ' . e($this->mobileDateTime($pb->backdate_created_at ?? $pb->created_at ?? null)) . '</small></div>'
            : '';

        $details = DB::table('trBPBDetail')
            ->where('trBPB_id', $id)
            ->orWhere('trbpb_id', $id)
            ->orderBy('id')
            ->get(['nama_barang', 'jumlah', 'satuan', 'unit_price', 'total_price', 'keterangan', 'is_high_value']);
        $pbTotalValue = (float) ($pb->total_value ?? $details->sum(fn ($item) => (float) ($item->total_price ?? 0)));

        $rows = $details->map(function ($item) {
            $high = (bool) ($item->is_high_value ?? false) ? '<span class="mini danger">High value</span>' : '';

            return '<div class="item-row">
                <div>
                    <strong>' . e($item->nama_barang) . '</strong>
                    <span>' . e($this->mobileQty($item->jumlah) . ' ' . $item->satuan) . '</span>
                    ' . $high . '
                </div>
                <div class="price">' . e($this->mobileRupiah($item->total_price)) . '</div>
            </div>';
        })->implode('');

        $status = $pb->status ?? 'pending';
        $approvedAt = $pb->approved_at ?? $pb->approval_level_2_at ?? $pb->approval_level_1_at ?? null;
        $approvalCurrentLevel = (int) (($pb->approval_current_level ?? null)
            ?: (DB::table('trBPB')->where('id', $id)->value('approval_current_level') ?? self::LEVEL_ONE));
        $pbStatus = strtolower((string) $status);
        $verificationStatus = strtolower((string) ($pb->verification_status ?? ''));
        $pbActionBlock = '';
        if ($auth->role === 'section_head'
            && $pbStatus === 'verification'
            && $verificationStatus === 'pending'
            && (int) ($pb->verification_section_head_id ?? 0) === (int) $auth->id) {
            $pbActionBlock = '
                <div class="mobile-action-panel">
                    <h3>Verifikasi Section Head</h3>
                    <p>Pastikan data PB dan daftar barang sudah sesuai sebelum dikirim ke Approval L1.</p>
                    <div id="pbRejectPanel" class="mobile-reject-panel" hidden>
                        <label for="pbRejectReason">Catatan Penolakan</label>
                        <textarea id="pbRejectReason" class="mobile-action-field" rows="3" placeholder="Wajib diisi jika PB ditolak"></textarea>
                    </div>
                    <div class="mobile-action-buttons">
                        <button type="button" class="approve" id="pbApproveButton" onclick="approvePbDetail(' . (int) $id . ', this)">Verifikasi PB</button>
                        <button type="button" class="reject" id="pbRejectButton" onclick="rejectPbDetail(' . (int) $id . ', this)">Tolak PB</button>
                    </div>
                </div>
            ';
        } elseif ($pbStatus === 'pending'
            && (($auth->role === 'approval' && $approvalCurrentLevel === self::LEVEL_ONE)
                || ($auth->role === 'approval2' && $approvalCurrentLevel === self::LEVEL_TWO))) {
            $pbActionBlock = '
                <div class="mobile-action-panel">
                    <h3>Keputusan Approval</h3>
                    <p>Approve bisa langsung diproses. Catatan wajib diisi hanya saat reject.</p>
                    <label for="pbApprovalNote">Catatan Approval (opsional)</label>
                    <textarea id="pbApprovalNote" class="mobile-action-field" rows="3" placeholder="Tambahkan catatan jika diperlukan"></textarea>
                    <div id="pbRejectPanel" class="mobile-reject-panel" hidden>
                        <label for="pbRejectReason">Catatan Reject</label>
                        <textarea id="pbRejectReason" class="mobile-action-field" rows="3" placeholder="Wajib diisi jika PB ditolak"></textarea>
                    </div>
                    <div class="mobile-action-buttons">
                        <button type="button" class="approve" id="pbApproveButton" onclick="approvePbDetail(' . (int) $id . ', this)">Approve PB</button>
                        <button type="button" class="reject" id="pbRejectButton" onclick="rejectPbDetail(' . (int) $id . ', this)">Reject PB</button>
                    </div>
                </div>
            ';
        }

        $body = '
            <article class="detail-card">
                <div class="detail-head">
                    <div>
                        <p class="eyebrow">Permintaan Barang</p>
                        <h1>' . e($pb->nomor_pb) . '</h1>
                    </div>
                    <div class="status-stack">
                        ' . ($pbIsBackdate ? '<span class="status pending">Backdate</span>' : '') . '
                        <span class="status ' . e($this->mobileStatusClass($status)) . '">' . e($this->mobileStatusLabelForType($status, 'PB')) . '</span>
                    </div>
                </div>
                <div class="meta-grid">
                    <div><span>Tujuan</span><strong>' . e($pbTujuan ?: '-') . '</strong></div>
                    <div><span>Jenis</span><strong>' . e($pb->jenis_pekerjaan ?: '-') . '</strong></div>
                    <div><span>Tanggal PB</span><strong>' . e($this->mobileDate($pb->tanggal_permintaan)) . '</strong></div>
                    <div><span>Diperlukan</span><strong>' . e($this->mobileDate($pb->tanggal_diperlukan)) . '</strong></div>
                </div>
                <div class="total-box">
                    <span>Total nilai</span>
                    <strong>' . e($this->mobileRupiah($pbTotalValue)) . '</strong>
                </div>
                ' . $pbBackdateBlock . '
                <div class="section-title">Daftar Barang</div>
                <div class="item-list">' . ($rows ?: '<div class="empty small">Tidak ada item.</div>') . '</div>
                <div class="timeline">
                    ' . (($pb->verification_status ?? '') ? '<div><span>Verifikasi SH</span><strong>' . e($this->mobileDateTime($pb->verified_at ?? $pb->rejected_at ?? null)) . '</strong></div>' : '') . '
                    <div><span>Diajukan</span><strong>' . e($this->mobileDateTime($pb->created_at)) . '</strong></div>
                    <div><span>Approval L1</span><strong>' . e($this->mobileDateTime($pb->approval_level_1_at ?? null)) . '</strong></div>
                    <div><span>Approval L2</span><strong>' . e($this->mobileDateTime($pb->approval_level_2_at ?? null)) . '</strong></div>
                    <div><span>Final</span><strong>' . e($this->mobileDateTime($approvedAt ?? $pb->rejected_at ?? null)) . '</strong></div>
                </div>
                ' . (($pb->verification_notes ?? '') !== '' ? '<div class="note"><span>Catatan verifikasi</span>' . e($pb->verification_notes) . '</div>' : '') . '
                ' . (($pb->rejection_reason ?? '') !== '' ? '<div class="note danger-note"><span>Catatan ditolak</span>' . e($pb->rejection_reason) . '</div>' : '') . '
                ' . $pbActionBlock . '
            </article>
        ';

        return $this->mobileWebResponse('Detail PB', $body);
    }

    public function webWoDetail(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->mobileWebResponse('Unauthorized', '<div class="empty">Sesi tidak valid. Silakan login ulang.</div>', 401);
        }

        $query = DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.id', $id)
            ->select('trWorkOrder.*', 'creator.name as created_by_name', 'creator.email as created_by_email');

        if ($auth->role === 'section_head') {
            $query->where('trWorkOrder.assigned_regu', $auth->name);
        } elseif ($auth->role === 'approval') {
            $query->where(function ($scope) use ($auth) {
                $scope->where('trWorkOrder.status', 'submitted')
                    ->orWhere('trWorkOrder.approved_by', $auth->id)
                    ->orWhere('trWorkOrder.rejected_by', $auth->id);
            });
        } elseif ($this->isEngineeringMobileUser($auth)) {
            $query->where('trWorkOrder.created_by', $auth->id);
        } else {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">Approval L2 tidak punya akses detail WO.</div>', 403);
        }

        $wo = $query->first();
        if (!$wo) {
            return $this->mobileWebResponse('WO tidak ditemukan', '<div class="empty">Data WO tidak ditemukan.</div>', 404);
        }

        if ($request->query('preview') === 'document') {
            return $this->mobileWoDocumentPreview($request, $wo);
        }

        $status = $wo->progress_status ?: $wo->status;
        $token = $request->bearerToken()
            ?: (string) $request->query('token', '')
            ?: (string) $request->input('token', '');
        $fileUrl = $wo->file_path
            ? url('/api/mobile/web/wo/' . (int) $wo->id) . '?' . http_build_query(array_filter([
                'preview' => 'document',
                'token' => $token !== '' ? $token : null,
            ], fn ($value) => $value !== null && $value !== ''))
            : '';
        $fileBlock = $fileUrl
            ? '<a class="file-link" href="' . e($fileUrl) . '" target="_blank" rel="noopener">Lihat dokumen: ' . e($wo->file_name ?: basename($wo->file_path)) . '</a>'
            : '<div class="empty small">Tidak ada file lampiran.</div>';

        $photos = $this->workOrderPhotos((int) $wo->id);
        $photoBlock = $photos->map(function ($photo, $idx) {
            $label = 'Foto ' . ($idx + 1);

            return '<a class="photo-card" href="' . e($photo['url']) . '">
                <img src="' . e($photo['url']) . '" alt="' . e($label) . '" loading="lazy">
                <div>
                    <strong>' . e($label) . '</strong>
                    <span>' . e($photo['uploaded_by_name'] ?: '-') . ' - ' . e($this->mobileDateTime($photo['created_at'])) . '</span>
                    <small>' . e($photo['notes'] ?: 'Foto hasil pekerjaan') . '</small>
                </div>
            </a>';
        })->implode('');
        $woActionBlock = '';
        if ($this->isEngineeringMobileUser($auth) && strtolower((string) ($wo->status ?? '')) === 'submitted') {
            $woActionBlock = '
                <div class="mobile-action-panel">
                    <h3>WO Sudah Disubmit</h3>
                    <p>Jika ada salah input atau double posting, unpost WO ini untuk mengembalikannya ke Draft sebelum Approval L1 memproses.</p>
                    <div class="mobile-action-buttons stacked">
                        <button type="button" class="reject" onclick="unpostWoDetail(' . (int) $id . ', this)">Unpost WO</button>
                    </div>
                </div>
            ';
        }

        if ($auth->role === 'approval' && strtolower((string) ($wo->status ?? '')) === 'submitted') {
            $pelaksanaOptions = DB::table('mtWorkOrderPelaksana')
                ->where('is_active', true)
                ->orderBy('nama')
                ->pluck('nama')
                ->map(fn ($nama) => '<option value="' . e($nama) . '">' . e($nama) . '</option>')
                ->implode('');

            $woActionBlock = '
                <div class="mobile-action-panel">
                    <h3>Approve & Assign WO</h3>
                    <p>Pilih pelaksana sebelum WO dikirim ke Section Head. Catatan delegasi dapat diisi jika diperlukan.</p>
                    <label for="woPelaksana">Pelaksana</label>
                    <select id="woPelaksana" class="mobile-action-field">
                        <option value="">Pilih pelaksana</option>
                        ' . $pelaksanaOptions . '
                    </select>
                    <label for="woDelegationNotes">Catatan Delegasi (opsional)</label>
                    <textarea id="woDelegationNotes" class="mobile-action-field" rows="3" placeholder="Contoh: cek panel MCC dan update kondisi akhir"></textarea>
                    <div id="woRejectPanel" class="mobile-reject-panel" hidden>
                        <label for="woRejectReason">Catatan Reject</label>
                        <textarea id="woRejectReason" class="mobile-action-field" rows="3" placeholder="Wajib diisi jika WO ditolak"></textarea>
                    </div>
                    <div class="mobile-action-buttons">
                        <button type="button" class="approve" onclick="approveWoDetail(' . (int) $id . ', this)">Approve + Assign</button>
                        <button type="button" class="reject" onclick="rejectWoDetail(' . (int) $id . ', this)">Reject WO</button>
                    </div>
                </div>
            ';
        }

        if ($auth->role === 'section_head'
            && in_array(strtolower((string) ($wo->progress_status ?: 'open')), ['open', 'progress', 'hold'], true)) {
            $progressStatus = strtolower((string) ($wo->progress_status ?: 'open'));
            if ($progressStatus === 'open') {
                $woActionBlock = '
                    <div class="mobile-action-panel">
                        <h3>Mulai Pekerjaan</h3>
                        <p>Klik In Progress saat pekerjaan mulai dikerjakan.</p>
                        <div class="mobile-action-buttons stacked">
                            <button type="button" class="approve" onclick="startSectionWoProgress(' . (int) $id . ', this)">In Progress</button>
                        </div>
                    </div>
                ';
            } elseif ($progressStatus === 'hold') {
                $woActionBlock = '
                    <div class="mobile-action-panel">
                        <h3>WO Sedang Hold</h3>
                        <p>Resume pekerjaan sebelum upload foto hasil atau menyelesaikan WO.</p>
                        <div class="mobile-action-buttons stacked">
                            <button type="button" class="approve" onclick="resumeSectionWo(' . (int) $id . ', this)">Resume ke In Progress</button>
                        </div>
                    </div>
                ';
            } else {
                $doneButton = $photos->count() > 0
                    ? '<button type="button" class="reject" onclick="doneSectionWo(' . (int) $id . ', this)">Done WO</button>'
                    : '<button type="button" class="reject" disabled>Upload foto untuk mengaktifkan Done</button>';

                $woActionBlock = '
                    <div class="mobile-action-panel">
                        <h3>Update Hasil Pekerjaan</h3>
                        <p>Upload dokumentasi hasil pekerjaan sebelum WO dapat diselesaikan.</p>
                        <label for="sectionWoPhotos">Foto Hasil Pekerjaan</label>
                        <input id="sectionWoPhotos" class="mobile-action-field" type="file" accept="image/*" multiple>
                        <label for="sectionWoNotes">Catatan / Deskripsi Hasil</label>
                        <textarea id="sectionWoNotes" class="mobile-action-field" rows="3" placeholder="Contoh: pekerjaan selesai, panel sudah normal"></textarea>
                        <div class="mobile-action-buttons stacked">
                            <button type="button" class="approve" disabled>In Progress</button>
                            <button type="button" class="reject" onclick="holdSectionWo(' . (int) $id . ', this)">Hold WO</button>
                            <button type="button" class="approve" onclick="uploadSectionWoPhotos(' . (int) $id . ', this)">Upload Foto</button>
                            ' . $doneButton . '
                        </div>
                    </div>
                ';
            }
        }

        $body = '
            <article class="detail-card">
                <div class="detail-head">
                    <div>
                        <p class="eyebrow">Work Order</p>
                        <h1>' . e($wo->nomor) . '</h1>
                    </div>
                    <span class="status ' . e($this->mobileStatusClass($status)) . '">' . e($this->mobileStatusLabel($status)) . '</span>
                </div>
                <div class="content-block">
                    <h2>' . e($wo->judul ?: '-') . '</h2>
                    <p>' . e($wo->deskripsi ?: '-') . '</p>
                </div>
                <div class="meta-grid">
                    <div><span>Dibuat oleh</span><strong>' . e($wo->created_by_name ?: '-') . '</strong></div>
                    <div><span>Pelaksana</span><strong>' . e($wo->assigned_regu ?: '-') . '</strong></div>
                    <div><span>Dibuat</span><strong>' . e($this->mobileDateTime($wo->created_at)) . '</strong></div>
                    <div><span>Assign</span><strong>' . e($this->mobileDateTime($wo->assigned_at ?? null)) . '</strong></div>
                    <div><span>Hold</span><strong>' . e($this->mobileDateTime($wo->hold_at ?? null)) . '</strong></div>
                    <div><span>Resume</span><strong>' . e($this->mobileDateTime($wo->resumed_at ?? null)) . '</strong></div>
                </div>
                ' . (($wo->delegation_notes ?? '') !== '' ? '<div class="note"><span>Catatan Delegasi</span>' . e($wo->delegation_notes) . '</div>' : '') . '
                ' . (($wo->progress_notes ?? '') !== '' ? '<div class="note success-note"><span>Deskripsi Pekerjaan Selesai</span>' . e($wo->progress_notes) . '</div>' : '') . '
                <div class="section-title">Lampiran WO</div>
                ' . $fileBlock . '
                <div class="section-title">Foto Hasil Pekerjaan (' . $photos->count() . ')</div>
                <div class="photo-list">' . ($photoBlock ?: '<div class="empty small">Belum ada foto hasil pekerjaan.</div>') . '</div>
                ' . $woActionBlock . '
            </article>
        ';

        return $this->mobileWebResponse('Detail WO', $body);
    }

    private function mobileWoDocumentPreview(Request $request, object $wo)
    {
        $token = $request->bearerToken()
            ?: (string) $request->query('token', '')
            ?: (string) $request->input('token', '');
        $documentUrl = url('/api/mobile/wo/' . (int) $wo->id . '/document') . ($token !== '' ? '?' . http_build_query(['token' => $token]) : '');
        $fileName = $wo->file_name ?: basename((string) $wo->file_path);
        $mimeType = $wo->file_path && Storage::disk('public')->exists($wo->file_path)
            ? (Storage::disk('public')->mimeType($wo->file_path) ?: '')
            : '';
        $previewBlock = str_starts_with((string) $mimeType, 'image/')
            ? '<div class="document-frame image-frame"><img src="' . e($documentUrl) . '" alt="' . e($fileName) . '"></div>'
            : '<div class="document-frame"><iframe src="' . e($documentUrl) . '" title="' . e($fileName) . '"></iframe></div>';
        $helperText = str_starts_with((string) $mimeType, 'image/')
            ? 'Lampiran gambar ditampilkan langsung dari server internal e-Request.'
            : 'Lampiran PDF ditampilkan dalam WebView jika perangkat mendukung. Jika pratinjau kosong, gunakan tombol buka dokumen.';

        $body = '
            <article class="detail-card document-preview">
                <div class="content-block">
                    <h2>' . e($fileName) . '</h2>
                    <p>' . e($helperText) . '</p>
                </div>
                ' . $previewBlock . '
                <div class="document-actions">
                    <a class="file-link" href="' . e($documentUrl) . '" target="_blank" rel="noopener">Buka dokumen</a>
                    <a class="file-link secondary-action" href="' . e(url('/api/mobile/web/wo/' . (int) $wo->id) . ($token !== '' ? '?' . http_build_query(['token' => $token]) : '')) . '">Kembali ke detail WO</a>
                </div>
            </article>
        ';

        return $this->mobileWebResponse('Lampiran WO', $body);
    }

    public function webEngineeringHome(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses mobile engineering.</div>', 403);
        }

        $pbBase = DB::table('trBPB')->where('user_id', $auth->id);
        $woBase = DB::table('trWorkOrder')->where('created_by', $auth->id);
        $pbTotal = (clone $pbBase)->count();
        $pbPending = (clone $pbBase)->where('status', 'pending')->count();
        $woTotal = (clone $woBase)->count();
        $woProgress = (clone $woBase)->where('status', 'approved')->whereIn('progress_status', ['open', 'progress', 'hold'])->count();
        $pbUrl = url('/api/mobile/web/engineering/pb');
        $pbPendingUrl = url('/api/mobile/web/engineering/pb') . '?status=pending';
        $woUrl = url('/api/mobile/web/engineering/wo');
        $woProgressUrl = url('/api/mobile/web/engineering/wo') . '?status=progress';
        $metric = function (string $label, int $value, string $class, string $hint, string $url) {
            return '<a class="queue-metric ' . e($class) . '" href="' . e($url) . '">
                <span>' . e($label) . '</span>
                <strong>' . e((string) $value) . '</strong>
                <small>' . e($hint) . '</small>
            </a>';
        };

        $body = '<section class="approval-dashboard factory-dashboard">
            <section class="factory-hero">
                <div>
                    <span>Admin Engineering</span>
                    <h2>Engineering Apps</h2>
                    <p>' . e((string) ($auth->username ?? $auth->name ?? 'adm-engineering')) . '</p>
                </div>
                <strong>' . e((string) ($pbTotal + $woTotal)) . '</strong>
                <small>total</small>
            </section>
            <section class="queue-card">
                <div class="queue-title">
                    <div>
                        <h2>Aktivitas Engineering</h2>
                        <p>Ringkasan PB dan WO yang dibuat dari mobile.</p>
                    </div>
                </div>
                <div class="factory-metric-grid engineering-metric-grid">
                    ' . $metric('Total PB', $pbTotal, 'progress', 'Semua permintaan', $pbUrl) . '
                    ' . $metric('PB Pending', $pbPending, 'waiting', 'Menunggu approval', $pbPendingUrl) . '
                    ' . $metric('Total WO', $woTotal, 'done', 'Semua work order', $woUrl) . '
                    ' . $metric('WO Progress', $woProgress, 'approved', 'Open / progress', $woProgressUrl) . '
                </div>
            </section>
            <section class="queue-card section-action-card">
                <div class="engineering-action-grid">
                    <a class="primary-action" href="' . e($pbUrl) . '">Permintaan Barang</a>
                    <a class="primary-action secondary-action" href="' . e($woUrl) . '">Work Order</a>
                </div>
            </section>
        </section>';

        return $this->mobileWebResponse('Engineering Mobile', $body);
        $body = '
            <section class="eng-hero">
                <span>e-Request Mobile</span>
                <h1>Admin Engineering</h1>
                <p>Ajukan PB dan WO, lalu pantau status approval serta progress pekerjaan.</p>
            </section>
            <section class="eng-grid">
                <a class="eng-tile" href="' . e(url('/api/mobile/web/engineering/pb')) . '">
                    <span>Permintaan Barang</span>
                    <strong>' . (clone $pbBase)->count() . '</strong>
                    <small>Pending ' . (clone $pbBase)->where('status', 'pending')->count() . ' · Approved ' . (clone $pbBase)->where('status', 'approved')->count() . '</small>
                </a>
                <a class="eng-tile accent" href="' . e(url('/api/mobile/web/engineering/wo')) . '">
                    <span>Work Order</span>
                    <strong>' . (clone $woBase)->count() . '</strong>
                    <small>Submitted ' . (clone $woBase)->where('status', 'submitted')->count() . ' · Progress ' . (clone $woBase)->where('status', 'approved')->whereIn('progress_status', ['open', 'progress', 'hold'])->count() . '</small>
                </a>
            </section>
        ';

        return $this->mobileWebResponse('Engineering Mobile', $body);
    }

    public function webEngineeringPb(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses mobile engineering.</div>', 403);
        }

        $statusFilter = strtolower((string) $request->query('status', ''));
        $pbs = $this->engineeringPbRows($auth, 80);
        if (in_array($statusFilter, ['pending', 'approved', 'rejected', 'verification', 'completed', 'in_progress'], true)) {
            $pbs = $pbs->filter(fn ($pb) => strtolower((string) $pb->status) === $statusFilter)->values();
        }
        $cards = $pbs->map(fn ($pb) => $this->engineeringPbCard($pb))->implode('') ?: '<div class="empty">Belum ada PB.</div>';
        $pbHeading = $statusFilter === 'pending' ? 'PB Pending' : 'Permintaan Barang';

        $body = '
            <section class="eng-tabs">
                <a class="active" href="' . e(url('/api/mobile/web/engineering/pb')) . '">PB</a>
                <a href="' . e(url('/api/mobile/web/engineering/wo')) . '">WO</a>
            </section>
            <section class="form-card compact-action">
                <a class="filter-toggle" href="' . e(url('/api/mobile/web/engineering/pb/create')) . '"><span>Buat PB Baru</span><strong>Mulai</strong></a>
            </section>
            <section class="history-filter-summary"><strong>' . e($pbHeading) . '</strong><span>' . e((string) $pbs->count()) . ' data</span></section>
            <section class="date-filter-row">
                <label class="field-wrap"><span>Dari</span><input id="pbDateFrom" data-date-filter="from" type="date" onchange="filterCards(document.getElementById(\'pbSearch\').value)"></label>
                <label class="field-wrap"><span>Sampai</span><input id="pbDateTo" data-date-filter="to" type="date" onchange="filterCards(document.getElementById(\'pbSearch\').value)"></label>
            </section>
            <section class="toolbar"><input id="pbSearch" class="search" type="search" placeholder="Cari nomor PB, status, tujuan..." oninput="filterCards(this.value)"></section>
            <section class="list">' . $cards . '</section>
            <script>' . $this->engineeringWebScript($request->bearerToken()) . '
            </script>
        ';

        return $this->mobileWebResponse('PB Engineering', $body);
    }

    public function webEngineeringPbCreate(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses mobile engineering.</div>', 403);
        }

        $nomor = $this->generateMobilePbNumber();
        $sectionHeads = $this->mobileSectionHeads()
            ->map(fn ($user) => '<option value="' . e($user->id) . '">' . e($user->name) . ' (' . e($user->username ?? '-') . ')</option>')
            ->implode('');
        $targetSearchUrl = url('/api/mobile/engineering/search/targets');
        $itemSearchUrl = url('/api/mobile/engineering/search/items');
        $woSearchUrl = url('/api/mobile/engineering/search/work-orders');

        $body = '
            <section class="form-card create-form">
                <div class="form-number"><span>Nomor PB</span><strong>' . e($nomor) . '</strong></div>
                <form id="pbForm" novalidate onsubmit="return window.submitEngineeringPb ? window.submitEngineeringPb(event) : false;">
                    <input type="hidden" name="nomor_pb" value="' . e($nomor) . '">
                    <div class="form-section">
                        <div class="form-section-title">Informasi Permintaan</div>
                        <label class="field-wrap"><span>Untuk</span><select name="untuk" id="pbUntuk" onchange="switchTarget()"><option value="mesin">Mesin</option><option value="bangunan">Bangunan</option></select></label>
                        <div class="field-wrap target mesin-target">
                            <span>Pilih Mesin</span>
                            <input class="target-search" id="pbMesinSearch" type="search" placeholder="Ketik minimal 2 huruf nama / kode mesin..." autocomplete="off" oninput="searchTarget(\'mesin\', this.value)">
                            <input type="hidden" name="mesin_id" id="pbMesinId">
                            <div class="suggestion-list" id="pbMesinResults"></div>
                        </div>
                        <div class="field-wrap target bangunan-target" hidden>
                            <span>Pilih Bangunan</span>
                            <input class="target-search" id="pbBangunanSearch" type="search" placeholder="Ketik minimal 2 huruf nama / kode bangunan..." autocomplete="off" oninput="searchTarget(\'bangunan\', this.value)">
                            <input type="hidden" name="bangunan_id" id="pbBangunanId" disabled>
                            <div class="suggestion-list" id="pbBangunanResults"></div>
                        </div>
                        <label class="field-wrap"><span>Tanggal Diperlukan</span><input name="tanggal_diperlukan" type="date" required></label>
                        <label class="field-wrap"><span>Jenis Pekerjaan</span><select name="jenis_pekerjaan"><option value="repair">Repair</option><option value="maintenance">Maintenance</option><option value="utility">Utility (Consumable)</option><option value="project">Project</option></select></label>
                        <label class="field-wrap"><span>Keterangan</span><textarea name="keterangan" rows="3" placeholder="Catatan tambahan"></textarea></label>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Referensi & Verifikasi</div>
                        <div class="field-wrap">
                            <span>Referensi WO Approved (opsional)</span>
                            <input class="target-search" id="pbWoSearch" type="search" placeholder="Cari nomor / judul WO approved..." autocomplete="off" oninput="searchWorkOrder(this.value)">
                            <input type="hidden" name="reference_wo_id" id="pbWoId">
                            <input type="hidden" name="reference_wo_number" id="pbWoNumber">
                            <div class="suggestion-list" id="pbWoResults"></div>
                        </div>
                        <label class="field-wrap">
                            <span>Diverifikasi Oleh</span>
                            <select name="verification_section_head_id" required>
                                <option value="">Pilih Section Head</option>
                                ' . $sectionHeads . '
                            </select>
                        </label>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">Daftar Barang</div>
                        <label class="field-wrap"><span>Jenis Barang</span><select name="material_type" id="pbMaterialType" onchange="changeMaterialType()"><option value="sparepart">Sparepart</option><option value="non_sparepart">Non Sparepart</option></select></label>
                        <div id="pbItems"></div>
                        <button class="reset-filter" id="addPbItemButton" type="button" onclick="return addPbItem()">+ Tambah Item</button>
                    </div>
                    <div id="pbSubmitNotice" class="submit-notice" hidden></div>
                    <button id="pbSubmitButton" class="apply-filter sticky-submit" type="button" onclick="return window.submitEngineeringPb(event)">Simpan PB</button>
                </form>
            </section>
            <script>
                (function () {
                    var token = ' . json_encode((string) ($request->bearerToken() ?: $request->query('token', ''))) . ' || new URLSearchParams(window.location.search).get("token") || "";
                    var targetSearchUrl = ' . json_encode($targetSearchUrl) . ';
                    var woSearchUrl = ' . json_encode($woSearchUrl) . ';
                    var timers = {};
                    function esc(value) {
                        return String(value || "").replace(/[&<>"\']/g, function (char) {
                            return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[char];
                        });
                    }
                    function trim(value) {
                        return String(value || "").replace(/^\\s+|\\s+$/g, "");
                    }
                    function withToken(url) {
                        if (!token) return url;
                        return url + (url.indexOf("?") >= 0 ? "&" : "?") + "token=" + encodeURIComponent(token);
                    }
                    function debounce(key, callback) {
                        clearTimeout(timers[key]);
                        timers[key] = setTimeout(callback, 260);
                    }
                    function requestJson(url, callback) {
                        var xhr = new XMLHttpRequest();
                        xhr.open("GET", withToken(url), true);
                        xhr.setRequestHeader("Accept", "application/json");
                        if (token) xhr.setRequestHeader("Authorization", "Bearer " + token);
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState !== 4) return;
                            var rows = [];
                            try { rows = (JSON.parse(xhr.responseText).data || []); } catch (e) {}
                            callback(rows);
                        };
                        xhr.send();
                    }
                    function setBox(id, html) {
                        var box = document.getElementById(id);
                        if (box) box.innerHTML = html || "";
                    }
                    function renderButtons(id, rows, onPick, emptyText) {
                        var box = document.getElementById(id);
                        if (!box) return;
                        if (!rows.length) {
                            box.innerHTML = \'<div class="suggestion-empty">\' + esc(emptyText || "Data tidak ditemukan") + \'</div>\';
                            return;
                        }
                        box.innerHTML = rows.map(function (row, index) {
                            return \'<button type="button" data-index="\' + index + \'"><strong>\' + esc(row.name || row.nomor || "-") + \'</strong><small>\' + esc(row.code || row.judul || "") + \'</small></button>\';
                        }).join("");
                        Array.prototype.forEach.call(box.querySelectorAll("button"), function (button) {
                            button.onclick = function () {
                                onPick(rows[Number(button.getAttribute("data-index"))]);
                            };
                        });
                    }
                    window.switchTarget = function () {
                        var untukEl = document.getElementById("pbUntuk");
                        var untuk = untukEl ? untukEl.value : "mesin";
                        var mesin = document.querySelector(".mesin-target");
                        var bangunan = document.querySelector(".bangunan-target");
                        var mesinId = document.getElementById("pbMesinId");
                        var bangunanId = document.getElementById("pbBangunanId");
                        if (mesin) mesin.hidden = untuk !== "mesin";
                        if (bangunan) bangunan.hidden = untuk !== "bangunan";
                        if (mesinId) mesinId.disabled = untuk !== "mesin";
                        if (bangunanId) bangunanId.disabled = untuk !== "bangunan";
                    };
                    window.searchTarget = function (type, value) {
                        var isMesin = type === "mesin";
                        var resultId = isMesin ? "pbMesinResults" : "pbBangunanResults";
                        var hiddenId = isMesin ? "pbMesinId" : "pbBangunanId";
                        var inputId = isMesin ? "pbMesinSearch" : "pbBangunanSearch";
                        var hidden = document.getElementById(hiddenId);
                        var query = trim(value);
                        if (hidden) hidden.value = "";
                        if (query.length < 2) {
                            setBox(resultId, "");
                            return;
                        }
                        setBox(resultId, \'<div class="suggestion-empty">Mencari...</div>\');
                        debounce("target-" + type, function () {
                            requestJson(targetSearchUrl + "?type=" + encodeURIComponent(type) + "&q=" + encodeURIComponent(query), function (rows) {
                                renderButtons(resultId, rows, function (row) {
                                    var input = document.getElementById(inputId);
                                    var picked = row.name || "";
                                    if (row.code) picked += " (" + row.code + ")";
                                    if (hidden) hidden.value = row.id || "";
                                    if (input) input.value = picked;
                                    setBox(resultId, "");
                                }, isMesin ? "Mesin tidak ditemukan" : "Bangunan tidak ditemukan");
                            });
                        });
                    };
                    window.searchWorkOrder = function (value) {
                        var woId = document.getElementById("pbWoId");
                        var woNumber = document.getElementById("pbWoNumber");
                        var query = trim(value);
                        if (woId) woId.value = "";
                        if (woNumber) woNumber.value = "";
                        if (query.length < 2) {
                            setBox("pbWoResults", "");
                            return;
                        }
                        setBox("pbWoResults", \'<div class="suggestion-empty">Mencari...</div>\');
                        debounce("wo-ref", function () {
                            requestJson(woSearchUrl + "?q=" + encodeURIComponent(query), function (rows) {
                                renderButtons("pbWoResults", rows, function (row) {
                                    var input = document.getElementById("pbWoSearch");
                                    if (woId) woId.value = row.id || "";
                                    if (woNumber) woNumber.value = row.nomor || "";
                                    if (input) input.value = (row.nomor || "-") + " - " + (row.judul || "-");
                                    setBox("pbWoResults", "");
                                }, "Belum ada WO approved yang cocok");
                            });
                        });
                    };
                })();
            </script>
            <script>
                (function () {
                    var token = ' . json_encode((string) ($request->bearerToken() ?: $request->query('token', ''))) . ' || new URLSearchParams(window.location.search).get("token") || "";
                    var itemSearchUrl = ' . json_encode($itemSearchUrl) . ';
                    function esc(value) {
                        return String(value || "").replace(/[&<>"\']/g, function (char) {
                            return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[char];
                        });
                    }
                    function withToken(url) {
                        if (!token) return url;
                        return url + (url.indexOf("?") >= 0 ? "&" : "?") + "token=" + encodeURIComponent(token);
                    }
                    window.setPbUnitValue = function (select, value) {
                        if (!select) return;
                        var unit = String(value || "PCS").toUpperCase();
                        if (!Array.prototype.some.call(select.options, function (option) { return option.value === unit; })) {
                            var option = document.createElement("option");
                            option.value = unit;
                            option.textContent = unit;
                            select.appendChild(option);
                        }
                        select.value = unit;
                    };
                    window.addPbItem = function () {
                        var wrap = document.getElementById("pbItems");
                        if (!wrap) return false;
                        var row = document.createElement("div");
                        row.className = "pb-item";
                        row.setAttribute("data-row", String(Date.now()));
                        row.innerHTML =
                            \'<input type="hidden" name="barang_id">\' +
                            \'<input name="nama_barang" type="search" placeholder="Ketik minimal 2 huruf nama / kode barang..." autocomplete="off" required oninput="searchItem(this, this.value)">\' +
                            \'<div class="suggestion-list item-results"></div>\' +
                            \'<div class="item-grid"><input name="jumlah" type="number" step="0.01" min="0.01" placeholder="Qty" required><select name="satuan" required><option value="PCS">PCS</option><option value="UNIT">Unit</option><option value="KG">Kilogram (KG)</option><option value="G">Gram (G)</option><option value="L">Liter (L)</option><option value="ML">Milliliter (ML)</option><option value="M">Meter (M)</option><option value="CM">Centimeter (CM)</option><option value="MM">Millimeter (MM)</option><option value="BOX">Box</option><option value="PACK">Pack</option><option value="ROLL">Roll</option><option value="SET">Set</option><option value="BTG">Batang (BTG)</option><option value="BUAH">Buah</option><option value="LEMBAR">Lembar</option><option value="PAIR">Pair (Pasang)</option><option value="BTL">Bottle (Botol)</option><option value="CAN">Can (Kaleng)</option><option value="TUBE">Tube (Tabung)</option><option value="BAG">Bag (Karung)</option><option value="DRUM">Drum</option><option value="CARTON">Carton (Kardus)</option><option value="PALLET">Pallet</option></select></div>\' +
                            \'<input name="item_keterangan" placeholder="Keterangan item (opsional)">\' +
                            \'<button type="button" onclick="this.closest(\\\'.pb-item\\\').remove()">Hapus item</button>\';
                        wrap.appendChild(row);
                        var input = row.querySelector("[name=nama_barang]");
                        if (input) input.focus();
                        return false;
                    };
                    window.searchItem = function (input, value) {
                        var row = input.closest(".pb-item");
                        if (!row) return;
                        var resultBox = row.querySelector(".item-results");
                        var hidden = row.querySelector("[name=barang_id]");
                        if (hidden) hidden.value = "";
                        row.removeAttribute("data-autopick-id");
                        row.removeAttribute("data-autopick-name");
                        row.removeAttribute("data-autopick-unit");
                        if (!resultBox) return;
                        value = (value || "").replace(/^\\s+|\\s+$/g, "");
                        if (value.length < 2) {
                            resultBox.innerHTML = "";
                            return;
                        }
                        resultBox.innerHTML = \'<div class="suggestion-empty">Mencari...</div>\';
                        var materialSelect = document.getElementById("pbMaterialType");
                        var materialType = materialSelect ? materialSelect.value : "sparepart";
                        var xhr = new XMLHttpRequest();
                        xhr.open("GET", withToken(itemSearchUrl + "?q=" + encodeURIComponent(value) + "&material_type=" + encodeURIComponent(materialType)), true);
                        xhr.setRequestHeader("Accept", "application/json");
                        if (token) xhr.setRequestHeader("Authorization", "Bearer " + token);
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState !== 4) return;
                            var rows = [];
                            try { rows = (JSON.parse(xhr.responseText).data || []); } catch (e) {}
                            if (!rows.length) {
                                row.removeAttribute("data-autopick-id");
                                row.removeAttribute("data-autopick-name");
                                row.removeAttribute("data-autopick-unit");
                                resultBox.innerHTML = \'<div class="suggestion-empty">Barang tidak ditemukan</div>\';
                                return;
                            }
                            if (rows.length === 1) {
                                row.setAttribute("data-autopick-id", rows[0].id || "");
                                row.setAttribute("data-autopick-name", rows[0].name || "");
                                row.setAttribute("data-autopick-unit", rows[0].unit || "PCS");
                            } else {
                                row.removeAttribute("data-autopick-id");
                                row.removeAttribute("data-autopick-name");
                                row.removeAttribute("data-autopick-unit");
                            }
                            resultBox.innerHTML = rows.map(function (item, index) {
                                return \'<button type="button" data-index="\' + index + \'"><strong>\' + esc(item.name) + \'</strong><small>\' + esc(item.code) + \' - \' + esc(item.unit || "PCS") + \'</small></button>\';
                            }).join("");
                            Array.prototype.forEach.call(resultBox.querySelectorAll("button"), function (button) {
                                button.onclick = function () {
                                    var item = rows[Number(button.getAttribute("data-index"))];
                                    if (hidden) hidden.value = item.id || "";
                                    input.value = item.name || "";
                                    row.setAttribute("data-autopick-id", item.id || "");
                                    row.setAttribute("data-autopick-name", item.name || "");
                                    row.setAttribute("data-autopick-unit", item.unit || "PCS");
                                    var satuan = row.querySelector("[name=satuan]");
                                    window.setPbUnitValue(satuan, item.unit || "PCS");
                                    resultBox.innerHTML = "";
                                };
                            });
                        };
                        xhr.send();
                    };
                    window.ensurePbItemRow = function () {
                        var wrap = document.getElementById("pbItems");
                        if (wrap && !wrap.querySelector(".pb-item")) window.addPbItem();
                    };
                    window.ensurePbItemRow();
                    var button = document.getElementById("addPbItemButton");
                    if (button) {
                        button.onclick = function (event) {
                            if (event && event.preventDefault) event.preventDefault();
                            return window.addPbItem();
                        };
                    }
                })();
            </script>
            <script>' . $this->engineeringWebScript($request->bearerToken()) . '
                switchTarget();
                window.ensurePbItemRow();
                var addPbItemButton = document.getElementById("addPbItemButton");
                if (addPbItemButton) {
                    addPbItemButton.addEventListener("click", function (event) {
                        event.preventDefault();
                        window.addPbItem();
                    });
                }
                var pbItemsWrap = document.getElementById("pbItems");
                if (pbItemsWrap && pbItemsWrap.querySelector(".pb-item:not([data-stock-ready])")) {
                    pbItemsWrap.innerHTML = "";
                    window.addPbItem();
                }
                function showPbSubmitNotice(type, message) {
                    var notice = document.getElementById("pbSubmitNotice");
                    if (!notice) return;
                    notice.hidden = false;
                    notice.className = "submit-notice " + (type || "info");
                    notice.textContent = message || "";
                    try { notice.scrollIntoView({ block: "center", behavior: "smooth" }); } catch (e) {}
                }
                window.submitEngineeringPb = function (event) {
                    if (event && event.preventDefault) event.preventDefault();
                    var form = document.getElementById("pbForm");
                    if (!form) return false;
                    var submitButton = document.getElementById("pbSubmitButton");
                    if (submitButton && submitButton.disabled) return;
                    showPbSubmitNotice("info", "Menyimpan PB...");
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = "Menyimpan...";
                    }
                    var items = [];
                    Array.prototype.forEach.call(form.querySelectorAll(".pb-item"), function (row) {
                        var hidden = row.querySelector("[name=barang_id]");
                        var nameInput = row.querySelector("[name=nama_barang]");
                        var unitInput = row.querySelector("[name=satuan]");
                        if (hidden && !hidden.value && row.getAttribute("data-autopick-id")) {
                            hidden.value = row.getAttribute("data-autopick-id") || "";
                            if (nameInput) nameInput.value = row.getAttribute("data-autopick-name") || nameInput.value;
                            window.setPbUnitValue(unitInput, row.getAttribute("data-autopick-unit") || "PCS");
                            updateItemStock(row, {
                                stock_available: row.getAttribute("data-autopick-stock") || 0,
                                stock_erp: row.getAttribute("data-stock-erp") || row.getAttribute("data-autopick-stock") || 0,
                                stock_erp_unit: row.getAttribute("data-stock-erp-unit") || row.getAttribute("data-autopick-unit") || "PCS",
                                stock_area: row.getAttribute("data-stock-area") || 0,
                                stock_area_unit: row.getAttribute("data-stock-area-unit") || row.getAttribute("data-autopick-unit") || "PCS",
                                unit: row.getAttribute("data-autopick-unit") || "PCS"
                            });
                        }
                        var stockInput = row.querySelector("[name=stock_available]");
                        var item = {
                            barang_id: hidden ? hidden.value : "",
                            nama_barang: nameInput ? nameInput.value : "",
                            jumlah: row.querySelector("[name=jumlah]").value,
                            satuan: unitInput ? unitInput.value : "",
                            material_type: form.material_type.value,
                            keterangan: row.querySelector("[name=item_keterangan]").value,
                            stock_available: stockInput ? stockInput.value : row.getAttribute("data-stock-available"),
                            stock_erp: row.getAttribute("data-stock-erp") || "",
                            stock_erp_unit: row.getAttribute("data-stock-erp-unit") || "",
                            stock_area: row.getAttribute("data-stock-area") || "",
                            stock_area_unit: row.getAttribute("data-stock-area-unit") || ""
                        };
                        if (item.nama_barang && Number(item.jumlah) > 0) items.push(item);
                    });
                    var untuk = form.untuk.value;
                    function stopWith(message) {
                        showPbSubmitNotice("error", message);
                        alert(message);
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = "Simpan PB";
                        }
                    }
                    if (!form.tanggal_diperlukan.value) {
                        stopWith("Pilih tanggal diperlukan.");
                        return;
                    }
                    if (!form.jenis_pekerjaan.value) {
                        stopWith("Pilih jenis pekerjaan.");
                        return;
                    }
                    if (untuk === "mesin" && !form.mesin_id.value) {
                        stopWith("Pilih mesin dari hasil pencarian.");
                        return;
                    }
                    if (untuk === "bangunan" && !form.bangunan_id.value) {
                        stopWith("Pilih bangunan dari hasil pencarian.");
                        return;
                    }
                    if (!items.length) {
                        stopWith("Lengkapi minimal 1 barang: nama barang dan qty wajib diisi.");
                        return;
                    }
                    for (var i = 0; i < items.length; i++) {
                        var erpValue = Number(items[i].stock_erp || items[i].stock_available || 0);
                        var areaValue = Number(items[i].stock_area || 0);
                        if (items[i].barang_id && erpValue <= 0 && areaValue <= 0) {
                            stopWith("Stok " + items[i].nama_barang + " kosong. Item tidak dapat diajukan.");
                            return;
                        }
                        var unit = normalizeStockUnit(items[i].satuan);
                        var areaCanSupply = normalizeStockUnit(items[i].stock_area_unit || "") === unit && Number(items[i].jumlah) <= areaValue;
                        var erpCanSupply = normalizeStockUnit(items[i].stock_erp_unit || items[i].satuan || "") === unit && Number(items[i].jumlah) <= erpValue;
                        if (items[i].barang_id && !areaCanSupply && !erpCanSupply) {
                            stopWith("Jumlah " + items[i].nama_barang + " belum cukup pada satuan yang dipilih. Stock Area: " + formatStockValue(areaValue) + " " + (items[i].stock_area_unit || "-") + ", ERP: " + formatStockValue(erpValue) + " " + (items[i].stock_erp_unit || items[i].satuan || "-") + ".");
                            return;
                        }
                    }
                    var missingItem = items.some
                        ? items.some(function (item) { return !item.barang_id; })
                        : (function () { for (var i = 0; i < items.length; i++) { if (!items[i].barang_id) return true; } return false; })();
                    if (missingItem) {
                        // Backend akan resolve otomatis jika teks barang menghasilkan satu master item.
                    }
                    if (!form.verification_section_head_id.value) {
                        stopWith("Pilih Section Head untuk verifikasi PB.");
                        return;
                    }
                    var body = {
                        nomor_pb: form.nomor_pb.value,
                        untuk: untuk,
                        untuk_id: untuk === "mesin" ? form.mesin_id.value : form.bangunan_id.value,
                        material_type: form.material_type.value,
                        reference_wo_id: form.reference_wo_id.value,
                        reference_wo_number: form.reference_wo_number.value,
                        verification_section_head_id: form.verification_section_head_id.value,
                        tanggal_diperlukan: form.tanggal_diperlukan.value,
                        jenis_pekerjaan: form.jenis_pekerjaan.value,
                        keterangan: form.keterangan.value,
                        barang: items
                    };
                    submitJson("' . e(url('/api/mobile/engineering/pb')) . '", body).then(function (result) {
                        var message = result.message || (result.success ? "PB tersimpan" : "Gagal simpan PB");
                        showPbSubmitNotice(result.success ? "success" : "error", message);
                        alert(message);
                        if (result.success) {
                            setTimeout(function () {
                                location.href = withMobileToken("' . e(url('/api/mobile/web/engineering/pb')) . '");
                            }, 850);
                        } else if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = "Simpan PB";
                        }
                    }).catch(function () {
                        var message = "Gagal simpan PB. Periksa koneksi lalu coba lagi.";
                        showPbSubmitNotice("error", message);
                        alert(message);
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = "Simpan PB";
                        }
                    });
                    return false;
                };
                document.getElementById("pbForm").addEventListener("submit", window.submitEngineeringPb);
            </script>
        ';

        return $this->mobileWebResponse('Buat PB', $body);
    }

    public function webEngineeringWo(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses mobile engineering.</div>', 403);
        }

        $statusFilter = strtolower((string) $request->query('status', ''));
        $rows = $this->engineeringWoRows($auth, 80);
        if ($statusFilter === 'progress') {
            $rows = $rows->filter(fn ($wo) => strtolower((string) $wo->status) === 'approved' && in_array(strtolower((string) ($wo->progress_status ?: 'open')), ['open', 'progress', 'hold'], true))->values();
        } elseif (in_array($statusFilter, ['submitted', 'approved', 'rejected', 'closed'], true)) {
            $rows = $rows->filter(fn ($wo) => strtolower((string) $wo->status) === $statusFilter || strtolower((string) $wo->progress_status) === $statusFilter)->values();
        }
        $cards = $rows->map(fn ($wo) => $this->engineeringWoCard($wo))->implode('') ?: '<div class="empty">Belum ada WO.</div>';
        $woHeading = $statusFilter === 'progress' ? 'WO Progress' : 'Work Order';

        $body = '
            <section class="eng-tabs">
                <a href="' . e(url('/api/mobile/web/engineering/pb')) . '">PB</a>
                <a class="active" href="' . e(url('/api/mobile/web/engineering/wo')) . '">WO</a>
            </section>
            <section class="form-card compact-action">
                <a class="filter-toggle" href="' . e(url('/api/mobile/web/engineering/wo/create')) . '"><span>Buat WO Baru</span><strong>Mulai</strong></a>
            </section>
            <section class="history-filter-summary"><strong>' . e($woHeading) . '</strong><span>' . e((string) $rows->count()) . ' data</span></section>
            <section class="date-filter-row">
                <label class="field-wrap"><span>Dari</span><input id="woDateFrom" data-date-filter="from" type="date" onchange="filterCards(document.getElementById(\'woSearch\').value)"></label>
                <label class="field-wrap"><span>Sampai</span><input id="woDateTo" data-date-filter="to" type="date" onchange="filterCards(document.getElementById(\'woSearch\').value)"></label>
            </section>
            <section class="toolbar"><input id="woSearch" class="search" type="search" placeholder="Cari nomor WO, status, judul..." oninput="filterCards(this.value)"></section>
            <section class="list">' . $cards . '</section>
            <script>' . $this->engineeringWebScript($request->bearerToken()) . '
            </script>
        ';

        return $this->mobileWebResponse('WO Engineering', $body);
    }

    public function webEngineeringWoCreate(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return $this->mobileWebResponse('Tidak ada akses', '<div class="empty">User tidak punya akses mobile engineering.</div>', 403);
        }

        $nomor = $this->generateMobileWoNumber();

        $body = '
            <section class="form-card create-form">
                <div class="form-number"><span>Nomor WO</span><strong>' . e($nomor) . '</strong></div>
                <form id="woForm" enctype="multipart/form-data">
                    <input type="hidden" name="nomor" value="' . e($nomor) . '">
                    <label class="field-wrap"><span>Judul</span><input name="judul" required maxlength="200" placeholder="Judul work order"></label>
                    <label class="field-wrap"><span>Deskripsi</span><textarea name="deskripsi" rows="4" placeholder="Deskripsi pekerjaan"></textarea></label>
                    <label class="field-wrap"><span>Lampiran PDF / Gambar</span><input name="dokumen[]" type="file" multiple accept=".pdf,image/*" required></label>
                    <button class="apply-filter sticky-submit" type="submit">Simpan & Submit WO</button>
                </form>
            </section>
            <script>' . $this->engineeringWebScript($request->bearerToken()) . '
                document.getElementById("woForm").addEventListener("submit", function (event) {
                    event.preventDefault();
                    submitForm("' . e(url('/api/mobile/engineering/wo')) . '", new FormData(event.target)).then(function (result) {
                        alert(result.message || (result.success ? "WO tersimpan" : "Gagal simpan WO"));
                        if (result.success) location.href = withMobileToken("' . e(url('/api/mobile/web/engineering/wo')) . '");
                    });
                });
            </script>
        ';

        return $this->mobileWebResponse('Buat WO', $body);
    }

    public function engineeringPbStore(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $data = $request->validate([
            'nomor_pb' => ['nullable', 'string', 'max:80'],
            'untuk' => ['required', Rule::in(['mesin', 'bangunan'])],
            'untuk_id' => ['required', 'integer'],
            'reference_wo_id' => ['nullable', 'integer'],
            'reference_wo_number' => ['nullable', 'string', 'max:80'],
            'verification_section_head_id' => ['required', 'integer', 'exists:users,id'],
            'tanggal_diperlukan' => ['required', 'date', 'after_or_equal:today'],
            'jenis_pekerjaan' => ['required', Rule::in(['repair', 'maintenance', 'utility', 'project', 'overhaul'])],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'material_type' => ['nullable', Rule::in(['sparepart', 'non_sparepart'])],
            'barang' => ['required', 'array', 'min:1'],
            'barang.*.barang_id' => ['nullable', 'integer'],
            'barang.*.nama_barang' => ['required', 'string', 'max:255'],
            'barang.*.jumlah' => ['required', 'numeric', 'min:0.01'],
            'barang.*.satuan' => ['required', 'string', 'max:30'],
            'barang.*.material_type' => ['nullable', Rule::in(['sparepart', 'non_sparepart'])],
            'barang.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        $nomor = $data['nomor_pb'] ?? $this->generateMobilePbNumber();
        if (DB::table('trBPB')->where('nomor_pb', $nomor)->exists()) {
            $nomor = $this->generateMobilePbNumber();
        }

        $sectionHead = DB::table('users')
            ->where('id', $data['verification_section_head_id'])
            ->where('role', 'section_head')
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->first();

        if (!$sectionHead) {
            return response()->json(['success' => false, 'message' => 'Pilih Section Head aktif untuk verifikasi PB.'], 422);
        }

        $prepared = [];
        $hasHighValue = false;
        $detailHasBarangId = Schema::hasColumn('trBPBDetail', 'barang_id');
        $formMaterialType = $this->normalizeMaterialType($data['material_type'] ?? 'sparepart');
        foreach ($data['barang'] as $index => $item) {
            $materialType = $this->normalizeMaterialType($item['material_type'] ?? $formMaterialType);
            $barangId = isset($item['barang_id']) && is_numeric($item['barang_id']) ? (int) $item['barang_id'] : null;
            $itemName = trim((string) ($item['nama_barang'] ?? ''));
            if (!$barangId) {
                $matches = DB::connection('pgsql2')
                    ->table('tb_skb080_1mmara')
                    ->select('id_items as id', 'item_name as name', 'meins as unit')
                    ->where(function ($query) use ($materialType) {
                        $this->applyMaterialScope($query, $materialType);
                    })
                    ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE ?", ['%jangan dipakai%'])
                    ->where(function ($query) use ($itemName) {
                        $query->where('item_name', 'ilike', '%' . $itemName . '%')
                            ->orWhere('code', 'ilike', '%' . $itemName . '%');
                    })
                    ->orderByRaw('CASE WHEN lower(item_name) = ? OR lower(code) = ? THEN 0 ELSE 1 END', [mb_strtolower($itemName), mb_strtolower($itemName)])
                    ->orderBy('item_name')
                    ->limit(2)
                    ->get();

                if ($matches->count() !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Baris ' . ($index + 1) . ': pilih barang dari hasil pencarian agar item master tepat.',
                    ], 422);
                }

                $match = $matches->first();
                $barangId = (int) $match->id;
                $itemName = (string) $match->name;
            }

            $unitPrice = $this->mobileItemAveragePrice($itemName, $barangId, $materialType);
            $qty = (float) $item['jumlah'];
            $stockSources = $this->mobileItemStockSources($itemName, $barangId, $materialType);
            $erpStock = (float) ($stockSources['erp_qty'] ?? 0);
            $erpUnit = $stockSources['erp_unit'] ?: ($item['satuan'] ?? '');
            $areaStock = (float) ($stockSources['area_qty'] ?? 0);
            $areaUnit = $stockSources['area_unit'] ?: ($item['satuan'] ?? '');
            if ($erpStock <= 0 && $areaStock <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Baris ' . ($index + 1) . ': stok ' . $itemName . ' kosong. Item tidak dapat diajukan.',
                ], 422);
            }
            $areaCanSupply = $areaStock > 0
                && $this->mobileSameStockUnit($item['satuan'] ?? '', $areaUnit)
                && $qty <= $areaStock;
            $erpCanSupply = $erpStock > 0
                && $this->mobileSameStockUnit($item['satuan'] ?? '', $erpUnit)
                && $qty <= $erpStock;
            if (!$areaCanSupply && !$erpCanSupply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Baris ' . ($index + 1) . ': jumlah ' . $itemName . ' belum cukup pada satuan yang dipilih. Stock Area: ' . $this->mobileFormatStockQuantity($areaStock) . ' ' . $areaUnit . ', ERP: ' . $this->mobileFormatStockQuantity($erpStock) . ' ' . $erpUnit . '.',
                ], 422);
            }
            $fulfillmentSource = $areaCanSupply ? 'stock_area' : 'erp';
            if ($fulfillmentSource === 'stock_area') {
                $unitPrice = 0;
            }
            $isHighValue = $unitPrice >= 10000000;
            $hasHighValue = $hasHighValue || $isHighValue;
            $prepared[] = [
                'nama_barang' => $itemName,
                'jumlah' => $qty,
                'satuan' => $item['satuan'],
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $qty,
                'is_high_value' => $isHighValue,
                'material_type' => $materialType,
                'fulfillment_source' => $fulfillmentSource,
                'keterangan' => $item['keterangan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($detailHasBarangId) {
                $prepared[count($prepared) - 1]['barang_id'] = $barangId;
            }
        }

        DB::beginTransaction();
        try {
            $header = [
                'nomor_pb' => $nomor,
                'tanggal_permintaan' => now(),
                'bagian' => 'Engineering',
                'untuk' => $data['untuk'],
                'untuk_id' => $data['untuk_id'],
                'dari_gudang' => 'Gudang 11 (Spareparts & Packaging)',
                'tanggal_diperlukan' => $data['tanggal_diperlukan'],
                'jenis_pekerjaan' => $data['jenis_pekerjaan'],
                'status' => 'verification',
                'approval_level_required' => $hasHighValue ? 2 : 1,
                'approval_current_level' => 0,
                'has_high_value_item' => $hasHighValue,
                'keterangan' => $data['keterangan'] ?? null,
                'user_id' => $auth->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('trBPB', 'reference_wo_id')) {
                $header['reference_wo_id'] = ($data['reference_wo_id'] ?? null) ?: null;
            }
            if (Schema::hasColumn('trBPB', 'reference_wo_number')) {
                $header['reference_wo_number'] = ($data['reference_wo_number'] ?? null) ?: null;
            }
            if (Schema::hasColumn('trBPB', 'verification_section_head_id')) {
                $header['verification_section_head_id'] = $sectionHead->id;
                $header['verification_status'] = 'pending';
            }
            if ($data['untuk'] === 'mesin') {
                $header['mesin_id'] = $data['untuk_id'];
            } else {
                $header['bangunan_id'] = $data['untuk_id'];
            }

            $id = DB::table('trBPB')->insertGetId($header);
            $detailColumns = Schema::getColumnListing('trBPBDetail');
            $pbDetailForeignKey = collect($detailColumns)->first(fn ($column) => $column === 'trBPB_id')
                ?: collect($detailColumns)->first(fn ($column) => strtolower($column) === 'trbpb_id');

            if (!$pbDetailForeignKey) {
                throw new \Exception('Kolom relasi detail PB tidak ditemukan');
            }

            foreach ($prepared as &$item) {
                $item[$pbDetailForeignKey] = $id;
            }
            DB::table('trBPBDetail')->insert($prepared);
            DB::commit();

            app(FirebasePushService::class)->sendToUserId((int) $sectionHead->id, 'PB Menunggu Verifikasi', $nomor . ' perlu diverifikasi sebelum masuk Approval L1.', [
                'type' => 'PB',
                'target' => 'pb_verification',
                'record_id' => $id,
                'nomor' => $nomor,
                'sound_type' => 'approval',
            ]);

            return response()->json(['success' => true, 'message' => 'PB berhasil dibuat dan dikirim ke Verifikasi Section Head.', 'id' => $id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile engineering PB store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan PB.'], 500);
        }
    }

    public function engineeringWoStore(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $data = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'dokumen' => ['required', 'array', 'min:1'],
            'dokumen.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $nomor = $this->generateMobileWoNumber();

        DB::beginTransaction();
        try {
            $firstFile = $request->file('dokumen')[0];
            $firstName = $nomor . '.' . $firstFile->getClientOriginalExtension();
            $firstPath = $firstFile->storeAs('work-orders', $firstName, 'public');

            $id = DB::table('trWorkOrder')->insertGetId([
                'nomor' => $nomor,
                'judul' => $data['judul'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'file_path' => $firstPath,
                'file_name' => $firstName,
                'status' => 'submitted',
                'created_by' => $auth->id,
                'submitted_by' => $auth->id,
                'submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            app(FirebasePushService::class)->sendToRole('approval', 'WO Menunggu Approval', $nomor . ' perlu keputusan Approval Level 1.', [
                'type' => 'WO',
                'target' => 'approval_wo',
                'record_id' => $id,
                'nomor' => $nomor,
            ]);

            return response()->json(['success' => true, 'message' => 'WO berhasil dibuat dan disubmit ke Approval L1.', 'id' => $id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile engineering WO store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan WO.'], 500);
        }
    }

    public function engineeringWoUnpost(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $wo = DB::table('trWorkOrder')->where('id', $id)->where('created_by', $auth->id)->first();
        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Hanya WO submitted yang belum diapprove yang bisa di-unpost.'], 422);
        }

        if (!empty($wo->approved_by) || !empty($wo->approved_at) || !empty($wo->assigned_regu) || !empty($wo->assigned_at)) {
            return response()->json(['success' => false, 'message' => 'WO sudah masuk proses approval/assign, tidak bisa di-unpost.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $id)->update([
            'status' => 'draft',
            'submitted_by' => null,
            'submitted_at' => null,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'WO berhasil di-unpost dan kembali ke Draft.']);
    }

    public function engineeringSearchTargets(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $type = (string) $request->query('type', 'mesin');
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || !in_array($type, ['mesin', 'bangunan'], true)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($type === 'mesin') {
            $rows = DB::table('mtMesin')
                ->where(function ($query) use ($q) {
                    $query->where('msnName', 'like', '%' . $q . '%')
                        ->orWhere('msnCode', 'like', '%' . $q . '%');
                })
                ->orderBy('msnName')
                ->limit(12)
                ->get(['msnID as id', 'msnName as name', 'msnCode as code']);
        } else {
            $rows = DB::table('mtBangunan')
                ->where(function ($query) use ($q) {
                    $query->where('buildName', 'like', '%' . $q . '%')
                        ->orWhere('buildCode', 'like', '%' . $q . '%');
                })
                ->orderBy('buildName')
                ->limit(12)
                ->get(['buildID as id', 'buildName as name', 'buildCode as code']);
        }

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function engineeringSearchWorkOrders(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $materialType = $this->normalizeMaterialType($request->query('material_type', 'sparepart'));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::table('trWorkOrder')
            ->where('created_by', $auth->id)
            ->where('status', 'approved')
            ->where(function ($query) use ($q) {
                $query->where('nomor', 'like', '%' . $q . '%')
                    ->orWhere('judul', 'like', '%' . $q . '%');
            })
            ->orderByDesc('approved_at')
            ->limit(12)
            ->get(['id', 'nomor', 'judul']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function engineeringSearchItems(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $q = trim((string) $request->query('q', ''));
        $materialType = $this->normalizeMaterialType($request->query('material_type', 'sparepart'));
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $rows = DB::connection('pgsql2')
                ->table('tb_skb080_1mmara')
                ->select('id_items as id', 'code', 'item_name as name', 'meins as unit')
                ->where(function ($query) use ($materialType) {
                    $this->applyMaterialScope($query, $materialType);
                })
                ->whereRaw("LOWER(COALESCE(item_name, '')) NOT LIKE ?", ['%jangan dipakai%'])
                ->where(function ($query) use ($q) {
                    $query->where('item_name', 'ilike', '%' . $q . '%')
                        ->orWhere('code', 'ilike', '%' . $q . '%');
                })
                ->orderBy('item_name')
                ->limit(12)
                ->get();

            $stockById = $this->mobileAvailableStocksForItems(
                $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all()
            );
            $areaStockByKey = $this->mobileAreaStocksForItems(
                $rows->map(fn ($item) => [
                    'code' => (string) ($item->code ?? ''),
                    'name' => (string) ($item->name ?? ''),
                ])->all()
            );

            $rows = $rows->map(function ($item) use ($materialType, $stockById, $areaStockByKey) {
                $unit = strtoupper((string) ($item->unit ?: 'PCS'));
                $erpStock = round((float) ($stockById[(int) $item->id] ?? 0), 2);
                $areaInfo = $areaStockByKey[$this->mobileStockAreaLookupKey($item->code ?? '', $item->name ?? '')] ?? ['qty' => 0, 'unit' => $unit];
                $areaStock = max(0, round((float) ($areaInfo['qty'] ?? 0), 2));

                return [
                    'id' => (int) $item->id,
                    'code' => (string) $item->code,
                    'name' => (string) $item->name,
                    'unit' => $unit,
                    'stock_available' => $erpStock,
                    'stock_erp' => $erpStock,
                    'stock_erp_unit' => $unit,
                    'stock_area' => $areaStock,
                    'stock_area_unit' => strtoupper((string) ($areaInfo['unit'] ?: $unit)),
                    'has_any_stock' => $areaStock > 0 || $erpStock > 0,
                    'material_type' => $materialType,
                    'material_type_label' => $materialType === 'non_sparepart' ? 'Non Sparepart' : 'Sparepart',
                ];
            });

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            Log::warning('Mobile item search error: ' . $e->getMessage(), ['q' => $q]);

            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function engineeringWoSubmit(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth || !$this->isEngineeringMobileUser($auth)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada akses.'], 403);
        }

        $wo = DB::table('trWorkOrder')->where('id', $id)->where('created_by', $auth->id)->first();
        if (!$wo || $wo->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'WO draft tidak ditemukan.'], 404);
        }

        DB::table('trWorkOrder')->where('id', $id)->update([
            'status' => 'submitted',
            'submitted_by' => $auth->id,
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'WO berhasil disubmit.']);
    }

    public function pbIndex(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $items = $this->pbListQuery($auth)
            ->paginate((int) $request->query('per_page', 20));

        $rows = $items->getCollection()
            ->map(fn ($item) => $this->pbPayload($item, true))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function pbShow(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $pb = $this->pbListQuery($auth)->where('trBPB.id', $id)->first();

        if (!$pb) {
            return response()->json(['success' => false, 'message' => 'PB tidak ditemukan.'], 404);
        }

        $details = DB::table('trBPBDetail')
            ->where('trBPB_id', $id)
            ->orWhere('trbpb_id', $id)
            ->select('id', 'nama_barang', 'jumlah', 'satuan', 'unit_price', 'total_price', 'keterangan', 'is_high_value')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nama_barang' => $item->nama_barang,
                'jumlah' => (float) $item->jumlah,
                'satuan' => $item->satuan,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'is_high_value' => (bool) $item->is_high_value,
                'keterangan' => $item->keterangan,
            ]);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->pbPayload($pb), ['details' => $details]),
        ]);
    }

    public function pbApprove(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        try {
            DB::beginTransaction();

            $pb = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();

            if ($auth->role === 'section_head') {
                if (!$pb || $pb->status !== 'verification' || (int) ($pb->verification_section_head_id ?? 0) !== (int) $auth->id) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'PB tidak tersedia untuk verifikasi user ini.'], 404);
                }

                DB::table('trBPB')->where('id', $id)->update([
                    'status' => 'pending',
                    'approval_current_level' => self::LEVEL_ONE,
                    'verification_status' => 'verified',
                    'verified_by' => $auth->id,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();

                app(FirebasePushService::class)->sendToRole('approval', 'PB Menunggu Approval L1', ($pb->nomor_pb ?? 'PB') . ' sudah diverifikasi Section Head.', [
                    'type' => 'PB',
                    'target' => 'approval',
                    'record_id' => $id,
                    'nomor' => $pb->nomor_pb ?? '',
                    'level' => 1,
                ]);

                return response()->json(['success' => true, 'message' => 'PB berhasil diverifikasi dan dikirim ke Approval L1.']);
            }

            $guard = $this->guardPbApproval($pb, $auth);

            if ($guard) {
                DB::rollBack();
                return $guard;
            }

            $currentLevel = (int) ($pb->approval_current_level ?? self::LEVEL_ONE);
            $requiredLevel = (int) ($pb->approval_level_required ?? self::LEVEL_ONE);
            $update = ['updated_at' => now()];

            if ($currentLevel === self::LEVEL_ONE) {
                $update['approval_level_1_at'] = now();
                $update['approval_level_1_by'] = $auth->id;

                if ($requiredLevel >= self::LEVEL_TWO) {
                    $update['approval_current_level'] = self::LEVEL_TWO;
                    $message = 'Approval L1 berhasil. PB menunggu Approval L2.';
                    $notifyRole = 'approval2';
                } else {
                    $update['status'] = 'approved';
                    $update['approved_at'] = now();
                    $update['approved_by'] = $auth->id;
                    $message = 'PB berhasil disetujui.';
                    $notifyRole = null;
                }
            } else {
                $update['approval_level_2_at'] = now();
                $update['approval_level_2_by'] = $auth->id;
                $update['status'] = 'approved';
                $update['approved_at'] = now();
                $update['approved_by'] = $auth->id;
                $message = 'Approval L2 berhasil. PB sudah disetujui.';
                $notifyRole = null;
            }

            DB::table('trBPB')->where('id', $id)->update($update);
            DB::commit();

            if ($notifyRole) {
                app(FirebasePushService::class)->sendToRole(
                    $notifyRole,
                    'PB Menunggu Approval L2',
                    ($pb->nomor_pb ?? 'PB') . ' bernilai > 10 juta dan perlu keputusan L2.',
                    [
                        'type' => 'PB',
                        'target' => 'approval',
                        'record_id' => $id,
                        'nomor' => $pb->nomor_pb ?? '',
                        'level' => 2,
                    ]
                );
            }

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile PB approve error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal approve PB.'], 500);
        }
    }

    public function pbReject(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            $pb = DB::table('trBPB')->where('id', $id)->lockForUpdate()->first();

            if ($auth->role === 'section_head') {
                if (!$pb || $pb->status !== 'verification' || (int) ($pb->verification_section_head_id ?? 0) !== (int) $auth->id) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'PB tidak tersedia untuk user ini.'], 404);
                }

                DB::table('trBPB')->where('id', $id)->update([
                    'status' => 'rejected',
                    'verification_status' => 'rejected',
                    'verification_notes' => $data['alasan'],
                    'rejection_reason' => $data['alasan'],
                    'rejected_at' => now(),
                    'rejected_by' => $auth->id,
                    'updated_at' => now(),
                ]);

                DB::commit();

                return response()->json(['success' => true, 'message' => 'PB berhasil ditolak di tahap verifikasi.']);
            }

            $guard = $this->guardPbApproval($pb, $auth);

            if ($guard) {
                DB::rollBack();
                return $guard;
            }

            DB::table('trBPB')->where('id', $id)->update([
                'status' => 'rejected',
                'rejection_reason' => $data['alasan'],
                'rejected_at' => now(),
                'rejected_by' => $auth->id,
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'PB berhasil ditolak.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile PB reject error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal reject PB.'], 500);
        }
    }

    public function woIndex(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role === 'section_head') {
            return response()->json(['success' => false, 'message' => 'Section Head hanya melihat WO assigned.'], 403);
        }

        if ($auth->role !== 'approval') {
            return response()->json(['success' => false, 'message' => 'Approval L2 tidak punya antrian WO.'], 403);
        }

        $items = $this->woBaseQuery()->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items->getCollection()->map(fn ($item) => $this->woPayload($item))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function woShow(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role === 'section_head') {
            return response()->json(['success' => false, 'message' => 'Section Head hanya melihat WO assigned.'], 403);
        }

        if ($auth->role !== 'approval') {
            return response()->json(['success' => false, 'message' => 'Approval L2 tidak punya akses WO.'], 403);
        }

        $wo = $this->woBaseQuery()->where('trWorkOrder.id', $id)->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->woPayload($wo)]);
    }

    public function woDocument(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $query = DB::table('trWorkOrder')->where('id', $id);
        if ($auth->role === 'approval') {
            $query->where(function ($scope) use ($auth) {
                $scope->where('status', 'submitted')
                    ->orWhere('approved_by', $auth->id)
                    ->orWhere('rejected_by', $auth->id);
            });
        } elseif ($auth->role === 'section_head') {
            $query->where('assigned_regu', $auth->name);
        } elseif ($this->isEngineeringMobileUser($auth)) {
            $query->where('created_by', $auth->id);
        } else {
            return response()->json(['success' => false, 'message' => 'Approval L2 tidak punya akses WO.'], 403);
        }

        $wo = $query->first();

        if (!$wo || !$wo->file_path || !Storage::disk('public')->exists($wo->file_path)) {
            return response()->json(['success' => false, 'message' => 'File WO tidak ditemukan.'], 404);
        }

        $fullPath = Storage::disk('public')->path($wo->file_path);
        $mimeType = Storage::disk('public')->mimeType($wo->file_path) ?: 'application/octet-stream';
        $fileName = $wo->file_name ?: basename($wo->file_path);

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pelaksana(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        $items = DB::table('mtWorkOrderPelaksana')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function woApprove(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'approval') {
            return response()->json(['success' => false, 'message' => 'Hanya Approval L1 yang bisa approve WO.'], 403);
        }

        $pelaksana = DB::table('mtWorkOrderPelaksana')
            ->where('is_active', true)
            ->pluck('nama')
            ->all();

        $data = $request->validate([
            'pelaksana' => ['nullable', 'string', Rule::in($pelaksana)],
            'assigned_regu' => ['nullable', 'string', Rule::in($pelaksana)],
            'delegation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $selected = $data['pelaksana'] ?? $data['assigned_regu'] ?? null;
        if (!$selected) {
            return response()->json(['success' => false, 'message' => 'Pelaksana wajib dipilih.'], 422);
        }

        $wo = DB::table('trWorkOrder')->where('id', $id)->first();
        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Hanya WO submitted yang bisa diapprove.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $id)->update([
            'status' => 'approved',
            'approved_by' => $auth->id,
            'approved_at' => now(),
            'progress_status' => 'open',
            'open_at' => now(),
            'assigned_regu' => $selected,
            'assigned_by' => $auth->id,
            'assigned_at' => now(),
            'delegation_notes' => $data['delegation_notes'] ?? null,
            'updated_at' => now(),
        ]);

        app(FirebasePushService::class)->sendToUserName(
            $selected,
            'WO Assigned',
            ($wo->nomor ?? 'WO') . ' diassign ke ' . $selected . '.',
            [
                'type' => 'WO',
                'target' => 'section_wo',
                'record_id' => $id,
                'nomor' => $wo->nomor ?? '',
                'sound_type' => 'work_order',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'WO berhasil diapprove dan diassign ke pelaksana ' . $selected . '.',
        ]);
    }

    public function woReject(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'approval') {
            return response()->json(['success' => false, 'message' => 'Hanya Approval L1 yang bisa reject WO.'], 403);
        }

        $data = $request->validate([
            'rejection_notes' => ['required', 'string', 'max:500'],
        ]);

        $wo = DB::table('trWorkOrder')->where('id', $id)->first();
        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'submitted') {
            return response()->json(['success' => false, 'message' => 'Hanya WO submitted yang bisa direject.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $id)->update([
            'status' => 'rejected',
            'rejected_by' => $auth->id,
            'rejected_at' => now(),
            'rejection_notes' => $data['rejection_notes'],
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'WO berhasil ditolak.']);
    }

    public function sectionWorkOrders(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $items = $this->sectionWorkOrderQuery($auth)
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items->getCollection()->map(fn ($item) => [
                'id' => (int) $item->id,
                'nomor' => $item->nomor,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'progress_status' => $item->progress_status ?: 'open',
                'assigned_at' => $item->assigned_at,
                'delegation_notes' => $item->delegation_notes ?? null,
                'approved_at' => $item->approved_at,
                'hold_at' => $item->hold_at ?? null,
                'resumed_at' => $item->resumed_at ?? null,
                'created_by_name' => $item->created_by_name,
                'photo_count' => $this->workOrderPhotoCount((int) $item->id),
                'photos' => $this->workOrderPhotos((int) $item->id),
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function sectionWorkOrderHistory(Request $request)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $items = DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.assigned_regu', $auth->name)
            ->where('trWorkOrder.status', 'approved')
            ->where('trWorkOrder.progress_status', 'closed')
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.deskripsi',
                'trWorkOrder.progress_status',
                'trWorkOrder.assigned_at',
                'trWorkOrder.approved_at',
                'trWorkOrder.closed_at',
                'trWorkOrder.delegation_notes',
                'trWorkOrder.progress_notes',
                'creator.name as created_by_name'
            )
            ->orderByDesc('trWorkOrder.closed_at')
            ->limit((int) $request->query('limit', 50))
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nomor' => $item->nomor,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'progress_status' => $item->progress_status ?: 'closed',
                'assigned_at' => $item->assigned_at,
                'approved_at' => $item->approved_at,
                'closed_at' => $item->closed_at,
                'delegation_notes' => $item->delegation_notes,
                'progress_notes' => $item->progress_notes,
                'created_by_name' => $item->created_by_name,
                'photos' => $this->workOrderPhotos((int) $item->id),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function sectionWorkOrderProgress(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $wo = DB::table('trWorkOrder')
            ->where('id', $id)
            ->where('assigned_regu', $auth->name)
            ->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO assigned tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'WO ini belum dalam status approved.'], 422);
        }

        if (($wo->progress_status ?: 'open') === 'closed') {
            return response()->json(['success' => false, 'message' => 'WO ini sudah selesai.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $wo->id)->update([
            'progress_status' => 'progress',
            'open_at' => $wo->open_at ?: now(),
            'progress_at' => $wo->progress_at ?: now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WO sudah masuk status In Progress.',
        ]);
    }

    public function sectionWorkOrderHold(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $wo = DB::table('trWorkOrder')
            ->where('id', $id)
            ->where('assigned_regu', $auth->name)
            ->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO assigned tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'WO ini belum dalam status approved.'], 422);
        }

        if (($wo->progress_status ?: 'open') !== 'progress') {
            return response()->json(['success' => false, 'message' => 'Hanya WO In Progress yang bisa di-Hold.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $wo->id)->update([
            'progress_status' => 'hold',
            'hold_at' => now(),
            'closed_at' => null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WO berhasil di-Hold.',
        ]);
    }

    public function sectionWorkOrderResume(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $wo = DB::table('trWorkOrder')
            ->where('id', $id)
            ->where('assigned_regu', $auth->name)
            ->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO assigned tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'WO ini belum dalam status approved.'], 422);
        }

        if (($wo->progress_status ?: 'open') !== 'hold') {
            return response()->json(['success' => false, 'message' => 'Hanya WO Hold yang bisa di-Resume.'], 422);
        }

        DB::table('trWorkOrder')->where('id', $wo->id)->update([
            'progress_status' => 'progress',
            'open_at' => $wo->open_at ?: now(),
            'progress_at' => $wo->progress_at ?: now(),
            'resumed_at' => now(),
            'closed_at' => null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'WO berhasil dilanjutkan ke In Progress.',
        ]);
    }

    public function sectionWorkOrderPhotos(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        if (!$request->hasFile('photos')) {
            return response()->json(['success' => false, 'message' => 'Minimal 1 foto wajib diupload.'], 422);
        }

        $request->validate([
            'photos.*' => ['image', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $files = $request->file('photos');
        if (!is_array($files)) {
            $files = [$files];
        }

        $wo = DB::table('trWorkOrder')
            ->where('id', $id)
            ->where('assigned_regu', $auth->name)
            ->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO assigned tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'WO ini belum dalam status approved.'], 422);
        }

        if (($wo->progress_status ?: 'open') !== 'progress') {
            return response()->json(['success' => false, 'message' => 'Ubah status WO ke In Progress sebelum upload foto hasil.'], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($files as $index => $file) {
                $name = preg_replace('/[^A-Za-z0-9_\\-]/', '_', $wo->nomor) . '-' . now()->format('YmdHis') . '-' . ($index + 1) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('work-order-photos', $name, 'public');

                DB::table('trWorkOrderPhotos')->insert([
                    'work_order_id' => $wo->id,
                    'uploaded_by' => $auth->id,
                    'file_path' => $path,
                    'file_name' => $name,
                    'notes' => $request->input('notes'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('trWorkOrder')->where('id', $wo->id)->update([
                'progress_notes' => $request->input('notes') ?: $wo->progress_notes,
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($files) . ' foto berhasil diupload. Klik Done jika pekerjaan sudah selesai.',
                'photo_count' => $this->workOrderPhotoCount((int) $wo->id),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile section WO photo upload error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal upload foto hasil pekerjaan.'], 500);
        }
    }

    public function sectionWorkOrderDone(Request $request, int $id)
    {
        $auth = $this->mobileUser($request);

        if (!$auth) {
            return $this->unauthorized();
        }

        if ($auth->role !== 'section_head') {
            return response()->json(['success' => false, 'message' => 'Endpoint ini hanya untuk Section Head.'], 403);
        }

        $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $wo = DB::table('trWorkOrder')
            ->where('id', $id)
            ->where('assigned_regu', $auth->name)
            ->first();

        if (!$wo) {
            return response()->json(['success' => false, 'message' => 'WO assigned tidak ditemukan.'], 404);
        }

        if ($wo->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'WO ini belum dalam status approved.'], 422);
        }

        if (($wo->progress_status ?: 'open') !== 'progress') {
            return response()->json(['success' => false, 'message' => 'Ubah status WO ke In Progress sebelum upload foto hasil.'], 422);
        }

        if ($this->workOrderPhotoCount((int) $wo->id) < 1) {
            return response()->json(['success' => false, 'message' => 'Minimal 1 foto wajib diupload sebelum WO ditutup.'], 422);
        }

        try {
            DB::beginTransaction();

            DB::table('trWorkOrder')->where('id', $wo->id)->update([
                'progress_status' => 'closed',
                'progress_notes' => $request->input('notes'),
                'open_at' => $wo->open_at ?: now(),
                'progress_at' => $wo->progress_at ?: now(),
                'closed_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'WO selesai.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Mobile section WO done error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal menyelesaikan WO.'], 500);
        }
    }

    private function mobileUser(Request $request): ?object
    {
        $token = $request->bearerToken()
            ?: (string) $request->query('token', '')
            ?: (string) $request->input('token', '');

        $token = trim((string) $token);

        if (!$token) {
            return null;
        }

        $record = DB::table('mobile_api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (!$record || ($record->expires_at && Carbon::parse($record->expires_at)->isPast())) {
            return null;
        }

        $user = DB::table('users')->where('id', $record->user_id)->first();

        if (!$user || !$this->isAllowedMobileUser($user)) {
            return null;
        }

        if (Schema::hasColumn('users', 'is_active') && ! (bool) ($user->is_active ?? true)) {
            DB::table('mobile_api_tokens')->where('id', $record->id)->delete();
            return null;
        }

        DB::table('mobile_api_tokens')->where('id', $record->id)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);

        $user->token_id = $record->id;

        return $user;
    }

    private function unauthorized()
    {
        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid atau sesi mobile sudah berakhir.',
        ], 401);
    }

    private function userPayload(object $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => $this->mobileRoleLabel($user),
        ];
    }

    private function isAllowedMobileUser(object $user): bool
    {
        return in_array($user->role, ['approval', 'approval2', 'section_head'], true)
            || $this->isEngineeringMobileUser($user);
    }

    private function isEngineeringMobileUser(object $user): bool
    {
        if (($user->role ?? '') !== 'user') {
            return false;
        }

        if (($user->username ?? '') === 'adm-engineering') {
            return true;
        }

        return strtolower((string) ($user->department_code ?? '')) === 'engineering';
    }

    private function mobileSectionHeads()
    {
        return DB::table('users')
            ->select('id', 'name', 'username')
            ->where('role', 'section_head')
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->get();
    }

    private function mobileRoleLabel(object $user): string
    {
        if ($this->isEngineeringMobileUser($user)) {
            return 'Admin Engineering';
        }

        return $user->role === 'approval2'
            ? 'Approval Level 2'
            : ($user->role === 'section_head' ? 'Section Head' : 'Approval Level 1');
    }

    private function sectionWorkOrderQuery(object $user)
    {
        return DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.assigned_regu', $user->name)
            ->where('trWorkOrder.status', 'approved')
            ->where(function ($q) {
                $q->whereNull('trWorkOrder.progress_status')
                    ->orWhereIn('trWorkOrder.progress_status', ['open', 'progress', 'hold']);
            })
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.deskripsi',
                'trWorkOrder.progress_status',
                'trWorkOrder.assigned_at',
                'trWorkOrder.delegation_notes',
                'trWorkOrder.approved_at',
                'trWorkOrder.hold_at',
                'trWorkOrder.resumed_at',
                'trWorkOrder.created_at',
                'creator.name as created_by_name'
            )
            ->orderByDesc('trWorkOrder.assigned_at')
            ->orderByDesc('trWorkOrder.approved_at');
    }

    private function pbBaseQuery(object $user)
    {
        $query = DB::table('trBPB');

        if ($user->role === 'section_head') {
            return $query
                ->where('status', 'verification')
                ->where('verification_status', 'pending')
                ->where('verification_section_head_id', $user->id);
        }

        return $query
            ->where('status', 'pending')
            ->when($user->role === 'approval', fn ($q) => $q->where('approval_current_level', self::LEVEL_ONE))
            ->when($user->role === 'approval2', fn ($q) => $q
                ->where('approval_current_level', self::LEVEL_TWO)
                ->where('approval_level_required', '>=', self::LEVEL_TWO)
                ->where('has_high_value_item', true));
    }

    private function mobileApprovalBudgetSnapshot(): array
    {
        if (!Schema::hasTable('trBPB') || !Schema::hasTable('trBPBDetail')) {
            return [
                'approved_direct_l1' => 0,
                'waiting_l2' => 0,
                'approved_l2' => 0,
                'rejected' => 0,
                'total_used' => 0,
            ];
        }

        $start = now()->startOfYear()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $approvedDirectL1 = $this->mobileSumPbBudget(function ($query) use ($start, $end) {
            $query->where('trBPB.approval_level_required', self::LEVEL_ONE)
                ->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
                ->where(function ($approval) {
                    $approval->whereNotNull('trBPB.approval_level_1_at')
                        ->orWhereNotNull('trBPB.approved_at');
                })
                ->whereRaw('COALESCE(trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end]);
        });

        $waitingL2 = $this->mobileSumPbBudget(function ($query) use ($start, $end) {
            $query->where('trBPB.approval_level_required', '>=', self::LEVEL_TWO)
                ->where('trBPB.status', 'pending')
                ->where('trBPB.approval_current_level', self::LEVEL_TWO)
                ->whereNotNull('trBPB.approval_level_1_at')
                ->whereBetween('trBPB.approval_level_1_at', [$start, $end]);
        });

        $approvedL2 = $this->mobileSumPbBudget(function ($query) use ($start, $end) {
            $query->where('trBPB.approval_level_required', '>=', self::LEVEL_TWO)
                ->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
                ->whereNotNull('trBPB.approval_level_1_at')
                ->whereNotNull('trBPB.approval_level_2_at')
                ->whereBetween('trBPB.approval_level_2_at', [$start, $end]);
        });

        $rejected = $this->mobileSumPbBudget(function ($query) use ($start, $end) {
            $query->where('trBPB.status', 'rejected')
                ->whereBetween('trBPB.rejected_at', [$start, $end]);
        });

        return [
            'approved_direct_l1' => $approvedDirectL1,
            'waiting_l2' => $waitingL2,
            'approved_l2' => $approvedL2,
            'rejected' => $rejected,
            'total_used' => $approvedDirectL1 + $approvedL2,
        ];
    }

    private function mobileDashboardPeriod(Request $request): array
    {
        $mode = $request->get('period_mode') === 'month' ? 'month' : 'ytd';
        $month = $request->get('period_month') ?: now()->format('Y-m');

        try {
            $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $monthDate = now()->startOfMonth();
            $month = $monthDate->format('Y-m');
        }

        if ($mode === 'month') {
            $start = $monthDate->copy()->startOfMonth();
            $end = $monthDate->copy()->endOfMonth();
            $label = $start->translatedFormat('F Y');
        } else {
            $start = now()->startOfYear();
            $end = now();
            $label = 'Year to Date ' . now()->format('Y');
        }

        return [
            'mode' => $mode,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    private function mobileSectionHeadBudgetSnapshot(object $user, ?array $period = null): array
    {
        if (!Schema::hasTable('trBPB') || !Schema::hasTable('trBPBDetail') || !Schema::hasColumn('trBPB', 'verification_section_head_id')) {
            return [
                'approved_count' => 0,
                'waiting_count' => 0,
                'rejected_count' => 0,
                'total_used' => 0,
                'waiting' => 0,
                'rejected' => 0,
            ];
        }

        $start = ($period['start'] ?? now()->startOfYear())->copy()->startOfDay()->toDateTimeString();
        $end = ($period['end'] ?? now())->copy()->endOfDay()->toDateTimeString();
        $sectionHeadId = (int) $user->id;

        $summarize = function (callable $filter) use ($sectionHeadId): array {
            $query = DB::table('trBPB')
                ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
                ->where('trBPB.verification_section_head_id', $sectionHeadId);

            $filter($query);

            $row = $query
                ->selectRaw('COUNT(DISTINCT trBPB.id) as pb_count, COALESCE(SUM(d.total_price), 0) as amount')
                ->first();

            return [
                'count' => (int) ($row->pb_count ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        };

        $approved = $summarize(function ($query) use ($start, $end) {
            $query->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
                ->where(function ($approval) {
                    $approval->where(function ($direct) {
                        $direct->where('trBPB.approval_level_required', self::LEVEL_ONE)
                            ->where(function ($approvedAt) {
                                $approvedAt->whereNotNull('trBPB.approval_level_1_at')
                                    ->orWhereNotNull('trBPB.approved_at');
                            });
                    })->orWhere(function ($l2) {
                        $l2->where('trBPB.approval_level_required', '>=', self::LEVEL_TWO)
                            ->whereNotNull('trBPB.approval_level_2_at');
                    });
                })
                ->whereRaw('COALESCE(trBPB.approval_level_2_at, trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end]);
        });

        $waiting = $summarize(function ($query) use ($start, $end) {
            $query->whereIn('trBPB.status', ['verification', 'pending'])
                ->whereRaw('COALESCE(trBPB.verified_at, trBPB.tanggal_permintaan, trBPB.created_at) BETWEEN ? AND ?', [$start, $end]);
        });

        $rejected = $summarize(function ($query) use ($start, $end) {
            $query->where('trBPB.status', 'rejected')
                ->whereBetween('trBPB.rejected_at', [$start, $end]);
        });

        return [
            'approved_count' => $approved['count'],
            'waiting_count' => $waiting['count'],
            'rejected_count' => $rejected['count'],
            'total_used' => $approved['amount'],
            'waiting' => $waiting['amount'],
            'rejected' => $rejected['amount'],
        ];
    }

    private function mobileApprovalQueueSummary(object $user): array
    {
        $start = now()->startOfYear()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $pbPending = $this->pbBaseQuery($user)->count();

        $pbApproved = DB::table('trBPB')
            ->when($user->role === 'approval', fn ($q) => $q->where('approval_level_1_by', $user->id))
            ->when($user->role === 'approval2', fn ($q) => $q->where('approval_level_2_by', $user->id))
            ->whereIn('status', ['approved', 'in_progress', 'completed'])
            ->whereRaw('COALESCE(approval_level_2_at, approval_level_1_at, approved_at) BETWEEN ? AND ?', [$start, $end])
            ->count();

        $pbProgress = DB::table('trBPB')
            ->when($user->role === 'approval', fn ($q) => $q->where('approval_level_1_by', $user->id))
            ->when($user->role === 'approval2', fn ($q) => $q->where('approval_level_2_by', $user->id))
            ->whereIn('status', ['approved', 'in_progress', 'progress'])
            ->whereRaw('COALESCE(approval_level_2_at, approval_level_1_at, approved_at) BETWEEN ? AND ?', [$start, $end])
            ->count();

        $pbDone = DB::table('trBPB')
            ->when($user->role === 'approval', fn ($q) => $q->where('approval_level_1_by', $user->id))
            ->when($user->role === 'approval2', fn ($q) => $q->where('approval_level_2_by', $user->id))
            ->whereIn('status', ['completed', 'done', 'fulfilled'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        $pbRejected = DB::table('trBPB')
            ->where('rejected_by', $user->id)
            ->where('status', 'rejected')
            ->whereBetween('rejected_at', [$start, $end])
            ->count();

        $woPending = $user->role === 'approval'
            ? DB::table('trWorkOrder')->where('status', 'submitted')->count()
            : 0;

        $woApproved = $user->role === 'approval'
            ? DB::table('trWorkOrder')
                ->where('approved_by', $user->id)
                ->where('status', 'approved')
                ->whereBetween('approved_at', [$start, $end])
                ->count()
            : 0;

        $woProgress = $user->role === 'approval'
            ? DB::table('trWorkOrder')
                ->where('approved_by', $user->id)
                ->where('status', 'approved')
                ->whereIn('progress_status', ['open', 'progress', 'hold'])
                ->whereBetween('approved_at', [$start, $end])
                ->count()
            : 0;

        $woDone = $user->role === 'approval'
            ? DB::table('trWorkOrder')
                ->where('approved_by', $user->id)
                ->where('status', 'approved')
                ->where('progress_status', 'closed')
                ->whereBetween('closed_at', [$start, $end])
                ->count()
            : 0;

        $woRejected = $user->role === 'approval'
            ? DB::table('trWorkOrder')
                ->where('rejected_by', $user->id)
                ->where('status', 'rejected')
                ->whereBetween('rejected_at', [$start, $end])
                ->count()
            : 0;

        return [
            'pb_pending' => (int) $pbPending,
            'pb_approved' => (int) $pbApproved,
            'pb_progress' => (int) $pbProgress,
            'pb_done' => (int) $pbDone,
            'pb_rejected' => (int) $pbRejected,
            'wo_pending' => (int) $woPending,
            'wo_approved' => (int) $woApproved,
            'wo_progress' => (int) $woProgress,
            'wo_done' => (int) $woDone,
            'wo_rejected' => (int) $woRejected,
        ];
    }

    private function mobileDashboardDetailItems(object $user, string $type, string $status, string $source = '')
    {
        $start = now()->startOfYear()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        if ($type === 'PB') {
            if ($status === 'pending') {
                return $this->pbListQuery($user)
                    ->limit(80)
                    ->get()
                    ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                        'status' => 'pending',
                        'history_type' => 'PB',
                    ]));
            }

            $query = $this->pbHistoryQuery($user);
            if ($status === 'approved') {
                $query->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
                    ->whereRaw('COALESCE(trBPB.approval_level_2_at, trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end]);

                if ($source !== '' && $user->role === 'approval') {
                    $this->applyPbWorkOrderSourceFilter($query, $source);
                }
            } elseif ($status === 'progress') {
                $query->whereIn('trBPB.status', ['approved', 'in_progress', 'progress'])
                    ->whereRaw('COALESCE(trBPB.approval_level_2_at, trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end]);
            } elseif ($status === 'done') {
                $query->whereIn('trBPB.status', ['completed', 'done', 'fulfilled'])
                    ->whereBetween('trBPB.updated_at', [$start, $end]);
            } else {
                $query->where('trBPB.rejected_by', $user->id)
                    ->where('trBPB.status', 'rejected')
                    ->whereBetween('trBPB.rejected_at', [$start, $end]);
            }

            return $query->limit(80)
                ->get()
                ->map(fn ($item) => array_merge($this->pbPayload($item, true), [
                    'status' => $item->status,
                    'approved_at' => $item->approved_at,
                    'rejected_at' => $item->rejected_at,
                    'approval_level_1_at' => $item->approval_level_1_at,
                    'approval_level_2_at' => $item->approval_level_2_at,
                    'rejection_reason' => $item->rejection_reason,
                    'history_type' => 'PB',
                ]));
        }

        if ($user->role !== 'approval') {
            return collect();
        }

        if ($status === 'pending') {
            return $this->woBaseQuery()
                ->limit(80)
                ->get()
                ->map(fn ($item) => array_merge($this->woPayload($item), [
                    'status' => 'submitted',
                    'history_type' => 'WO',
                ]));
        }

        $query = $this->woHistoryQuery($user);
        if ($status === 'approved') {
            $query->where('trWorkOrder.status', 'approved')
                ->whereBetween('trWorkOrder.approved_at', [$start, $end]);
        } elseif ($status === 'progress') {
            $query->where('trWorkOrder.status', 'approved')
                ->whereIn('trWorkOrder.progress_status', ['open', 'progress', 'hold'])
                ->whereBetween('trWorkOrder.approved_at', [$start, $end]);
        } elseif ($status === 'done') {
            $query->where('trWorkOrder.status', 'approved')
                ->where('trWorkOrder.progress_status', 'closed')
                ->whereBetween('trWorkOrder.closed_at', [$start, $end]);
        } else {
            $query->where('trWorkOrder.rejected_by', $user->id)
                ->where('trWorkOrder.status', 'rejected')
                ->whereBetween('trWorkOrder.rejected_at', [$start, $end]);
        }

        return $query->limit(80)
            ->get()
            ->map(fn ($item) => array_merge($this->woPayload($item), [
                'approved_at' => $item->approved_at,
                'rejected_at' => $item->rejected_at,
                'assigned_regu' => $item->assigned_regu,
                'delegation_notes' => $item->delegation_notes ?? null,
                'rejection_notes' => $item->rejection_notes,
                'progress_status' => $item->progress_status ?? null,
                'closed_at' => $item->closed_at ?? null,
                'history_type' => 'WO',
            ]));
    }

    private function mobileDashboardDetailTitle(string $type, string $status): string
    {
        if ($type === 'PB' && $status === 'progress') {
            return 'PB Fulfillment';
        }

        $labels = [
            'pending' => 'Menunggu',
            'approved' => 'Approved',
            'progress' => 'Progress',
            'done' => 'Done',
            'rejected' => 'Rejected',
        ];

        return $type . ' ' . ($labels[$status] ?? ucfirst($status));
    }

    private function applyPbWorkOrderSourceFilter($query, string $source): void
    {
        $hasReferenceId = Schema::hasColumn('trBPB', 'reference_wo_id');
        $hasReferenceNumber = Schema::hasColumn('trBPB', 'reference_wo_number');

        if (!$hasReferenceId && !$hasReferenceNumber) {
            return;
        }

        if ($source === 'with_wo') {
            $query->where(function ($sourceQuery) use ($hasReferenceId, $hasReferenceNumber) {
                if ($hasReferenceId) {
                    $sourceQuery->whereNotNull('trBPB.reference_wo_id');
                }
                if ($hasReferenceNumber) {
                    $method = $hasReferenceId ? 'orWhere' : 'where';
                    $sourceQuery->{$method}(function ($number) {
                        $number->whereNotNull('trBPB.reference_wo_number')
                            ->where('trBPB.reference_wo_number', '<>', '');
                    });
                }
            });

            return;
        }

        if ($source === 'without_wo') {
            $query->where(function ($sourceQuery) use ($hasReferenceId, $hasReferenceNumber) {
                if ($hasReferenceId) {
                    $sourceQuery->whereNull('trBPB.reference_wo_id');
                }
                if ($hasReferenceNumber) {
                    $sourceQuery->where(function ($number) {
                        $number->whereNull('trBPB.reference_wo_number')
                            ->orWhere('trBPB.reference_wo_number', '');
                    });
                }
            });
        }
    }

    private function mobileApprovalBudgetSourceBreakdown(): array
    {
        $start = now()->startOfYear()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $base = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
            ->where(function ($query) {
                $query->where(function ($direct) {
                    $direct->where('trBPB.approval_level_required', self::LEVEL_ONE)
                        ->where(function ($approval) {
                            $approval->whereNotNull('trBPB.approval_level_1_at')
                                ->orWhereNotNull('trBPB.approved_at');
                        });
                })->orWhere(function ($l2) {
                    $l2->where('trBPB.approval_level_required', '>=', self::LEVEL_TWO)
                        ->whereNotNull('trBPB.approval_level_2_at');
                });
            })
            ->whereRaw('COALESCE(trBPB.approval_level_2_at, trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end]);

        $summarize = function ($query): array {
            $row = $query
                ->selectRaw('COUNT(DISTINCT trBPB.id) as pb_count, COALESCE(SUM(d.total_price), 0) as amount')
                ->first();

            return [
                'count' => (int) ($row->pb_count ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        };

        $hasReferenceId = Schema::hasColumn('trBPB', 'reference_wo_id');
        $hasReferenceNumber = Schema::hasColumn('trBPB', 'reference_wo_number');

        if (!$hasReferenceId && !$hasReferenceNumber) {
            return [
                'with_wo' => ['count' => 0, 'amount' => 0],
                'without_wo' => $summarize(clone $base),
            ];
        }

        $withWo = clone $base;
        $this->applyPbWorkOrderSourceFilter($withWo, 'with_wo');

        $withoutWo = clone $base;
        $this->applyPbWorkOrderSourceFilter($withoutWo, 'without_wo');

        return [
            'with_wo' => $summarize($withWo),
            'without_wo' => $summarize($withoutWo),
        ];
    }

    private function mobileGreeting(): string
    {
        $hour = (int) now()->timezone('Asia/Jakarta')->format('H');

        if ($hour < 11) {
            return 'Selamat pagi';
        }

        if ($hour < 15) {
            return 'Selamat siang';
        }

        if ($hour < 18) {
            return 'Selamat sore';
        }

        return 'Selamat malam';
    }

    private function mobileSumPbBudget(callable $filter): float
    {
        $query = DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id');

        $filter($query);

        return (float) $query->sum('d.total_price');
    }

    private function mobileBudgetBySectionHead(float $totalUsed): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $start = now()->startOfYear()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        return DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->leftJoin('users as verifier', 'trBPB.verification_section_head_id', '=', 'verifier.id')
            ->whereIn('trBPB.status', ['approved', 'in_progress', 'completed'])
            ->where(function ($query) {
                $query->where(function ($direct) {
                    $direct->where('trBPB.approval_level_required', self::LEVEL_ONE)
                        ->where(function ($approval) {
                            $approval->whereNotNull('trBPB.approval_level_1_at')
                                ->orWhereNotNull('trBPB.approved_at');
                        });
                })->orWhere(function ($l2) {
                    $l2->where('trBPB.approval_level_required', '>=', self::LEVEL_TWO)
                        ->whereNotNull('trBPB.approval_level_2_at');
                });
            })
            ->whereRaw('COALESCE(trBPB.approval_level_2_at, trBPB.approval_level_1_at, trBPB.approved_at) BETWEEN ? AND ?', [$start, $end])
            ->select(
                DB::raw("COALESCE(verifier.name, 'Belum ada Section Head') as name"),
                DB::raw("COALESCE(verifier.username, '-') as username"),
                DB::raw('COUNT(DISTINCT trBPB.id) as pb_count'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as amount')
            )
            ->groupBy('verifier.id', 'verifier.name', 'verifier.username')
            ->orderByDesc('amount')
            ->limit(8)
            ->get()
            ->map(function ($item) use ($totalUsed) {
                $amount = (float) $item->amount;

                return [
                    'name' => $item->name,
                    'username' => $item->username,
                    'pb_count' => (int) $item->pb_count,
                    'amount' => $amount,
                    'percent' => $totalUsed > 0 ? round(($amount / $totalUsed) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function pbListQuery(object $user)
    {
        return $this->pbBaseQuery($user)
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw("COALESCE(MAX(mtMesin.msnCode), MAX(mtBangunan.buildCode), '') as tujuan_kode"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value'),
                DB::raw('COALESCE(MAX(d.unit_price), 0) as max_unit_price')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at'
            )
            ->orderBy('trBPB.tanggal_diperlukan')
            ->orderByDesc('trBPB.created_at');
    }

    private function pbHistoryQuery(object $user)
    {
        return DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', 'trBPB.id', '=', 'd.trBPB_id')
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->when($user->role === 'approval', fn ($q) => $q->where(function ($query) use ($user) {
                $query->where('trBPB.approval_level_1_by', $user->id)
                    ->orWhere('trBPB.rejected_by', $user->id);
            }))
            ->when($user->role === 'approval2', fn ($q) => $q->where(function ($query) use ($user) {
                $query->where('trBPB.approval_level_2_by', $user->id)
                    ->orWhere('trBPB.rejected_by', $user->id);
            }))
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.rejected_at',
                'trBPB.approval_level_1_at',
                'trBPB.approval_level_2_at',
                'trBPB.rejection_reason',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw("COALESCE(MAX(mtMesin.msnCode), MAX(mtBangunan.buildCode), '') as tujuan_kode"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value'),
                DB::raw('COALESCE(MAX(d.unit_price), 0) as max_unit_price')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.rejected_at',
                'trBPB.approval_level_1_at',
                'trBPB.approval_level_2_at',
                'trBPB.rejection_reason'
            )
            ->orderByDesc(DB::raw('COALESCE(trBPB.approved_at, trBPB.rejected_at, trBPB.created_at)'));
    }

    private function sectionPbVerificationHistoryRows(object $user, int $limit = 80)
    {
        return DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', function ($join) {
                $join->on('trBPB.id', '=', 'd.trBPB_id')
                    ->orOn('trBPB.id', '=', 'd.trbpb_id');
            })
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->where('trBPB.verification_section_head_id', $user->id)
            ->whereIn('trBPB.verification_status', ['verified', 'rejected'])
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.verification_status',
                'trBPB.verification_notes',
                'trBPB.verified_at',
                'trBPB.rejected_at',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.approval_level_1_at',
                'trBPB.approval_level_2_at',
                'trBPB.rejection_reason',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw("COALESCE(MAX(mtMesin.msnCode), MAX(mtBangunan.buildCode), '') as tujuan_kode"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value'),
                DB::raw('COALESCE(MAX(d.unit_price), 0) as max_unit_price')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.untuk',
                'trBPB.jenis_pekerjaan',
                'trBPB.status',
                'trBPB.verification_status',
                'trBPB.verification_notes',
                'trBPB.verified_at',
                'trBPB.rejected_at',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.approval_level_1_at',
                'trBPB.approval_level_2_at',
                'trBPB.rejection_reason'
            )
            ->orderByDesc(DB::raw('COALESCE(trBPB.verified_at, trBPB.rejected_at, trBPB.updated_at, trBPB.created_at)'))
            ->limit($limit)
            ->get();
    }

    private function pbPayload(object $item, bool $withPreviewItems = false): array
    {
        $payload = [
            'id' => (int) $item->id,
            'nomor_pb' => $item->nomor_pb,
            'tanggal_permintaan' => $item->tanggal_permintaan,
            'tanggal_diperlukan' => $item->tanggal_diperlukan,
            'is_backdate' => (bool) ($item->is_backdate ?? false),
            'backdate_reason' => $item->backdate_reason ?? null,
            'backdate_created_at' => $item->backdate_created_at ?? null,
            'untuk' => $item->untuk,
            'tujuan_nama' => $item->tujuan_nama,
            'tujuan_kode' => $item->tujuan_kode,
            'jenis_pekerjaan' => $item->jenis_pekerjaan,
            'approval_current_level' => (int) $item->approval_current_level,
            'approval_level_required' => (int) $item->approval_level_required,
            'is_high_value' => (bool) $item->has_high_value_item,
            'jumlah_barang' => (int) $item->jumlah_barang,
            'total_value' => (float) $item->total_value,
            'max_unit_price' => (float) $item->max_unit_price,
            'created_at' => $item->created_at,
        ];

        if ($withPreviewItems) {
            $payload['items'] = DB::table('trBPBDetail')
                ->where('trBPB_id', $item->id)
                ->orWhere('trbpb_id', $item->id)
                ->orderBy('id')
                ->limit(5)
                ->get(['nama_barang', 'jumlah', 'satuan'])
                ->map(fn ($detail) => [
                    'nama_barang' => $detail->nama_barang,
                    'jumlah' => (float) $detail->jumlah,
                    'satuan' => $detail->satuan,
                ])
                ->values();
        }

        return $payload;
    }

    private function woBaseQuery()
    {
        return DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.status', 'submitted')
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.deskripsi',
                'trWorkOrder.status',
                'trWorkOrder.file_name',
                'trWorkOrder.file_path',
                'trWorkOrder.created_at',
                'trWorkOrder.submitted_at',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->orderByDesc('trWorkOrder.submitted_at')
            ->orderByDesc('trWorkOrder.created_at');
    }

    private function woPayload(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'nomor' => $item->nomor,
            'judul' => $item->judul,
            'deskripsi' => $item->deskripsi,
            'status' => $item->status,
            'file_name' => $item->file_name,
            'created_at' => $item->created_at,
            'submitted_at' => $item->submitted_at,
            'created_by_name' => $item->created_by_name,
            'created_by_email' => $item->created_by_email,
        ];
    }

    private function woHistoryQuery(object $user)
    {
        return DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where(function ($query) use ($user) {
                $query->where('trWorkOrder.approved_by', $user->id)
                    ->orWhere('trWorkOrder.rejected_by', $user->id);
            })
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.deskripsi',
                'trWorkOrder.status',
                'trWorkOrder.progress_status',
                'trWorkOrder.file_name',
                'trWorkOrder.file_path',
                'trWorkOrder.created_at',
                'trWorkOrder.submitted_at',
                'trWorkOrder.approved_at',
                'trWorkOrder.rejected_at',
                'trWorkOrder.closed_at',
                'trWorkOrder.assigned_at',
                'trWorkOrder.assigned_regu',
                'trWorkOrder.delegation_notes',
                'trWorkOrder.rejection_notes',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->orderByDesc(DB::raw('COALESCE(trWorkOrder.approved_at, trWorkOrder.rejected_at, trWorkOrder.created_at)'));
    }

    private function workOrderPhotos(int $workOrderId)
    {
        if (!Schema::hasTable('trWorkOrderPhotos')) {
            return collect();
        }

        return DB::table('trWorkOrderPhotos as p')
            ->leftJoin('users as uploader', 'p.uploaded_by', '=', 'uploader.id')
            ->where('p.work_order_id', $workOrderId)
            ->orderBy('p.id')
            ->get(['p.file_path', 'p.file_name', 'p.notes', 'p.created_at', 'uploader.name as uploaded_by_name'])
            ->map(fn ($photo) => [
                'file_name' => $photo->file_name,
                'url' => rtrim(request()->getSchemeAndHttpHost(), '/') . Storage::url($photo->file_path),
                'notes' => $photo->notes,
                'created_at' => $photo->created_at,
                'uploaded_by_name' => $photo->uploaded_by_name,
            ])
            ->values();
    }

    private function workOrderPhotoCount(int $workOrderId): int
    {
        if (!Schema::hasTable('trWorkOrderPhotos')) {
            return 0;
        }

        return DB::table('trWorkOrderPhotos')
            ->where('work_order_id', $workOrderId)
            ->count();
    }

    private function sectionWorkOrderHistoryRows(object $user, int $limit = 80)
    {
        return DB::table('trWorkOrder')
            ->leftJoin('users as creator', 'trWorkOrder.created_by', '=', 'creator.id')
            ->where('trWorkOrder.assigned_regu', $user->name)
            ->where('trWorkOrder.status', 'approved')
            ->where('trWorkOrder.progress_status', 'closed')
            ->select(
                'trWorkOrder.id',
                'trWorkOrder.nomor',
                'trWorkOrder.judul',
                'trWorkOrder.deskripsi',
                'trWorkOrder.progress_status',
                'trWorkOrder.assigned_at',
                'trWorkOrder.approved_at',
                'trWorkOrder.closed_at',
                'trWorkOrder.delegation_notes',
                'trWorkOrder.progress_notes',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->orderByDesc('trWorkOrder.closed_at')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nomor' => $item->nomor,
                'judul' => $item->judul,
                'deskripsi' => $item->deskripsi,
                'progress_status' => $item->progress_status,
                'assigned_at' => $item->assigned_at,
                'approved_at' => $item->approved_at,
                'closed_at' => $item->closed_at,
                'delegation_notes' => $item->delegation_notes,
                'progress_notes' => $item->progress_notes,
                'created_by_name' => $item->created_by_name,
                'created_by_email' => $item->created_by_email,
                'photos' => $this->workOrderPhotos((int) $item->id),
                'history_type' => 'WO',
            ])
            ->values();
    }

    private function mobileHistoryCard(array $item): string
    {
        $type = $item['history_type'] ?? (isset($item['nomor_pb']) ? 'PB' : 'WO');
        $isPb = $type === 'PB';
        $id = (int) ($item['id'] ?? 0);
        $number = $isPb ? ($item['nomor_pb'] ?? '-') : ($item['nomor'] ?? '-');
        $title = $isPb ? ($item['tujuan_nama'] ?? $item['untuk'] ?? '-') : ($item['judul'] ?? '-');
        $description = $isPb ? ($item['jenis_pekerjaan'] ?? '-') : ($item['deskripsi'] ?? '-');
        $status = $item['progress_status'] ?? $item['status'] ?? '-';
        $date = $item['closed_at'] ?? $item['verified_at'] ?? $item['approved_at'] ?? $item['rejected_at'] ?? $item['created_at'] ?? '';
        $dateKey = $date ? Carbon::parse($date)->timezone('Asia/Jakarta')->format('Y-m-d') : '';
        $url = $this->mobileTokenUrl($isPb ? url('/api/mobile/web/pb/' . $id) : url('/api/mobile/web/wo/' . $id));
        $searchText = trim($type . ' ' . $number . ' ' . $title . ' ' . $description . ' ' . $status);
        $typeLabel = (!$isPb && str_ends_with((string) $number, '-NWO')) ? 'NWO' : $type;
        $typeClass = strtolower($typeLabel);
        $backdateBadge = $isPb && !empty($item['is_backdate'])
            ? '<span class="status pending">Backdate</span>'
            : '';
        $meta = $isPb
            ? '<span>' . e($this->mobileDateTime($date)) . '</span><span>' . e($this->mobileRupiah($item['total_value'] ?? 0)) . '</span><span>' . e(($item['jumlah_barang'] ?? 0) . ' item') . '</span>'
            : '<span>' . e($this->mobileDateTime($date)) . '</span><span>' . e(count($item['photos'] ?? []) . ' foto') . '</span>';

        $items = '';
        if ($isPb && !empty($item['items'])) {
            foreach ($item['items'] as $detail) {
                $items .= '<li>' . e(($detail['nama_barang'] ?? '-') . ' x ' . $this->mobileQty($detail['jumlah'] ?? 0) . ' ' . ($detail['satuan'] ?? '')) . '</li>';
            }
            $items = '<ul class="preview-items">' . $items . '</ul>';
        }

        return '<a class="history-card" href="' . e($url) . '" data-type="' . e($type) . '" data-date="' . e($dateKey) . '" data-text="' . e($searchText) . '">
            <div class="card-top">
                <span class="type-pill ' . e($typeClass) . '">' . e($typeLabel) . '</span>
                <span class="card-statuses">' . $backdateBadge . '<span class="status ' . e($this->mobileStatusClass($status)) . '">' . e($this->mobileStatusLabelForType($status, $type)) . '</span></span>
            </div>
            <h2>' . e($number) . '</h2>
            <p>' . e($title) . '</p>
            <small>' . e($description) . '</small>
            ' . $items . '
            <div class="card-meta">' . $meta . '</div>
        </a>';
    }

    private function engineeringPbRows(object $user, int $limit = 40)
    {
        return DB::table('trBPB')
            ->leftJoin('trBPBDetail as d', function ($join) {
                $join->on('trBPB.id', '=', 'd.trBPB_id')
                    ->orOn('trBPB.id', '=', 'd.trbpb_id');
            })
            ->leftJoin('mtMesin', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.mesin_id)'), '=', 'mtMesin.msnID')
                    ->where('trBPB.untuk', '=', 'mesin');
            })
            ->leftJoin('mtBangunan', function ($join) {
                $join->on(DB::raw('COALESCE(trBPB.untuk_id, trBPB.bangunan_id)'), '=', 'mtBangunan.buildID')
                    ->where('trBPB.untuk', '=', 'bangunan');
            })
            ->where('trBPB.user_id', $user->id)
            ->select(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.status',
                'trBPB.jenis_pekerjaan',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.rejected_at',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item',
                DB::raw("COALESCE(MAX(mtMesin.msnName), MAX(mtBangunan.buildName), trBPB.untuk) as tujuan_nama"),
                DB::raw('COUNT(d.id) as jumlah_barang'),
                DB::raw('COALESCE(SUM(d.total_price), 0) as total_value')
            )
            ->groupBy(
                'trBPB.id',
                'trBPB.nomor_pb',
                'trBPB.status',
                'trBPB.jenis_pekerjaan',
                'trBPB.tanggal_permintaan',
                'trBPB.tanggal_diperlukan',
                'trBPB.is_backdate',
                'trBPB.backdate_reason',
                'trBPB.backdate_created_at',
                'trBPB.created_at',
                'trBPB.approved_at',
                'trBPB.rejected_at',
                'trBPB.approval_current_level',
                'trBPB.approval_level_required',
                'trBPB.has_high_value_item'
            )
            ->orderByDesc('trBPB.created_at')
            ->limit($limit)
            ->get();
    }

    private function engineeringWoRows(object $user, int $limit = 40)
    {
        return DB::table('trWorkOrder')
            ->where('created_by', $user->id)
            ->select(
                'id',
                'nomor',
                'judul',
                'deskripsi',
                'status',
                'progress_status',
                'file_name',
                'created_at',
                'submitted_at',
                'approved_at',
                'assigned_regu',
                'assigned_at',
                'closed_at',
                'rejected_at'
            )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function engineeringPbCard(object $pb): string
    {
        $status = $pb->status === 'pending'
            ? 'Menunggu L' . (int) ($pb->approval_current_level ?: 1)
            : $this->mobileStatusLabelForType($pb->status, 'PB');
        $search = trim($pb->nomor_pb . ' ' . $pb->tujuan_nama . ' ' . $pb->status . ' ' . $pb->jenis_pekerjaan);
        $backdateBadge = !empty($pb->is_backdate) ? '<span class="status pending">Backdate</span>' : '';

        $dateKey = $pb->tanggal_permintaan ? Carbon::parse($pb->tanggal_permintaan)->timezone('Asia/Jakarta')->format('Y-m-d') : '';

        return '<a class="history-card" href="' . e($this->mobileTokenUrl(url('/api/mobile/web/pb/' . $pb->id))) . '" data-text="' . e(strtolower($search)) . '" data-date="' . e($dateKey) . '">
            <div class="card-top">
                <span class="type-pill pb">PB</span>
                <span class="card-statuses">' . $backdateBadge . '<span class="status ' . e($this->mobileStatusClass($pb->status)) . '">' . e($status) . '</span></span>
            </div>
            <h2>' . e($pb->nomor_pb) . '</h2>
            <p>' . e($pb->tujuan_nama ?: '-') . '</p>
            <small>' . e($pb->jenis_pekerjaan ?: '-') . '</small>
            <div class="card-meta">
                <span>' . e($this->mobileDate($pb->tanggal_permintaan)) . '</span>
                <span>' . e($this->mobileRupiah($pb->total_value)) . '</span>
                <span>' . e((int) $pb->jumlah_barang . ' item') . '</span>
            </div>
        </a>';
    }

    private function engineeringWoCard(object $wo): string
    {
        $status = $wo->progress_status ?: $wo->status;
        $search = trim($wo->nomor . ' ' . $wo->judul . ' ' . $wo->status . ' ' . $wo->progress_status);
        $dateKey = ($wo->submitted_at ?: $wo->created_at)
            ? Carbon::parse($wo->submitted_at ?: $wo->created_at)->timezone('Asia/Jakarta')->format('Y-m-d')
            : '';

        return '<a class="history-card" href="' . e($this->mobileTokenUrl(url('/api/mobile/web/wo/' . $wo->id))) . '" data-text="' . e(strtolower($search)) . '" data-date="' . e($dateKey) . '">
            <div class="card-top">
                <span class="type-pill wo">WO</span>
                <span class="status ' . e($this->mobileStatusClass($status)) . '">' . e($this->mobileStatusLabel($status)) . '</span>
            </div>
            <h2>' . e($wo->nomor) . '</h2>
            <p>' . e($wo->judul ?: '-') . '</p>
            <small>' . e($wo->deskripsi ?: '-') . '</small>
            <div class="card-meta">
                <span>' . e($this->mobileDateTime($wo->submitted_at ?: $wo->created_at)) . '</span>
                <span>' . e($wo->assigned_regu ?: 'Belum assign') . '</span>
            </div>
        </a>';
    }

    private function engineeringWebScript(?string $token): string
    {
        $targetSearchUrl = json_encode(url('/api/mobile/engineering/search/targets'));
        $itemSearchUrl = json_encode(url('/api/mobile/engineering/search/items'));
        $workOrderSearchUrl = json_encode(url('/api/mobile/engineering/search/work-orders'));
        $engineeringWoUrl = json_encode(url('/api/mobile/web/engineering/wo'));

        return 'var TOKEN_FROM_SERVER = ' . json_encode((string) $token) . ';
' . <<<JS
            var TOKEN = TOKEN_FROM_SERVER || (window.URLSearchParams ? new URLSearchParams(window.location.search).get("token") : "") || "";
            function withMobileToken(url) {
                if (!TOKEN || !url || url.indexOf("#") === 0 || url.indexOf("javascript:") === 0) return url;
                try {
                    var parsed = new URL(url, window.location.origin);
                    if (parsed.pathname.indexOf("/api/mobile/") === 0) {
                        parsed.searchParams.set("token", TOKEN);
                        return parsed.toString();
                    }
                } catch (e) {}
                return url;
            }
            document.addEventListener("DOMContentLoaded", function () {
                Array.prototype.forEach.call(document.querySelectorAll("a[href*=\'/api/mobile/web/\']"), function (link) {
                    link.setAttribute("href", withMobileToken(link.getAttribute("href")));
                });
            });
            var API = {
                targets: $targetSearchUrl,
                items: $itemSearchUrl,
                workOrders: $workOrderSearchUrl
            };
            var timers = {};
            function trimText(value) { return String(value || "").replace(/^\s+|\s+$/g, ""); }
            function toggleForm(id) { var el = document.getElementById(id); if (el) el.hidden = !el.hidden; }
            function switchTarget() {
                var untukEl = document.getElementById("pbUntuk");
                var untuk = untukEl ? untukEl.value : "mesin";
                var mesin = document.querySelector(".mesin-target");
                var bangunan = document.querySelector(".bangunan-target");
                if (mesin) mesin.hidden = untuk !== "mesin";
                if (bangunan) bangunan.hidden = untuk !== "bangunan";
                var mesinId = document.getElementById("pbMesinId");
                var bangunanId = document.getElementById("pbBangunanId");
                if (mesinId) mesinId.disabled = untuk !== "mesin";
                if (bangunanId) bangunanId.disabled = untuk !== "bangunan";
            }
            function debounce(key, callback) {
                clearTimeout(timers[key]);
                timers[key] = setTimeout(callback, 280);
            }
            function fetchData(url) {
                var headers = { "Accept":"application/json" };
                if (TOKEN) headers.Authorization = "Bearer " + TOKEN;
                if (typeof fetch !== "function") {
                    return new Promise(function (resolve) {
                        var xhr = new XMLHttpRequest();
                        xhr.open("GET", withMobileToken(url), true);
                        xhr.setRequestHeader("Accept", "application/json");
                        if (TOKEN) xhr.setRequestHeader("Authorization", "Bearer " + TOKEN);
                        xhr.onreadystatechange = function () {
                            if (xhr.readyState !== 4) return;
                            try { resolve((JSON.parse(xhr.responseText).data || [])); }
                            catch (e) { resolve([]); }
                        };
                        xhr.onerror = function () { resolve([]); };
                        xhr.send();
                    });
                }
                return fetch(withMobileToken(url), { headers: headers })
                    .then(function (res) { return res.json().catch(function () { return { success:false, data:[] }; }); })
                    .then(function (json) { return json.data || []; })
                    .catch(function () { return []; });
            }
            function clearSuggestions(id, message) {
                var box = document.getElementById(id);
                if (box) box.innerHTML = message ? '<div class="suggestion-empty">' + escapeHtml(message) + '</div>' : "";
            }
            function renderSuggestions(id, rows, onPick, emptyMessage) {
                var box = document.getElementById(id);
                if (!box) return;
                emptyMessage = emptyMessage || "Tidak ada data";
                if (!rows.length) {
                    clearSuggestions(id, emptyMessage);
                    return;
                }
                box.innerHTML = rows.map(function (row, index) {
                    return '<button type="button" data-index="' + index + '">' +
                        '<strong>' + escapeHtml(row.name || row.nomor || "-") + '</strong>' +
                        '<small>' + escapeHtml(row.code || row.judul || "") + '</small>' +
                        '</button>';
                }).join("");
                Array.prototype.forEach.call(box.querySelectorAll("button"), function (button) {
                    button.onclick = function () { onPick(rows[Number(button.getAttribute("data-index"))]); };
                });
            }
            function escapeHtml(value) {
                return String(value || "").replace(/[&<>"\']/g, function (char) {
                    return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[char];
                });
            }
            function formatStockValue(value) {
                var number = Number(value || 0);
                if (!isFinite(number)) number = 0;
                return number.toLocaleString("id-ID", { maximumFractionDigits: 2 });
            }
            function normalizeStockUnit(value) {
                var unit = String(value || "").trim().toUpperCase();
                var map = {
                    PC: "PCS",
                    PCS: "PCS",
                    UN: "UNIT",
                    UNIT: "UNIT",
                    KG: "KG",
                    KGM: "KG",
                    M: "M",
                    MTR: "M",
                    METER: "M",
                    ROL: "ROLL",
                    ROLL: "ROLL",
                    BTG: "BTG",
                    BATANG: "BTG"
                };
                return map[unit] || unit;
            }
            function clearItemStock(row) {
                if (!row) return;
                row.removeAttribute("data-stock-available");
                row.removeAttribute("data-stock-erp");
                row.removeAttribute("data-stock-erp-unit");
                row.removeAttribute("data-stock-area");
                row.removeAttribute("data-stock-area-unit");
                row.removeAttribute("data-autopick-stock");
                var stockInput = row.querySelector("[name=stock_available]");
                if (stockInput) stockInput.value = "";
                var stockLine = row.querySelector(".pb-stock-line");
                if (stockLine) {
                    stockLine.hidden = true;
                    stockLine.className = "pb-stock-line";
                    stockLine.innerHTML = "";
                }
                var qtyInput = row.querySelector("[name=jumlah]");
                if (qtyInput) {
                    qtyInput.disabled = false;
                    qtyInput.removeAttribute("max");
                }
            }
            function updateItemStock(row, item) {
                if (!row || !item) return;
                var erpStock = Number(item.stock_erp || item.stock_available || 0);
                var areaStock = Number(item.stock_area || 0);
                var unit = item.unit || "PCS";
                var erpUnit = item.stock_erp_unit || unit;
                var areaUnit = item.stock_area_unit || unit;
                row.setAttribute("data-stock-available", String(erpStock));
                row.setAttribute("data-stock-erp", String(erpStock));
                row.setAttribute("data-stock-erp-unit", erpUnit);
                row.setAttribute("data-stock-area", String(areaStock));
                row.setAttribute("data-stock-area-unit", areaUnit);
                row.setAttribute("data-autopick-stock", String(erpStock));
                var stockInput = row.querySelector("[name=stock_available]");
                if (stockInput) stockInput.value = String(erpStock);
                var stockLine = row.querySelector(".pb-stock-line");
                if (stockLine) {
                    stockLine.hidden = false;
                    stockLine.className = "pb-stock-line " + ((areaStock > 0 || erpStock > 0) ? "ok" : "empty");
                    stockLine.innerHTML = '<span>Stock Area: ' + formatStockValue(areaStock) + ' ' + escapeHtml(areaUnit) + '</span><strong>ERP: ' + formatStockValue(erpStock) + ' ' + escapeHtml(erpUnit) + '</strong>';
                }
                var qtyInput = row.querySelector("[name=jumlah]");
                if (qtyInput) {
                    qtyInput.disabled = areaStock <= 0 && erpStock <= 0;
                    if (areaStock <= 0 && erpStock <= 0) qtyInput.value = "";
                    qtyInput.removeAttribute("max");
                }
            }
            function searchTarget(type, value) {
                var needle = trimText(value).toLowerCase();
                var isMesin = type === "mesin";
                var resultId = isMesin ? "pbMesinResults" : "pbBangunanResults";
                var hiddenId = isMesin ? "pbMesinId" : "pbBangunanId";
                var hidden = document.getElementById(hiddenId);
                if (hidden) hidden.value = "";
                if (needle.length < 2) {
                    clearSuggestions(resultId);
                    return;
                }
                clearSuggestions(resultId, "Mencari...");
                debounce("target-" + type, function () {
                    fetchData(API.targets + "?type=" + encodeURIComponent(type) + "&q=" + encodeURIComponent(value)).then(function (rows) {
                        renderSuggestions(resultId, rows, function (row) {
                        document.getElementById(hiddenId).value = row.id;
                        document.getElementById(isMesin ? "pbMesinSearch" : "pbBangunanSearch").value = row.name + " (" + (row.code || "-") + ")";
                        clearSuggestions(resultId);
                        });
                    });
                });
            }
            function searchWorkOrder(value) {
                document.getElementById("pbWoId").value = "";
                document.getElementById("pbWoNumber").value = "";
                if (trimText(value).length < 2) {
                    clearSuggestions("pbWoResults");
                    return;
                }
                clearSuggestions("pbWoResults", "Mencari...");
                debounce("wo-ref", function () {
                    fetchData(API.workOrders + "?q=" + encodeURIComponent(value)).then(function (rows) {
                        renderSuggestions("pbWoResults", rows, function (row) {
                            document.getElementById("pbWoId").value = row.id;
                            document.getElementById("pbWoNumber").value = row.nomor;
                            document.getElementById("pbWoSearch").value = row.nomor + " - " + (row.judul || "-");
                            clearSuggestions("pbWoResults");
                        }, "Belum ada WO approved yang cocok");
                    });
                });
            }
            function searchItem(input, value) {
                var row = input.closest ? input.closest(".pb-item") : input.parentNode;
                var resultBox = row ? row.querySelector(".item-results") : null;
                var materialSelect = document.getElementById("pbMaterialType");
                var materialType = materialSelect ? materialSelect.value : "sparepart";
                if (!row || !resultBox) return;
                row.querySelector("[name=barang_id]").value = "";
                row.removeAttribute("data-autopick-id");
                row.removeAttribute("data-autopick-name");
                row.removeAttribute("data-autopick-unit");
                clearItemStock(row);
                if (trimText(value).length < 2) {
                    resultBox.innerHTML = "";
                    return;
                }
                resultBox.innerHTML = '<div class="suggestion-empty">Mencari...</div>';
                debounce("item-" + row.getAttribute("data-row"), function () {
                    fetchData(API.items + "?q=" + encodeURIComponent(value) + "&material_type=" + encodeURIComponent(materialType)).then(function (rows) {
                    if (!rows.length) {
                        row.removeAttribute("data-autopick-id");
                        row.removeAttribute("data-autopick-name");
                        row.removeAttribute("data-autopick-unit");
                        resultBox.innerHTML = '<div class="suggestion-empty">Barang tidak ditemukan</div>';
                        return;
                    }
                    if (rows.length === 1) {
                        row.setAttribute("data-autopick-id", rows[0].id || "");
                        row.setAttribute("data-autopick-name", rows[0].name || "");
                        row.setAttribute("data-autopick-unit", rows[0].unit || "PCS");
                        row.setAttribute("data-autopick-stock", rows[0].stock_available || 0);
                        updateItemStock(row, rows[0]);
                    } else {
                        row.removeAttribute("data-autopick-id");
                        row.removeAttribute("data-autopick-name");
                        row.removeAttribute("data-autopick-unit");
                        row.removeAttribute("data-autopick-stock");
                    }
                    resultBox.innerHTML = rows.map(function (item, index) {
                        return '<button type="button" data-index="' + index + '">' +
                            '<strong>' + escapeHtml(item.name) + '</strong>' +
                            '<small>' + escapeHtml(item.code) + ' - ' + escapeHtml(item.unit || "PCS") + ' | Stock Area: ' + formatStockValue(item.stock_area || 0) + ' ' + escapeHtml(item.stock_area_unit || item.unit || "PCS") + ' | ERP: ' + formatStockValue(item.stock_erp || item.stock_available || 0) + ' ' + escapeHtml(item.stock_erp_unit || item.unit || "PCS") + '</small>' +
                            '</button>';
                    }).join("");
                    Array.prototype.forEach.call(resultBox.querySelectorAll("button"), function (button) {
                        button.onclick = function () {
                            var item = rows[Number(button.getAttribute("data-index"))];
                            row.querySelector("[name=barang_id]").value = item.id || "";
                            row.querySelector("[name=nama_barang]").value = item.name || "";
                            row.setAttribute("data-autopick-id", item.id || "");
                            row.setAttribute("data-autopick-name", item.name || "");
                            row.setAttribute("data-autopick-unit", item.unit || "PCS");
                            row.setAttribute("data-autopick-stock", item.stock_available || 0);
                            setUnitValue(row.querySelector("[name=satuan]"), item.unit || "PCS");
                            updateItemStock(row, item);
                            resultBox.innerHTML = "";
                        };
                    });
                    });
                });
            }
            function setUnitValue(select, value) {
                if (!select) return;
                var unit = String(value || "PCS").toUpperCase();
                if (!Array.prototype.some.call(select.options, function (option) { return option.value === unit; })) {
                    var option = document.createElement("option");
                    option.value = unit;
                    option.textContent = unit;
                    select.appendChild(option);
                }
                select.value = unit;
            }
            window.changeMaterialType = function changeMaterialType() {
                var wrap = document.getElementById("pbItems");
                if (!wrap) return;
                wrap.innerHTML = "";
                window.addPbItem();
            };
            window.ensurePbItemRow = function ensurePbItemRow() {
                var wrap = document.getElementById("pbItems");
                if (wrap && !wrap.querySelector(".pb-item")) {
                    window.addPbItem();
                }
            };
            window.addPbItem = function addPbItem() {
                var wrap = document.getElementById("pbItems");
                if (!wrap) return false;
                var row = document.createElement("div");
                row.className = "pb-item";
                row.setAttribute("data-row", Date.now() + "-" + Math.random().toString(16).slice(2));
                row.setAttribute("data-stock-ready", "1");
                row.innerHTML =
                    '<input type="hidden" name="barang_id">' +
                    '<input type="hidden" name="stock_available">' +
                    '<input name="nama_barang" type="search" placeholder="Ketik minimal 2 huruf nama / kode barang..." autocomplete="off" required oninput="searchItem(this, this.value)">' +
                    '<div class="suggestion-list item-results"></div>' +
                    '<div class="pb-stock-line" hidden></div>' +
                    '<div class="item-grid"><input name="jumlah" type="number" step="0.01" min="0.01" placeholder="Qty" required><select name="satuan" required><option value="PCS">PCS</option><option value="UNIT">Unit</option><option value="KG">Kilogram (KG)</option><option value="G">Gram (G)</option><option value="L">Liter (L)</option><option value="ML">Milliliter (ML)</option><option value="M">Meter (M)</option><option value="CM">Centimeter (CM)</option><option value="MM">Millimeter (MM)</option><option value="BOX">Box</option><option value="PACK">Pack</option><option value="ROLL">Roll</option><option value="SET">Set</option><option value="BTG">Batang (BTG)</option><option value="BUAH">Buah</option><option value="LEMBAR">Lembar</option><option value="PAIR">Pair (Pasang)</option><option value="BTL">Bottle (Botol)</option><option value="CAN">Can (Kaleng)</option><option value="TUBE">Tube (Tabung)</option><option value="BAG">Bag (Karung)</option><option value="DRUM">Drum</option><option value="CARTON">Carton (Kardus)</option><option value="PALLET">Pallet</option></select></div>' +
                    '<input name="item_keterangan" placeholder="Keterangan item (opsional)">' +
                    '<button type="button" onclick="var p=this.parentNode;if(p&&p.parentNode)p.parentNode.removeChild(p);">Hapus item</button>';
                wrap.appendChild(row);
                var nameInput = row.querySelector("[name=nama_barang]");
                if (nameInput) nameInput.focus();
                return false;
            };
            function submitJson(url, body) {
                var payload = {};
                var key;
                body = body || {};
                for (key in body) {
                    if (Object.prototype.hasOwnProperty.call(body, key)) payload[key] = body[key];
                }
                if (TOKEN && !payload.token) payload.token = TOKEN;
                var headers = { "Accept":"application/json", "Content-Type":"application/json" };
                if (TOKEN) headers.Authorization = "Bearer " + TOKEN;
                return fetch(withMobileToken(url), { method:"POST", headers: headers, body: JSON.stringify(payload), credentials:"same-origin" })
                    .then(function (res) {
                        return res.json().catch(function () { return { success:false, message:"Response tidak valid" }; })
                            .then(function (data) {
                                if (!res.ok && data && data.success !== true) data.success = false;
                                if (!res.ok && data && !data.message) data.message = "Request gagal (" + res.status + ").";
                                return data;
                            });
                    })
                    .catch(function () { return { success:false, message:"Koneksi gagal. Coba lagi." }; });
            }
            function submitForm(url, body) {
                if (TOKEN && body && typeof body.append === "function" && !body.has("token")) body.append("token", TOKEN);
                var headers = { "Accept":"application/json" };
                if (TOKEN) headers.Authorization = "Bearer " + TOKEN;
                return fetch(withMobileToken(url), { method:"POST", headers: headers, body: body })
                    .then(function (res) { return res.json().catch(function () { return { success:false, message:"Response tidak valid" }; }); })
                    .catch(function () { return { success:false, message:"Koneksi gagal. Coba lagi." }; });
            }
            function mobileToast(message, type) {
                var toast = document.getElementById("mobileToast");
                if (!toast) {
                    toast = document.createElement("div");
                    toast.id = "mobileToast";
                    toast.style.cssText = "position:fixed;left:18px;right:18px;bottom:92px;z-index:9999;padding:13px 16px;border-radius:16px;background:#111827;color:#fff;font-weight:800;text-align:center;box-shadow:0 18px 40px rgba(15,23,42,.24);";
                    document.body.appendChild(toast);
                }
                toast.textContent = message || "Proses selesai.";
                toast.style.background = type === "error" ? "#dc2626" : "#111827";
                toast.style.display = "block";
                clearTimeout(window.__mobileToastTimer);
                window.__mobileToastTimer = setTimeout(function () { toast.style.display = "none"; }, 2200);
            }
            function setActionLoading(button, loading, text) {
                if (!button) return;
                if (!button.dataset.label) button.dataset.label = button.textContent;
                button.disabled = !!loading;
                button.textContent = loading ? text : button.dataset.label;
            }
            function approvePbDetail(id, button) {
                var noteEl = document.getElementById("pbApprovalNote");
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/pb/" + id + "/approve", { notes: noteEl ? trimText(noteEl.value) : "" }).then(function (result) {
                    mobileToast(result.message || (result.success ? "PB berhasil diapprove." : "Gagal approve PB."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function rejectPbDetail(id, button) {
                var panel = document.getElementById("pbRejectPanel");
                if (panel && panel.hidden) {
                    panel.hidden = false;
                    var input = document.getElementById("pbRejectReason");
                    setTimeout(function () { if (input) input.focus(); }, 50);
                    mobileToast("Isi catatan reject lalu tekan Reject PB lagi.", "error");
                    return;
                }
                var reasonEl = document.getElementById("pbRejectReason");
                var reason = reasonEl ? trimText(reasonEl.value) : "";
                if (!reason) { mobileToast("Catatan reject PB wajib diisi.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/pb/" + id + "/reject", { alasan: reason }).then(function (result) {
                    mobileToast(result.message || (result.success ? "PB berhasil direject." : "Gagal reject PB."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function approveWoDetail(id, button) {
                var pelaksanaEl = document.getElementById("woPelaksana");
                var notesEl = document.getElementById("woDelegationNotes");
                var pelaksana = pelaksanaEl ? pelaksanaEl.value : "";
                var notes = notesEl ? trimText(notesEl.value) : "";
                if (!pelaksana) { mobileToast("Pilih pelaksana terlebih dahulu.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/wo/" + id + "/approve", { pelaksana: pelaksana, delegation_notes: notes }).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil diapprove dan di-assign." : "Gagal approve WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function rejectWoDetail(id, button) {
                var panel = document.getElementById("woRejectPanel");
                if (panel && panel.hidden) {
                    panel.hidden = false;
                    var input = document.getElementById("woRejectReason");
                    setTimeout(function () { if (input) input.focus(); }, 50);
                    mobileToast("Isi catatan reject lalu tekan Reject WO lagi.", "error");
                    return;
                }
                var reasonEl = document.getElementById("woRejectReason");
                var reason = reasonEl ? trimText(reasonEl.value) : "";
                if (!reason) { mobileToast("Catatan reject WO wajib diisi.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/wo/" + id + "/reject", { rejection_notes: reason }).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil direject." : "Gagal reject WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function unpostWoDetail(id, button) {
                var now = Date.now();
                var confirmUntil = button && button.dataset ? Number(button.dataset.confirmUntil || 0) : 0;
                if (!confirmUntil || now > confirmUntil) {
                    if (button && button.dataset) {
                        button.dataset.confirmUntil = String(now + 5000);
                        button.dataset.label = button.dataset.label || button.textContent;
                        button.textContent = "Klik lagi untuk Unpost";
                    }
                    mobileToast("Tekan Unpost WO sekali lagi untuk konfirmasi.", "error");
                    return;
                }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/engineering/wo/" + id + "/unpost", {}).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil di-unpost." : "Gagal unpost WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.href = withMobileToken($engineeringWoUrl); }, 700);
                    else setActionLoading(button, false);
                });
            }
            function filterCards(value) {
                var needle = (value || "").toLowerCase();
                var fromInput = document.querySelector("[data-date-filter=from]");
                var toInput = document.querySelector("[data-date-filter=to]");
                var from = fromInput ? fromInput.value : "";
                var to = toInput ? toInput.value : "";
                Array.prototype.forEach.call(document.querySelectorAll("[data-text]"), function (card) {
                    var date = card.dataset.date || "";
                    var matchText = (card.dataset.text || "").indexOf(needle) >= 0;
                    var matchFrom = !from || !date || date >= from;
                    var matchTo = !to || !date || date <= to;
                    card.style.display = matchText && matchFrom && matchTo ? "" : "none";
                });
            }
JS;
    }

    private function generateMobilePbNumber(): string
    {
        $prefix = 'PB-ENG-' . now()->format('Ymd') . '-';
        $last = DB::table('trBPB')->where('nomor_pb', 'like', $prefix . '%')->orderByDesc('nomor_pb')->value('nomor_pb');
        $next = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $nomor = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (DB::table('trBPB')->where('nomor_pb', $nomor)->exists());

        return $nomor;
    }

    private function generateMobileWoNumber(): string
    {
        $prefix = 'WO-' . now()->format('Ymd') . '-';
        $last = DB::table('trWorkOrder')->where('nomor', 'like', $prefix . '%')->orderByDesc('nomor')->value('nomor');
        $next = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        do {
            $nomor = $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (DB::table('trWorkOrder')->where('nomor', $nomor)->exists());

        return $nomor;
    }

    private function mobileItemAveragePrice(string $name, $itemId = null, string $materialType = 'sparepart'): float
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                WITH target_items AS (
                    SELECT id_items, code
                    FROM PUBLIC.tb_skb080_1mmara m
                    WHERE {$materialScope}
                      AND (
                          id_items = CAST(? AS bigint)
                          OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                    )
                    ORDER BY
                        CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END,
                        id_items
                    LIMIT 1
                ),
                latest_po AS (
                    SELECT DISTINCT ON (ma.id_items)
                        ma.id_items,
                        CASE
                            WHEN COALESCE(dp.unit_price, 0) > 0
                                THEN dp.unit_price
                            WHEN COALESCE(dp.subtotal, 0) > 0
                                AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                                THEN ROUND(dp.subtotal / COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)), 2)
                            ELSE 0
                        END AS harga_satuan
                    FROM PUBLIC.tb_skb002_1mpurch_ord mp
                    LEFT JOIN PUBLIC.tb_skb002_2dpurch_ord_items dp
                        ON mp.id_purch_ord = dp.id_purch_ord
                    LEFT JOIN PUBLIC.tb_skb080_1mmara ma
                        ON dp.id_items = ma.id_items
                    INNER JOIN target_items ti
                        ON ti.id_items = ma.id_items
                    WHERE mp.po_date >= (CURRENT_DATE - INTERVAL '5 years')
                      AND (
                          COALESCE(dp.unit_price, 0) > 0
                          OR (
                              COALESCE(dp.subtotal, 0) > 0
                              AND COALESCE(NULLIF(dp.qty_aprv, 0), NULLIF(dp.qty_po, 0)) IS NOT NULL
                          )
                      )
                    ORDER BY ma.id_items, mp.po_date DESC NULLS LAST, mp.id_purch_ord DESC
                ),
                pur AS (
                    SELECT matnr, SUM(menge) AS qty, SUM(wrbtr) AS amt
                    FROM PUBLIC.tb_skb008_2dmseg
                    WHERE werks = 1
                      AND bwart = '101'
                      AND cpudt BETWEEN DATE '2026-01-01' AND CURRENT_DATE
                      AND matnr IN (SELECT id_items FROM target_items)
                    GROUP BY matnr
                ),
                sa AS (
                    SELECT matnr, SUM(menge) AS qty, SUM(dmbtr) AS amt
                    FROM PUBLIC.tb_skb111_1mbgni
                    WHERE werks = 1
                      AND mjahr = 2026
                      AND lfmon = 1
                      AND ypotp = 'YPO2'
                      AND matnr IN (SELECT id_items FROM target_items)
                    GROUP BY matnr
                )
                SELECT
                    CASE
                        WHEN COALESCE(latest_po.harga_satuan, 0) > 0 THEN latest_po.harga_satuan
                        WHEN COALESCE(pur.qty, 0) > 0 THEN ROUND(COALESCE(pur.amt, 0) / NULLIF(COALESCE(pur.qty, 0), 0), 2)
                        WHEN COALESCE(sa.qty, 0) > 0 THEN ROUND(COALESCE(sa.amt, 0) / NULLIF(COALESCE(sa.qty, 0), 0), 2)
                        ELSE 0
                    END AS avg_price
                FROM target_items ti
                LEFT JOIN latest_po ON latest_po.id_items = ti.id_items
                LEFT JOIN pur ON pur.matnr = ti.id_items
                LEFT JOIN sa ON sa.matnr = ti.id_items
                LIMIT 1
            ";

            $safeItemId = is_numeric($itemId) ? $itemId : 0;
            $price = DB::connection('pgsql2')->selectOne($sql, [$safeItemId, $name, $safeItemId]);

            return (float) ($price->avg_price ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil harga barang mobile PB: ' . $e->getMessage(), [
                'nama_barang' => $name,
                'barang_id' => $itemId,
            ]);

            return 0;
        }
    }

    private function mobileItemAvailableStock(string $name, $itemId = null, string $materialType = 'sparepart'): float
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                WITH target_items AS (
                    SELECT id_items
                    FROM PUBLIC.tb_skb080_1mmara m
                    WHERE {$materialScope}
                      AND (
                          id_items = CAST(? AS bigint)
                          OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                    )
                    ORDER BY
                        CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END,
                        id_items
                    LIMIT 1
                )
                SELECT qty
                FROM (
                    SELECT
                        COALESCE((
                            SELECT SUM(sa.menge)
                            FROM PUBLIC.tb_skb111_1mbgni sa
                            WHERE sa.werks = 1
                              AND sa.mjahr = EXTRACT(YEAR FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                              AND sa.lfmon = EXTRACT(MONTH FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                              AND sa.ypotp = 'YPO2'
                              AND sa.matnr IN (SELECT id_items FROM target_items)
                        ), 0)
                        + COALESCE((
                            SELECT SUM(CASE
                                WHEN d.bwart IN ('101', '921', '931', '971') THEN d.menge
                                WHEN d.bwart IN ('122', '941', '981') THEN -d.menge
                                WHEN d.bwart = '201' AND d.saknr <> 7755 THEN -d.menge
                                WHEN d.bwart = '201' AND d.lvorm = 'U' AND d.saknr = 7755 THEN -d.menge
                                ELSE 0
                            END)
                            FROM PUBLIC.tb_skb008_2dmseg d
                            WHERE d.werks = 1
                              AND d.cpudt BETWEEN DATE_TRUNC('month', CURRENT_DATE)::date AND CURRENT_DATE
                              AND d.matnr IN (SELECT id_items FROM target_items)
                        ), 0) AS qty
                ) stock
            ";

            $safeItemId = is_numeric($itemId) ? $itemId : 0;
            $stock = DB::connection('pgsql2')->selectOne($sql, [$safeItemId, $name, $safeItemId]);

            return (float) ($stock->qty ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil stok barang mobile PB: ' . $e->getMessage(), [
                'nama_barang' => $name,
                'barang_id' => $itemId,
            ]);

            return 0;
        }
    }

    private function mobileItemStockSources(string $name, $itemId = null, string $materialType = 'sparepart'): array
    {
        $erpQty = $this->mobileItemAvailableStock($name, $itemId, $materialType);
        $master = $this->mobileItemMasterInfo($name, $itemId, $materialType);
        $area = $this->mobileItemAreaStock($master['code'] ?? '', $name);

        return [
            'erp_qty' => $erpQty,
            'erp_unit' => $this->mobileNormalizeStockUnit($master['unit'] ?? ''),
            'area_qty' => (float) ($area['qty'] ?? 0),
            'area_unit' => $this->mobileNormalizeStockUnit($area['unit'] ?? ''),
        ];
    }

    private function mobileItemMasterInfo(string $name, $itemId = null, string $materialType = 'sparepart'): array
    {
        try {
            $materialScope = $this->materialSql('m', $materialType);
            $sql = "
                SELECT code, meins
                FROM PUBLIC.tb_skb080_1mmara m
                WHERE {$materialScope}
                  AND (
                      id_items = CAST(? AS bigint)
                      OR LOWER(TRIM(item_name)) = LOWER(TRIM(?))
                  )
                ORDER BY CASE WHEN id_items = CAST(? AS bigint) THEN 0 ELSE 1 END, id_items
                LIMIT 1
            ";
            $safeItemId = is_numeric($itemId) ? $itemId : 0;
            $row = DB::connection('pgsql2')->selectOne($sql, [$safeItemId, $name, $safeItemId]);

            return [
                'code' => strtoupper(trim((string) ($row->code ?? ''))),
                'unit' => strtoupper(trim((string) ($row->meins ?? ''))),
            ];
        } catch (\Throwable $e) {
            return ['code' => '', 'unit' => ''];
        }
    }

    private function mobileItemAreaStock(?string $code, string $name): array
    {
        if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
            return ['qty' => 0, 'unit' => ''];
        }

        $query = DB::table('warehouse2_stock as s')
            ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
            ->select(DB::raw('SUM(s.quantity) as qty'), DB::raw('MAX(i.unit) as unit'))
            ->where('s.quantity', '>', 0);

        $code = strtoupper(trim((string) $code));
        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
        if ($code !== '') {
            $query->where(function ($match) use ($code, $hasErpCodeColumn) {
                $match->where(DB::raw('UPPER(TRIM(i.code))'), $code);

                if ($hasErpCodeColumn) {
                    $match->orWhere(DB::raw('UPPER(TRIM(i.erp_code))'), $code);
                }
            });
        } else {
            $query->where(DB::raw('UPPER(TRIM(i.name))'), strtoupper(trim($name)));
        }

        $row = $query->first();

        return [
            'qty' => (float) ($row->qty ?? 0),
            'unit' => strtoupper(trim((string) ($row->unit ?? ''))),
        ];
    }

    private function mobileAvailableStocksForItems(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        if (empty($itemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $rows = DB::connection('pgsql2')->select("
            WITH target_items AS (
                SELECT UNNEST(ARRAY[{$placeholders}]::bigint[]) AS id_items
            ),
            stok_awal AS (
                SELECT matnr, SUM(menge) AS qty
                FROM PUBLIC.tb_skb111_1mbgni
                WHERE werks = 1
                  AND mjahr = EXTRACT(YEAR FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND lfmon = EXTRACT(MONTH FROM (DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 day'))
                  AND ypotp = 'YPO2'
                  AND matnr IN (SELECT id_items FROM target_items)
                GROUP BY matnr
            ),
            mutasi AS (
                SELECT
                    matnr,
                    SUM(CASE
                        WHEN bwart IN ('101', '921', '931', '971') THEN menge
                        WHEN bwart IN ('122', '941', '981') THEN -menge
                        WHEN bwart = '201' AND saknr <> 7755 THEN -menge
                        WHEN bwart = '201' AND lvorm = 'U' AND saknr = 7755 THEN -menge
                        ELSE 0
                    END) AS qty
                FROM PUBLIC.tb_skb008_2dmseg
                WHERE werks = 1
                  AND cpudt BETWEEN DATE_TRUNC('month', CURRENT_DATE)::date AND CURRENT_DATE
                  AND matnr IN (SELECT id_items FROM target_items)
                GROUP BY matnr
            )
            SELECT ti.id_items, COALESCE(sa.qty, 0) + COALESCE(m.qty, 0) AS qty
            FROM target_items ti
            LEFT JOIN stok_awal sa ON sa.matnr = ti.id_items
            LEFT JOIN mutasi m ON m.matnr = ti.id_items
        ", $itemIds);

        $stockById = [];
        foreach ($rows as $row) {
            $stockById[(int) $row->id_items] = (float) $row->qty;
        }

        return $stockById;
    }

    private function mobileAreaStocksForItems(array $items): array
    {
        if (!Schema::hasTable('warehouse2_stock') || !Schema::hasTable('warehouse2_items')) {
            return [];
        }

        $codes = collect($items)->pluck('code')->map(fn ($value) => strtoupper(trim((string) $value)))->filter()->unique()->values();
        $names = collect($items)->pluck('name')->map(fn ($value) => strtoupper(trim((string) $value)))->filter()->unique()->values();

        if ($codes->isEmpty() && $names->isEmpty()) {
            return [];
        }

        $hasErpCodeColumn = Schema::hasColumn('warehouse2_items', 'erp_code');
        $select = [
            DB::raw('UPPER(TRIM(i.code)) as code_key'),
            DB::raw('UPPER(TRIM(i.name)) as name_key'),
            DB::raw('SUM(s.quantity) as quantity'),
            DB::raw('MAX(i.unit) as unit'),
        ];

        if ($hasErpCodeColumn) {
            $select[] = DB::raw('UPPER(TRIM(i.erp_code)) as erp_code_key');
        }

        $rows = DB::table('warehouse2_stock as s')
            ->join('warehouse2_items as i', 's.item_id', '=', 'i.id')
            ->select($select)
            ->where('s.quantity', '>', 0)
            ->where(function ($query) use ($codes, $names, $hasErpCodeColumn) {
                if ($codes->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('UPPER(TRIM(i.code))'), $codes->all());

                    if ($hasErpCodeColumn) {
                        $query->orWhereIn(DB::raw('UPPER(TRIM(i.erp_code))'), $codes->all());
                    }
                }
                if ($names->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('UPPER(TRIM(i.name))'), $names->all());
                }
            })
            ->groupBy(
                DB::raw('UPPER(TRIM(i.code))'),
                DB::raw('UPPER(TRIM(i.name))'),
                ...($hasErpCodeColumn ? [DB::raw('UPPER(TRIM(i.erp_code))')] : [])
            )
            ->get();

        $stockByKey = [];
        foreach ($rows as $row) {
            $qty = (float) ($row->quantity ?? 0);
            $unit = strtoupper(trim((string) ($row->unit ?? '')));
            if ($row->code_key) {
                $key = 'code:' . $row->code_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
            if (($row->erp_code_key ?? '') !== '') {
                $key = 'code:' . $row->erp_code_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
            if ($row->name_key) {
                $key = 'name:' . $row->name_key;
                $stockByKey[$key] = [
                    'qty' => ($stockByKey[$key]['qty'] ?? 0) + $qty,
                    'unit' => $stockByKey[$key]['unit'] ?? $unit,
                ];
            }
        }

        $mapped = [];
        foreach ($items as $item) {
            $mapped[$this->mobileStockAreaLookupKey($item['code'] ?? '', $item['name'] ?? '')] =
                $stockByKey[$this->mobileStockAreaLookupKey($item['code'] ?? '', $item['name'] ?? '')] ?? ['qty' => 0, 'unit' => null];
        }

        return $mapped;
    }

    private function mobileStockAreaLookupKey(?string $code, ?string $name): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code !== '') {
            return 'code:' . $code;
        }

        return 'name:' . strtoupper(trim((string) $name));
    }

    private function mobileSameStockUnit(?string $left, ?string $right): bool
    {
        return $this->mobileNormalizeStockUnit((string) $left) === $this->mobileNormalizeStockUnit((string) $right);
    }

    private function mobileNormalizeStockUnit(string $unit): string
    {
        $unit = strtoupper(trim($unit));
        $map = [
            'PC' => 'PCS',
            'PCS' => 'PCS',
            'UN' => 'UNIT',
            'UNIT' => 'UNIT',
            'KG' => 'KG',
            'KGM' => 'KG',
            'M' => 'M',
            'MTR' => 'M',
            'METER' => 'M',
            'ROLL' => 'ROLL',
            'ROL' => 'ROLL',
            'BTG' => 'BTG',
            'BATANG' => 'BTG',
        ];

        return $map[$unit] ?? $unit;
    }

    private function mobileFormatStockQuantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function mobileWebResponse(string $title, string $body, int $status = 200)
    {
        $token = request()->bearerToken()
            ?: (string) request()->query('token', '')
            ?: (string) request()->input('token', '');

        $html = '<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>' . e($title) . '</title>
    <style>
        :root { --bg:#f3f6fa; --surface:#ffffff; --soft:#f8fafc; --text:#0f172a; --muted:#64748b; --border:#dbe4ef; --blue:#2563eb; --teal:#0f766e; --green:#16a34a; --red:#dc2626; --orange:#d97706; --amber:#f59e0b; }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        [hidden] { display:none !important; }
        body { margin:0; padding:0 0 14px; background:var(--bg); color:var(--text); font-family:Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size:14px; }
        a { color:inherit; text-decoration:none; }
        .subtitle { margin:4px 0 10px; color:var(--muted); line-height:1.45; }
        .toolbar { position:sticky; top:0; z-index:3; padding:4px 0 12px; background:linear-gradient(180deg, var(--bg) 86%, rgba(243,246,250,0)); }
        .date-filter-row { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; margin:8px 0 0; }
        .date-filter-row .field-wrap { gap:3px; padding:8px 10px; border:1px solid var(--border); border-radius:13px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .date-filter-row .field-wrap span { font-size:10px; }
        .date-filter-row .field-wrap input { height:30px; padding:0; border:0; border-radius:0; box-shadow:none; background:transparent; font-size:13px; font-weight:800; color:var(--text); }
        .date-filter-row .field-wrap input:focus { box-shadow:none; border:0; }
        .filter-toggle { width:100%; min-height:46px; margin:0 0 10px; padding:0 14px; display:flex; align-items:center; justify-content:space-between; border:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; border-radius:14px; font:inherit; font-weight:800; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .filter-toggle strong { color:var(--blue); font-size:13px; }
        .filter-panel { margin:0 0 10px; padding:12px; border:1px solid var(--border); background:#fff; border-radius:16px; box-shadow:0 2px 6px rgba(15,23,42,.05); }
        .filter-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .field-wrap { display:grid; gap:6px; min-width:0; }
        .field-wrap span { color:var(--muted); font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .field-wrap select, .field-wrap input, .search { width:100%; border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:12px; height:44px; padding:0 12px; font:inherit; outline:none; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .field-wrap select:focus, .field-wrap input:focus, textarea:focus, .search:focus { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(37,99,235,.10); }
        .target { padding:10px; border:1px solid #dbeafe; border-radius:16px; background:#f8fbff; }
        .target-search { background:#fff !important; }
        .suggestion-list { display:grid; gap:6px; margin-top:8px; }
        .suggestion-list:empty { display:none; }
        .suggestion-list button { width:100%; min-height:48px; padding:9px 11px; border:1px solid #dbeafe; border-radius:12px; background:#fff; color:var(--text); text-align:left; font:inherit; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .suggestion-list button strong { display:block; font-size:13px; line-height:1.25; }
        .suggestion-list button small { display:block; margin-top:3px; color:var(--muted); font-size:12px; line-height:1.25; }
        .suggestion-list button:active { background:#eff6ff; border-color:#93c5fd; }
        .suggestion-empty { padding:9px 11px; border:1px dashed #cbd5e1; border-radius:12px; background:#f8fafc; color:var(--muted); font-size:12px; }
        .field-wrap input:invalid { color:var(--muted); }
        .search { margin-top:10px; margin-bottom:2px; background:#fff; }
        .filter-actions { display:grid; grid-template-columns:1fr 1.2fr; gap:10px; margin-top:10px; }
        .reset-filter, .apply-filter { width:100%; height:42px; border-radius:12px; font:inherit; font-weight:900; }
        .reset-filter { border:1px solid #bfdbfe; background:#f8fbff; color:var(--blue); }
        .apply-filter { border:1px solid var(--blue); background:var(--blue); color:#fff; box-shadow:0 8px 18px rgba(37,99,235,.18); }
        .list { display:grid; gap:12px; }
        .history-screen { padding-bottom:18px; }
        .history-filter-summary { display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin:2px 0 14px; padding:0 2px; line-height:1.35; }
        .history-filter-summary strong { font-weight:900; }
        .history-filter-summary span { color:var(--muted); }
        .history-list { display:grid; gap:16px; }
        .history-card, .detail-card { display:block; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:15px; box-shadow:0 2px 6px rgba(15,23,42,.05); }
        .history-card h2, .detail-card h1 { margin:8px 0 8px; font-size:18px; line-height:1.2; letter-spacing:0; }
        .history-card p { margin:0 0 6px; font-weight:700; color:#243044; }
        .history-card small, .photo-card span, .photo-card small { display:block; color:var(--muted); line-height:1.4; }
        .card-top, .detail-head { display:flex; gap:10px; align-items:flex-start; justify-content:space-between; }
        .card-statuses, .status-stack { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; }
        .status-stack { max-width:48%; }
        .type-pill, .status, .mini { display:inline-flex; align-items:center; justify-content:center; min-height:24px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid transparent; }
        .type-pill.pb { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
        .type-pill.wo { color:#0f766e; background:#ecfdf5; border-color:#ccfbf1; }
        .type-pill.nwo { color:#6d28d9; background:#f5f3ff; border-color:#ddd6fe; }
        .status.done, .status.approved { color:#047857; background:#dcfce7; border-color:#bbf7d0; }
        .status.rejected, .mini.danger { color:#b91c1c; background:#fee2e2; border-color:#fecaca; }
        .status.submitted, .status.pending { color:#7c3aed; background:#f3e8ff; border-color:#ddd6fe; }
        .status.progress { color:#1d4ed8; background:#dbeafe; border-color:#bfdbfe; }
        .status.open { color:#b45309; background:#fef3c7; border-color:#fde68a; }
        .card-meta { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; color:var(--muted); font-weight:700; }
        .card-meta span { background:var(--soft); border:1px solid #edf2f7; border-radius:10px; padding:7px 9px; }
        .preview-items { margin:10px 0 0; padding-left:18px; color:#334155; }
        .preview-items li { margin:4px 0; }
        .empty { background:var(--surface); border:1px dashed var(--border); border-radius:16px; padding:24px 16px; color:var(--muted); text-align:center; }
        .empty.small { padding:12px; border-radius:12px; }
        .eyebrow { margin:0; color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
        .meta-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; margin:14px 0; }
        .meta-grid div, .total-box, .note, .content-block, .file-link, .photo-card { border:1px solid var(--border); background:var(--soft); border-radius:14px; padding:12px; }
        .meta-grid span, .total-box span, .note span { display:block; color:var(--muted); font-size:12px; margin-bottom:4px; }
        .meta-grid strong, .total-box strong { display:block; line-height:1.35; }
        .total-box { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .total-box strong { color:var(--blue); font-size:18px; }
        .section-title { margin:16px 0 8px; font-size:14px; font-weight:900; }
        .item-list { display:grid; gap:8px; }
        .item-row { display:flex; justify-content:space-between; gap:10px; padding:12px; border:1px solid var(--border); border-radius:14px; background:#fff; }
        .item-row strong, .item-row span { display:block; }
        .item-row span { color:var(--muted); margin-top:3px; }
        .price { white-space:nowrap; font-weight:900; color:#0f172a; }
        .timeline { margin-top:14px; display:grid; gap:8px; }
        .timeline div { display:flex; justify-content:space-between; gap:12px; padding:10px 0; border-top:1px solid #e5eaf1; }
        .timeline span { color:var(--muted); }
        .note { margin-top:10px; line-height:1.5; }
        .note small { display:block; margin-top:6px; color:var(--muted); }
        .backdate-note { background:#fffbeb; border-color:#fcd34d; color:#78350f; }
        .backdate-note span, .backdate-note small { color:#92400e; }
        .success-note { background:#f0fdf4; border-color:#bbf7d0; }
        .danger-note { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
        .content-block h2 { margin:0 0 8px; font-size:17px; }
        .content-block p { margin:0; color:#334155; line-height:1.5; }
        .file-link { display:block; color:var(--blue); font-weight:800; }
        .document-preview { display:grid; gap:12px; }
        .document-frame { min-height:560px; border:1px solid var(--border); border-radius:16px; overflow:hidden; background:#fff; }
        .document-frame iframe { display:block; width:100%; height:560px; border:0; background:#fff; }
        .document-frame.image-frame { min-height:0; display:flex; align-items:center; justify-content:center; padding:10px; background:#f8fafc; }
        .document-frame.image-frame img { display:block; width:100%; max-height:680px; object-fit:contain; border-radius:10px; }
        .document-actions { display:grid; gap:10px; }
        .document-actions .file-link { text-align:center; }
        .photo-list { display:grid; gap:10px; }
        .photo-card { display:grid; grid-template-columns:78px 1fr; gap:12px; align-items:center; background:#fff; }
        .photo-card img { width:78px; height:78px; object-fit:cover; border-radius:12px; border:1px solid var(--border); background:#eef2f7; }
        .photo-card strong { display:block; margin-bottom:3px; }
        .mobile-action-panel { margin-top:16px; padding:14px; border:1px solid #bfdbfe; border-radius:16px; background:#f8fbff; box-shadow:inset 4px 0 0 var(--blue), 0 2px 6px rgba(15,23,42,.04); }
        .mobile-action-panel h3 { margin:0; font-size:16px; color:var(--text); }
        .mobile-action-panel p { margin:6px 0 12px; color:var(--muted); font-size:12px; line-height:1.45; }
        .mobile-action-panel label { display:block; margin:10px 0 6px; color:#475569; font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .mobile-action-field { width:100%; min-height:44px; border:1px solid #d7e2f0; border-radius:14px; padding:12px; font:inherit; color:var(--text); background:#fff; box-sizing:border-box; }
        .mobile-action-field:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .mobile-action-buttons { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:12px; }
        .mobile-action-buttons.stacked { grid-template-columns:1fr; }
        .mobile-action-buttons button { border:0; border-radius:14px; padding:13px 10px; font-weight:900; font-size:14px; color:#fff; box-shadow:0 8px 18px rgba(15,23,42,.12); }
        .mobile-action-buttons button:disabled { opacity:.62; box-shadow:none; }
        .mobile-action-buttons .approve { background:var(--blue); }
        .mobile-action-buttons .reject { background:#dc2626; }
        .eng-hero { margin:4px 0 14px; padding:18px; border-radius:20px; color:#fff; background:linear-gradient(135deg,#0f172a,#0f766e); box-shadow:0 12px 24px rgba(15,23,42,.14); }
        .eng-hero span { color:#bfdbfe; font-size:12px; font-weight:800; }
        .eng-hero h1 { margin:8px 0 6px; font-size:24px; line-height:1.1; }
        .eng-hero p { margin:0; color:#dbeafe; line-height:1.45; }
        .create-head { margin:2px 0 12px; }
        .create-head h1 { margin:10px 0 4px; font-size:24px; line-height:1.15; }
        .create-head p { margin:0; color:var(--muted); line-height:1.45; }
        .back-link { display:inline-flex; min-height:38px; align-items:center; gap:6px; padding:0 12px; color:var(--text); font-weight:900; white-space:nowrap; border:1px solid #dbeafe; border-radius:999px; background:rgba(255,255,255,.94); box-shadow:0 8px 18px rgba(15,23,42,.08); }
        .back-link:active { transform:translateY(1px); background:#eff6ff; }
        .back-link span { font-size:18px; line-height:1; margin-top:-1px; }
        .mobile-page-head { position:sticky; top:0; z-index:70; display:grid; grid-template-columns:auto 1fr; align-items:start; gap:12px; min-height:68px; margin:0 -10px 10px; padding:10px 10px 12px; border-bottom:1px solid rgba(219,228,239,.74); background:linear-gradient(180deg,rgba(243,246,250,.98) 0%,rgba(243,246,250,.95) 82%,rgba(243,246,250,0) 100%); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); }
        .mobile-page-head .back-link { align-self:start; }
        .mobile-page-head h1 { margin:0; text-align:right; font-size:24px; line-height:1.08; letter-spacing:0; }
        .mobile-page-head p { margin:8px 0 0; color:var(--muted); text-align:right; line-height:1.35; }
        .compact-action { padding:12px; }
        .compact-action .filter-toggle { margin:0; }
        .create-form { padding:14px; margin-top:2px; }
        .form-number { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; padding:12px 14px; border:1px solid #bfdbfe; border-radius:14px; background:#eff6ff; color:#1e3a8a; }
        .form-number span { color:#64748b; font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .form-number strong { color:var(--blue); font-size:15px; }
        .create-form form { display:grid; gap:12px; }
        .form-section { display:grid; gap:11px; padding:12px; border:1px solid #dbeafe; border-radius:18px; background:linear-gradient(180deg,#ffffff,#f8fbff); box-shadow:0 1px 4px rgba(15,23,42,.04); }
        .form-section-title { display:flex; align-items:center; gap:8px; color:#1e3a8a; font-size:13px; font-weight:950; letter-spacing:.01em; }
        .form-section-title::before { content:""; display:block; width:8px; height:8px; border-radius:999px; background:var(--blue); box-shadow:0 0 0 4px rgba(37,99,235,.10); }
        .sticky-submit { position:sticky; bottom:12px; z-index:4; margin-top:4px; box-shadow:0 10px 22px rgba(37,99,235,.22); }
        .eng-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; }
        .eng-tile { position:relative; display:block; min-height:132px; padding:14px 12px 12px; overflow:hidden; border:1px solid #bfdbfe; border-radius:18px; background:linear-gradient(180deg,#f8fbff,#eff6ff); box-shadow:0 2px 8px rgba(15,23,42,.06); }
        .eng-tile::before { content:""; position:absolute; inset:0 0 auto; height:4px; background:var(--blue); }
        .eng-tile.accent { border-color:#99f6e4; background:linear-gradient(180deg,#f8fffd,#ecfdf5); }
        .eng-tile.accent::before { background:var(--teal); }
        .eng-tile span, .eng-tile small { display:block; color:var(--muted); }
        .eng-tile span { min-height:34px; font-size:12px; font-weight:900; line-height:1.25; }
        .eng-tile strong { display:block; margin:6px 0 7px; font-size:30px; line-height:1; color:var(--blue); letter-spacing:0; }
        .eng-tile small { min-height:34px; font-size:11px; line-height:1.45; }
        .eng-tile.accent strong { color:var(--teal); }
        .eng-tabs { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
        .eng-tabs a { display:flex; align-items:center; justify-content:center; height:42px; border:1px solid var(--border); border-radius:14px; background:#fff; color:var(--muted); font-weight:900; }
        .eng-tabs a.active { color:#fff; border-color:var(--blue); background:var(--blue); box-shadow:0 8px 18px rgba(37,99,235,.18); }
        .stock-shortcut { margin:0 0 12px; }
        .stock-shortcut a { display:flex; align-items:center; justify-content:space-between; gap:12px; min-height:66px; padding:14px; border:1px solid #99f6e4; border-radius:18px; background:linear-gradient(180deg,#f8fffd,#ecfdf5); box-shadow:0 2px 8px rgba(15,23,42,.06); }
        .stock-shortcut span { display:block; color:#0f766e; font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
        .stock-shortcut strong { display:block; margin-top:4px; font-size:15px; line-height:1.25; }
        .stock-shortcut b { display:inline-flex; align-items:center; justify-content:center; min-width:62px; height:36px; border-radius:12px; color:#fff; background:var(--teal); font-size:13px; box-shadow:0 8px 18px rgba(15,118,110,.16); }
        .stock-head { display:grid; gap:7px; margin-bottom:10px; }
        .stock-head h1 { margin:0; font-size:24px; line-height:1.12; }
        .stock-head p { margin:0; color:var(--muted); line-height:1.45; }
        .stock-summary { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; }
        .stock-summary div { padding:12px; border:1px solid #bfdbfe; border-radius:15px; background:#eff6ff; }
        .stock-summary span, .stock-line span { display:block; color:var(--muted); font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .stock-summary strong { display:block; margin-top:4px; color:var(--blue); font-size:22px; line-height:1; }
        .stock-toolbar form { display:grid; gap:8px; }
        .stock-toolbar .search { margin:0; }
        .stock-card { display:block; background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:15px; box-shadow:0 2px 6px rgba(15,23,42,.05); }
        .stock-card h2 { margin:10px 0 5px; font-size:17px; line-height:1.25; }
        .stock-card small { display:block; color:var(--muted); }
        .stock-result-card { display:grid; gap:12px; padding:14px; border:1px solid var(--border); border-radius:16px; background:var(--surface); box-shadow:0 2px 7px rgba(15,23,42,.05); }
        .stock-result-main { display:grid; grid-template-columns:1fr; gap:8px; padding-bottom:11px; border-bottom:1px solid #edf2f7; }
        .stock-result-main span, .stock-result-grid span { display:block; color:var(--muted); font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .stock-result-main strong { display:block; margin-top:4px; color:var(--text); line-height:1.28; }
        .stock-result-name { font-size:16px; }
        .stock-result-code { font-size:15px; color:#1e3a8a !important; }
        .stock-result-grid { display:grid; grid-template-columns:.9fr .9fr 1.2fr; gap:8px; }
        .stock-result-grid div { min-width:0; padding:10px; border:1px solid #edf2f7; border-radius:13px; background:#f8fafc; }
        .stock-result-grid strong { display:block; margin-top:5px; color:var(--text); font-size:15px; }
        .stock-line { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:8px; margin-top:12px; }
        .stock-line div { padding:10px; border:1px solid var(--border); border-radius:13px; background:var(--soft); }
        .stock-line strong { display:block; margin-top:4px; line-height:1.25; }
        .form-card { margin-bottom:12px; padding:12px; border:1px solid var(--border); border-radius:18px; background:#fff; box-shadow:0 2px 6px rgba(15,23,42,.05); }
        form { display:grid; gap:10px; }
        textarea, input, select { font-family:inherit; }
        textarea { width:100%; border:1px solid var(--border); border-radius:12px; padding:11px 12px; resize:vertical; outline:none; }
        .pb-item { display:grid; gap:8px; margin-bottom:10px; padding:10px; border:1px solid var(--border); border-radius:14px; background:var(--soft); }
        .pb-item input, .pb-item select { width:100%; border:1px solid var(--border); background:#fff; color:var(--text); border-radius:12px; height:42px; padding:0 12px; font:inherit; outline:none; }
        .pb-item input:disabled { background:#f1f5f9; color:#94a3b8; }
        .pb-item > button { height:36px; border:1px solid #fecaca; border-radius:12px; background:#fff1f2; color:#be123c; font-weight:800; }
        .pb-item .suggestion-list { display:grid; gap:6px; max-height:232px; overflow-y:auto; margin-top:8px; margin-bottom:12px; padding:0 2px 6px; overscroll-behavior:contain; }
        .pb-item .suggestion-list:empty { display:none; }
        .pb-item .suggestion-list button { display:grid; gap:4px; width:100%; min-height:66px; padding:10px 12px; border:1px solid #dbeafe; border-radius:12px; background:#fff; color:var(--text); text-align:left; font:inherit; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .pb-item .suggestion-list button strong { display:-webkit-box; color:var(--text); font-size:13px; line-height:1.25; overflow:hidden; overflow-wrap:anywhere; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .pb-item .suggestion-list button small { display:block; color:var(--muted); font-size:12px; line-height:1.25; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .pb-item .suggestion-list button:active { background:#eff6ff; border-color:#93c5fd; }
        .pb-item .suggestion-empty { padding:9px 11px; border:1px dashed #cbd5e1; border-radius:12px; background:#f8fafc; color:var(--muted); font-size:12px; }
        .pb-stock-line { display:flex; align-items:center; justify-content:space-between; gap:8px; min-height:34px; padding:7px 10px; border-radius:12px; border:1px solid #bbf7d0; background:#ecfdf5; color:#047857; font-size:12px; font-weight:900; }
        .pb-stock-line span { color:inherit; opacity:.88; }
        .pb-stock-line strong { white-space:nowrap; }
        .pb-stock-line.empty { border-color:#fecaca; background:#fef2f2; color:#b91c1c; }
        .item-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .submit-notice { margin:2px 0 4px; padding:11px 12px; border-radius:14px; font-weight:800; line-height:1.35; }
        .submit-notice.info { border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; }
        .submit-notice.success { border:1px solid #bbf7d0; background:#f0fdf4; color:#15803d; }
        .submit-notice.error { border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; }
        .approval-dashboard { display:grid; gap:12px; }
        .dashboard-topbar { position:sticky; top:0; z-index:70; display:grid; grid-template-columns:auto 1fr; align-items:start; gap:12px; min-height:68px; margin:0 -10px 10px; padding:10px 10px 12px; border-bottom:1px solid rgba(219,228,239,.74); background:linear-gradient(180deg,rgba(243,246,250,.98) 0%,rgba(243,246,250,.95) 82%,rgba(243,246,250,0) 100%); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); }
        .dashboard-topbar .back-link { align-self:start; }
        .dashboard-topbar h1 { margin:0; text-align:right; font-size:24px; line-height:1.08; letter-spacing:0; }
        .dashboard-topbar p { margin:8px 0 0; color:var(--muted); text-align:right; line-height:1.35; }
        .dashboard-main-title { display:block; min-height:auto; padding:2px 2px 8px; }
        .dashboard-main-title h1, .dashboard-main-title p { text-align:left; }
        .detail-title { margin:0 0 8px; font-size:24px; line-height:1.12; letter-spacing:0; }
        .dashboard-hero { padding:18px; border-radius:22px; color:#fff; background:linear-gradient(135deg,#0f172a,#0f766e); box-shadow:0 12px 24px rgba(15,23,42,.14); }
        .dashboard-hero span { display:block; color:#bfdbfe; font-size:12px; font-weight:900; letter-spacing:.02em; }
        .dashboard-hero h1 { margin:8px 0 4px; font-size:21px; line-height:1.15; letter-spacing:0; }
        .dashboard-hero p { margin:0; color:#dbeafe; font-weight:700; }
        .factory-dashboard { gap:12px; }
        .factory-hero { position:relative; overflow:hidden; display:grid; grid-template-columns:1fr auto; align-items:end; gap:12px; padding:18px; border-radius:24px; color:#fff; background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 56%,#0f766e 100%); box-shadow:0 16px 30px rgba(15,23,42,.18); }
        .factory-hero:after { content:""; position:absolute; right:-52px; top:-64px; width:150px; height:150px; border-radius:999px; background:rgba(255,255,255,.14); }
        .factory-hero span { position:relative; display:inline-flex; width:max-content; padding:5px 9px; border-radius:999px; background:rgba(255,255,255,.14); color:#dbeafe; font-size:11px; font-weight:900; letter-spacing:.02em; }
        .factory-hero h2 { position:relative; margin:12px 0 6px; font-size:23px; line-height:1.06; letter-spacing:0; }
        .factory-hero p { position:relative; margin:0; max-width:250px; color:#e0f2fe; font-size:13px; line-height:1.36; font-weight:750; }
        .factory-hero strong { position:relative; display:block; min-width:58px; text-align:right; font-size:38px; line-height:1; letter-spacing:0; }
        .factory-hero small { position:relative; grid-column:2; align-self:start; margin-top:-8px; color:#dbeafe; font-size:11px; font-weight:900; text-align:right; text-transform:uppercase; letter-spacing:.04em; }
        .factory-panel { padding:12px; border:1px solid #dbeafe; border-radius:22px; background:#fff; box-shadow:0 2px 10px rgba(15,23,42,.07); }
        .factory-panel-head { display:flex; justify-content:space-between; gap:12px; margin-bottom:11px; padding:0 2px; }
        .factory-panel-head h2 { margin:0 0 4px; font-size:18px; line-height:1.2; }
        .factory-panel-head p { margin:0; color:var(--muted); font-size:12px; line-height:1.35; }
        .factory-metric-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .factory-metric-grid .queue-metric { min-height:92px; border-color:#e2e8f0; border-radius:18px; background:linear-gradient(180deg,#ffffff,#f8fafc); box-shadow:0 1px 0 rgba(15,23,42,.03); }
        .factory-metric-grid .queue-metric span { min-height:0; color:#64748b; font-size:11px; letter-spacing:.01em; }
        .factory-metric-grid .queue-metric strong { margin-top:10px; font-size:31px; }
        .factory-metric-grid .queue-metric small { margin-top:9px; font-size:11px; }
        .factory-metric-grid .queue-metric:last-child { grid-column:1 / -1; display:grid; grid-template-columns:1fr auto; align-items:center; min-height:74px; }
        .factory-metric-grid .queue-metric:last-child span,
        .factory-metric-grid .queue-metric:last-child small { grid-column:1; }
        .factory-metric-grid .queue-metric:last-child strong { grid-column:2; grid-row:1 / span 2; margin:0; font-size:32px; }
        .engineering-metric-grid .queue-metric:last-child { grid-column:auto; min-height:92px; }
        .engineering-metric-grid .queue-metric:last-child strong { margin-top:10px; font-size:31px; }
        .queue-card { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .budget-mobile-card { padding:12px; border:1px solid var(--border); border-radius:18px; background:#fff; box-shadow:0 2px 8px rgba(15,23,42,.06); }
        .queue-title { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:12px; padding:0 2px; }
        .queue-title h2, .budget-mobile-card h2 { margin:0 0 4px; font-size:18px; line-height:1.2; }
        .queue-title p, .budget-mobile-card p { margin:0; color:var(--muted); line-height:1.4; }
        .queue-title small { white-space:nowrap; color:var(--muted); font-size:11px; font-weight:800; }
        .mobile-budget-filter { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:10px 0 2px; padding:10px; border:1px solid #bfdbfe; border-radius:14px; background:#eff6ff; }
        .mobile-budget-filter > div { grid-column:1 / -1; display:grid; gap:2px; }
        .mobile-budget-filter strong { color:#172554; font-size:12px; line-height:1.25; }
        .mobile-budget-filter small { color:#64748b; font-size:11px; line-height:1.3; }
        .mobile-budget-filter label { display:grid; gap:4px; min-width:0; }
        .mobile-budget-filter label.is-hidden { display:none; }
        .mobile-budget-filter label span { color:#64748b; font-size:10px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .mobile-budget-filter select { width:100%; height:38px; border:1px solid #bfdbfe; border-radius:11px; background:#fff; color:#0f172a; padding:0 9px; font:inherit; font-size:12px; font-weight:800; }
        .mobile-budget-filter button { grid-column:1 / -1; height:38px; border:0; border-radius:11px; background:#2563eb; color:#fff; font-weight:900; font-size:13px; }
        .queue-lanes { position:relative; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .queue-lanes::before { content:""; position:absolute; top:0; bottom:0; left:50%; width:1px; background:linear-gradient(180deg,transparent,#d7e2f1 8%,#d7e2f1 92%,transparent); transform:translateX(-.5px); pointer-events:none; }
        .queue-lane { display:grid; gap:9px; min-width:0; padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .queue-lanes.pb-only { grid-template-columns:1fr; }
        .queue-lanes.pb-only::before { display:none; }
        .queue-lanes.pb-only .queue-lane { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; padding:10px; background:#ffffff; }
        .queue-lanes.pb-only .queue-lane-head { grid-column:1 / -1; }
        .queue-lanes.pb-only .queue-metric:last-child { grid-column:1 / -1; }
        .queue-lane-head { min-height:auto; padding:2px 2px 6px; border:0; border-radius:0; color:#172554; background:transparent; box-shadow:none; }
        .queue-lane:nth-child(2) .queue-lane-head { border:0; color:#134e4a; background:transparent; box-shadow:none; }
        .queue-lane-head span { display:inline-flex; align-items:center; justify-content:center; min-width:31px; height:24px; padding:0 8px; border-radius:999px; background:#2563eb; color:#fff; font-size:10px; font-weight:950; letter-spacing:.08em; }
        .queue-lane:nth-child(2) .queue-lane-head span { background:#0f766e; }
        .queue-lane-head strong { display:block; margin-top:8px; font-size:14px; line-height:1.18; white-space:normal; overflow-wrap:anywhere; }
        .queue-lane-head small { display:block; margin-top:4px; color:#64748b; font-size:10px; line-height:1.25; font-weight:800; }
        .queue-metric { position:relative; display:grid; grid-template-columns:minmax(0,1fr) auto; grid-template-rows:auto auto; align-items:end; gap:5px 8px; min-height:72px; padding:10px 10px 10px 13px; border:1px solid #e2e8f0; border-radius:16px; background:linear-gradient(180deg,#ffffff,#fbfdff); color:inherit; text-decoration:none; overflow:hidden; box-shadow:0 1px 0 rgba(15,23,42,.03); transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease; }
        .queue-metric::before { content:""; position:absolute; left:0; top:11px; bottom:11px; width:4px; border-radius:0 999px 999px 0; background:#cbd5e1; }
        .queue-metric:active { transform:scale(.985); box-shadow:0 8px 18px rgba(15,23,42,.10); border-color:#bfdbfe; }
        .queue-metric span { display:block; min-height:0; color:#64748b; font-size:10px; line-height:1.25; font-weight:950; letter-spacing:.01em; }
        .queue-metric strong { grid-column:2; grid-row:1 / span 2; display:block; margin:0; align-self:center; font-size:28px; line-height:1; letter-spacing:0; }
        .queue-metric small { grid-column:1; display:block; margin:0; color:var(--muted); font-size:10px; line-height:1.2; }
        .queue-metric em { position:absolute; right:11px; top:11px; width:10px; height:10px; display:block; border-radius:999px; background:#f97316; border:2px solid #fff7ed; font-size:0; line-height:0; box-shadow:0 0 0 0 rgba(249,115,22,.36); animation:mobileAttentionDot 1.35s ease-in-out infinite; }
        .queue-metric.needs-attention { border-color:#fdba74; background:linear-gradient(180deg,#fff7ed 0%,#ffffff 82%); box-shadow:0 10px 24px rgba(234,88,12,.14); animation:mobileAttentionCard 1.55s ease-in-out infinite; }
        .queue-metric.needs-attention span { align-self:start; padding-top:1px; padding-right:18px; }
        .queue-metric.needs-attention small { align-self:end; }
        .queue-metric.needs-attention::after { content:""; position:absolute; inset:0; border-radius:inherit; border:1px solid rgba(249,115,22,.42); pointer-events:none; }
        .queue-metric.waiting::before { background:var(--orange); }
        .queue-metric.approved::before, .queue-metric.done::before { background:var(--green); }
        .queue-metric.progress::before { background:var(--blue); }
        .queue-metric.rejected::before { background:var(--red); }
        .queue-metric.waiting strong { color:var(--orange); }
        .queue-metric.approved strong, .queue-metric.done strong { color:var(--green); }
        .queue-metric.progress strong { color:var(--blue); }
        .queue-metric.rejected strong { color:var(--red); }
        .quick-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .quick-actions a { display:flex; align-items:center; justify-content:center; min-height:46px; border-radius:14px; font-weight:900; }
        .section-action-card { padding:0; background:transparent; border:0; box-shadow:none; }
        .stock-action-button { display:flex; align-items:center; justify-content:center; min-height:52px; border-radius:16px; font-weight:950; letter-spacing:0; box-shadow:0 10px 18px rgba(37,99,235,.18); }
        .engineering-action-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .engineering-action-grid .primary-action { display:flex; align-items:center; justify-content:center; min-height:52px; border-radius:16px; font-size:13px; font-weight:950; text-align:center; }
        .primary-action { color:#fff; background:var(--blue); border:1px solid var(--blue); box-shadow:0 8px 18px rgba(37,99,235,.18); }
        .secondary-action { color:var(--blue); background:#fff; border:1px solid #bfdbfe; }
        .budget-mobile-card { display:grid; gap:10px; }
        .budget-box { padding:12px; border-radius:14px; border:1px solid transparent; }
        .budget-box span { display:block; margin-bottom:5px; font-size:11px; font-weight:900; letter-spacing:.04em; text-transform:uppercase; }
        .budget-box strong { display:block; font-size:18px; line-height:1; }
        .budget-box small { display:block; margin-top:6px; font-size:10px; font-weight:800; opacity:.8; }
        .budget-click { cursor:pointer; }
        .budget-click:active { transform:scale(.99); }
        .budget-box.success { color:#047857; background:#ecfdf5; border-color:#bbf7d0; }
        .budget-box.warning { color:#b45309; background:#fffbeb; border-color:#fde68a; }
        .budget-box.danger { color:#b91c1c; background:#fef2f2; border-color:#fecaca; }
        .budget-overlay { display:none; position:fixed; inset:0; z-index:60; padding:18px; align-items:center; justify-content:center; }
        .budget-toggle:checked ~ .budget-overlay { display:flex; }
        .budget-backdrop { position:absolute; inset:0; background:rgba(15,23,42,.48); }
        .budget-modal { position:relative; width:min(100%,420px); display:grid; gap:10px; padding:16px; border-radius:20px; background:#fff; box-shadow:0 20px 50px rgba(15,23,42,.24); }
        .budget-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding-bottom:8px; border-bottom:1px solid var(--border); }
        .budget-modal-head h2 { margin:0 0 4px; font-size:17px; }
        .budget-modal-head p { margin:0; color:var(--muted); font-size:12px; }
        .budget-modal-head label { display:grid; place-items:center; width:36px; height:36px; border-radius:12px; background:#f1f5f9; color:#475569; font-size:24px; line-height:1; }
        .budget-break-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px; border:1px solid var(--border); border-radius:14px; background:#f8fafc; }
        .budget-break-link { color:inherit; text-decoration:none; }
        .budget-break-link:active { transform:scale(.99); border-color:#93c5fd; background:#eff6ff; }
        .budget-break-row strong, .budget-break-row span { display:block; }
        .budget-break-row span { margin-top:3px; color:var(--muted); font-size:11px; line-height:1.25; }
        .budget-break-row b { color:#0f766e; white-space:nowrap; }
        .budget-break-row i { display:grid; place-items:center; flex:0 0 auto; width:22px; height:22px; border-radius:999px; background:#e0f2fe; color:#2563eb; font-style:normal; font-weight:900; }
        .budget-mobile-card h3 { margin:6px 0 0; font-size:15px; }
        .budget-list { display:grid; gap:8px; }
        .budget-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px; border:1px solid var(--border); border-radius:13px; background:#f8fafc; }
        .budget-row strong, .budget-row span { display:block; }
        .budget-row span { margin-top:2px; color:var(--muted); font-size:11px; }
        .budget-row b { white-space:nowrap; color:#0f766e; }
        @keyframes mobileAttentionDot { 0%,100% { opacity:1; box-shadow:0 0 0 0 rgba(249,115,22,.36); } 50% { opacity:.72; box-shadow:0 0 0 6px rgba(249,115,22,0); } }
        @keyframes mobileAttentionCard { 0%,100% { border-color:#fdba74; box-shadow:0 10px 24px rgba(234,88,12,.14); } 50% { border-color:#f97316; box-shadow:0 0 0 4px rgba(249,115,22,.10), 0 10px 24px rgba(234,88,12,.16); } }
        @media (prefers-reduced-motion:reduce) { .queue-metric.needs-attention, .queue-metric em { animation:none; } }
        @media (max-width:360px) { .meta-grid, .filter-grid { grid-template-columns:1fr; } .photo-card { grid-template-columns:64px 1fr; } .photo-card img { width:64px; height:64px; } }
    </style>
</head>
<body>' . $this->mobileWebChrome($title) . $body . '
    <script>' . $this->mobileWebActionScript($token) . '</script>
</body>
</html>';

        return response($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function mobileWebActionScript(?string $token): string
    {
        return 'var TOKEN_FROM_SERVER = ' . json_encode((string) $token) . ';
' . <<<'JS'
            var TOKEN = TOKEN_FROM_SERVER || (window.URLSearchParams ? new URLSearchParams(window.location.search).get("token") : "") || "";
            function withMobileToken(url) {
                if (!TOKEN || !url || url.indexOf("#") === 0 || url.indexOf("javascript:") === 0) return url;
                try {
                    var parsed = new URL(url, window.location.origin);
                    if (parsed.pathname.indexOf("/api/mobile/") === 0) {
                        parsed.searchParams.set("token", TOKEN);
                        if (!parsed.searchParams.get("back") && window.location.pathname.indexOf("/api/mobile/web/") === 0) {
                            parsed.searchParams.set("back", window.location.pathname + window.location.search);
                        }
                        return parsed.toString();
                    }
                } catch (e) {}
                return url;
            }
            document.addEventListener("DOMContentLoaded", function () {
                Array.prototype.forEach.call(document.querySelectorAll("a[href*='/api/mobile/web/']"), function (link) {
                    link.setAttribute("href", withMobileToken(link.getAttribute("href")));
                });
            });
            function mobileBack(event, link) {
                if (event) event.preventDefault();
                var fallback = link && link.getAttribute("href") ? link.getAttribute("href") : "/api/mobile/web/dashboard";
                if (link && link.dataset && link.dataset.backMode === "home") {
                    window.location.href = withMobileToken(fallback);
                    return false;
                }
                var qsBack = "";
                try {
                    qsBack = window.URLSearchParams ? new URLSearchParams(window.location.search).get("back") : "";
                } catch (e) {}
                if (qsBack) {
                    window.location.href = withMobileToken(qsBack);
                    return false;
                }
                if (window.history.length > 1 && !(link && link.dataset && link.dataset.backMode === "fallback")) {
                    window.history.back();
                    return false;
                }
                window.location.href = withMobileToken(fallback);
                return false;
            }
            function submitJson(url, body) {
                var payload = {};
                var key;
                body = body || {};
                for (key in body) {
                    if (Object.prototype.hasOwnProperty.call(body, key)) payload[key] = body[key];
                }
                if (TOKEN && !payload.token) payload.token = TOKEN;
                var headers = { "Accept":"application/json", "Content-Type":"application/json" };
                if (TOKEN) headers.Authorization = "Bearer " + TOKEN;
                return fetch(withMobileToken(url), { method:"POST", headers: headers, body: JSON.stringify(payload), credentials:"same-origin" })
                    .then(function (res) {
                        return res.json().catch(function () {
                            return { success:false, message:"Response tidak valid" };
                        }).then(function (data) {
                            if (!res.ok && data && data.success !== true) data.success = false;
                            if (!res.ok && data && !data.message) data.message = "Request gagal (" + res.status + ").";
                            return data;
                        });
                    })
                    .catch(function () {
                        return { success:false, message:"Koneksi gagal. Coba lagi." };
                    });
            }
            function submitMobileForm(url, body) {
                if (TOKEN && body && typeof body.append === "function" && !body.has("token")) body.append("token", TOKEN);
                var headers = { "Accept":"application/json" };
                if (TOKEN) headers.Authorization = "Bearer " + TOKEN;
                return fetch(withMobileToken(url), { method:"POST", headers: headers, body: body, credentials:"same-origin" })
                    .then(function (res) {
                        return res.json().catch(function () {
                            return { success:false, message:"Response tidak valid" };
                        }).then(function (data) {
                            if (!res.ok && data && data.success !== true) data.success = false;
                            if (!res.ok && data && !data.message) data.message = "Request gagal (" + res.status + ").";
                            return data;
                        });
                    })
                    .catch(function () {
                        return { success:false, message:"Koneksi gagal. Coba lagi." };
                    });
            }
            function mobileToast(message, type) {
                var toast = document.getElementById("mobileToast");
                if (!toast) {
                    toast = document.createElement("div");
                    toast.id = "mobileToast";
                    toast.style.cssText = "position:fixed;left:18px;right:18px;bottom:92px;z-index:9999;padding:13px 16px;border-radius:16px;background:#111827;color:#fff;font-weight:800;text-align:center;box-shadow:0 18px 40px rgba(15,23,42,.24);";
                    document.body.appendChild(toast);
                }
                toast.textContent = message || "Proses selesai.";
                toast.style.background = type === "error" ? "#dc2626" : "#111827";
                toast.style.display = "block";
                clearTimeout(window.__mobileToastTimer);
                window.__mobileToastTimer = setTimeout(function () { toast.style.display = "none"; }, 2200);
            }
            function setActionLoading(button, loading, text) {
                if (!button) return;
                if (!button.dataset.label) button.dataset.label = button.textContent;
                button.disabled = !!loading;
                button.textContent = loading ? text : button.dataset.label;
            }
            function approvePbDetail(id, button) {
                var noteEl = document.getElementById("pbApprovalNote");
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/pb/" + id + "/approve", { notes: noteEl ? noteEl.value.replace(/^\s+|\s+$/g, "") : "" }).then(function (result) {
                    mobileToast(result.message || (result.success ? "PB berhasil diapprove." : "Gagal approve PB."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function rejectPbDetail(id, button) {
                var panel = document.getElementById("pbRejectPanel");
                if (panel && panel.hidden) {
                    panel.hidden = false;
                    var input = document.getElementById("pbRejectReason");
                    setTimeout(function () { if (input) input.focus(); }, 50);
                    mobileToast("Isi catatan reject lalu tekan Reject PB lagi.", "error");
                    return;
                }
                var reasonEl = document.getElementById("pbRejectReason");
                var reason = reasonEl ? reasonEl.value.replace(/^\s+|\s+$/g, "") : "";
                if (!reason) { mobileToast("Catatan reject PB wajib diisi.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/pb/" + id + "/reject", { alasan: reason }).then(function (result) {
                    mobileToast(result.message || (result.success ? "PB berhasil direject." : "Gagal reject PB."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function approveWoDetail(id, button) {
                var pelaksanaEl = document.getElementById("woPelaksana");
                var notesEl = document.getElementById("woDelegationNotes");
                var pelaksana = pelaksanaEl ? pelaksanaEl.value : "";
                var notes = notesEl ? notesEl.value.replace(/^\s+|\s+$/g, "") : "";
                if (!pelaksana) { mobileToast("Pilih pelaksana terlebih dahulu.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/wo/" + id + "/approve", { pelaksana: pelaksana, delegation_notes: notes }).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil diapprove dan di-assign." : "Gagal approve WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function rejectWoDetail(id, button) {
                var panel = document.getElementById("woRejectPanel");
                if (panel && panel.hidden) {
                    panel.hidden = false;
                    var input = document.getElementById("woRejectReason");
                    setTimeout(function () { if (input) input.focus(); }, 50);
                    mobileToast("Isi catatan reject lalu tekan Reject WO lagi.", "error");
                    return;
                }
                var reasonEl = document.getElementById("woRejectReason");
                var reason = reasonEl ? reasonEl.value.replace(/^\s+|\s+$/g, "") : "";
                if (!reason) { mobileToast("Catatan reject WO wajib diisi.", "error"); return; }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/wo/" + id + "/reject", { rejection_notes: reason }).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil direject." : "Gagal reject WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function startSectionWoProgress(id, button) {
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/section/work-orders/" + id + "/progress", {}).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO masuk In Progress." : "Gagal mulai progress."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function holdSectionWo(id, button) {
                setActionLoading(button, true, "Hold...");
                submitJson("/api/mobile/section/work-orders/" + id + "/hold", {}).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO berhasil di-Hold." : "Gagal Hold WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function resumeSectionWo(id, button) {
                setActionLoading(button, true, "Resume...");
                submitJson("/api/mobile/section/work-orders/" + id + "/resume", {}).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO kembali In Progress." : "Gagal Resume WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 650);
                    else setActionLoading(button, false);
                });
            }
            function uploadSectionWoPhotos(id, button) {
                var input = document.getElementById("sectionWoPhotos");
                var notes = document.getElementById("sectionWoNotes");
                var files = input && input.files ? input.files : [];
                if (!files.length) {
                    mobileToast("Pilih minimal 1 foto hasil pekerjaan.", "error");
                    return;
                }
                var form = new FormData();
                for (var i = 0; i < files.length; i++) {
                    form.append("photos[]", files[i]);
                }
                form.append("notes", notes ? notes.value.replace(/^\s+|\s+$/g, "") : "");
                setActionLoading(button, true, "Upload...");
                submitMobileForm("/api/mobile/section/work-orders/" + id + "/photos", form).then(function (result) {
                    mobileToast(result.message || (result.success ? "Foto berhasil diupload." : "Gagal upload foto."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.reload(); }, 750);
                    else setActionLoading(button, false);
                });
            }
            function doneSectionWo(id, button) {
                var notes = document.getElementById("sectionWoNotes");
                var value = notes ? notes.value.replace(/^\s+|\s+$/g, "") : "";
                if (!value) {
                    mobileToast("Catatan hasil pekerjaan wajib diisi sebelum Done.", "error");
                    if (notes) notes.focus();
                    return;
                }
                setActionLoading(button, true, "Memproses...");
                submitJson("/api/mobile/section/work-orders/" + id + "/done", { notes: value }).then(function (result) {
                    mobileToast(result.message || (result.success ? "WO selesai." : "Gagal menyelesaikan WO."), result.success ? "success" : "error");
                    if (result.success) setTimeout(function () { window.location.href = withMobileToken("/api/mobile/web/section/done-today"); }, 750);
                    else setActionLoading(button, false);
                });
            }
            function filterCards(value) {
                var needle = (value || "").toLowerCase();
                var fromInput = document.querySelector("[data-date-filter=from]");
                var toInput = document.querySelector("[data-date-filter=to]");
                var from = fromInput ? fromInput.value : "";
                var to = toInput ? toInput.value : "";
                Array.prototype.forEach.call(document.querySelectorAll("[data-text]"), function (card) {
                    var date = card.dataset.date || "";
                    var matchText = card.dataset.text.indexOf(needle) >= 0;
                    var matchFrom = !from || !date || date >= from;
                    var matchTo = !to || !date || date <= to;
                    card.style.display = matchText && matchFrom && matchTo ? "" : "none";
                });
            }
JS;
    }

    private function mobileWebChrome(string $title): string
    {
        if (in_array($title, ['Dashboard', 'Engineering Mobile'], true)) {
            return '';
        }

        $displayTitle = match ($title) {
            'PB Engineering' => 'Permintaan Barang',
            'WO Engineering' => 'Work Order',
            default => $title,
        };

        $subtitle = match ($title) {
            'History' => 'Riwayat approval dan pekerjaan.',
            'Stock Sparepart' => 'Cek stok dari data ERP. Hasil tampil setelah mengetik minimal 2 huruf.',
            'PB Engineering' => 'Buat PB dan pantau progress approval.',
            'WO Engineering' => 'Buat WO dan pantau progress pekerjaan.',
            'Buat PB' => 'Lengkapi permintaan barang, lalu kirim ke approval.',
            'Buat WO' => 'Lengkapi work order, lalu submit ke Approval L1.',
            'Detail PB' => 'Detail permintaan barang.',
            'Detail WO' => 'Detail work order.',
            default => '',
        };

        $backUrl = match ($title) {
            'PB Engineering', 'WO Engineering' => url('/api/mobile/web/engineering'),
            'Buat PB' => url('/api/mobile/web/engineering/pb'),
            'Buat WO' => url('/api/mobile/web/engineering/wo'),
            'Detail PB', 'Detail WO' => url('/api/mobile/web/history'),
            'History' => url('/api/mobile/web/dashboard'),
            'Stock Sparepart' => url('/api/mobile/web/history'),
            default => url('/api/mobile/web/dashboard'),
        };
        $backMode = $title === 'History' ? 'home' : 'history';

        return '<section class="mobile-page-head">
            <a class="back-link" href="' . e($backUrl) . '" data-back-mode="' . e($backMode) . '" onclick="return mobileBack(event, this)"><span aria-hidden="true">&lsaquo;</span>Kembali</a>
            <div>
                <h1>' . e($displayTitle) . '</h1>
                ' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '
            </div>
        </section>';
    }

    private function mobileStatusClass(?string $status): string
    {
        $status = strtolower((string) $status);
        if (in_array($status, ['closed', 'completed', 'done', 'approved', 'verified'], true)) {
            return in_array($status, ['approved', 'verified'], true) ? 'approved' : 'done';
        }
        if (in_array($status, ['rejected', 'reject'], true)) {
            return 'rejected';
        }
        if (in_array($status, ['submitted', 'pending'], true)) {
            return $status;
        }
        if ($status === 'hold') {
            return 'pending';
        }
        if (in_array($status, ['progress', 'in_progress'], true)) {
            return 'progress';
        }

        return 'open';
    }

    private function mobileStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'closed', 'completed', 'fulfilled' => 'Done',
            'progress', 'in_progress' => 'In Progress',
            'hold' => 'Hold',
            'approved' => 'Approved',
            'verified' => 'Terverifikasi',
            'rejected', 'reject' => 'Rejected',
            'submitted' => 'Submitted',
            'pending' => 'Pending',
            'open' => 'Open',
            default => ucfirst((string) ($status ?: '-')),
        };
    }

    private function mobileStatusLabelForType(?string $status, string $type): string
    {
        $normalized = strtolower((string) $status);

        if (strtoupper($type) === 'PB' && in_array($normalized, ['progress', 'in_progress'], true)) {
            return 'Fulfillment';
        }

        return $this->mobileStatusLabel($status);
    }

    private function mobileDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->timezone('Asia/Jakarta')->format('d M Y, H.i');
    }

    private function mobileDate($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->timezone('Asia/Jakarta')->format('d M Y');
    }

    private function mobileRupiah($value): string
    {
        return 'Rp ' . number_format((float) $value, 0, ',', '.');
    }

    private function mobileTokenUrl(string $url, array $params = []): string
    {
        $token = request()->bearerToken()
            ?: (string) request()->query('token', '')
            ?: (string) request()->input('token', '');

        if ($token !== '') {
            $params['token'] = $token;
        }

        if (!$params) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }

    private function mobileQty($value): string
    {
        $formatted = number_format((float) $value, 2, ',', '.');

        return preg_replace('/,00$/', '', $formatted) ?: '0';
    }

    private function sparepartMaterialPrefixes(): array
    {
        return ['YSPR'];
    }

    private function normalizeMaterialType(?string $value): string
    {
        return in_array($value, ['sparepart', 'non_sparepart'], true) ? $value : 'sparepart';
    }

    private function applyMaterialScope($query, string $materialType, string $mtartColumn = 'mtart', string $codeColumn = 'code'): void
    {
        $prefixes = $this->sparepartMaterialPrefixes();

        if ($this->normalizeMaterialType($materialType) === 'non_sparepart') {
            $query->where(function ($scope) use ($prefixes, $mtartColumn) {
                $scope->whereNull($mtartColumn)
                    ->orWhereNotIn(DB::raw('UPPER(TRIM(' . $mtartColumn . '))'), $prefixes);
            });

            foreach ($prefixes as $prefix) {
                $query->where(function ($scope) use ($codeColumn, $prefix) {
                    $scope->whereNull($codeColumn)
                        ->orWhere(DB::raw('UPPER(TRIM(' . $codeColumn . '))'), 'NOT LIKE', $prefix . '%');
                });
            }

            return;
        }

        $query->whereIn(DB::raw('UPPER(TRIM(' . $mtartColumn . '))'), $prefixes);

        foreach ($prefixes as $prefix) {
            $query->orWhere(DB::raw('UPPER(TRIM(' . $codeColumn . '))'), 'LIKE', $prefix . '%');
        }
    }

    private function applySparepartMaterialScope($query, string $mtartColumn = 'mtart', string $codeColumn = 'code'): void
    {
        $this->applyMaterialScope($query, 'sparepart', $mtartColumn, $codeColumn);
    }

    private function materialSql(string $alias, string $materialType = 'sparepart'): string
    {
        $prefixes = $this->sparepartMaterialPrefixes();
        $quoted = implode(', ', array_map(fn ($prefix) => "'" . str_replace("'", "''", $prefix) . "'", $prefixes));
        $codePredicates = implode(' OR ', array_map(
            fn ($prefix) => "UPPER(TRIM({$alias}.code)) LIKE '" . str_replace("'", "''", $prefix) . "%'",
            $prefixes
        ));

        if ($this->normalizeMaterialType($materialType) === 'non_sparepart') {
            $notCodePredicates = implode(' AND ', array_map(
                fn ($prefix) => "({$alias}.code IS NULL OR UPPER(TRIM({$alias}.code)) NOT LIKE '" . str_replace("'", "''", $prefix) . "%')",
                $prefixes
            ));

            return "(({$alias}.mtart IS NULL OR UPPER(TRIM({$alias}.mtart)) NOT IN ({$quoted})) AND {$notCodePredicates} AND LOWER(COALESCE({$alias}.item_name, '')) NOT LIKE '%jangan dipakai%')";
        }

        return "((UPPER(TRIM({$alias}.mtart)) IN ({$quoted}) OR {$codePredicates}) AND LOWER(COALESCE({$alias}.item_name, '')) NOT LIKE '%jangan dipakai%')";
    }

    private function sparepartMaterialSql(string $alias): string
    {
        return $this->materialSql($alias, 'sparepart');
    }

    private function guardPbApproval(?object $pb, object $user)
    {
        if (!$pb) {
            return response()->json(['success' => false, 'message' => 'PB tidak ditemukan.'], 404);
        }

        if ($pb->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'PB sudah tidak pending.'], 422);
        }

        $currentLevel = (int) ($pb->approval_current_level ?? self::LEVEL_ONE);
        $requiresL2 = (int) ($pb->approval_level_required ?? self::LEVEL_ONE) >= self::LEVEL_TWO
            && (bool) ($pb->has_high_value_item ?? false);

        if ($currentLevel === self::LEVEL_TWO && !$requiresL2) {
            return response()->json(['success' => false, 'message' => 'PB ini tidak memerlukan Approval L2.'], 422);
        }

        $canApprove = ($currentLevel === self::LEVEL_ONE && $user->role === 'approval')
            || ($currentLevel === self::LEVEL_TWO && $user->role === 'approval2');

        if (!$canApprove) {
            return response()->json(['success' => false, 'message' => 'User tidak punya akses approval level ini.'], 403);
        }

        return null;
    }
}
