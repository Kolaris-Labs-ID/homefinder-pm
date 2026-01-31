<?php

namespace App\Enums;

enum TicketFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Get the human-readable label for the frequency.
     */
    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Get the human-readable label for a day value based on frequency.
     */
    public function dayValueLabel(int $value): string
    {
        return match ($this) {
            self::Weekly => $this->weekdayName($value),
            self::Monthly => "Day {$value}",
        };
    }

    /**
     * Get the weekday name for a given day number (1=Monday, 7=Sunday).
     */
    private function weekdayName(int $day): string
    {
        return match ($day) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
            default => 'Unknown',
        };
    }
}
