<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateRecurringBookingDTO
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $roomId;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['cleaning', 'maintenance'], message: 'Type must be cleaning or maintenance')]
    public string $type;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Time must be in HH:MM format')]
    public string $startTime;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Time must be in HH:MM format')]
    public string $endTime;

    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 7)]
    /** @var array<int> Array of day numbers (1=Monday, 7=Sunday) */
    public array $daysOfWeek;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $weeksAhead = 12; // Default 3 months
}
