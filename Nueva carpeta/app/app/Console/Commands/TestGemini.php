<?php

namespace App\Console\Commands;

use App\Services\GeminiService;
use Illuminate\Console\Command;

class TestGemini extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:gemini {prompt=Hola}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Gemini API';

    /**
     * Execute the console command.
     */
    public function handle(GeminiService $geminiService)
    {
        $prompt = $this->argument('prompt');
        $this->info("Asking Gemini: {$prompt}");

        $response = $geminiService->askGemini($prompt);

        $this->info("Response:");
        $this->line($response);
    }
}
