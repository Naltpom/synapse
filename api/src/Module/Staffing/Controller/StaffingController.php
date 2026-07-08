<?php

declare(strict_types=1);

namespace App\Module\Staffing\Controller;

use App\Module\Staffing\Service\StaffingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/staffing')]
final class StaffingController extends AbstractController
{
    public function __construct(private readonly StaffingService $staffing)
    {
    }

    #[Route('/consultants', name: 'staffing_consultants_list', methods: ['GET'])]
    public function consultants(): JsonResponse
    {
        return $this->json($this->staffing->consultants());
    }

    #[Route('/missions', name: 'staffing_missions_list', methods: ['GET'])]
    public function missions(): JsonResponse
    {
        return $this->json($this->staffing->missions());
    }

    #[Route('/missions/{id}', name: 'staffing_missions_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function mission(int $id): JsonResponse
    {
        return $this->json($this->staffing->mission($id));
    }
}
