<?php

declare(strict_types=1);

namespace App\Feature\Auth\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class CheckAuthenticationDataListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->getPathInfo() !== '/api/login_check' || $request->getMethod() !== 'POST') {
            return;
        }

        $data = json_decode($request->getContent(), true);
        $error = null;

        if (!is_array($data)) {
            $error = 'Bad request: Invalid JSON';
        } elseif (!isset($data['username']) || !isset($data['password'])) {
            $error = 'Bad request: Username or password missing';
        } elseif (!is_string($data['username']) || !is_string($data['password'])) {
            $error = 'Bad request: Username and password must be strings';
        } elseif (empty($data['username']) || empty($data['password'])) {
            $error = 'Bad request: Username or password missing';
        }

        if ($error !== null) {
            $response = new JsonResponse([
                'code' => 400,
                'message' => $error
            ], 400);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
