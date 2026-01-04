<?php

declare(strict_types=1);

namespace App\Common\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InsufficientAuthenticationException;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $pathInfo = $request->getPathInfo();
        if (!str_starts_with($pathInfo, '/api') || str_starts_with($pathInfo, '/api/doc')) {
            return;
        }

        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'An error occurred';


        if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException) {
            $statusCode = Response::HTTP_FORBIDDEN;
            $message = $exception->getMessage() ?: 'Access denied';

            if (str_contains($message, "doesn't have")) {
                $message = 'Access denied. You do not have sufficient permissions to access this resource.';
            }
        } elseif ($exception instanceof AccountStatusException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $message = $exception->getMessage() ?: 'Account status issue';
        } elseif ($exception instanceof InsufficientAuthenticationException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $message = 'Authentication required';
        } elseif ($exception instanceof AuthenticationException) {
            $statusCode = Response::HTTP_UNAUTHORIZED;
            $message = $exception->getMessage() ?: 'Authentication failed';
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: 'An error occurred';
        }

        $response = new JsonResponse([
            'code' => $statusCode,
            'message' => $message
        ], $statusCode);

        $event->setResponse($response);
    }
}
