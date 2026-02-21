<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\BotSetting;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = BotSetting::find('system_instruction');

        if (!$setting) {
            return;
        }

        $currentInstruction = $setting->value;

        // Define the new payment schedule XML block
        $paymentRules = <<<'EOT'

<payment_schedule>
    - **Monto Mínimo de Retiro**: $50.000 COP (o equivalente en dólares).
    - **Requisitos Legales**:
        - Rut (Colombia) actualizado.
        - **Certificación Bancaria**: Debe estar **a nombre del titular de la cuenta Fornuvi**. No se aceptan cuentas de terceros. Los datos del banco deben coincidir exactamente con los datos registrados en el sistema.
    - **Tiempos de Pago**:
        - **Solicitud**: Una vez acumulados los $50.000 COP y entregados los documentos.
        - **Desembolso**: La empresa tiene hasta **3 días hábiles** para consignar después de recibir la solicitud con documentos en regla.
    - **Cierre de Mes y Cálculo**:
        - Fornuvi hace cierre cada fin de mes.
        - **Periodo de Gestión (7 días hábiles)**: Después del cierre, hay 7 días hábiles para liquidar comisiones. ¿Por qué? Para recaudar y procesar los pagos pendientes de los Comercios Aliados.
    - **Soporte de Pagos**: Si necesitas asesoría específica sobre tus pagos, contacta a la línea oficial de tesorería vía WhatsApp: **+57 314 520 7814**.
</payment_schedule>
EOT;

        // Safe Merge Strategy: Inject inside <business_logic> before it closes
        if (Str::contains($currentInstruction, '</business_logic>')) {
            // Remove existing payment_schedule if it exists to avoid duplication (replace logic)
            if (Str::contains($currentInstruction, '<payment_schedule>')) {
                $currentInstruction = preg_replace('/<payment_schedule>.*?<\/payment_schedule>/s', '', $currentInstruction);
            }

            // Insert new block
            $newInstruction = str_replace(
                '</business_logic>',
                $paymentRules . "\n</business_logic>",
                $currentInstruction
            );

            // Update version and date (simple regex update)
            $newInstruction = preg_replace(
                '/last_update="\d{4}-\d{2}-\d{2}"/',
                'last_update="' . date('Y-m-d') . '"',
                $newInstruction
            );

            $setting->update(['value' => $newInstruction]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting = BotSetting::find('system_instruction');

        if (!$setting) {
            return;
        }

        $currentInstruction = $setting->value;

        // Remove the payment_schedule block
        if (Str::contains($currentInstruction, '<payment_schedule>')) {
            $newInstruction = preg_replace('/<payment_schedule>.*?<\/payment_schedule>/s', '', $currentInstruction);
            $setting->update(['value' => $newInstruction]);
        }
    }
};
