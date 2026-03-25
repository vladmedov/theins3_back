<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sets authors.user_id using only emails: legacy admins.email ↔ local users.email.
 * Local authors are found by slug (= legacy people.slug), not by numeric id.
 */
class LegacySyncAuthorUserIds extends Command
{
    protected $signature = 'legacy:sync_author_user_ids
                            {--dry-run : Print summary only, do not update the database}';

    protected $description = 'Restore Author–User links: legacy admin email ↔ local user; authors matched by slug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $legacy = DB::connection('legacy_pgsql');

        $rows = $legacy->select('
            SELECT p.slug, a.email AS admin_email
            FROM public.people p
            LEFT JOIN public.admins a ON a.id = p.admin_id
        ');

        /** @var array<string, string|null> $adminEmailBySlug legacy admin email per slug (last row wins if duplicate slugs) */
        $adminEmailBySlug = [];
        $legacyEmptySlug = 0;
        foreach ($rows as $row) {
            $slug = isset($row->slug) ? trim((string) $row->slug) : '';
            if ($slug === '') {
                $legacyEmptySlug++;
                continue;
            }
            $adminEmailBySlug[$slug] = isset($row->admin_email) && $row->admin_email !== ''
                ? (string) $row->admin_email
                : null;
        }

        $usersByEmail = [];
        $duplicateLocalEmails = [];
        foreach (User::query()->whereNotNull('email')->get(['id', 'email']) as $user) {
            $key = $this->normalizeEmail($user->email);
            if ($key === '') {
                continue;
            }
            if (isset($usersByEmail[$key]) && $usersByEmail[$key] !== $user->id) {
                $duplicateLocalEmails[$key] = true;
            }
            $usersByEmail[$key] = $user->id;
        }

        $authorsBySlug = Author::query()
            ->get(['id', 'user_id', 'slug'])
            ->groupBy('slug');

        $wouldChange = 0;
        $unchanged = 0;
        $noLocalAuthor = 0;
        $warnings = [];

        /** @var array<int, int|null> $updates author id => user id */
        $updates = [];

        foreach ($adminEmailBySlug as $slug => $legacyAdminEmail) {
            $authors = $authorsBySlug->get($slug, collect());
            if ($authors->isEmpty()) {
                $noLocalAuthor++;
                continue;
            }

            $targetUserId = null;

            if ($legacyAdminEmail === null || $legacyAdminEmail === '') {
                $targetUserId = null;
            } else {
                $emailKey = $this->normalizeEmail($legacyAdminEmail);
                if ($emailKey === '') {
                    $warnings[] = "slug={$slug} email invalid in legacy";
                    $targetUserId = null;
                } elseif (isset($duplicateLocalEmails[$emailKey])) {
                    $warnings[] = "slug={$slug} email={$legacyAdminEmail} (duplicate local users for this email)";
                    $targetUserId = null;
                } elseif (!isset($usersByEmail[$emailKey])) {
                    $warnings[] = "slug={$slug} email={$legacyAdminEmail} (no local user)";
                    $targetUserId = null;
                } else {
                    $targetUserId = $usersByEmail[$emailKey];
                }
            }

            foreach ($authors as $author) {
                $current = $author->user_id !== null ? (int) $author->user_id : null;

                if ($current === $targetUserId) {
                    $unchanged++;
                    continue;
                }

                $wouldChange++;
                $updates[$author->id] = $targetUserId;
            }
        }

        if ($dryRun) {
            $this->info('[dry-run] No writes performed.');
        } elseif ($wouldChange > 0) {
            $now = now();
            Author::withoutEvents(function () use ($updates, $now) {
                foreach ($updates as $authorId => $userId) {
                    Author::query()->whereKey($authorId)->update([
                        'user_id' => $userId,
                        'updated_at' => $now,
                    ]);
                }
            });
            $this->info("Updated {$wouldChange} author row(s).");
        } else {
            $this->info('Nothing to update.');
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Legacy people rows (total)', count($rows)],
                ['Legacy rows skipped (empty slug)', $legacyEmptySlug],
                ['Distinct legacy slugs considered', count($adminEmailBySlug)],
                ['Slugs with no local author (skipped)', $noLocalAuthor],
                ['Slugs with local author(s)', count($adminEmailBySlug) - $noLocalAuthor],
                ['Author rows already correct', $unchanged],
                [$dryRun ? 'Author rows would change' : 'Author rows changed', $wouldChange],
            ]
        );

        if ($warnings !== []) {
            $this->warn('Could not resolve local user (user_id cleared or not set for those slugs):');
            foreach (array_slice($warnings, 0, 40) as $line) {
                $this->line('  - ' . $line);
            }
            if (count($warnings) > 40) {
                $this->line('  … and ' . (count($warnings) - 40) . ' more');
            }
        }

        return self::SUCCESS;
    }

    private function normalizeEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '';
        }

        return strtolower(trim($email));
    }
}
