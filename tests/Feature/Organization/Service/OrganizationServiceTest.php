<?php

declare(strict_types=1);

namespace App\Tests\Feature\Organization\Service;

use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Organization\DTO\CreateOrganizationDTO;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\Organization\Service\OrganizationService;
use App\Feature\Room\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class OrganizationServiceTest extends TestCase
{
    private OrganizationRepository $organizationRepository;
    private RoomRepository $roomRepository;
    private BookingRepository $bookingRepository;
    private OrganizationService $organizationService;

    protected function setUp(): void
    {
        $this->organizationRepository = $this->createMock(OrganizationRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);

        $this->organizationService = new OrganizationService(
            $this->organizationRepository,
            $this->roomRepository,
            $this->bookingRepository
        );
    }

    public function testGetAllOrganizationsCallsRepository(): void
    {
        $organizations = [
            $this->createMock(Organization::class),
            $this->createMock(Organization::class)
        ];

        $this->organizationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($organizations);

        $result = $this->organizationService->getAllOrganizations();

        $this->assertSame($organizations, $result);
        $this->assertCount(2, $result);
    }

    public function testGetOrganizationByIdCallsRepository(): void
    {
        $id = Uuid::v4();
        $organization = $this->createMock(Organization::class);

        $this->organizationRepository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($organization);

        $result = $this->organizationService->getOrganizationById($id);

        $this->assertSame($organization, $result);
    }

    public function testGetOrganizationByIdReturnsNullWhenNotFound(): void
    {
        $id = Uuid::v4();

        $this->organizationRepository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $result = $this->organizationService->getOrganizationById($id);

        $this->assertNull($result);
    }

    public function testCreateOrganizationCreatesNewOrganization(): void
    {
        $dto = new CreateOrganizationDTO();
        $dto->regon = '123456789';
        $dto->name = 'Test Organization';
        $dto->email = 'test@organization.com';

        $this->organizationRepository
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($org) use ($dto) {
                    return $org instanceof Organization
                        && $org->getRegon() === $dto->regon
                        && $org->getName() === $dto->name
                        && $org->getEmail() === $dto->email;
                }),
                true
            );

        $result = $this->organizationService->createOrganization($dto);

        $this->assertInstanceOf(Organization::class, $result);
        $this->assertEquals($dto->regon, $result->getRegon());
        $this->assertEquals($dto->name, $result->getName());
        $this->assertEquals($dto->email, $result->getEmail());
    }

    public function testDeleteOrganizationReturnsConflictWhenHasUsers(): void
    {
        $organization = $this->createMock(Organization::class);
        $users = new ArrayCollection(['user1', 'user2']);

        $organization
            ->expects($this->once())
            ->method('getUsers')
            ->willReturn($users);

        $result = $this->organizationService->deleteOrganization($organization);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals(409, $result->getCode());
        $this->assertStringContainsString('Cannot delete organization with assigned users', $result->getMessage());
    }

    public function testDeleteOrganizationSucceedsWhenNoUsers(): void
    {
        $organization = $this->createMock(Organization::class);
        $users = new ArrayCollection();

        $organization
            ->expects($this->once())
            ->method('getUsers')
            ->willReturn($users);

        $this->roomRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['organization' => $organization])
            ->willReturn([]);

        $this->organizationRepository
            ->expects($this->once())
            ->method('remove')
            ->with($organization, true);

        $result = $this->organizationService->deleteOrganization($organization);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(204, $result->getCode());
    }
}
