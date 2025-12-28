<?php

declare(strict_types=1);

namespace App\Feature\Issue\Service;

use App\Feature\Issue\DTO\CreateIssueDTO;
use App\Feature\Issue\DTO\CreateNoteDTO;
use App\Feature\Issue\DTO\IssueCountsResponseDTO;
use App\Feature\Issue\DTO\IssueResponseDTO;
use App\Feature\Issue\DTO\UpdateIssueDTO;
use App\Feature\Issue\Entity\IssueHistory;
use App\Feature\Issue\Entity\IssueNote;
use App\Feature\Issue\Entity\RoomIssue;
use App\Feature\Issue\Repository\RoomIssueRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

readonly class IssueService
{
    public function __construct(
        private RoomIssueRepository $issueRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getAllIssues(?Organization $organization = null, ?string $status = null): array
    {
        if ($organization) {
            $issues = $this->issueRepository->findByOrganization($organization, $status);
        } else {
            $issues = $this->issueRepository->findAll();
        }

        return array_map(
            fn(RoomIssue $issue) => IssueResponseDTO::fromEntity($issue, false)->toArray(),
            $issues
        );
    }

    public function getIssueById(Uuid $uuid, bool $withDetails = false): ?IssueResponseDTO
    {
        $issue = $this->issueRepository->findByUuid($uuid);

        if (!$issue) {
            return null;
        }

        return IssueResponseDTO::fromEntity($issue, $withDetails);
    }

    public function getMyIssues(User $user, ?string $status = null): array
    {
        $issues = $this->issueRepository->findByReporter($user->getId()->toRfc4122(), $status);

        return array_map(
            fn(RoomIssue $issue) => IssueResponseDTO::fromEntity($issue, false)->toArray(),
            $issues
        );
    }

    public function createIssue(CreateIssueDTO $dto, User $reporter): RoomIssue
    {
        $roomUuid = Uuid::fromString($dto->roomId);
        $room = $this->entityManager->getRepository(Room::class)->find($roomUuid);

        if (!$room) {
            throw new InvalidArgumentException('Room not found');
        }

        if ($room->getOrganization()->getId()->toRfc4122() !== $reporter->getOrganization()->getId()->toRfc4122()) {
            throw new InvalidArgumentException('Room does not belong to your organization');
        }

        $issue = new RoomIssue();
        $issue->setRoom($room);
        $issue->setReporter($reporter);
        $issue->setOrganization($reporter->getOrganization());
        $issue->setCategory($dto->category);
        $issue->setDescription($dto->description);
        $issue->setPriority($dto->priority);
        $issue->setStatus('pending');

        $history = new IssueHistory();
        $history->setIssue($issue);
        $history->setUser($reporter);
        $history->setAction('created');
        $history->setDescription('Issue was created');
        $issue->addHistory($history);

        $this->issueRepository->save($issue, true);

        return $issue;
    }

    public function updateIssue(RoomIssue $issue, UpdateIssueDTO $dto, User $user): void
    {
        $changes = [];

        if ($dto->status !== null && $dto->status !== $issue->getStatus()) {
            $oldStatus = $issue->getStatus();
            $issue->setStatus($dto->status);
            $changes[] = "Status changed from '{$oldStatus}' to '{$dto->status}'";

            if ($dto->status === 'closed') {
                $issue->setClosedAt(new DateTimeImmutable());
                $history = new IssueHistory();
                $history->setIssue($issue);
                $history->setUser($user);
                $history->setAction('closed');
                $history->setDescription('Issue was closed');
                $issue->addHistory($history);
            } elseif ($dto->status === 'in_progress') {
                $history = new IssueHistory();
                $history->setIssue($issue);
                $history->setUser($user);
                $history->setAction('status_changed');
                $history->setDescription("Status changed to 'in_progress'");
                $issue->addHistory($history);
            }
        }

        if ($dto->priority !== null && $dto->priority !== $issue->getPriority()) {
            $oldPriority = $issue->getPriority();
            $issue->setPriority($dto->priority);
            $changes[] = "Priority changed from '{$oldPriority}' to '{$dto->priority}'";

            $history = new IssueHistory();
            $history->setIssue($issue);
            $history->setUser($user);
            $history->setAction('priority_changed');
            $history->setDescription("Priority changed from '{$oldPriority}' to '{$dto->priority}'");
            $issue->addHistory($history);
        }

        if (!empty($changes)) {
            $this->issueRepository->flush();
        }
    }

    public function addNote(RoomIssue $issue, CreateNoteDTO $dto, User $author): IssueNote
    {
        $note = new IssueNote();
        $note->setIssue($issue);
        $note->setAuthor($author);
        $note->setContent($dto->content);

        $history = new IssueHistory();
        $history->setIssue($issue);
        $history->setUser($author);
        $history->setAction('note_added');
        $history->setDescription('Service note was added');
        $issue->addHistory($history);

        $issue->addNote($note);
        $this->issueRepository->flush();

        return $note;
    }

    public function getIssueCounts(User $user): IssueCountsResponseDTO
    {
        $counts = $this->issueRepository->getIssueCountsByUser($user);

        return new IssueCountsResponseDTO(
            $counts['count'],
            $counts['pending'],
            $counts['in_progress'],
            $counts['closed']
        );
    }

    public function getIssueCountsByOrganization(Organization $organization): IssueCountsResponseDTO
    {
        $counts = $this->issueRepository->getIssueCountsByOrganization($organization);

        return new IssueCountsResponseDTO(
            $counts['count'],
            $counts['pending'],
            $counts['in_progress'],
            $counts['closed']
        );
    }

    public function deleteIssue(RoomIssue $issue): void
    {
        $this->issueRepository->remove($issue, true);
    }
}
