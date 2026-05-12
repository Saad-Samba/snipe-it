<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssetModel;
use Illuminate\Support\Facades\Http;

class PriceModels extends Command
{
    protected $signature = 'research:prices';
    protected $description = 'Search for market prices using Gemini API';

    public function handle()
    {
        $this->info("Starting Price Research for LEAMS Models...");

        
        $models = AssetModel::whereNull('price')->get();
        
        if ($models->isEmpty()) {
            $this->warn("All models already have prices.");
            return;
        }

        $this->info("Found " . $models->count() . " models to research.");
        $apiKey = env('GEMINI_API_KEY');

        foreach ($models as $model) {
            $this->info("------------------------------------------------");
            $this->info("Researching: " . $model->name);

            $prompt = "Search for the current average market price of the hardware model: '" . $model->name . "'. 
            Return ONLY a JSON object: {\"price\": numeric, \"source\": \"URL\"}";

            $modelName = "gemini-3.1-flash-lite"; 
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $apiKey;
            
            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post($url, [
                        'contents' => [['parts' => [['text' => $prompt]]]]
                    ]);

                if ($response->successful()) {
                    $resData = $response->json();
                    $rawText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    
                    $cleanJson = str_replace(['```json', '```'], '', $rawText);
                    $data = json_decode(trim($cleanJson), true);

                    if ($data && isset($data['price'])) {
                        $model->price = $data['price'];
                        $model->save();
                        
                        $this->line("Price found: $" . number_format($data['price'], 2));
                        $this->line("Source: " . ($data['source'] ?? 'N/A'));
                    }
                } else {
                    $this->error("API Error: " . $response->status());
                }
            } catch (\Exception $e) {
                $this->error("Connection failed: " . $e->getMessage());
            }

            
            sleep(2); 
        }

        $this->info("------------------------------------------------");
        $this->info("Research Task Completed.");
    }
}