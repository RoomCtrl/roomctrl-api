<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use App\Feature\Issue\Entity\RoomIssue;

class IssueResponseDTO
{
    public string $id;
    public string $roomId;
    public string $roomName;
    public string $reporterId;
    public string $reporterName;
    public string $category;
    public string $description;
    public string $status;
    public string $priority;
    public string $reportedAt;
    public ?string $closedAt = null;
    public array $notes = [];
    public array $history = [];

    public static function fromEntity(RoomIssue $issue, bool $withDetails = false): self
    {
        $dto = new self();
        $dto->id = $issue->getId()->toRfc4122();
        $dto->roomId = $issue->getRoom()->getId()->toRfc4122();
        $dto->roomName = $issue->getRoom()->getRoomName();
        $dto->reporterId = $issue->getReporter()->getId()->toRfc4122();
        $dto->reporterName = $issue->getReporter()->getFirstName() . ' ' . $issue->getReporter()->getLastName();
        $dto->category = $issue->getCategory();
        $dto->description = $issue->getDescription();
        $dto->status = $issue->getStatus();
        $dto->priority = $issue->getPriority();
        $dto->reportedAt = $issue->getReportedAt()->format('c');
        $dto->closedAt = $issue->getClosedAt()?->format('c');

        if ($withDetails) {
            foreach ($issue->getNotes() as $note) {
                $author = $note->getAuthor();
                $dto->notes[] = [
                    'id' => $note->getId()->toRfc4122(),
                    'content' => $note->getContent(),
                    'authorId' => $author?->getId()->toRfc4122(),
                    'authorName' => $author ? $author->getFirstName() . ' ' . $author->getLastName() : 'Deleted User',
                    'createdAt' => $note->getCreatedAt()->format('c')
                ];
            }

            foreach ($issue->getHistory() as $history) {
                $user = $history->getUser();
                $dto->history[] = [
                    'id' => $history->getId()->toRfc4122(),
                    'action' => $history->getAction(),
                    'description' => $history->getDescription(),
                    'userId' => $user?->getId()->toRfc4122(),
                    'userName' => $user ? $user->getFirstName() . ' ' . $user->getLastName() : 'Deleted User',
                    'createdAt' => $history->getCreatedAt()->format('c')
                ];
            }
        }

        return $dto;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'roomId' => $this->roomId,
            'roomName' => $this->roomName,
            'reporterId' => $this->reporterId,
            'reporterName' => $this->reporterName,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'reportedAt' => $this->reportedAt,
            'closedAt' => $this->closedAt,
        ];

        if (!empty($this->notes)) {
            $data['notes'] = $this->notes;
        }

        if (!empty($this->history)) {
            $data['history'] = $this->history;
        }

        return $data;
    }
}
