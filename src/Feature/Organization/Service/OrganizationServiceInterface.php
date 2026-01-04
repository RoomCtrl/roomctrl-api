<?php

declare(strict_types=1);

namespace App\Feature\Organization\Service;

use App\Feature\Organization\DTO\CreateOrganizationDTO;
use App\Feature\Organization\DTO\OrganizationDeleteResultDTO;
use App\Feature\Organization\DTO\OrganizationResponseDTO;
use App\Feature\Organization\DTO\UpdateOrganizationDTO;
use App\Feature\Organization\Entity\Organization;
use Symfony\Component\Uid\Uuid;

interface OrganizationServiceInterface
{
    /**
     * Get all organizations
     *
     * @return array<int, Organization>
     */
    public function getAllOrganizations(): array;

    /**
     * Get organization by ID
     */
    public function getOrganizationById(Uuid $id): ?Organization;

    /**
     * Create new organization
     */
    public function createOrganization(CreateOrganizationDTO $dto): Organization;

    /**
     * Update existing organization
     */
    public function updateOrganization(Organization $organization, UpdateOrganizationDTO $dto): void;

    /**
     * Delete organization
     */
    public function deleteOrganization(Organization $organization): OrganizationDeleteResultDTO;

    /**
     * Get organization response DTO
     */
    public function getOrganizationResponse(Organization $organization, bool $withUsers = false): OrganizationResponseDTO;
}
