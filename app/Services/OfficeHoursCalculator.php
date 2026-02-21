<?php

namespace App\Services;

use Carbon\Carbon;

class OfficeHoursCalculator
{
    /**
     * Calculate the scheduled time by distributing delay hours across office windows.
     *
     * @param \Carbon\CarbonInterface $startTime The starting time (e.g., last interaction)
     * @param int $delayHours The number of hours to wait (within office hours)
     * @param string $openTimeStr Office opening time (e.g., "07:00")
     * @param string $closeTimeStr Office closing time (e.g., "19:00")
     * @return \Carbon\CarbonInterface The calculated scheduled time
     */
    public function calculateScheduledTime(\Carbon\CarbonInterface $startTime, int $delayHours, string $openTimeStr, string $closeTimeStr): \Carbon\CarbonInterface
    {
        if ($delayHours <= 0) {
            return $startTime->copy();
        }

        $current = $startTime->copy();
        $remainingHours = $delayHours;

        // Parse open/close times into Carbon instances (we just need the hour/minute components)
        $openTime = Carbon::createFromFormat('H:i', $openTimeStr);
        $closeTime = Carbon::createFromFormat('H:i', $closeTimeStr);

        $startHour = $openTime->hour;
        $startMinute = $openTime->minute;
        $endHour = $closeTime->hour;
        $endMinute = $closeTime->minute;

        // Safety check to avoid infinite loops if office hours are invalid
        if ($startHour >= $endHour) {
            // Fallback: just add hours directly
            return $current->addHours($delayHours);
        }

        while ($remainingHours > 0) {
            // Define office window for the current day
            // Ensure we assign back because copy() might be immutable depending on origin, 
            // but here we are modifying the copy.
            // Actually, if $current is Immutable, copy() is Immutable. setTime returns new instance.
            $officeStart = $current->copy()->setTime($startHour, $startMinute, 0);
            $officeEnd = $current->copy()->setTime($endHour, $endMinute, 0);

            // If current time is before office start, move to office start
            if ($current->lt($officeStart)) {
                $current = $officeStart;
            }

            // If current time is after office end, move to next day's office start
            if ($current->gte($officeEnd)) {
                $current = $current->addDay()->setTime($startHour, $startMinute, 0);
                continue;
            }

            // Calculate active hours remaining in today's window
            // officeEnd and current are compatible for diffInSeconds
            $hoursLeftToday = $current->diffInSeconds($officeEnd) / 3600;

            if ($remainingHours <= $hoursLeftToday) {
                // We can fit the remaining delay in today's window
                $current = $current->addSeconds($remainingHours * 3600);
                $remainingHours = 0;
            } else {
                // Use up all remaining time today and move to next day
                $remainingHours -= $hoursLeftToday;
                $current = $current->addDay()->setTime($startHour, $startMinute, 0);
            }
        }

        return $current;
    }
}
