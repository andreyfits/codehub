<?php

declare(strict_types=1);

namespace App\Command;

use App\Rates\RateProviderInterface;
use App\Rates\RatesFileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:rates:fetch',
    description: 'Fetch currency/coin rates from every configured provider and store them as JSON.',
)]
final class FetchRatesCommand extends Command
{
    /**
     * @param iterable<RateProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.rate_provider')]
        private readonly iterable $providers,
        private readonly RatesFileStorage $storage,
    ) {
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $exitCode = Command::SUCCESS;
        $hasProviders = false;

        foreach ($this->providers as $provider) {
            $hasProviders = true;

            try {
                $rates = $provider->fetchRates();
            } catch (\Throwable $e) {
                $io->error(sprintf('Provider "%s" failed: %s', $provider->getName(), $e->getMessage()));
                $exitCode = Command::FAILURE;
                continue;
            }

            if ([] === $rates) {
                $io->warning(
                    sprintf(
                        'Provider "%s" returned no rates, previous data (if any) was kept.',
                        $provider->getName()
                    )
                );
                $exitCode = Command::FAILURE;
                continue;
            }

            $this->storage->write($provider->getName(), $rates, $now);
            $io->success(sprintf('Saved %d rate(s) from the "%s" provider.', \count($rates), $provider->getName()));
        }

        if (!$hasProviders) {
            $io->warning('No rate providers are registered.');

            return Command::FAILURE;
        }

        return $exitCode;
    }
}
