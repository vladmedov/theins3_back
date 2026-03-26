<?php

namespace App\Services\Nova;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Per-post edit lock for Nova (Redis, TTL 1h, user_id scoped).
 */
class PostEditLockService
{
    public const TTL_SECONDS = 3600;

    /** Колонка индекса Nova: «онлайн» по last_heartbeat_at. */
    private const INDEX_PRESENCE_ONLINE_WITHIN_SECONDS = 300;

    /**
     * @return non-empty-string
     */
    public static function makePostKey(string $resourceUriKey, string $postId): string
    {
        return $resourceUriKey.':'.$postId;
    }

    /**
     * @return non-empty-string
     */
    protected function redisKey(string $postKey): string
    {
        return 'post-edit-lock:'.$postKey;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLock(string $postKey): ?array
    {
        $raw = Redis::get($this->redisKey($postKey));

        return $this->decodeLock($raw);
    }

    /**
     * Claim lock if missing; if same user already holds it, refresh TTL and heartbeat.
     *
     * @return array<string, mixed>
     */
    public function claimIfMissing(string $postKey, Authenticatable $user): array
    {
        $key = $this->redisKey($postKey);
        $payload = $this->newLockPayloadForUser($user);

        $set = Redis::set($key, json_encode($payload, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS, 'NX');

        if ($set) {
            return $this->normalizeLockForClient($payload);
        }

        $existing = $this->getLock($postKey);
        if ($existing === null) {
            // Race: key disappeared between SET NX and GET — retry claim once
            return $this->claimIfMissing($postKey, $user);
        }

        if ((int) ($existing['editor_user_id'] ?? 0) === (int) $user->getAuthIdentifier()) {
            $existing['last_heartbeat_at'] = now()->toIso8601String();
            Redis::set($key, json_encode($existing, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS);

            return $this->normalizeLockForClient($existing);
        }

        return $this->normalizeLockForClient($existing);
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(string $postKey, Authenticatable $user, ?int $clientVersion): array
    {
        $lock = $this->getLock($postKey);
        if ($lock === null) {
            // Не claim через heartbeat: иначе можно получить лок без перезагрузки и править устаревшую форму.
            return $this->buildHeartbeatResponsePublicationFreed($clientVersion);
        }

        $uid = (int) $user->getAuthIdentifier();
        $editorId = (int) ($lock['editor_user_id'] ?? 0);

        if ($editorId === $uid) {
            $lock['last_heartbeat_at'] = now()->toIso8601String();
            Redis::set($this->redisKey($postKey), json_encode($lock, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS);

            return $this->buildHeartbeatResponseWithKey($postKey, $lock, $user, $clientVersion, true);
        }

        return $this->buildHeartbeatResponseWithKey($postKey, $lock, $user, $clientVersion, false);
    }

    /**
     * Ключ лока в Redis отсутствует: клиент не должен занимать лок без полной перезагрузки (claim только в PHP при открытии формы).
     *
     * @return array<string, mixed>
     */
    protected function buildHeartbeatResponsePublicationFreed(?int $clientVersion): array
    {
        $v = max(1, (int) ($clientVersion ?? 1));

        return [
            'can_edit' => false,
            'publication_freed' => true,
            'lock_version' => $v,
            'editor' => [
                'id' => 0,
                'name' => '',
                'email' => '',
            ],
            'last_edited_at' => null,
            'last_heartbeat_at' => null,
            'takeover' => null,
            'lock_ttl_seconds' => 0,
            'lock_expires_at' => null,
        ];
    }

    /**
     * Force takeover for the current user.
     *
     * @return array<string, mixed>
     */
    public function takeover(string $postKey, Authenticatable $user): array
    {
        $old = $this->getLock($postKey);
        $oldEditorId = $old !== null ? (int) ($old['editor_user_id'] ?? 0) : null;

        $version = $old !== null ? (int) ($old['lock_version'] ?? 1) + 1 : 1;

        $payload = $this->newLockPayloadForUser($user);
        $payload['lock_version'] = $version;
        $payload['acquired_at'] = now()->toIso8601String();
        $payload['last_heartbeat_at'] = now()->toIso8601String();

        if ($oldEditorId !== null && $oldEditorId !== (int) $user->getAuthIdentifier()) {
            $payload['displaced_notify_user_id'] = $oldEditorId;
        } else {
            $payload['displaced_notify_user_id'] = null;
        }

        if ($old !== null) {
            $payload['last_edited_at'] = $old['last_edited_at'] ?? null;
            $payload['last_edited_by_user_id'] = $old['last_edited_by_user_id'] ?? null;
            $payload['last_edited_by_name'] = $old['last_edited_by_name'] ?? null;
        }

        Redis::set($this->redisKey($postKey), json_encode($payload, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS);

        return $this->normalizeLockForClient($payload);
    }

    public function assertCanEditOrFail(string $postKey, Authenticatable $user): void
    {
        $lock = $this->getLock($postKey);
        if ($lock === null) {
            throw new HttpException(403, __('post_edit_lock.cannot_save_without_lock'));
        }

        if ((int) ($lock['editor_user_id'] ?? 0) !== (int) $user->getAuthIdentifier()) {
            throw new HttpException(403, __('post_edit_lock.cannot_save_not_editor'));
        }
    }

    public function recordLastEdited(string $postKey, Authenticatable $user): void
    {
        $lock = $this->getLock($postKey);
        if ($lock === null) {
            return;
        }

        if ((int) ($lock['editor_user_id'] ?? 0) !== (int) $user->getAuthIdentifier()) {
            return;
        }

        $lock['last_edited_at'] = now()->toIso8601String();
        $lock['last_edited_by_user_id'] = (int) $user->getAuthIdentifier();
        $lock['last_edited_by_name'] = $this->editorDisplayName($user);

        Redis::set($this->redisKey($postKey), json_encode($lock, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS);
    }

    /**
     * Ячейка списка публикаций в Nova: свободно / вы / кто держит лок (HTML, данные экранированы).
     */
    public function indexLockColumnHtml(string $postKey, ?int $viewerUserId): string
    {
        $lock = $this->getLock($postKey);
        if ($lock === null) {
            return '<span class="whitespace-nowrap text-slate-500">'.e(__('post_edit_lock.index_lock_free')).'</span>';
        }

        $dot = $this->indexLockPresenceDotHtml($lock);
        $lastSeenLine = $this->indexLockLastSeenHtml($lock);

        $editorId = (int) ($lock['editor_user_id'] ?? 0);
        if ($viewerUserId !== null && $editorId === $viewerUserId) {
            return '<span class="inline-flex flex-col items-start text-emerald-700">'
                .'<span class="inline-flex items-center gap-1.5 whitespace-nowrap font-medium">'.$dot.e(__('post_edit_lock.index_lock_you')).'</span>'
                .$lastSeenLine
                .'</span>';
        }

        $displayName = '';
        if ($editorId > 0) {
            $displayName = trim((string) (User::query()->whereKey($editorId)->value('name') ?? ''));
        }

        if ($displayName === '') {
            return '<span class="inline-flex items-center gap-1.5 whitespace-nowrap text-amber-900">'
                .$dot.e(__('post_edit_lock.index_lock_busy_unknown')).'</span>';
        }

        return '<span class="inline-flex flex-col items-start text-amber-900">'
            .'<span class="inline-flex items-center gap-1.5 whitespace-nowrap">'.$dot.e($displayName).'</span>'
            .$lastSeenLine
            .'</span>';
    }

    /**
     * @param  array<string, mixed>  $lock
     */
    protected function indexLockPresenceDotHtml(array $lock): string
    {
        $raw = $lock['last_heartbeat_at'] ?? null;
        $recent = false;
        if (is_string($raw) && $raw !== '') {
            try {
                $recent = Carbon::parse($raw)->isAfter(now()->subSeconds(self::INDEX_PRESENCE_ONLINE_WITHIN_SECONDS));
            } catch (\Throwable) {
                $recent = false;
            }
        }

        if ($recent) {
            $title = __('post_edit_lock.index_presence_online_title');
            $color = '#22c55e';
        } else {
            $title = __('post_edit_lock.index_presence_away_title');
            $color = '#eab308';
        }

        // Inline color: Tailwind-классы из PHP в Nova не попадают в сборку CSS.
        $style = 'display:inline-block;width:8px;height:8px;margin-right:6px;border-radius:50%;vertical-align:middle;flex-shrink:0;background-color:'.$color.';';

        return '<span style="'.e($style).'" title="'.e($title).'" aria-hidden="true"></span>';
    }

    /**
     * @param  array<string, mixed>  $lock
     */
    protected function indexLockLastSeenHtml(array $lock): string
    {
        $raw = $lock['last_heartbeat_at'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return '<span class="text-xs leading-tight text-slate-500">'.e(__('post_edit_lock.index_last_seen_unknown')).'</span>';
        }

        try {
            $minutes = (int) floor(max(0, Carbon::parse($raw)->diffInMinutes(now())));
        } catch (\Throwable) {
            return '<span class="text-xs leading-tight text-slate-500">'.e(__('post_edit_lock.index_last_seen_unknown')).'</span>';
        }

        if ($minutes <= 0) {
            $text = __('post_edit_lock.index_last_seen_just_now');
        } else {
            $text = __('post_edit_lock.index_last_seen_minutes_ago', ['minutes' => $minutes]);
        }

        return '<span class="text-xs leading-tight text-slate-500">'.$text.'</span>';
    }

    /**
     * Оставшееся время жизни ключа блокировки в Redis (секунды).
     */
    public function remainingTtl(string $postKey): int
    {
        $ttl = Redis::ttl($this->redisKey($postKey));

        if ($ttl < 0) {
            return self::TTL_SECONDS;
        }

        return $ttl;
    }

    /**
     * @param  array<string, mixed>  $lock
     * @return array<string, mixed>
     */
    protected function buildHeartbeatResponseWithKey(string $postKey, array $lock, Authenticatable $user, ?int $clientVersion, bool $canEdit): array
    {
        $key = $this->redisKey($postKey);
        $lockVersion = (int) ($lock['lock_version'] ?? 1);
        $takeover = null;

        // Notify the displaced editor once: do not require clientVersion < lockVersion — after reload
        // the victim's client_version can already equal lock_version, and they would never see the alert.
        if (! $canEdit) {
            $notifyId = isset($lock['displaced_notify_user_id']) ? (int) $lock['displaced_notify_user_id'] : null;
            if ($notifyId !== null && $notifyId === (int) $user->getAuthIdentifier()) {
                $takeover = [
                    'by' => [
                        'id' => (int) ($lock['editor_user_id'] ?? 0),
                        'name' => (string) ($lock['editor_name'] ?? ''),
                        'email' => (string) ($lock['editor_email'] ?? ''),
                    ],
                    'at' => now()->toIso8601String(),
                ];

                $lock['displaced_notify_user_id'] = null;
                Redis::set($key, json_encode($lock, JSON_THROW_ON_ERROR), 'EX', self::TTL_SECONDS);
            }
        }

        $editor = [
            'id' => (int) ($lock['editor_user_id'] ?? 0),
            'name' => (string) ($lock['editor_name'] ?? ''),
            'email' => (string) ($lock['editor_email'] ?? ''),
        ];

        $ttlSeconds = $this->remainingTtl($postKey);

        return [
            'can_edit' => $canEdit,
            'lock_version' => $lockVersion,
            'editor' => $editor,
            'last_edited_at' => $lock['last_edited_at'] ?? null,
            'last_heartbeat_at' => $lock['last_heartbeat_at'] ?? null,
            'takeover' => $takeover,
            'lock_ttl_seconds' => $ttlSeconds,
            'lock_expires_at' => now()->utc()->addSeconds($ttlSeconds)->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeLockForClient(array $payload): array
    {
        return [
            'editor_user_id' => (int) ($payload['editor_user_id'] ?? 0),
            'editor_name' => (string) ($payload['editor_name'] ?? ''),
            'editor_email' => (string) ($payload['editor_email'] ?? ''),
            'lock_version' => (int) ($payload['lock_version'] ?? 1),
            'last_edited_at' => $payload['last_edited_at'] ?? null,
            'last_heartbeat_at' => $payload['last_heartbeat_at'] ?? null,
            'last_edited_by_user_id' => $payload['last_edited_by_user_id'] ?? null,
            'last_edited_by_name' => $payload['last_edited_by_name'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function newLockPayloadForUser(Authenticatable $user): array
    {
        return [
            'editor_user_id' => (int) $user->getAuthIdentifier(),
            'editor_name' => $this->editorDisplayName($user),
            'editor_email' => (string) ($user->email ?? ''),
            'lock_version' => 1,
            'acquired_at' => now()->toIso8601String(),
            'last_heartbeat_at' => now()->toIso8601String(),
            'last_edited_at' => null,
            'last_edited_by_user_id' => null,
            'last_edited_by_name' => null,
            'displaced_notify_user_id' => null,
        ];
    }

    protected function editorDisplayName(Authenticatable $user): string
    {
        if (isset($user->name) && $user->name !== '') {
            return (string) $user->name;
        }

        return (string) ($user->email ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeLock(mixed $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }

        try {
            $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
