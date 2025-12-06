<?php

declare(strict_types=1);

namespace App\Feature\User\Service;

use Symfony\Component\Uid\Uuid;

class UserService
{
    public function getUserInfo($user, bool $withDetails = false): array
    {
        $data = $this->getBasicUserData($user);

        if ($withDetails) {
            $this->addDetailedData($user, $data);
        }

        return $data;
    }

    private function getBasicUserData($user): array
    {
        return [
            'id' => $user->getId() ? $user->getId()->toRfc4122() : null,
            'username' => $user->getUsername() ? $user->getUsername() : null,
            'roles' => $user->getRoles() ? $user->getRoles() : [],
            'firstName' => $user->getFirstName() ? $user->getFirstName() : null,
            'lastName' => $user->getLastName() ? $user->getLastName() : null,
            'email' => $user->getEmail() ? $user->getEmail() : null,
            'phone' => $user->getPhone() ? $user->getPhone() : null,
            'firstLoginStatus' => $user->isFirstLoginStatus() ? $user->isFirstLoginStatus() : null,
        ];
    }

    private function addDetailedData($user, array &$data): void
    {
        $org = $user->getOrganization() ? $user->getOrganization() : null;

        if ($org) {
            $data['organization'] = $this->getEntityData($org, ['getUsers']);
        }
    }

    private function getEntityData($entity, array $excludeMethods = []): array
    {
        $data = [];
        foreach (get_class_methods($entity) as $method) {
            if (strpos($method, 'get') === 0 && !in_array($method, $excludeMethods)) {
                $key = lcfirst(preg_replace('/^get/', '', $method));
                $value = $entity->$method();
                
                if ($value instanceof Uuid) {
                    $value = $value->toRfc4122();
                }
                
                $data[$key] = $value;
            }
        }
        return $data;
    }
}
