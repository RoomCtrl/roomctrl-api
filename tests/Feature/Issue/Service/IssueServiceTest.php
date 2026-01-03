<?php

declare(strict_types=1);

namespace App\Tests\Feature\Issue\Service;

use App\Feature\Issue\DTO\CreateIssueDTO;
use App\Feature\Issue\DTO\CreateNoteDTO;
use App\Feature\Issue\DTO\UpdateIssueDTO;
use App\Feature\Issue\Entity\IssueNote;
use App\Feature\Issue\Entity\RoomIssue;
use App\Feature\Issue\Repository\RoomIssueRepository;
use App\Feature\Issue\Service\IssueService;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\Entity\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class IssueServiceTest extends TestCase
{
    private IssueService $issueService;
    private RoomIssueRepository $issueRepository;
    private RoomRepository $roomRepository;

    protected function setUp(): void
    {
        $this->issueRepository = $this->createMock(RoomIssueRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);

        $this->issueService = new IssueService(
            $this->issueRepository,
            $this->roomRepository
        );
    }

    public function testGetAllIssuesReturnsArrayOfIssues(): void
    {
        $issue = $this->createMock(RoomIssue::class);
        $room = $this->createMock(Room::class);
        $organization = $this->createMock(Organization::class);
        $reporter = $this->createMock(User::class);

        $issue->method('getId')->willReturn(Uuid::v4());
        $issue->method('getRoom')->willReturn($room);
        $issue->method('getOrganization')->willReturn($organization);
        $issue->method('getReporter')->willReturn($reporter);
        $issue->method('getCategory')->willReturn('maintenance');
        $issue->method('getDescription')->willReturn('Test issue');
        $issue->method('getPriority')->willReturn('medium');
        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getReportedAt')->willReturn(new \DateTimeImmutable());

        $room->method('getId')->willReturn(Uuid::v4());
        $organization->method('getId')->willReturn(Uuid::v4());
        $reporter->method('getId')->willReturn(Uuid::v4());

        $this->issueRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$issue]);

        $result = $this->issueService->getAllIssues();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetIssueByIdReturnsIssueWhenFound(): void
    {
        $uuid = Uuid::v4();
        $issue = $this->createMock(RoomIssue::class);
        $room = $this->createMock(Room::class);
        $organization = $this->createMock(Organization::class);
        $reporter = $this->createMock(User::class);

        $issue->method('getId')->willReturn($uuid);
        $issue->method('getRoom')->willReturn($room);
        $issue->method('getOrganization')->willReturn($organization);
        $issue->method('getReporter')->willReturn($reporter);
        $issue->method('getCategory')->willReturn('maintenance');
        $issue->method('getDescription')->willReturn('Test issue');
        $issue->method('getPriority')->willReturn('medium');
        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getReportedAt')->willReturn(new \DateTimeImmutable());

        $room->method('getId')->willReturn(Uuid::v4());
        $organization->method('getId')->willReturn(Uuid::v4());
        $reporter->method('getId')->willReturn(Uuid::v4());

        $this->issueRepository
            ->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn($issue);

        $result = $this->issueService->getIssueById($uuid);

        $this->assertNotNull($result);
    }

    public function testGetIssueByIdReturnsNullWhenNotFound(): void
    {
        $uuid = Uuid::v4();

        $this->issueRepository
            ->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn(null);

        $result = $this->issueService->getIssueById($uuid);

        $this->assertNull($result);
    }

    public function testCreateIssueThrowsExceptionWhenRoomNotFound(): void
    {
        $reporter = $this->createMock(User::class);
        $roomId = Uuid::v4()->toRfc4122();

        $this->roomRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $dto = CreateIssueDTO::fromArray([
            'roomId' => $roomId,
            'category' => 'equipment',
            'description' => 'Test issue description here',
            'priority' => 'medium'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Room not found');

        $this->issueService->createIssue($dto, $reporter);
    }

    public function testCreateIssueThrowsExceptionWhenRoomNotInUserOrganization(): void
    {
        $reporter = $this->createMock(User::class);
        $room = $this->createMock(Room::class);
        $roomOrganization = $this->createMock(Organization::class);
        $userOrganization = $this->createMock(Organization::class);

        $roomOrgId = Uuid::v4();
        $userOrgId = Uuid::v4();

        $roomOrganization->method('getId')->willReturn($roomOrgId);
        $userOrganization->method('getId')->willReturn($userOrgId);

        $room->method('getOrganization')->willReturn($roomOrganization);
        $reporter->method('getOrganization')->willReturn($userOrganization);

        $this->roomRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($room);

        $dto = CreateIssueDTO::fromArray([
            'roomId' => Uuid::v4()->toRfc4122(),
            'category' => 'equipment',
            'description' => 'Test issue description here',
            'priority' => 'medium'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Room does not belong to your organization');

        $this->issueService->createIssue($dto, $reporter);
    }

    public function testUpdateIssueChangesStatus(): void
    {
        $issue = $this->createMock(RoomIssue::class);
        $user = $this->createMock(User::class);

        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getPriority')->willReturn('medium');

        $issue
            ->expects($this->once())
            ->method('setStatus')
            ->with('in_progress');

        $issue
            ->expects($this->once())
            ->method('addHistory')
            ->with($this->isInstanceOf(\App\Feature\Issue\Entity\IssueHistory::class));

        $this->issueRepository
            ->expects($this->once())
            ->method('flush');

        $dto = UpdateIssueDTO::fromArray([
            'status' => 'in_progress'
        ]);

        $this->issueService->updateIssue($issue, $dto, $user);
    }

    public function testUpdateIssueChangesPriority(): void
    {
        $issue = $this->createMock(RoomIssue::class);
        $user = $this->createMock(User::class);

        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getPriority')->willReturn('medium');

        $issue
            ->expects($this->once())
            ->method('setPriority')
            ->with('high');

        $issue
            ->expects($this->once())
            ->method('addHistory')
            ->with($this->isInstanceOf(\App\Feature\Issue\Entity\IssueHistory::class));

        $this->issueRepository
            ->expects($this->once())
            ->method('flush');

        $dto = UpdateIssueDTO::fromArray([
            'priority' => 'high'
        ]);

        $this->issueService->updateIssue($issue, $dto, $user);
    }

    public function testUpdateIssueDoesNotFlushWhenNoChanges(): void
    {
        $issue = $this->createMock(RoomIssue::class);
        $user = $this->createMock(User::class);

        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getPriority')->willReturn('medium');

        $this->issueRepository
            ->expects($this->never())
            ->method('flush');

        $dto = UpdateIssueDTO::fromArray([
            'status' => 'pending',
            'priority' => 'medium'
        ]);

        $this->issueService->updateIssue($issue, $dto, $user);
    }

    public function testGetMyIssuesReturnsUserIssues(): void
    {
        $user = $this->createMock(User::class);
        $issue = $this->createMock(RoomIssue::class);
        $room = $this->createMock(Room::class);
        $organization = $this->createMock(Organization::class);

        $userId = Uuid::v4();
        $user->method('getId')->willReturn($userId);

        $issue->method('getId')->willReturn(Uuid::v4());
        $issue->method('getRoom')->willReturn($room);
        $issue->method('getOrganization')->willReturn($organization);
        $issue->method('getReporter')->willReturn($user);
        $issue->method('getCategory')->willReturn('maintenance');
        $issue->method('getDescription')->willReturn('Test issue');
        $issue->method('getPriority')->willReturn('medium');
        $issue->method('getStatus')->willReturn('pending');
        $issue->method('getReportedAt')->willReturn(new \DateTimeImmutable());

        $room->method('getId')->willReturn(Uuid::v4());
        $organization->method('getId')->willReturn(Uuid::v4());

        $this->issueRepository
            ->expects($this->once())
            ->method('findByReporter')
            ->with($userId->toRfc4122(), null)
            ->willReturn([$issue]);

        $result = $this->issueService->getMyIssues($user);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }
}
