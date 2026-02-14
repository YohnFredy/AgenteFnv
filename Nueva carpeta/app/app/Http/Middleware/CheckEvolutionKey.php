<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckEvolutionKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Obtenemos la API Key que tenemos guardada en el .env
        $mySecretKey = env('EVOLUTION_API_KEY');

        // 2. Obtenemos la API Key que viene dentro del JSON del mensaje O en los headers
        $incomingKey = $request->input('apikey') ?? $request->header('apikey');

        // 3. Comparamos. Si no son iguales, o si no viene la key, bloqueamos.
        if (!$incomingKey || $incomingKey !== $mySecretKey) {
            // Logueamos el intento fallido por seguridad
            Log::warning('Intento de acceso no autorizado al Webhook', [
                'ip' => $request->ip(),
                'incoming_key' => $incomingKey,
                'header_key' => $request->header('apikey')
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Si coincide, dejamos pasar el mensaje
        return $next($request);
    }
}
