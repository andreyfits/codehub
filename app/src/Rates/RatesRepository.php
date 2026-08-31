<?php

declare(strict_types=1);

namespace App\Rates;

use App\Rates\Exception\RatesUnavailableException;
use App\Rates\Exception\UnknownCurrencyException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class RatesRepository
{
    private const string BASE_CURRENCY = 'USD';

    public function __construct(
        private readonly RatesFileStorage $storage,
        #[AutowireIterator('app.rate_provider')]
        private readonly iterable $providers,
    ) {
    }

    /**
     * @return list<array{rate: float, code: string}>
     * @throws \JsonException
     */
    public function getRates(string $base = self::BASE_CURRENCY): array
    {
        $rates = $this->getRatesMap($base);

        $list = [];

        foreach ($rates as $code => $rate) {
            $list[] = ['rate' => $rate, 'code' => $code];
        }

        usort($list, static fn (array $a, array $b): int => $a['code'] <=> $b['code']);

        return $list;
    }

    /**
     * @return array<string, float>
     * @throws \JsonException
     */
    private function getRatesMap(string $base = self::BASE_CURRENCY): array
    {
        $base = strtoupper($base);
        $rates = [self::BASE_CURRENCY => 1.0];
        $hasData = false;

        foreach ($this->providers as $provider) {
            $dataset = $this->storage->read($provider->getName());

            if (null !== $dataset) {
                $hasData = true;
                $rates = [...$rates, ...$dataset['rates']];
            }
        }

        if (!$hasData) {
            throw new RatesUnavailableException();
        }

        if (!isset($rates[$base])) {
            throw new UnknownCurrencyException($base);
        }

        if (self::BASE_CURRENCY !== $base) {
            $baseRate = $rates[$base];
            $rates = array_map(static fn (float $rate): float => $rate / $baseRate, $rates);
        }

        return $rates;
    }

    /**
     * @param string $from
     * @param string $to
     * @param float $amount
     * @return array
     * @throws \JsonException
     */
    public function convert(string $from, string $to, float $amount): array
    {
        $rates = $this->getRatesMap();

        foreach ([$from, $to] as $code) {
            if (!isset($rates[strtoupper($code)])) {
                throw new UnknownCurrencyException($code);
            }
        }

        $from = strtoupper($from);
        $to = strtoupper($to);

        return [
            'amount' => $amount * $rates[$to] / $rates[$from],
            'currency_from' => ['rate' => $rates[$from], 'code' => $from],
            'currency_to' => ['rate' => $rates[$to], 'code' => $to],
        ];
    }
}
