<?php

declare(strict_types=1);

namespace App\Tests\Rates;

use App\Rates\Exception\RatesUnavailableException;
use App\Rates\Exception\UnknownCurrencyException;
use App\Rates\RateProviderInterface;
use App\Rates\RatesFileStorage;
use App\Rates\RatesRepository;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class RatesRepositoryTest extends TestCase
{
    private string $dataDir;
    private RatesFileStorage $storage;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/codehub-rates-test-' . uniqid();
        $this->storage = new RatesFileStorage(new Filesystem(), $this->dataDir);
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
    public function testGetRatesMergesAllProvidersInUsd(): void
    {
        $this->storage->write('fiat', ['EUR' => 0.9], new \DateTimeImmutable());
        $this->storage->write('crypto', ['BTC' => 0.00001], new \DateTimeImmutable());

        $repository = $this->createRepository(['fiat', 'crypto']);

        $result = $repository->getRates();

        self::assertSame(
            [
                ['rate' => 0.00001, 'code' => 'BTC'],
                ['rate' => 0.9, 'code' => 'EUR'],
                ['rate' => 1.0, 'code' => 'USD'],
            ],
            $result,
        );
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testGetRatesRebasesToRequestedCurrency(): void
    {
        $this->storage->write('fiat', ['EUR' => 0.5], new \DateTimeImmutable());

        $repository = $this->createRepository(['fiat']);

        $result = $repository->getRates('eur');

        $indexed = [];

        foreach ($result as $item) {
            $indexed[$item['code']] = $item['rate'];
        }

        self::assertSame('EUR', $result[0]['code']);
        self::assertEqualsWithDelta(1.0, $indexed['EUR'], 0.0000001);
        self::assertEqualsWithDelta(2.0, $indexed['USD'], 0.0000001);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testGetRatesThrowsWhenNoDataWasEverFetched(): void
    {
        $repository = $this->createRepository(['fiat', 'crypto']);

        $this->expectException(RatesUnavailableException::class);

        $repository->getRates();
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testGetRatesThrowsForUnknownBaseCurrency(): void
    {
        $this->storage->write('fiat', ['EUR' => 0.9], new \DateTimeImmutable());

        $repository = $this->createRepository(['fiat']);

        $this->expectException(UnknownCurrencyException::class);

        $repository->getRates('XXX');
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testConvertComputesCrossRateBetweenTwoCurrencies(): void
    {
        $this->storage->write('fiat', ['EUR' => 0.5, 'GBP' => 0.25], new \DateTimeImmutable());

        $repository = $this->createRepository(['fiat']);

        $result = $repository->convert('eur', 'gbp', 10.0);

        self::assertEqualsWithDelta(5.0, $result['amount'], 0.0000001);
        self::assertSame('EUR', $result['currency_from']['code']);
        self::assertSame('GBP', $result['currency_to']['code']);
        self::assertEqualsWithDelta(0.5, $result['currency_from']['rate'], 0.0000001);
        self::assertEqualsWithDelta(0.25, $result['currency_to']['rate'], 0.0000001);
    }

    /**
     * @return void
     * @throws \JsonException
     */
    public function testConvertThrowsForUnknownCurrency(): void
    {
        $this->storage->write('fiat', ['EUR' => 0.9], new \DateTimeImmutable());

        $repository = $this->createRepository(['fiat']);

        $this->expectException(UnknownCurrencyException::class);

        $repository->convert('EUR', 'XXX', 1.0);
    }

    /**
     * @param list<string> $providerNames
     */
    private function createRepository(array $providerNames): RatesRepository
    {
        $providers = array_map(
            static fn(string $name): RateProviderInterface => new readonly class($name) implements RateProviderInterface {
                public function __construct(private string $name)
                {
                }

                public function getName(): string
                {
                    return $this->name;
                }

                public function fetchRates(): array
                {
                    return [];
                }
            },
            $providerNames,
        );

        return new RatesRepository($this->storage, $providers);
    }
}
