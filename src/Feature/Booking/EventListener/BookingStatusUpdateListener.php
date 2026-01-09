<?php

declare(strict_types=1);

namespace App\Feature\Booking\EventListener;

use App\Feature\Booking\Service\BookingServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Exception;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class BookingStatusUpdateListener
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        
        if ($request->getMethod() !== 'GET') {
            return;
        }

        $path = $request->getPathInfo();
        
        if (!str_starts_with($path, '/api/bookings')) {
            return;
        }

        try {
            $count = $this->bookingService->updateExpiredBookingStatuses();
            
            if ($count > 0) {
                $this->logger->info(
                    'Booking statuses updated automatically on GET request',
                    [
                        'path' => $path,
                        'updated_count' => $count
                    ]
                );
            }
        } catch (Exception $e) {
            $this->logger->error(
                'Failed to update booking statuses automatically',
                [
                    'path' => $path,
                    'error' => $e->getMessage()
                ]
            );
        }
    }
}
