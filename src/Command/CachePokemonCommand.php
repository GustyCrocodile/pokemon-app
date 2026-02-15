<?php

namespace App\Command;

use App\Service\PokeCacheService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:cache-pokemon')]
class CachePokemonCommand extends Command
{
    public function __construct(
        private PokeCacheService $pokeCacheService
    ) {
        parent::__construct();
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln('Starting to cache Pokemon names...');

        $count = $this->pokeCacheService->populateNamesCache();

        $output->writeln('Done!');

        return Command::SUCCESS;
    }
}
