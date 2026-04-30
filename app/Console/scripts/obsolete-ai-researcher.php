<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();


use App\Models\Asset;
use App\Models\AssetModel;

echo "--- Database Connected. Starting Model-Level Audit (Safe Speed Mode) ---\n";
echo "Pacing: 1 request every 40 seconds to prevent 429 Quota Errors.\n";


$apiKey = env('GEMINI_API_KEY');

if (!$apiKey) {
    die("FATAL ERROR: GEMINI_API_KEY is not defined in your .env file.\n");
}

// We look for models that have assets but haven't been marked obsolete yet

$models = AssetModel::has('assets')
    ->where('obsolete', 0) 
    ->get();

echo "Auditing " . $models->count() . " models using batch processing.\n";

$chunks = $models->chunk(5);

foreach ($chunks as $chunk) {
    $modelNames = [];
    $modelMap = []; 
    
    foreach ($chunk as $m) {
        $name = trim($m->name);
        $modelNames[] = $name;
        $modelMap[$name] = $m; 
    }

    $modelListString = implode(", ", $modelNames);
    echo "\n[!] RESEARCHING BATCH: {$modelListString}\n";

    $query = "Analyze the manufacturer support status for the following hardware models: " . $modelListString . ". 
              For EACH model, provide the response in this EXACT format:
              
              Model: [Name]
              Status: [End of Service Life status]
              Source: [Specific source name like Apple Vintage List or Dell Support Matrix]
              Result: [RESULT: OBSOLETE or RESULT: SUPPORTED]
              Explanation: [2-sentence summary]
              ---";
                
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    $payload = [
        'contents' => [['parts' => [['text' => $query]]]],
        'tools' => [['google_search' => (object)[]]],
        'generationConfig' => ['temperature' => 0]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = json_decode($result, true);

    if ($httpCode === 200) {
        $aiText = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $individualResults = explode('---', $aiText);

        foreach ($individualResults as $section) {
            if (trim($section) == '') continue;

            foreach ($modelNames as $name) {
                if (stripos($section, "Model: " . $name) !== false || stripos($section, $name) !== false) {
                    $isObsolete = (stripos($section, 'RESULT: OBSOLETE') !== false) ? 1 : 0;
                    
                    
                    $modelToUpdate = $modelMap[$name];
                    $modelToUpdate->obsolete = $isObsolete;
                    $modelToUpdate->save();

                    echo ">> UPDATED MODEL '{$name}': " . ($isObsolete ? "OBSOLETE" : "SUPPORTED") . "\n";
                    break; 
                }
            }
        }
    } else {
        echo "API ERROR ({$httpCode}): " . ($response['error']['message'] ?? 'Unknown') . "\n";
        if ($httpCode === 429) {
            echo "Rate limit hit. Sleeping for 60s...\n";
            sleep(60);
        }
    }

    echo "Pacing... waiting 40 seconds before next batch to ensure total safety.\n";
    sleep(40);
}

echo "\nAudit complete!\n";