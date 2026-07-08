<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json($this->dashboard->overview(includeActivity: $this->isGranted('ROLE_ADMIN')));
    }
}
