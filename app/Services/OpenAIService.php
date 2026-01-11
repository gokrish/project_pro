<?php

namespace App\Services;

use OpenAI;

class OpenAIService
{
    private $client;

    public function __construct()
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        if ($apiKey) {
            $this->client = OpenAI::client($apiKey);
        }
    }

    public function parseResumeText(string $text): array
    {
        if (!$this->client) {
            return ['error' => 'OpenAI API Key not configured'];
        }

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a Recruiter Assistant. Extract structured data from the resume text provided. Return ONLY JSON.'],
                    [
                        'role' => 'user',
                        'content' => "Extract the following fields from this resume text:
                    - first_name
                    - last_name
                    - email
                    - phone
                    - linkedin_url
                    - summary (short professional summary)
                    - skills (array of strings)
                    
                    Resume Text:
                    $text"
                    ]
                ],
                'temperature' => 0.2,
            ]);

            $content = $response->choices[0]->message->content;

            // Clean markdown code blocks if present
            $content = str_replace('```json', '', $content);
            $content = str_replace('```', '', $content);

            return json_decode($content, true) ?: ['error' => 'Failed to parse JSON response'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
