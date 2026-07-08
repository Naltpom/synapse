<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Service\MarginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class FinanceController extends AbstractController
{
    public function __construct(private readonly MarginService $margins)
    {
    }

    // Vue direction : marges et rentabilité, réservées aux managers et à la direction.
    #[Route('/api/finance/margins', name: 'api_finance_margins', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function margins(): JsonResponse
    {
        return $this->json($this->margins->margins());
    }
}
