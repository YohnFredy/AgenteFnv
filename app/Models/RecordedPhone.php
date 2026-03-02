<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordedPhone extends Model
{
    public $timestamps = false;

    protected $fillable = ['phone', 'normalized_phone', 'imported_at'];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /**
     * Normalize a raw phone string to the Colombian 12-digit format: 57XXXXXXXXXX
     * Returns null if the number is invalid/incomplete.
     */
    public static function normalizeNumber(string $raw): ?string
    {
        // Remove everything except digits
        $digits = preg_replace('/\D/', '', $raw);

        // If empty, invalid
        if (empty($digits)) {
            return null;
        }

        // Already 12 digits starting with 57 → valid
        if (strlen($digits) === 12 && str_starts_with($digits, '57')) {
            return $digits;
        }

        // 10 digits starting with 3 → add Colombian country code
        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            return '57' . $digits;
        }

        // 11 digits starting with 57 followed by 3 → strip leading 5 and try? No.
        // 13 digits starting with 057 → strip leading 0
        if (strlen($digits) === 13 && str_starts_with($digits, '057')) {
            $candidate = substr($digits, 1); // 12 digits
            if (str_starts_with($candidate, '57')) {
                return $candidate;
            }
        }

        // Field may have multiple numbers separated by non-digit chars.
        // Try to extract the FIRST 10-digit sequence starting with 3.
        if (preg_match('/3\d{9}/', $raw, $matches)) {
            return '57' . $matches[0];
        }

        // Fallback: if we have more than 10 digits starting with 57 and a 3 after the prefix
        if (strlen($digits) > 12 && str_starts_with($digits, '57')) {
            $candidate = substr($digits, 0, 12);
            if (strlen($candidate) === 12) {
                return $candidate;
            }
        }

        return null;
    }
}
