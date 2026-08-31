<?php

declare(strict_types=1);

namespace App\Tests\Rates\Provider;

use App\Rates\Provider\FloatRatesProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FloatRatesProviderTest extends TestCase
{
    /**
     * @return void
     */
    public function testFetchRatesNormalizesResponseToUppercaseCodeMap(): void
    {
        $body = json_encode([
            'eur' => ['code' => 'EUR', 'name' => 'Euro', 'rate' => 0.92],
            'gbp' => ['code' => 'GBP', 'name' => 'British Pound', 'rate' => 0.79],
        ]);

        $provider = new FloatRatesProvider(new MockHttpClient(new MockResponse($body)), 'https://example.test/daily/usd.json');

        self::assertSame(['EUR' => 0.92, 'GBP' => 0.79], $provider->fetchRates());
    }

    /**
     * @return void
     */
    public function testFetchRatesSkipsEntriesWithoutCodeOrRate(): void
    {
        $body = json_encode([
            'eur' => ['code' => 'EUR', 'rate' => 0.92],
            'broken' => ['name' => 'No code or rate'],
        ]);

        $provider = new FloatRatesProvider(new MockHttpClient(new MockResponse($body)), 'https://example.test/daily/usd.json');

        self::assertSame(['EUR' => 0.92], $provider->fetchRates());
    }

    /**
     * @return void
     */
    public function testFetchRatesWrapsTransportFailures(): void
    {
        $provider = new FloatRatesProvider(
            new MockHttpClient(new MockResponse('', ['http_code' => 500])),
            'https://example.test/daily/usd.json',
        );

        $this->expectException(\RuntimeException::class);

        $provider->fetchRates();
    }

    /**
     * @return void
     */
    public function testGetNameReturnsFiat(): void
    {
        $provider = new FloatRatesProvider(new MockHttpClient(), 'https://example.test/daily/usd.json');

        self::assertSame('fiat', $provider->getName());
    }
}
