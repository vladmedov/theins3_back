<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ExchangeRate;

class UpdateOilPrice extends Command
{
    protected $signature = 'update:oil';
    protected $description = 'Обновление цены на нефть Brent с OilPriceAPI';

    public function handle()
    {
        $this->info('Начало обновления цены на нефть...');
        $apiToken = config('services.oilprice.api_token');

        if (!$apiToken) {
            $this->error('OILPRICE_API_TOKEN is not configured');
            Log::error('UpdateOilPrice failed', [
                'error' => 'OILPRICE_API_TOKEN is not configured',
            ]);
            return 1;
        }
        
        try {
            $responseToday = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Token {$apiToken}",
                    'Content-Type' => 'application/json',
                ])
                ->get('https://api.oilpriceapi.com/v1/prices/latest');
            
            if (!$responseToday->successful()) {
                throw new \Exception('Не удалось получить текущую цену нефти');
            }

            $priceToday = $responseToday->json('data.price');

            if (!$priceToday) {
                throw new \Exception('Не удалось извлечь цену из ответа API');
            }

            $previousRate = ExchangeRate::where('currency', 'OIL')->first();
            $previousPrice = $previousRate ? (float) $previousRate->value : null;
            $dynamics = $previousPrice === null ? true : $priceToday > $previousPrice;
            
            ExchangeRate::updateRate('OIL', $priceToday, $dynamics);
            
            $this->info("OIL: {$priceToday} (" . ($dynamics ? '↑' : '↓') . ")");
            
            Log::info('Oil price updated successfully', [
                'price' => $priceToday,
                'dynamics' => $dynamics
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Ошибка при обновлении цены нефти: ' . $e->getMessage());
            Log::error('UpdateOilPrice failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

