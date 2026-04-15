<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeOnlineMessageImagesArray extends Command
{
    protected $signature = 'gallery:normalize-online-images-array
                            {--dry-run : Show how many rows would be updated}
                            {--online-message-id= : Process only this online message}
                            {--chunk=500 : Chunk size}';

    protected $description = 'Convert online_messages.images from single object to array of one object';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlineMessageId = $this->option('online-message-id');
        $onlineMessageId = ($onlineMessageId === null || $onlineMessageId === '') ? null : (int) $onlineMessageId;
        $chunk = max(1, (int) $this->option('chunk'));

        $query = DB::table('online_messages')
            ->select(['id', 'images'])
            ->whereNotNull('images')
            ->whereRaw("json_typeof(images) = 'object'")
            ->orderBy('id');

        if ($onlineMessageId !== null) {
            $query->where('id', $onlineMessageId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No online_messages with object-shaped images found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $updated = 0;
        $invalid = 0;

        $query->chunkById($chunk, function ($rows) use ($dryRun, &$updated, &$invalid, $bar) {
            foreach ($rows as $row) {
                try {
                    $images = json_decode($row->images, true);
                    if (!is_array($images) || !isset($images['link'])) {
                        $invalid++;

                        continue;
                    }

                    if ($dryRun) {
                        $updated++;

                        continue;
                    }

                    DB::table('online_messages')
                        ->where('id', $row->id)
                        ->update([
                            'images' => json_encode([$images], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ]);

                    $updated++;
                } finally {
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Done.');
        $this->info('Updated: ' . $updated . ($dryRun ? ' (dry-run)' : ''));
        $this->info('Skipped invalid payloads: ' . $invalid);

        return self::SUCCESS;
    }
}

