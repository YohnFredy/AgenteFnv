<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BotRule;
use App\Models\BotRuleMessage;

class BotRulesSeeder extends Seeder
{
    public function run(): void
    {
        // Regla Stage 0 -> Stage 1
        $rule = BotRule::create([
            'trigger_stage' => 0,
            'next_stage' => 1,
            'keywords' => 'más información,información,explíqueme el negocio,ya vi el video,info',
            'is_active' => true,
        ]);

        // Mensajes
        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => '{saludo}, ¿cómo estás?',
            'delay' => 1,
            'sort_order' => 0,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => 'Analiza el siguiente video',
            'delay' => 2,
            'sort_order' => 1,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule->id,
            'content' => 'https://www.youtube.com/watch?v=Ad_CN2uC_HI',
            'delay' => 2,
            'sort_order' => 2,
        ]);

        $this->command->info('Regla Stage 0 creada exitosamente.');
        
        // Regla Stage 1 -> Stage 2 (Ejemplo adicional basado en solicitud)
        $rule2 = BotRule::create([
            'trigger_stage' => 1,
            'next_stage' => 2,
            'keywords' => 'ya vi el video, mas info',
            'is_active' => true,
        ]);

         BotRuleMessage::create([
            'bot_rule_id' => $rule2->id,
            'content' => '{saludo}, excelente.',
            'delay' => 1,
            'sort_order' => 0,
        ]);
        
        BotRuleMessage::create([
            'bot_rule_id' => $rule2->id,
            'content' => 'Analiza este segundo video que te explica mejor el negocio',
            'delay' => 2,
            'sort_order' => 1,
        ]);

        BotRuleMessage::create([
            'bot_rule_id' => $rule2->id,
            'content' => 'https://www.youtube.com/watch?v=n9zdZX7nTs8',
            'delay' => 2,
            'sort_order' => 2,
        ]);
    }
}
