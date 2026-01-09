<?php

declare(strict_types=1);

namespace App\Feature\Booking\Command;

use App\Feature\Booking\Service\BookingServiceInterface;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:booking:update-status',
    description: 'Updates booking statuses from active to completed for bookings that have ended'
)]
class UpdateBookingStatusCommand extends Command
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new DateTimeImmutable();

        $io->title('Updating booking statuses');
        $io->text(sprintf('Current time: %s', $now->format('Y-m-d H:i:s')));

        $count = $this->bookingService->updateExpiredBookingStatuses();

        if ($count === 0) {
            $io->success('No bookings to update.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Successfully updated %d booking(s) to completed status.', $count));

        return Command::SUCCESS;
    }
}
