<?php

namespace App\Services;

class LemmatizerService
{
    /**
     * Lemmatize text for search index; keeps safe fallback when pymorphy is unavailable.
     */
    public function lemmatizeText(?string $text, string $languageCode): string
    {
        $result = $this->lemmatizeManyTexts(
            ['single' => (string) $text],
            $languageCode
        );

        return $result['single'] ?? '';
    }

    /**
     * Lemmatize multiple texts in one pass.
     *
     * @param  array<string, string>  $texts
     * @return array<string, string>
     */
    public function lemmatizeManyTexts(array $texts, string $languageCode): array
    {
        $tokenizedByKey = [];
        $allTokens = [];

        foreach ($texts as $key => $text) {
            $normalized = mb_strtolower(trim((string) $text));
            $tokens = $normalized === '' ? [] : $this->tokenize($normalized);
            $tokenizedByKey[$key] = $tokens;
            if ($tokens !== []) {
                $allTokens = array_merge($allTokens, $tokens);
            }
        }

        if ($allTokens === []) {
            return array_fill_keys(array_keys($texts), '');
        }

        if (mb_strtolower($languageCode) !== 'ru') {
            return $this->implodeTokenizedTexts($tokenizedByKey);
        }

        // Deduplicate to reduce pymorphy calls; mapping is token-level anyway.
        $uniqueTokens = array_values(array_unique($allTokens));
        $lemmas = $this->lemmatizeWithPymorphy3($uniqueTokens);
        if ($lemmas === [] || count($lemmas) !== count($uniqueTokens)) {
            // Fallback keeps indexing stable even if python/pymorphy is unavailable.
            return $this->implodeTokenizedTexts($tokenizedByKey);
        }

        $lemmaByToken = [];
        foreach ($uniqueTokens as $index => $token) {
            $lemmaByToken[$token] = $lemmas[$index] ?? $token;
        }

        $result = [];
        foreach ($tokenizedByKey as $key => $tokens) {
            if ($tokens === []) {
                $result[$key] = '';
                continue;
            }
            $result[$key] = implode(
                ' ',
                array_map(
                    static fn (string $token): string => $lemmaByToken[$token] ?? $token,
                    $tokens
                )
            );
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $text, $matches);

        return array_values(array_filter($matches[0] ?? [], static fn ($token) => $token !== ''));
    }

    /**
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function lemmatizeWithPymorphy3(array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        $pythonBinary = (string) env('PYMORPHY2_PYTHON_BIN', 'python3');
        $tokensJson = json_encode($tokens, JSON_UNESCAPED_UNICODE);
        if ($tokensJson === false) {
            return [];
        }

        $script = <<<'PY'
import json
import sys
import pymorphy3

tokens = json.loads(sys.argv[1])
morph = pymorphy3.MorphAnalyzer()
lemmas = []
for token in tokens:
    parsed = morph.parse(token)
    lemmas.append(parsed[0].normal_form if parsed else token)

print(" ".join(lemmas))
PY;

        $command = [$pythonBinary, '-c', $script, $tokensJson];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptorSpec, $pipes);
        if (! is_resource($process)) {
            return [];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $stdout === false || trim((string) $stdout) === '') {
            if ($stderr !== false && trim($stderr) !== '') {
                \Log::debug('pymorphy3 lemmatization fallback', ['stderr' => trim($stderr)]);
            }

            return [];
        }

        return $this->tokenize((string) $stdout);
    }

    /**
     * @param  array<string, list<string>>  $tokenizedByKey
     * @return array<string, string>
     */
    private function implodeTokenizedTexts(array $tokenizedByKey): array
    {
        $result = [];
        foreach ($tokenizedByKey as $key => $tokens) {
            $result[$key] = $tokens === [] ? '' : implode(' ', $tokens);
        }

        return $result;
    }
}
