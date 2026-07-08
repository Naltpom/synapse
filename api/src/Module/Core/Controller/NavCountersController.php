<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Service\NavCounterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class NavCountersController extends AbstractController
{
    public function __construct(private readonly NavCounterService $counters)
    {
    }

    #[Route('/api/nav-counters', name: 'api_nav_counters', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json($this->counters->counters());
    }
}
