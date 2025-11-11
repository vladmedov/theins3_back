<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class ExchangeRate extends Model
{
    protected $fillable = ['currency', 'value', 'dynamics'];
    
    protected $casts = [
        'value' => 'decimal:2',
        'dynamics' => 'boolean',
    ];
    
    public static function getLatestRates(): array
    {
        $redis = Redis::connection()->client();
        $cachedRates = $redis->get('exchange_rates');
        
        if ($cachedRates) {
            return json_decode($cachedRates, true);
        }
        
        $rates = self::all()->map(function ($rate) {
            return [
                'currency' => $rate->currency,
                'value' => (float) $rate->value,
                'dynamics' => $rate->dynamics,
            ];
        })->toArray();
        
        if (!empty($rates)) {
            $redis->setex('exchange_rates', 14400, json_encode($rates));
        }
        
        return $rates;
    }
   
    public static function updateRate(string $currency, float $value, bool $dynamics): void
    {
        self::updateOrCreate(
            ['currency' => $currency],
            ['value' => $value, 'dynamics' => $dynamics]
        );
        
        $redis = Redis::connection()->client();
        $redis->del('exchange_rates');
    }
}

