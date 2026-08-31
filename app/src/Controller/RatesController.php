<?php

declare(strict_types=1);

namespace App\Controller;

use App\Rates\Dto\ConvertRequest;
use App\Rates\RatesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class RatesController extends AbstractController
{
    public function __construct(
        private readonly RatesRepository $ratesRepository,
    ) {
    }

    /**
     * @param string $base
     * @return JsonResponse
     * @throws \JsonException
     */
    #[Route('/rates', name: 'api_rates', methods: ['GET'])]
    public function rates(#[MapQueryParameter] string $base = 'USD'): JsonResponse
    {
        return $this->json($this->ratesRepository->getRates($base));
    }

    /**
     * @param ConvertRequest $request
     * @return JsonResponse
     * @throws \JsonException
     */
    #[Route('/convert', name: 'api_convert', methods: ['GET'])]
    public function convert(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ConvertRequest $request,
    ): JsonResponse {
        return $this->json($this->ratesRepository->convert($request->from, $request->to, $request->amount));
    }
}
