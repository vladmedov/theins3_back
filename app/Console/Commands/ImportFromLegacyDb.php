<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\TestController;

class ImportFromLegacyDb extends Command
{
    protected $signature = 'import:legacy {action?}';
    protected $description = 'Import data from legacy database';

    public function handle()
    {
        $action = $this->argument('action') ?? 'test';
        
        $controller = new TestController();
        
        $this->info("Starting import/test: {$action}");
        
        try {
            switch ($action) {
                case 'test':
                    $this->info('Running full test import...');
                    $controller->test();
                    $this->info('Test import completed successfully');
                    break;
                    
                case 'post':
                    $postId = $this->ask('Enter post ID (default: 283015)', 283015);
                    $controller->checkPost($postId);
                    $this->info("Post {$postId} checked successfully");
                    break;
                    
                case 'reimport':
                    $postId = $this->ask('Enter post ID (default: 283015)', 283015);
                    $controller->reimportPost($postId);
                    $this->info("Post {$postId} reimported successfully");
                    break;
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info('Available actions: test, post, reimport');
                    return 1;
            }
            
            $this->info('Command completed!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}

