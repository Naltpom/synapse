<?php

declare(strict_types=1);

namespace App\Module\Core\EventSubscriber;

use App\Module\Core\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Trace les connexions réussies et les tentatives échouées — exigence de base
 * pour un outil interne dans une société de cybersécurité.
 */
final class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->log('login', $event->getUser()->getUserIdentifier(), $event->getRequest()->getClientIp());
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        // Ne jamais journaliser le mot de passe ; l'identifiant tenté suffit.
        $badge = $event->getPassport()?->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class);
        $identifier = $badge instanceof \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge
            ? $badge->getUserIdentifier()
            : 'inconnu';

        $this->log('login_failure', $identifier, $event->getRequest()->getClientIp());
    }

    private function log(string $action, string $actor, ?string $ip): void
    {
        $this->em->persist(new AuditLog($actor, $action, 'Session', null, null, $ip));
        $this->em->flush();
    }
}
