<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

class IssueCountsResponseDTO
{
    public int $count;
    public int $pending;
    public int $inProgress;
    public int $closed;

    public function __construct(int $count, int $pending, int $inProgress, int $closed)
    {
        $this->count = $count;
        $this->pending = $pending;
        $this->inProgress = $inProgress;
        $this->closed = $closed;
    }

    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'pending' => $this->pending,
            'in_progress' => $this->inProgress,
            'closed' => $this->closed
        ];
    }
}
