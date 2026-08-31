<?php

declare(strict_types=1);

namespace App\Rates\Provider;

use App\Rates\RateProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CoinPaprikaProvider implements RateProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'COINPAPRIKA_API_URL')]
        private string $apiUrl,
        #[Autowire(env: 'csv:COINPAPRIKA_COINS')]
        private array $coinIds,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'crypto';
    }

    /**
     * @return array|float[]
     */
    public function fetchRates(): array
    {
        $rates = [];
        $errors = [];

        foreach ($this->coinIds as $coinId) {
            try {
                $ticker = $this->httpClient
                    ->request('GET', rtrim($this->apiUrl, '/') . '/' . $coinId)
                    ->toArray();
            } catch (HttpExceptionInterface $e) {
                $errors[] = sprintf('%s: %s', $coinId, $e->getMessage());
                continue;
            }

            $symbol = strtoupper((string)($ticker['symbol'] ?? ''));
            $price = $ticker['quotes']['USD']['price'] ?? null;

            if ('' === $symbol || !$price) {
                $errors[] = sprintf('%s: unexpected response shape', $coinId);
                continue;
            }

            $rates[$symbol] = 1 / (float)$price;
        }

        if ([] === $rates && [] !== $errors) {
            throw new \RuntimeException(sprintf('Could not fetch CoinPaprika data: %s', implode('; ', $errors)));
        }

        return $rates;
    }
}
