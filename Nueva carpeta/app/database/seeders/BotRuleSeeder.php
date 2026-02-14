<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotRule;
use App\Models\BotRuleMessage;

class BotRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Regla de Primer Contacto
        $rule = BotRule::create([
            'trigger_stage' => 0,
            'next_stage' => 1,
            'keywords' => 'quiero información, de que trata, me interesa, de qué trata',
            'is_active' => true,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => "{saludo}. Soy el Asistente Virtual Oficial de Fornuvi S.A.S. He notado que tienes interés en la oportunidad de negocio.",
            'delay' => 1,
            'sort_order' => 0,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => "Para que puedas entender cómo funciona de forma sencilla, tenemos un video que explica la oportunidad. \n📹 Mira el video aquí: https://tinyurl.com/mr47ezuy",
            'delay' => 2,
            'sort_order' => 1,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => "¿Te gustaría que te ayude a registrarte o tienes alguna duda específica después de ver el video?",
            'delay' => 1,
            'sort_order' => 2,
        ]);
    }
}
