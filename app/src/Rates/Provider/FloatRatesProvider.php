<?php

declare(strict_types=1);

namespace App\Rates\Provider;

use App\Rates\RateProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FloatRatesProvider implements RateProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'FLOATRATES_API_URL')]
        private string $apiUrl,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'fiat';
    }

    /**
     * @return array|float[]
     */
    public function fetchRates(): array
    {
        try {
            $entries = $this->httpClient->request('GET', $this->apiUrl)->toArray();
        } catch (HttpExceptionInterface $e) {
            throw new \RuntimeException(sprintf('Could not fetch FloatRates data: %s', $e->getMessage()), previous: $e);
        }

        $rates = [];

        foreach ($entries as $entry) {
            $code = strtoupper((string) ($entry['code'] ?? ''));

            if ('' === $code || !isset($entry['rate'])) {
                continue;
            }

            $rates[$code] = (float) $entry['rate'];
        }

        return $rates;
    }
}
