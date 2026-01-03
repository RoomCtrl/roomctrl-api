<?php

declare(strict_types=1);

namespace App\Feature\Issue\Service;

use App\Feature\Issue\DTO\CreateIssueDTO;
use App\Feature\Issue\DTO\CreateNoteDTO;
use App\Feature\Issue\DTO\IssueCountsResponseDTO;
use App\Feature\Issue\DTO\IssueResponseDTO;
use App\Feature\Issue\DTO\UpdateIssueDTO;
use App\Feature\Issue\Entity\IssueNote;
use App\Feature\Issue\Entity\RoomIssue;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use Symfony\Component\Uid\Uuid;

interface IssueServiceInterface
{
    /**
     * Get all issues, optionally filtered by organization and status
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllIssues(?Organization $organization = null, ?string $status = null): array;

    /**
     * Get issue by UUID
     */
    public function getIssueById(Uuid $uuid, bool $withDetails = false): ?IssueResponseDTO;

    /**
     * Get issue entity by UUID
     */
    public function getIssueEntityById(Uuid $uuid): ?RoomIssue;

    /**
     * Get issues reported by specific user
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMyIssues(User $user, ?string $status = null): array;

    /**
     * Create new issue
     */
    public function createIssue(CreateIssueDTO $dto, User $reporter): RoomIssue;

    /**
     * Update existing issue
     */
    public function updateIssue(RoomIssue $issue, UpdateIssueDTO $dto, User $user): void;

    /**
     * Add note to issue
     */
    public function addNote(RoomIssue $issue, CreateNoteDTO $dto, User $author): IssueNote;

    /**
     * Get issue counts for user
     */
    public function getIssueCounts(User $user): IssueCountsResponseDTO;

    /**
     * Get issue counts for organization
     */
    public function getIssueCountsByOrganization(Organization $organization): IssueCountsResponseDTO;

    /**
     * Delete issue
     */
    public function deleteIssue(RoomIssue $issue): void;
}
