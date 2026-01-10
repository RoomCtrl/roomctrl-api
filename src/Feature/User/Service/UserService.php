<?php

declare(strict_types=1);

namespace App\Feature\User\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use App\Feature\User\DTO\CreateUserDTO;
use App\Feature\User\DTO\UpdateUserDTO;
use App\Feature\User\DTO\UserResponseDTO;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class UserService implements UserServiceInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private OrganizationRepository $organizationRepository,
        private BookingRepository $bookingRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $mailFromAddress
    ) {
    }

    public function getAllUsers(bool $withDetails = false, ?Organization $organization = null): array
    {
        if ($organization) {
            $users = $this->userRepository->findBy(['organization' => $organization]);
        } else {
            $users = $this->userRepository->findAll();
        }

        return array_map(
            fn(User $user) => UserResponseDTO::fromEntity($user, $withDetails)->toArray(),
            $users
        );
    }

    public function getUserById(Uuid $uuid, bool $withDetails = false): ?UserResponseDTO
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            return null;
        }

        return UserResponseDTO::fromEntity($user, $withDetails);
    }

    public function createUser(CreateUserDTO $dto): User
    {
        $existingUser = $this->userRepository->findByUsername($dto->username);
        if ($existingUser) {
            throw new InvalidArgumentException('This username is already taken.');
        }

        $existingUserByEmail = $this->userRepository->findByEmail($dto->email);
        if ($existingUserByEmail) {
            throw new InvalidArgumentException('This email is already in use.');
        }

        $existingUserByPhone = $this->userRepository->findByPhone($dto->phone);
        if ($existingUserByPhone) {
            throw new InvalidArgumentException('This phone number is already in use.');
        }

        $organizationUuid = Uuid::fromString($dto->organizationId);
        $organization = $this->organizationRepository->find($organizationUuid);

        if (!$organization) {
            throw new InvalidArgumentException('Organization not found');
        }

        $user = new User();
        $user->setUsername($this->sanitizeInput($dto->username));
        $user->setFirstName($this->sanitizeInput($dto->firstName));
        $user->setLastName($this->sanitizeInput($dto->lastName));
        $user->setEmail($this->sanitizeInput($dto->email));
        $user->setPhone($this->sanitizeInput($dto->phone));
        $user->setOrganization($organization);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        if (is_array($dto->roles)) {
            $user->setRoles($dto->roles);
        }

        $user->setIsActive($dto->isActive);

        $this->userRepository->save($user, true);

        return $user;
    }

    private function sanitizeInput(string $input): string
    {
        return strip_tags($input);
    }

    public function updateUser(User $user, UpdateUserDTO $dto): void
    {
        if ($dto->username !== null) {
            $existingUser = $this->userRepository->findByUsername($dto->username);
            if ($existingUser && $existingUser->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
                throw new InvalidArgumentException('This username is already taken.');
            }
            $user->setUsername($this->sanitizeInput($dto->username));
        }

        if ($dto->password !== null) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
            $user->setPassword($hashedPassword);
        }

        if ($dto->firstName !== null) {
            $user->setFirstName($this->sanitizeInput($dto->firstName));
        }

        if ($dto->lastName !== null) {
            $user->setLastName($this->sanitizeInput($dto->lastName));
        }

        if ($dto->email !== null) {
            $existingUserByEmail = $this->userRepository->findByEmail($dto->email);
            if ($existingUserByEmail && $existingUserByEmail->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
                throw new InvalidArgumentException('This email is already in use.');
            }
            $user->setEmail($this->sanitizeInput($dto->email));
        }

        if ($dto->phone !== null) {
            $existingUserByPhone = $this->userRepository->findByPhone($dto->phone);
            if ($existingUserByPhone && $existingUserByPhone->getId()->toRfc4122() !== $user->getId()->toRfc4122()) {
                throw new InvalidArgumentException('This phone number is already in use.');
            }
            $user->setPhone($this->sanitizeInput($dto->phone));
        }

        if (is_array($dto->roles)) {
            $user->setRoles($dto->roles);
        }

        if ($dto->organizationId !== null) {
            $organizationUuid = Uuid::fromString($dto->organizationId);
            $organization = $this->organizationRepository->find($organizationUuid);

            if (!$organization) {
                throw new InvalidArgumentException('Organization not found');
            }

            $user->setOrganization($organization);
        }

        if ($dto->isActive !== null) {
            $user->setIsActive($dto->isActive);
        }

        $this->userRepository->flush();
    }

    public function deleteUser(User $user): void
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $adminCount = $this->userRepository->countAdminsInOrganization(
                $user->getOrganization()->getId()
            );

            if ($adminCount <= 1) {
                throw new InvalidArgumentException(
                    'Cannot delete the last admin of the organization. Please assign another admin before deleting this account.'
                );
            }
        }

        $activeBookings = $this->bookingRepository->findBy(['user' => $user, 'status' => 'active']);

        foreach ($activeBookings as $booking) {
            $booking->setStatus('cancelled');
        }

        if (!empty($activeBookings)) {
            $this->bookingRepository->flush();
        }

        $this->userRepository->remove($user, true);
    }

    public function validateUser(User $user): array
    {
        $errors = $this->validator->validate($user);
        $errorMessages = [];

        foreach ($errors as $error) {
            $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
        }

        return $errorMessages;
    }

    public function validateDTO(object $dto): array
    {
        $errors = $this->validator->validate($dto);
        $errorMessages = [];

        foreach ($errors as $error) {
            $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
        }

        return $errorMessages;
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RandomException
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function requestPasswordReset(string $email): bool
    {
        $user = $this->userRepository->findByEmail($email);

        if (!$user) {
            return false;
        }

        $resetToken = $this->generateResetToken();
        $expiresAt = new DateTimeImmutable('+1 hour');

        $user->setResetToken($resetToken);
        $user->setResetTokenExpiresAt($expiresAt);

        $this->userRepository->flush();

        $this->sendResetEmail($user, $resetToken);

        return true;
    }

    /**
     * @throws RandomException
     */
    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function sendResetEmail(User $user, string $token): void
    {
        $html = $this->twig->render('emails/password_reset.html.twig', [
            'user' => $user,
            'token' => $token,
        ]);

        $email = new Email()
            ->from($this->mailFromAddress)
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html($html);

        $this->mailer->send($email);
    }

    public function confirmPasswordReset(string $token, string $newPassword): bool
    {
        $user = $this->userRepository->findByResetToken($token);

        if (!$user || !$user->isResetTokenValid()) {
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->userRepository->flush();

        return true;
    }

    public function canCurrentUserAccessUser(User $targetUser, User $currentUser): bool
    {
        if (in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return $targetUser->getOrganization()->getId()->toRfc4122() === $currentUser->getOrganization()->getId()->toRfc4122();
        }

        return $targetUser->getId()->toRfc4122() === $currentUser->getId()->toRfc4122();
    }
}
