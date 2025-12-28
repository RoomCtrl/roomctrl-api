<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateIssueDTO
{
    #[Assert\Choice(choices: ['pending', 'in_progress', 'closed'], message: 'Invalid status.')]
    public ?string $status = null;

    #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical'], message: 'Invalid priority.')]
    public ?string $priority = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->status = $data['status'] ?? null;
        $dto->priority = $data['priority'] ?? null;

        return $dto;
    }
}
