<?php

namespace App\DTOs;

class PolicyWarning
{
    /**
     * @param  'none'|'warning'|'danger'  $level
     */
    public function __construct(
        public string $level,
        public string $message,
        public float $weeklyTotal,
        public int $consecutiveWeeks,
        public float $weeklyLimit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'weekly_total' => $this->weeklyTotal,
            'weeklyTotal' => $this->weeklyTotal,
            'consecutive_weeks' => $this->consecutiveWeeks,
            'consecutiveWeeks' => $this->consecutiveWeeks,
            'weekly_limit' => $this->weeklyLimit,
            'weeklyLimit' => $this->weeklyLimit,
        ];
    }
}
