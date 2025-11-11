<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'update:currencies';
    protected $description = 'Обновление курсов USD и EUR с API ЦБ РФ';

    public function handle()
    {
        $this->info('Начало обновления курсов валют...');
        
        try {
            $today = Carbon::now()->format('d/m/Y');
            $responseToday = Http::timeout(10)->get("http://www.cbr.ru/scripts/XML_daily.asp?date_req={$today}");
            
            if (!$responseToday->successful()) {
                throw new \Exception('Не удалось получить данные за сегодня');
            }
            
            $yesterday = Carbon::yesterday()->format('d/m/Y');
            $responseYesterday = Http::timeout(10)->get("http://www.cbr.ru/scripts/XML_daily.asp?date_req={$yesterday}");
            
            if (!$responseYesterday->successful()) {
                throw new \Exception('Не удалось получить данные за вчера');
            }
            
            $xmlToday = simplexml_load_string($responseToday->body());
            $xmlYesterday = simplexml_load_string($responseYesterday->body());
            
            $currencies = [
                'R01235' => 'USD',
                'R01239' => 'EUR',
            ];
            
            foreach ($currencies as $cbCode => $currency) {
                $valuteToday = $xmlToday->xpath("//Valute[@ID='{$cbCode}']");
                if (empty($valuteToday)) {
                    $this->warn("Валюта {$currency} не найдена за сегодня");
                    continue;
                }
                
                $valuteYesterday = $xmlYesterday->xpath("//Valute[@ID='{$cbCode}']");
                if (empty($valuteYesterday)) {
                    $this->warn("Валюта {$currency} не найдена за вчера");
                    continue;
                }
                
                $valueToday = (float) str_replace(',', '.', (string) $valuteToday[0]->Value);
                $valueYesterday = (float) str_replace(',', '.', (string) $valuteYesterday[0]->Value);
                
                $dynamics = $valueToday > $valueYesterday;
                
                ExchangeRate::updateRate($currency, $valueToday, $dynamics);
                
                $this->info("{$currency}: {$valueToday} (" . ($dynamics ? '↑' : '↓') . ")");
            }
            
            Log::info('Currency rates updated successfully');
            $this->info('Курсы валют успешно обновлены');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Ошибка при обновлении курсов: ' . $e->getMessage());
            Log::error('UpdateCurrencyRates failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}

