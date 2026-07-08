<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Entity\User;
use App\Module\Core\Service\SecurityProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class MeSecurityController extends AbstractController
{
    public function __construct(private readonly SecurityProfileService $profiles)
    {
    }

    #[Route('/api/me/security', name: 'api_me_security', methods: ['GET'])]
    public function security(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->profiles->profile($user));
    }
}
