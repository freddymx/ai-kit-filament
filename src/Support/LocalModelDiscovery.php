<?php

declare(strict_types=1);

namespace SolutionForest\AiKit\Filament\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks a local/custom server which models it serves, so the settings form can
 * offer a picker instead of a free-text field.
 *
 * It never throws: any failure comes back as `error` and the form falls back
 * to a manual model-id input.
 */
final class LocalModelDiscovery
{
    private const TIMEOUT_SECONDS = 5;

    /**
     * @return array{models: array<string, string>, error: ?string}
     */
    public function fetch(string $url, ?string $key = null, string $driver = 'openai-compatible'): array
    {
        $url = rtrim(trim($url), '/');

        if ($url === '') {
            return $this->failure('Falta la URL base.');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->failure('La URL base no es válida.');
        }

        $isOllama = $driver === 'ollama';

        if ($isOllama) {
            $endpoint = $url . '/api/tags';
        } else {
            if (! str_ends_with($url, '/v1')) {
                $url .= '/v1';
            }

            $endpoint = $url . '/models';
        }

        try {
            $request = Http::acceptJson()->timeout(self::TIMEOUT_SECONDS);

            if (filled($key)) {
                $request = $request->withToken($key);
            }

            $response = $request->get($endpoint);

            if (! $response->successful()) {
                return $this->failure("HTTP {$response->status()}");
            }

            $items = $isOllama ? $response->json('models') : $response->json('data');
            $field = $isOllama ? 'name' : 'id';

            $models = [];

            foreach (is_array($items) ? $items : [] as $item) {
                $id = is_array($item) ? ($item[$field] ?? null) : null;

                if (is_string($id) && $id !== '') {
                    $models[$id] = $id;
                }
            }

            if ($models === []) {
                return $this->failure('El servidor no devolvió modelos.');
            }

            ksort($models);

            return ['models' => $models, 'error' => null];
        } catch (Throwable $e) {
            report($e);

            return $this->failure('No se pudo conectar con el servidor.');
        }
    }

    /**
     * @return array{models: array<string, string>, error: string}
     */
    private function failure(string $error): array
    {
        return ['models' => [], 'error' => $error];
    }
}
