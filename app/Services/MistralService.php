<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MistralService
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';
    private const MODEL_CHAT = 'mistral-small-latest';
    private const MODEL_VISION = 'pixtral-12b-2409';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.mistral.key');
    }

    /**
     * Stream chat completion via SSE
     *
     * @param array $messages Chat messages array
     * @param callable $onChunk Callback for each chunk: function(string $text): void
     * @param float $temperature Sampling temperature (default: 0.7)
     * @return string Complete response content
     */
    public function streamChat(array $messages, callable $onChunk, float $temperature = 0.7): string
    {
        $payload = json_encode([
            'model' => self::MODEL_CHAT,
            'messages' => $messages,
            'temperature' => $temperature,
            'stream' => true,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload),
                ]),
                'content' => $payload,
                'timeout' => 120,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => config('app.env') === 'production',
                'verify_peer_name' => config('app.env') === 'production',
            ],
        ]);

        $fullContent = '';

        try {
            $fp = fopen(self::API_URL, 'r', false, $ctx);

            if (!$fp) {
                throw new \Exception('Failed to open stream to Mistral API');
            }

            while (!feof($fp)) {
                $line = fgets($fp, 4096);
                if ($line === false) break;

                $line = trim($line);
                if (!str_starts_with($line, 'data: ')) continue;

                $data = substr($line, 6);
                if ($data === '[DONE]') break;

                $chunk = json_decode($data, true);
                $text = $chunk['choices'][0]['delta']['content'] ?? '';

                if ($text !== '') {
                    $fullContent .= $text;
                    $onChunk($text);
                }
            }

            fclose($fp);

        } catch (\Exception $e) {
            Log::error('Mistral stream error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        return $fullContent;
    }

    /**
     * Standard chat completion (non-streaming)
     *
     * @param array $messages Chat messages array
     * @param int $maxTokens Maximum tokens to generate
     * @param float $temperature Sampling temperature
     * @return string Response content
     */
    public function chat(array $messages, int $maxTokens = 800, float $temperature = 0.7): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL_CHAT,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);

            if (!$response->successful()) {
                Log::error('Mistral API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return '';
            }

            return trim($response->json('choices.0.message.content', ''));

        } catch (\Exception $e) {
            Log::error('Mistral chat error', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Generate a short title for content
     *
     * @param string $content Content to generate title from
     * @return string Generated title
     */
    public function generateTitle(string $content): string
    {
        $prompt = "Giv denne samtale et kort emnenavn på dansk (maks 5 ord, ingen anførselstegn). Brugerens besked: \"{$content}\"";

        $title = $this->chat(
            [['role' => 'user', 'content' => $prompt]],
            maxTokens: 30,
            temperature: 0.3
        );

        return mb_substr(trim($title, "\"' \n"), 0, 100);
    }

    /**
     * Extract text from image using vision model
     *
     * @param string $base64Image Base64-encoded image
     * @param string $mimeType Image MIME type
     * @return string Extracted text
     */
    public function extractTextFromImage(string $base64Image, string $mimeType): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(90)->post(self::API_URL, [
                'model' => self::MODEL_VISION,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => 'Udtræk al tekst fra dette billede. Skriv kun teksten, intet andet.',
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => "data:{$mimeType};base64,{$base64Image}",
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 2000,
            ]);

            if (!$response->successful()) {
                Log::error('Mistral vision API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return '';
            }

            return trim($response->json('choices.0.message.content', ''));

        } catch (\Exception $e) {
            Log::error('Mistral vision error', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Create embeddings for text
     *
     * @param string|array $texts Text(s) to embed
     * @return array Embedding vectors
     */
    public function createEmbeddings(string|array $texts): array
    {
        $inputs = is_array($texts) ? $texts : [$texts];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.mistral.ai/v1/embeddings', [
                'model' => 'mistral-embed',
                'input' => $inputs,
            ]);

            if (!$response->successful()) {
                Log::error('Mistral embeddings API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $embeddings = [];
            foreach ($response->json('data', []) as $item) {
                $embeddings[] = $item['embedding'];
            }

            return is_array($texts) ? $embeddings : ($embeddings[0] ?? []);

        } catch (\Exception $e) {
            Log::error('Mistral embeddings error', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Analyze text with specific instructions
     *
     * @param string $text Text to analyze
     * @param string $instructions Analysis instructions
     * @param float $temperature Sampling temperature
     * @return string Analysis result
     */
    public function analyze(string $text, string $instructions, float $temperature = 0.1): string
    {
        $prompt = "{$instructions}\n\nTekst:\n{$text}";

        return $this->chat(
            [['role' => 'user', 'content' => $prompt]],
            maxTokens: 1000,
            temperature: $temperature
        );
    }
}
