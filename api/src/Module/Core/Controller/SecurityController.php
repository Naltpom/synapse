<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class SecurityController extends AbstractController
{
    /**
     * L'authentification est portée par json_login (security.yaml) ; quand ce contrôleur
     * s'exécute, l'utilisateur est déjà authentifié et la session ouverte.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->serializeUser($user));
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        // Interceptée par le listener logout du firewall — jamais exécutée.
        throw new \LogicException('Intercepted by the logout listener.');
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $user): array
    {
        return [
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'jobTitle' => $user->getJobTitle(),
            'roles' => $user->getRoles(),
        ];
    }
}
