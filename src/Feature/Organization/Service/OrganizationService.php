<?php

declare(strict_types=1);

namespace App\Feature\Organization\Service;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Organization\Repository\OrganizationRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;

class OrganizationService
{
    private OrganizationRepository $organizationRepository;
    private ValidatorInterface $validator;

    public function __construct(
        OrganizationRepository $organizationRepository,
        ValidatorInterface $validator
    ) {
        $this->organizationRepository = $organizationRepository;
        $this->validator = $validator;
    }

    public function getAllOrganizations(): array
    {
        return $this->organizationRepository->findAll();
    }

    public function getOrganizationById(Uuid $id): ?Organization
    {
        return $this->organizationRepository->findById($id);
    }

    public function createOrganization(array $data): array
    {
        $missingFields = $this->validateRequiredFields($data, ['regon', 'name', 'email']);
        
        if (!empty($missingFields)) {
            return [
                'success' => false,
                'code' => 400,
                'message' => 'Missing required fields',
                'errors' => $missingFields
            ];
        }

        $organization = new Organization();
        $organization->setRegon($this->sanitizeInput($data['regon']));
        $organization->setName($this->sanitizeInput($data['name']));
        $organization->setEmail($this->sanitizeInput($data['email']));

        $validationResult = $this->validate($organization);
        if (!$validationResult['success']) {
            return $validationResult;
        }

        try {
            $this->organizationRepository->save($organization, true);

            return [
                'success' => true,
                'code' => 201,
                'message' => 'Organization created successfully',
                'id' => $organization->getId()->toRfc4122()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to create organization: ' . $e->getMessage()
            ];
        }
    }

    public function updateOrganization(Organization $organization, array $data): array
    {
        if (isset($data['regon'])) {
            $organization->setRegon($this->sanitizeInput($data['regon']));
        }

        if (isset($data['name'])) {
            $organization->setName($this->sanitizeInput($data['name']));
        }

        if (isset($data['email'])) {
            $organization->setEmail($this->sanitizeInput($data['email']));
        }

        $validationResult = $this->validate($organization);
        if (!$validationResult['success']) {
            return $validationResult;
        }

        try {
            $this->organizationRepository->flush();

            return [
                'success' => true,
                'code' => 200,
                'message' => 'Organization updated successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to update organization: ' . $e->getMessage()
            ];
        }
    }

    public function deleteOrganization(Organization $organization): array
    {
        try {
            $this->organizationRepository->remove($organization, true);

            return [
                'success' => true,
                'code' => 204
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'code' => 500,
                'message' => 'Failed to delete organization: ' . $e->getMessage()
            ];
        }
    }

    public function serializeOrganization(Organization $organization, bool $withUsers = false): array
    {
        $data = [
            'id' => $organization->getId()->toRfc4122(),
            'regon' => $organization->getRegon(),
            'name' => $organization->getName(),
            'email' => $organization->getEmail()
        ];

        if ($withUsers) {
            $data['usersCount'] = $organization->getUsers()->count();
        }

        return $data;
    }

    private function validate(Organization $organization): array
    {
        $errors = $this->validator->validate($organization);
        
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return [
                'success' => false,
                'code' => 400,
                'message' => 'Validation failed',
                'errors' => $errorMessages
            ];
        }

        return ['success' => true];
    }

    private function validateRequiredFields(array $data, array $requiredFields): array
    {
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    private function sanitizeInput(string $input): string
    {
        return strip_tags($input);
    }
}
