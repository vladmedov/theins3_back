<?php

namespace App\Console\Commands;

use App\Services\Images\ImageStorageLayout;
use App\Services\Images\ImageType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneImageVariants extends Command
{
    protected $signature = 'images:prune-variants
                            {--dry-run : List directories that would be deleted}
                            {--disk= : Only ru_public or en_public (default: both)}';

    protected $description = 'Delete legacy small/medium image variant directories from public disks';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $diskOption = $this->option('disk');
        $disks = $diskOption
            ? [(string) $diskOption]
            : ['ru_public', 'en_public'];

        $types = array_map(fn (ImageType $t) => $t->value, ImageType::cases());
        $variants = ['small', 'medium'];

        $deleted = 0;

        foreach ($disks as $diskName) {
            $disk = Storage::disk($diskName);
            foreach ($types as $type) {
                foreach ($variants as $variant) {
                    $dir = "{$type}/{$variant}";
                    if (! $disk->exists($dir)) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("[dry-run] would delete {$diskName}:{$dir}");
                    } else {
                        $disk->deleteDirectory($dir);
                        $this->line("Deleted {$diskName}:{$dir}");
                    }
                    $deleted++;
                }
            }
        }

        $this->info($dryRun
            ? "Dry-run complete. Directories matched: {$deleted}"
            : "Done. Directories deleted: {$deleted}");

        return self::SUCCESS;
    }
}
