<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\FetchRatesCommand;
use App\Rates\RateProviderInterface;
use App\Rates\RatesFileStorage;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class FetchRatesCommandTest extends TestCase
{
    private string $dataDir;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/codehub-fetch-cmd-test-' . uniqid();
    }

    /**
     * @return void
     */
    #[After]
    public function cleanupDataDir(): void
    {
        new Filesystem()->remove($this->dataDir);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testExecuteStoresRatesFromEveryProvider(): void
    {
        $storage = new RatesFileStorage(new Filesystem(), $this->dataDir);
        $providers = [
            $this->fakeProvider('fiat', ['EUR' => 0.9]),
            $this->fakeProvider('crypto', ['BTC' => 0.00001]),
        ];

        $tester = $this->createTester(new FetchRatesCommand($providers, $storage));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertSame(['EUR' => 0.9], $storage->read('fiat')['rates']);
        self::assertSame(['BTC' => 0.00001], $storage->read('crypto')['rates']);
        self::assertStringContainsString('fiat', $tester->getDisplay());
    }

    /**
     * @return void
     */
    public function testExecuteReportsFailureWhenAProviderThrows(): void
    {
        $storage = new RatesFileStorage(new Filesystem(), $this->dataDir);
        $providers = [
            $this->fakeProvider('fiat', new \RuntimeException('boom')),
        ];

        $tester = $this->createTester(new FetchRatesCommand($providers, $storage));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('boom', $tester->getDisplay());
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testExecuteKeepsPreviousDataWhenProviderReturnsEmpty(): void
    {
        $storage = new RatesFileStorage(new Filesystem(), $this->dataDir);
        $storage->write('fiat', ['EUR' => 0.9], new \DateTimeImmutable());
        $providers = [$this->fakeProvider('fiat', [])];

        $tester = $this->createTester(new FetchRatesCommand($providers, $storage));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertSame(['EUR' => 0.9], $storage->read('fiat')['rates']);
    }

    /**
     * @param string $name
     * @param array|\Throwable $ratesOrException
     * @return RateProviderInterface
     */
    private function fakeProvider(string $name, array|\Throwable $ratesOrException): RateProviderInterface
    {
        return new readonly class($name, $ratesOrException) implements RateProviderInterface {
            public function __construct(
                private string $name,
                private array|\Throwable $ratesOrException,
            ) {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function fetchRates(): array
            {
                if ($this->ratesOrException instanceof \Throwable) {
                    throw $this->ratesOrException;
                }

                return $this->ratesOrException;
            }
        };
    }

    /**
     * @param FetchRatesCommand $command
     * @return CommandTester
     */
    private function createTester(FetchRatesCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:rates:fetch'));
    }
}
