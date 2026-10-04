<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FirebasePushService
{
    private string $credentialPath;

    public function __construct(?string $credentialPath = null)
    {
        $this->credentialPath = $credentialPath ?: storage_path('app/firebase/e-request-approval-service-account.json');
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): int
    {
        if (!Schema::hasTable('mobile_api_tokens') || !Schema::hasColumn('mobile_api_tokens', 'fcm_token')) {
            return 0;
        }

        $tokens = DB::table('mobile_api_tokens as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('u.role', $role)
            ->whereNotNull('t.fcm_token')
            ->where('t.fcm_token', '<>', '')
            ->where(function ($query) {
                $query->whereNull('t.expires_at')
                    ->orWhere('t.expires_at', '>', now());
            })
            ->pluck('t.fcm_token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToUserName(string $name, string $title, string $body, array $data = []): int
    {
        if (!Schema::hasTable('mobile_api_tokens') || !Schema::hasColumn('mobile_api_tokens', 'fcm_token')) {
            return 0;
        }

        $tokens = DB::table('mobile_api_tokens as t')
            ->join('users as u', 't.user_id', '=', 'u.id')
            ->where('u.name', $name)
            ->whereNotNull('t.fcm_token')
            ->where('t.fcm_token', '<>', '')
            ->where(function ($query) {
                $query->whereNull('t.expires_at')
                    ->orWhere('t.expires_at', '>', now());
            })
            ->pluck('t.fcm_token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToUserId(int $userId, string $title, string $body, array $data = []): int
    {
        if (!Schema::hasTable('mobile_api_tokens') || !Schema::hasColumn('mobile_api_tokens', 'fcm_token')) {
            return 0;
        }

        $tokens = DB::table('mobile_api_tokens as t')
            ->where('t.user_id', $userId)
            ->whereNotNull('t.fcm_token')
            ->where('t.fcm_token', '<>', '')
            ->where(function ($query) {
                $query->whereNull('t.expires_at')
                    ->orWhere('t.expires_at', '>', now());
            })
            ->pluck('t.fcm_token')
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $credential = $this->credential();
            if (!$credential) {
                return false;
            }

            $accessToken = $this->accessToken($credential);
            if (!$accessToken) {
                return false;
            }

            $projectId = $credential['project_id'] ?? null;
            if (!$projectId) {
                return false;
            }

            $soundType = (string) ($data['sound_type'] ?? 'approval');
            $channelId = $soundType === 'work_order'
                ? 'erequest_work_order_voice_v1'
                : 'erequest_approval_requests_voice_v2';
            $sound = $soundType === 'work_order'
                ? 'work_order_notification'
                : 'erequest_notification';

            $payload = [
                'message' => [
                    'token' => $token,
                    'data' => collect($data)
                        ->merge([
                            'title' => $title,
                            'body' => $body,
                        ])
                        ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
                        ->all(),
                    'android' => [
                        'priority' => 'HIGH',
                        'ttl' => '3600s',
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if (!$response->successful()) {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if (str_contains($response->body(), 'UNREGISTERED')) {
                    DB::table('mobile_api_tokens')
                        ->where('fcm_token', $token)
                        ->update([
                            'fcm_token' => null,
                            'updated_at' => now(),
                        ]);
                }
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('FCM send exception: ' . $e->getMessage());
            return false;
        }
    }

    private function credential(): ?array
    {
        if (!is_file($this->credentialPath)) {
            Log::warning('FCM credential file not found: ' . $this->credentialPath);
            return null;
        }

        $credential = json_decode((string) file_get_contents($this->credentialPath), true);
        return is_array($credential) ? $credential : null;
    }

    private function accessToken(array $credential): ?string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->base64Url(json_encode([
            'iss' => $credential['client_email'] ?? '',
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        $ok = openssl_sign($header . '.' . $claim, $signature, $credential['private_key'] ?? '', OPENSSL_ALGO_SHA256);
        if (!$ok) {
            return null;
        }

        $jwt = $header . '.' . $claim . '.' . $this->base64Url($signature);
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            Log::warning('FCM access token failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        return $response->json('access_token');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
