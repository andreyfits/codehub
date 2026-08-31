<?php

declare(strict_types=1);

namespace App\Rates;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class RatesFileStorage
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/data')]
        private string $dataDir,
    ) {
    }

    /**
     * @param array<string, float> $rates
     * @throws \JsonException
     */
    public function write(string $name, array $rates, \DateTimeImmutable $updatedAt, string $base = 'USD'): void
    {
        $payload = [
            'base' => $base,
            'updated_at' => $updatedAt->format(\DATE_ATOM),
            'rates' => $rates,
        ];

        $this->filesystem->dumpFile(
            $this->getPath($name),
            json_encode($payload, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return array{base: string, updated_at: string, rates: array<string, float>}|null
     * @throws \JsonException
     */
    public function read(string $name): ?array
    {
        $path = $this->getPath($name);

        if (!$this->filesystem->exists($path)) {
            return null;
        }

        return json_decode(file_get_contents($path), associative: true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $name
     * @return string
     */
    private function getPath(string $name): string
    {
        return rtrim($this->dataDir, '/') . '/' . $name . '_rates.json';
    }
}
