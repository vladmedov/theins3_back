<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFrontendRevalidation extends Command
{
    protected $signature = 'frontend:revalidate-test
                            {tags?* : Tags to revalidate}
                            {--timeout= : Override request timeout in seconds}';

    protected $description = 'Send a test revalidation request to the frontend';

    public function handle(): int
    {
        $tags = $this->argument('tags');

        if (empty($tags)) {
            $tags = ['home:ru'];
        }

        $url = config('services.frontend_revalidation.url');
        $secret = config('services.frontend_revalidation.secret');
        $timeout = $this->option('timeout') !== null
            ? (int) $this->option('timeout')
            : (int) config('services.frontend_revalidation.timeout', 10);

        $this->line('URL: ' . ($url ?: '[missing]'));
        $this->line('Secret: ' . ($secret ? '[set]' : '[missing]'));
        $this->line('Timeout: ' . $timeout . 's');
        $this->line('Tags: ' . json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (empty($url) || empty($secret)) {
            $this->error('Frontend revalidation is not configured.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout($timeout)
                ->asJson()
                ->withHeaders([
                    'x-revalidate-secret' => $secret,
                ])
                ->post($url, [
                    'tags' => $tags,
                ]);
        } catch (\Throwable $exception) {
            $this->error('Request failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Status: ' . $response->status());
        $this->line('Successful: ' . ($response->successful() ? 'yes' : 'no'));
        $this->line('Body:');
        $this->line($response->body());

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }
}
