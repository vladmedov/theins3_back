<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use MailchimpMarketing\ApiClient;

class MailchimpController extends Controller
{
    private ApiClient $mailchimp;

    public function __construct()
    {
        $this->mailchimp = new ApiClient();
        $this->mailchimp->setConfig([
            'apiKey' => config('services.mailchimp.api_key'),
            'server' => config('services.mailchimp.server_prefix'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'language_code' => 'required|in:ru,en',
            'recaptcha_token' => 'required|string',
        ]);

        $email = $validated['email'];
        $languageCode = $validated['language_code'];

        \Log::debug('Received reCAPTCHA token', [
            'token_length' => strlen($validated['recaptcha_token']),
            'token_preview' => substr($validated['recaptcha_token'], 0, 20) . '...',
        ]);

        if (!$this->verifyRecaptcha($validated['recaptcha_token'])) {
            \Log::warning('reCAPTCHA verification failed', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed',
            ], 400);
        }
        
        $listId = config("services.mailchimp.lists.{$languageCode}");

        if (!$listId) {
            \Log::error('Mailchimp list not found', ['lang' => $languageCode]);
            return response()->json(['success' => true]);
        }

        try {
            $subscriberData = [
                'email_address' => $email,
                'status' => 'subscribed',
            ];

            $this->mailchimp->lists->addListMember($listId, $subscriberData);
            \Log::info('Newsletter subscription successful', ['email' => $email, 'lang' => $languageCode]);

        } catch (\MailchimpMarketing\ApiException $e) {
            $errorBody = json_decode($e->getMessage(), true);
            \Log::warning('Newsletter subscription Mailchimp error', [
                'email' => $email,
                'error' => $errorBody['detail'] ?? $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Newsletter subscription unexpected error', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function verifyRecaptcha(string $token): bool
    {
        $secretKey = config('services.recaptcha.secret_key');

        if (!$secretKey) {
            \Log::error('reCAPTCHA secret key not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => request()->ip(),
            ]);

            $result = $response->json();

            \Log::debug('reCAPTCHA verification response', [
                'result' => $result,
                'ip' => request()->ip(),
            ]);

            if (isset($result['success']) && $result['success']) {
                if (isset($result['score'])) {
                    if ($result['score'] >= 0.5) {
                        return true;
                    } else {
                        \Log::warning('reCAPTCHA score too low', ['score' => $result['score']]);
                        return false;
                    }
                }
                
                return true;
            }

            \Log::warning('reCAPTCHA verification failed', ['result' => $result]);
            return false;

        } catch (\Exception $e) {
            \Log::error('reCAPTCHA verification exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

