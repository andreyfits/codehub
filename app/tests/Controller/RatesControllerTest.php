<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Rates\RatesFileStorage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class RatesControllerTest extends WebTestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        parent::tearDown();
        new Filesystem()->remove($projectDir . '/var/test_data');
    }

    /**
     * @return void
     */
    public function testGetRatesReturnsMergedRatesInUsdByDefault(): void
    {
        $client = self::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/rates');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($data);
        self::assertSame('BTC', $data[0]['code']);
        self::assertEqualsWithDelta(0.00001, $data[0]['rate'], 0.0000000001);
        self::assertSame('EUR', $data[1]['code']);
        self::assertEqualsWithDelta(0.9, $data[1]['rate'], 0.0000000001);
        self::assertSame('USD', $data[2]['code']);
        self::assertEqualsWithDelta(1.0, $data[2]['rate'], 0.0000000001);
    }

    /**
     * @return void
     */
    public function testGetRatesRebasesWhenBaseParameterIsGiven(): void
    {
        $client = static::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/rates?base=EUR');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $arr = [];

        foreach ($data as $item) {
            $arr[$item['code']] = $item['rate'];
        }

        self::assertEqualsWithDelta(1.0, $arr['EUR'], 0.0000001);
        self::assertEqualsWithDelta(1 / 0.9, $arr['USD'], 0.0000001);
    }

    /**
     * @return void
     */
    public function testGetRatesReturns404ForUnknownBase(): void
    {
        $client = self::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/rates?base=XXX');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return void
     */
    public function testGetRatesReturns503WhenNothingWasFetchedYet(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/rates');

        self::assertResponseStatusCodeSame(503);
    }

    /**
     * @return void
     */
    public function testConvertReturnsComputedResult(): void
    {
        $client = static::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/convert?from=EUR&to=USD&amount=9');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertEqualsWithDelta(10.0, $data['amount'], 0.0000001);
        self::assertSame('EUR', $data['currency_from']['code']);
        self::assertEqualsWithDelta(0.9, $data['currency_from']['rate'], 0.0000000001);
        self::assertSame('USD', $data['currency_to']['code']);
        self::assertEqualsWithDelta(1.0, $data['currency_to']['rate'], 0.0000000001);
    }

    /**
     * @return void
     */
    public function testConvertReturns400WhenAmountIsMissing(): void
    {
        $client = static::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/convert?from=EUR&to=USD');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return void
     */
    public function testConvertReturns400ForNegativeAmount(): void
    {
        $client = static::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/convert?from=EUR&to=USD&amount=-1');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return void
     */
    public function testConvertReturns404ForUnknownCurrency(): void
    {
        $client = static::createClient();
        $this->seedFixtures();

        $client->request('GET', '/api/convert?from=EUR&to=XXX&amount=1');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return void
     */
    private function seedFixtures(): void
    {
        $storage = RatesControllerTest::getContainer()->get(RatesFileStorage::class);
        $storage->write('fiat', ['EUR' => 0.9], new \DateTimeImmutable());
        $storage->write('crypto', ['BTC' => 0.00001], new \DateTimeImmutable());
    }
}
