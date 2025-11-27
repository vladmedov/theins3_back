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
        $action = $this->argument('action') ?? 'all';
        
        $controller = new TestController();
        
        $this->info("Starting import: {$action}");
        
        try {
            switch ($action) {
                case 'categories':
                    $controller->importCategories(1);
                    $this->info('Categories imported successfully');
                    break;
                    
                case 'admins':
                    $controller->importAdmins();
                    $this->info('Admins imported successfully');
                    break;
                    
                case 'all':
                    $this->call('import:legacy', ['action' => 'categories']);
                    $this->call('import:legacy', ['action' => 'admins']);
                    break;
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info('Available actions: categories, admins, all');
                    return 1;
            }
            
            $this->info('Import completed!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Import failed: ' . $e->getMessage());
            return 1;
        }
    }
}

