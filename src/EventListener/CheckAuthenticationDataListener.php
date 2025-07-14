<?php

namespace App\EventListener;

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
        
        if ($request->getPathInfo() !== '/api/v1/login_check' || $request->getMethod() !== 'POST') {
            return;
        }

        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['username']) || empty($data['username']) || !isset($data['password']) || empty($data['password'])) {
            $response = new JsonResponse([
                'code' => 400,
                'message' => 'Bad request: Username or password missing'
            ], 400);
            
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }
}
