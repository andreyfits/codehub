<?php

declare(strict_types=1);

namespace App\Tests\Rates\Provider;

use App\Rates\Provider\CoinPaprikaProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CoinPaprikaProviderTest extends TestCase
{
    /**
     * @return void
     */
    public function testFetchRatesInvertsPriceToUnitsPerUsd(): void
    {
        $client = new MockHttpClient($this->responseFactory());

        $provider = new CoinPaprikaProvider($client, 'https://example.test/v1/tickers', ['btc-bitcoin', 'eth-ethereum']);

        self::assertEqualsWithDelta(
            ['BTC' => 1 / 50000.0, 'ETH' => 1 / 2500.0],
            $provider->fetchRates(),
            0.0000000001,
        );
    }

    /**
     * @return void
     */
    public function testFetchRatesSkipsFailingCoinsButKeepsWorkingOnes(): void
    {
        $client = new MockHttpClient($this->responseFactory());

        $provider = new CoinPaprikaProvider(
            $client, 'https://example.test/v1/tickers',
            ['btc-bitcoin', 'unknown-coin']
        );

        self::assertEqualsWithDelta(['BTC' => 1 / 50000.0], $provider->fetchRates(), 0.0000000001);
    }

    /**
     * @return void
     */
    public function testFetchRatesThrowsWhenEveryCoinFails(): void
    {
        $client = new MockHttpClient($this->responseFactory());

        $provider = new CoinPaprikaProvider($client, 'https://example.test/v1/tickers', ['unknown-coin']);

        $this->expectException(\RuntimeException::class);

        $provider->fetchRates();
    }

    /**
     * @return void
     */
    public function testGetNameReturnsCrypto(): void
    {
        $provider = new CoinPaprikaProvider(new MockHttpClient(), 'https://example.test/v1/tickers', []);

        self::assertSame('crypto', $provider->getName());
    }

    /**
     * @return \Closure
     */
    private function responseFactory(): \Closure
    {
        return static function (string $method, string $url): MockResponse {
            return match (true) {
                str_ends_with($url, '/btc-bitcoin') => new MockResponse(json_encode([
                    'symbol' => 'BTC',
                    'quotes' => ['USD' => ['price' => 50000.0]],
                ])),
                str_ends_with($url, '/eth-ethereum') => new MockResponse(json_encode([
                    'symbol' => 'ETH',
                    'quotes' => ['USD' => ['price' => 2500.0]],
                ])),
                default => new MockResponse('Not Found', ['http_code' => 404]),
            };
        };
    }
}
