<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Normalizes every /api/* error (validation failures, 404s, domain rule
 * violations, ...) into a single consistent JSON envelope instead of
 * Symfony's default HTML/debug error page.
 *
 * Setting a Response on ExceptionEvent stops propagation, which means the
 * framework's own ErrorListener::logKernelException never runs for these
 * requests — so unexpected (5xx) errors are logged here instead.
 */
#[AsEventListener(event: 'kernel.exception')]
final class ApiExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        $payload = [
            'error' => $status >= 500 ? 'Une erreur interne est survenue.' : $exception->getMessage(),
            'status' => $status,
        ];

        $previous = $exception->getPrevious();
        if ($previous instanceof ValidationFailedException) {
            $violations = [];
            foreach ($previous->getViolations() as $violation) {
                $violations[(string) $violation->getPropertyPath()][] = $violation->getMessage();
            }
            $payload['violations'] = $violations;
        }

        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];
        $event->setResponse(new JsonResponse($payload, $status, $headers));
    }
}
