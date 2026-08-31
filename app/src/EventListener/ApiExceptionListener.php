<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enums\HttpStatus;
use App\Rates\Exception\RatesUnavailableException;
use App\Rates\Exception\UnknownCurrencyException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    /**
     * @param ExceptionEvent $event
     * @return void
     */
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        [$status, $message] = match (true) {
            $exception instanceof UnknownCurrencyException => [HttpStatus::NotFound->value, $exception->getMessage()],
            $exception instanceof RatesUnavailableException => [HttpStatus::ServiceUnavailable->value, $exception->getMessage()],
            $exception instanceof HttpExceptionInterface => [
                HttpStatus::tryFrom($exception->getStatusCode())?->value ?? $exception->getStatusCode(),
                $this->describe($exception),
            ],
            default => [HttpStatus::InternalServerError->value, 'Internal server error.'],
        };

        $event->setResponse(new JsonResponse(['error' => $message], $status));
    }

    /**
     * @param HttpExceptionInterface $exception
     * @return string
     */
    private function describe(HttpExceptionInterface $exception): string
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ValidationFailedException) {
            $violations = [];

            foreach ($previous->getViolations() as $violation) {
                $violations[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            return implode(' ', $violations);
        }

        return $exception->getMessage();
    }
}
