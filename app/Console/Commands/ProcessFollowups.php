<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessFollowups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'followups:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa los seguimientos programados y envía los mensajes correspondientes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $this->info("Iniciando procesamiento de seguimientos: {$now}");

        // Buscar seguimientos programados que ya deban ejecutarse
        $followups = \App\Models\ContactFollowup::where('status', 'scheduled')
            ->where('scheduled_at', '<=', $now)
            ->with(['campaign.steps', 'chat'])
            ->get();

        $count = $followups->count();
        $this->info("Se encontraron {$count} seguimientos para procesar.");

        foreach ($followups as $followup) {
            if (!$followup->campaign || !$followup->chat) {
                $this->error("Seguimiento ID {$followup->id} tiene datos incompletos.");
                $followup->update(['status' => 'canceled']);
                continue;
            }

            // Marcar como procesando para evitar duplicados si corre de nuevo
            $followup->update(['status' => 'pending']); // O 'processing' si tuviéramos ese estado enum

            $chat = $followup->chat;
            $steps = $followup->campaign->steps()->orderBy('delay', 'asc')->get();

            if ($steps->isEmpty()) {
                $this->warn("Campaña {$followup->campaign->name} sin pasos.");
                $followup->update(['status' => 'completed']);
                continue;
            }

            $currentDelay = 0;

            foreach ($steps as $step) {
                // Acumulamos el delay (o usamos el delay del paso como offset desde "ahora")
                // Asumiremos que 'delay' en el paso es relativo al paso anterior o al inicio.
                // En FollowupCampaigns.php dice "$step->delay", asumamos que es relativo al inicio o secuencial.
                // Para simplificar, haremos que cada Job se despache con un delay acumulado.

                // Si el delay es "esperar X segundos desde el mensaje anterior", sumamos.
                // Si es "X segundos desde el inicio", usamos el valor directo (si están ordenados por tiempo).
                // Vamos a sumar para asegurar secuencia si los delays son "entre mensajes".
                $currentDelay += $step->delay;

                // Despachar Job para enviar este mensaje específico
                // Usamos ProcessWhatsappMessage pero necesitamos adaptarlo o crear uno nuevo.
                // ProcessWhatsappMessage recibe ($chat, $text, $messageId).
                // Aquí NO tenemos un messageId entrante, es saliente "proactivo".

                // Opción 1: Crear un mensaje en DB primero (status=pending) y luego el Job lo envía.
                // Opción 2: Usar el Job existente modificándolo para que soporte envío sin messageId previo (outbound).

                // Vamos a crear el Mensaje como "asistente" en la DB ahora, y el Job se encargará de enviarlo a la API.

                $messageContent = $step->message_content;
                $messageType = $step->message_type; // text, image, video

                // Crear mensaje en DB
                $newMessage = \App\Models\Message::create([
                    'chat_id' => $chat->id,
                    'role' => 'assistant',
                    'content' => $messageContent,
                    'media_type' => $messageType === 'text' ? null : $messageType,
                    // 'media_url' => Si fuera imagen/video necesitaríamos la URL.
                    // El campo 'content' podría tener la URL si es media.
                    // Asumiremos que si es media, el content tiene la URL o el ID.
                    'status' => 'pending_send' // Estado hipotético o simplemente creado
                ]);

                // Despachar Job Directo (Sin IA)
                \App\Jobs\SendCampaignMessage::dispatch($newMessage->id)
                    ->delay(now()->addSeconds($currentDelay));
            }

            $followup->update(['status' => 'completed']);
            $this->info("Seguimiento ID {$followup->id} procesado y mensajes programados.");
        }

        $this->info("Procesamiento finalizado.");
    }
}
