<?php

declare(strict_types=1);

namespace App\Feature\User\Service;

use App\Feature\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class UserPasswordResetService
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private Environment $twig;
    private UserPasswordHasherInterface $passwordHasher;
    private string $mailFromAddress;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        Environment $twig,
        UserPasswordHasherInterface $passwordHasher,
        string $mailFromAddress
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->passwordHasher = $passwordHasher;
        $this->mailFromAddress = $mailFromAddress;
    }

    public function requestPasswordReset(string $email): bool
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return false;
        }

        $resetToken = $this->generateResetToken();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($resetToken);
        $user->setResetTokenExpiresAt($expiresAt);

        $this->entityManager->flush();

        $this->sendResetEmail($user, $resetToken);

        return true;
    }

    public function confirmPasswordReset(string $token, string $newPassword): bool
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            return false;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->entityManager->flush();

        return true;
    }

    private function generateResetToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sendResetEmail(User $user, string $token): void
    {
        $html = $this->twig->render('emails/password_reset.html.twig', [
            'user' => $user,
            'token' => $token,
        ]);

        $email = (new Email())
            ->from($this->mailFromAddress)
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html($html);

        $this->mailer->send($email);
    }
}
