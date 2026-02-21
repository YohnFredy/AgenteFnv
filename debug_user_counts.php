<?php

use App\Models\Chat;
use App\Models\Message;

echo "--- Chat Distribution Analysis ---\n";

$total = Chat::count();
$active = Chat::where('is_active', true)->count();
$withJid = Chat::whereNotNull('remote_jid')->count();
$activeWithJid = Chat::where('is_active', true)->whereNotNull('remote_jid')->count();

echo "Total Chats: $total\n";
echo "Active Chats: $active\n";
echo "With remote_jid: $withJid\n";
echo "Active + JOD: $activeWithJid\n";

// Has User Messages
$hasUserMsg = Chat::where('is_active', true)
    ->whereNotNull('remote_jid')
    ->whereHas('messages', function ($q) {
        $q->where('role', 'user');
    })->count();

$noUserMsg = $activeWithJid - $hasUserMsg;

echo "With at least 1 user message: $hasUserMsg\n";
echo "WITHOUT any user message (Silent): $noUserMsg\n";

// Time Logic Analysis (24h default)
$timeHours = 24;
$cutoffTime = now()->subHours($timeHours);

$recentActive = Chat::where('is_active', true)
    ->whereNotNull('remote_jid')
    ->whereHas('messages', function ($q) use ($cutoffTime) {
        $q->where('role', 'user')
            ->where('created_at', '>=', $cutoffTime);
    })->count();

$inactiveWithHistory = Chat::where('is_active', true)
    ->whereNotNull('remote_jid')
    ->whereDoesntHave('messages', function ($q) use ($cutoffTime) {
        $q->where('role', 'user')
            ->where('created_at', '>=', $cutoffTime);
    })->count();

echo "\n--- Filter Simulation (Hours: $timeHours) ---\n";
echo "Mode 'Manual' (Recent): $recentActive\n";
echo "Mode 'Template' (Inactive, with history): $inactiveWithHistory\n";
echo "Excluded (Silent users): $noUserMsg\n";
