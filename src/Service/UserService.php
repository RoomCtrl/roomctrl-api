<?php

namespace App\Service;

class UserService
{
    public function getUserInfo($user, bool $withOrganization = false): array
    {
        if (!is_object($user) || !method_exists($user, 'getId')) {
            return ['error' => 'User not found or invalid'];
        }

        $org = method_exists($user, 'getOrganization') ? $user->getOrganization() : null;

        $data = [
            'id' => method_exists($user, 'getId') ? $user->getId() : null,
            'username' => method_exists($user, 'getUsername') ? $user->getUsername() : null,
            'roles' => method_exists($user, 'getRoles') ? $user->getRoles() : [],
            'firstName' => method_exists($user, 'getFirstName') ? $user->getFirstName() : null,
            'lastName' => method_exists($user, 'getLastName') ? $user->getLastName() : null,
            'firstLogonStatus' => method_exists($user, 'isFirstLogonStatus') ? $user->isFirstLogonStatus() : null,
        ];

        if ($withOrganization && $org) {
            $orgData = [];
            foreach (get_class_methods($org) as $method) {
                if (strpos($method, 'get') === 0 && $method !== 'getUsers') {
                    $key = lcfirst(preg_replace('/^get/', '', $method));
                    $value = $org->$method();
                    $orgData[$key] = $value;
                }
            }
            $data['organization'] = $orgData;
        }

        return $data;
    }
}
