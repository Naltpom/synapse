<?php

declare(strict_types=1);

namespace App\Module\Core\EventSubscriber;

use App\Module\Core\Exception\DomainException;
use App\Module\Core\Exception\ValidationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Traduit les exceptions de domaine en réponses JSON cohérentes pour les routes /api.
 * C'est le seul endroit qui connaît la correspondance erreur métier → code HTTP.
 */
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof DomainException) {
            return;
        }
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $payload = $exception instanceof ValidationException
            ? ['errors' => $exception->errors()]
            : ['error' => $exception->getMessage()];

        $event->setResponse(new JsonResponse($payload, $exception->statusCode()));
    }
}
