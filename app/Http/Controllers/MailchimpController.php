<?php

namespace App\Http\Controllers;

use App\Services\MailchimpNewsletterService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use MailchimpMarketing\ApiClient;

class MailchimpController extends Controller
{
    private ApiClient $mailchimp;

    public function __construct(
        private readonly MailchimpNewsletterService $newsletterService,
    ) {
        $this->mailchimp = new ApiClient();
        $this->mailchimp->setConfig([
            'apiKey' => config('services.mailchimp.api_key'),
            'server' => config('services.mailchimp.server_prefix'),
        ]);
    }

    public function lists(string $language_code)
    {
        return response()->json([
            'lists' => $this->newsletterService->publicListsForLocale($language_code),
        ]);
    }

    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'list_id' => ['required', 'string', Rule::in($this->newsletterService->allowedListIds())],
            'recaptcha_token' => 'required|string',
        ]);

        $email = $validated['email'];
        $listId = $validated['list_id'];

        \Log::debug('Received reCAPTCHA token', [
            'token_length' => strlen($validated['recaptcha_token']),
            'token_preview' => substr($validated['recaptcha_token'], 0, 20) . '...',
        ]);

        if (!$this->verifyRecaptcha($validated['recaptcha_token'])) {
            \Log::warning('reCAPTCHA verification failed', [
                'email' => $email,
                'list_id' => $listId,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'reCAPTCHA verification failed',
            ], 400);
        }

        $mailchimpAudienceId = $this->newsletterService->resolveMailchimpId($listId);

        if (!$mailchimpAudienceId) {
            \Log::error('Mailchimp audience id not configured', ['list_id' => $listId]);

            return response()->json([
                'success' => false,
                'message' => 'Newsletter list is not available',
            ], 422);
        }

        try {
            $subscriberData = [
                'email_address' => $email,
                'status' => 'subscribed',
            ];

            $this->mailchimp->lists->addListMember($mailchimpAudienceId, $subscriberData);
            \Log::info('Newsletter subscription successful', ['email' => $email, 'list_id' => $listId]);

        } catch (\MailchimpMarketing\ApiException $e) {
            $errorBody = json_decode($e->getMessage(), true);
            \Log::warning('Newsletter subscription Mailchimp error', [
                'email' => $email,
                'list_id' => $listId,
                'error' => $errorBody['detail'] ?? $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Newsletter subscription unexpected error', [
                'email' => $email,
                'list_id' => $listId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    private function verifyRecaptcha(string $token): bool
    {
        $siteKey = config('services.recaptcha.site_key');
        $apiKey = config('services.recaptcha.api_key');
        $projectId = config('services.recaptcha.project_id');
        $expectedAction = config('services.recaptcha.expected_action', 'newsletter_subscribe');
        $minScore = (float) config('services.recaptcha.min_score', 0.5);

        if (!$siteKey || !$apiKey || !$projectId) {
            \Log::error('reCAPTCHA Enterprise is not fully configured', [
                'has_site_key' => (bool) $siteKey,
                'has_api_key' => (bool) $apiKey,
                'has_project_id' => (bool) $projectId,
            ]);
            return false;
        }

        try {
            $response = Http::acceptJson()
                ->post(
                    "https://recaptchaenterprise.googleapis.com/v1/projects/{$projectId}/assessments?key={$apiKey}",
                    [
                        'event' => [
                            'token' => $token,
                            'siteKey' => $siteKey,
                            'expectedAction' => $expectedAction,
                        ],
                    ]
                )
                ->throw();

            $result = $response->json() ?? [];
            $tokenProperties = $result['tokenProperties'] ?? [];
            $riskAnalysis = $result['riskAnalysis'] ?? [];
            $score = $riskAnalysis['score'] ?? null;

            \Log::debug('reCAPTCHA verification response', [
                'result' => $result,
                'ip' => request()->ip(),
            ]);

            if (!($tokenProperties['valid'] ?? false)) {
                \Log::warning('reCAPTCHA token is invalid', [
                    'invalid_reason' => $tokenProperties['invalidReason'] ?? null,
                    'action' => $tokenProperties['action'] ?? null,
                ]);
                return false;
            }

            if (($tokenProperties['action'] ?? null) !== $expectedAction) {
                \Log::warning('reCAPTCHA action mismatch', [
                    'expected_action' => $expectedAction,
                    'actual_action' => $tokenProperties['action'] ?? null,
                ]);
                return false;
            }

            if (!is_numeric($score)) {
                \Log::warning('reCAPTCHA score is missing', ['result' => $result]);
                return false;
            }

            if ((float) $score < $minScore) {
                \Log::warning('reCAPTCHA score too low', [
                    'score' => $score,
                    'min_score' => $minScore,
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            \Log::error('reCAPTCHA verification exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
