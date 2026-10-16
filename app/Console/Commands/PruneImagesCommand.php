<?php

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Command;

/**
 * Esborra les imatges generades de versions antigues (es conserven les de la versió confirmada actual).
 */
class PruneImagesCommand extends Command
{
    protected $signature = 'app:images:prune {--days= : Antiguitat mínima en dies (per defecte, IMAGE_KEEP_DAYS)}';

    protected $description = 'Esborra les imatges de versions antigues de la programació';

    public function handle(ImageService $images): int
    {
        $days = (int) ($this->option('days') ?? config('gestpartits.images.keep_days'));
        $deleted = $images->pruneOlderThan($days);

        $this->info("S'han esborrat {$deleted} imatges de més de {$days} dies.");

        return self::SUCCESS;
    }
}
