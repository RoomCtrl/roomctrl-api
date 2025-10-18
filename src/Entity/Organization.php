<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Entity\User;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
#[UniqueEntity(fields: ['regon'], message: 'This REGON is already registered.')]
#[UniqueEntity(fields: ['email'], message: 'This email is already registered.')]
class Organization
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(message: 'REGON cannot be blank.')]
    #[Assert\Length(
        min: 9,
        max: 14,
        minMessage: 'REGON must be at least {{ limit }} characters long.',
        maxMessage: 'REGON cannot be longer than {{ limit }} characters.'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'REGON must contain only digits.'
    )]
    private string $regon;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Organization name cannot be blank.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Organization name must be at least {{ limit }} characters long.',
        maxMessage: 'Organization name cannot be longer than {{ limit }} characters.'
    )]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    private string $email;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: User::class)]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRegon(): string
    {
        return $this->regon;
    }

    public function setRegon(string $regon): self
    {
        $this->regon = $regon;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users[] = $user;
            $user->setOrganization($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        if ($this->users->removeElement($user)) {
            if ($user->getOrganization() === $this) {
                $user->setOrganization(null);
            }
        }

        return $this;
    }
}
