<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportDatabaseCommand extends Command
{
    protected static $defaultName = 'app:import-database';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();

        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Imports data from the database.sql file into the PostgreSQL database')
            ->setHelp('This command allows you to import data from the database.sql file into the PostgreSQL database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath = __DIR__ . '/../../database.sql';

        if (!file_exists($filePath)) {
            $io->error('The database.sql file does not exist.');
            return Command::FAILURE;
        }

        $sql = file_get_contents($filePath);

        try {
            $this->connection->executeStatement($sql);
            $io->success('Data has been imported successfully.');
        } catch (\Exception $e) {
            $io->error('An error occurred while importing data: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
