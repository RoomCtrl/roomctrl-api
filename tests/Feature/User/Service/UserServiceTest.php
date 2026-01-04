<?php

declare(strict_types=1);

namespace App\Tests\Feature\User\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\User\DTO\CreateUserDTO;
use App\Feature\User\DTO\UpdateUserDTO;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use App\Feature\User\Service\UserService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository $userRepository;
    private OrganizationRepository $organizationRepository;
    private BookingRepository $bookingRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private ValidatorInterface $validator;
    private MailerInterface $mailer;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->organizationRepository = $this->createMock(OrganizationRepository::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->userService = new UserService(
            $this->userRepository,
            $this->organizationRepository,
            $this->bookingRepository,
            $this->passwordHasher,
            $this->validator,
            $this->mailer,
            $this->twig,
            'test@example.com'
        );
    }

    public function testGetAllUsersReturnsArrayOfUsers(): void
    {
        $organization = $this->createMock(Organization::class);
        $user = $this->createMock(User::class);
        
        $user->method('getId')->willReturn(Uuid::v4());
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getFirstName')->willReturn('John');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getPhone')->willReturn('123456789');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getIsActive')->willReturn(true);
        $user->method('getOrganization')->willReturn($organization);
        
        $organization->method('getId')->willReturn(Uuid::v4());

        $this->userRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$user]);

        $result = $this->userService->getAllUsers();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetUserByIdReturnsUserWhenFound(): void
    {
        $uuid = Uuid::v4();
        $user = $this->createMock(User::class);
        $organization = $this->createMock(Organization::class);

        $user->method('getId')->willReturn($uuid);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getFirstName')->willReturn('John');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getPhone')->willReturn('123456789');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getIsActive')->willReturn(true);
        $user->method('getOrganization')->willReturn($organization);

        $organization->method('getId')->willReturn(Uuid::v4());

        $this->userRepository
            ->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn($user);

        $result = $this->userService->getUserById($uuid);

        $this->assertNotNull($result);
        $this->assertEquals('testuser', $result->username);
    }

    public function testGetUserByIdReturnsNullWhenNotFound(): void
    {
        $uuid = Uuid::v4();

        $this->userRepository
            ->expects($this->once())
            ->method('findByUuid')
            ->with($uuid)
            ->willReturn(null);

        $result = $this->userService->getUserById($uuid);

        $this->assertNull($result);
    }

    public function testCreateUserThrowsExceptionWhenUsernameExists(): void
    {
        $existingUser = $this->createMock(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->with('existinguser')
            ->willReturn($existingUser);

        $dto = CreateUserDTO::fromArray([
            'username' => 'existinguser',
            'password' => 'password123',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '123456789',
            'organizationId' => Uuid::v4()->toRfc4122(),
            'roles' => ['ROLE_USER'],
            'isActive' => true
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This username is already taken.');

        $this->userService->createUser($dto);
    }

    public function testCreateUserThrowsExceptionWhenEmailExists(): void
    {
        $existingUser = $this->createMock(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $dto = CreateUserDTO::fromArray([
            'username' => 'newuser',
            'password' => 'password123',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'existing@example.com',
            'phone' => '123456789',
            'organizationId' => Uuid::v4()->toRfc4122(),
            'roles' => ['ROLE_USER'],
            'isActive' => true
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This email is already in use.');

        $this->userService->createUser($dto);
    }

    public function testDeleteUserCancelsActiveBookings(): void
    {
        $user = $this->createMock(User::class);
        $booking = $this->createMock(Booking::class);

        $booking
            ->expects($this->once())
            ->method('setStatus')
            ->with('cancelled');

        $this->bookingRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['user' => $user, 'status' => 'active'])
            ->willReturn([$booking]);

        $this->bookingRepository
            ->expects($this->once())
            ->method('flush');

        $this->userRepository
            ->expects($this->once())
            ->method('remove')
            ->with($user, true);

        $this->userService->deleteUser($user);
    }

    public function testUpdateUserThrowsExceptionWhenUsernameIsTaken(): void
    {
        $user = $this->createMock(User::class);
        $existingUser = $this->createMock(User::class);
        
        $userId = Uuid::v4();
        $existingUserId = Uuid::v4();

        $user->method('getId')->willReturn($userId);
        $existingUser->method('getId')->willReturn($existingUserId);

        $this->userRepository
            ->expects($this->once())
            ->method('findByUsername')
            ->with('takenusername')
            ->willReturn($existingUser);

        $dto = UpdateUserDTO::fromArray([
            'username' => 'takenusername'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This username is already taken.');

        $this->userService->updateUser($user, $dto);
    }

    public function testUpdateUserThrowsExceptionWhenEmailIsTaken(): void
    {
        $user = $this->createMock(User::class);
        $existingUser = $this->createMock(User::class);
        
        $userId = Uuid::v4();
        $existingUserId = Uuid::v4();

        $user->method('getId')->willReturn($userId);
        $existingUser->method('getId')->willReturn($existingUserId);

        $this->userRepository
            ->method('findByUsername')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($existingUser);

        $dto = UpdateUserDTO::fromArray([
            'email' => 'taken@example.com'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This email is already in use.');

        $this->userService->updateUser($user, $dto);
    }

    public function testUpdateUserUpdatesUsernameSuccessfully(): void
    {
        $user = $this->createMock(User::class);
        $userId = Uuid::v4();

        $user->method('getId')->willReturn($userId);

        $this->userRepository
            ->method('findByUsername')
            ->willReturn(null);

        $user
            ->expects($this->once())
            ->method('setUsername')
            ->with('newusername');

        $this->userRepository
            ->expects($this->once())
            ->method('flush');

        $dto = UpdateUserDTO::fromArray([
            'username' => 'newusername'
        ]);

        $this->userService->updateUser($user, $dto);
    }
}
