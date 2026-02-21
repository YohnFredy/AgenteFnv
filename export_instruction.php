<?php

use App\Models\BotSetting;

$instruction = BotSetting::find('system_instruction')->value;
file_put_contents('current_instruction_full.xml', $instruction);
