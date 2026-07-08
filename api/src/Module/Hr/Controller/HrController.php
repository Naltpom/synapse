<?php

declare(strict_types=1);

namespace App\Module\Hr\Controller;

use App\Module\Hr\Service\LeaveCalendarService;
use App\Module\Hr\Service\LeaveService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/hr')]
final class HrController extends AbstractController
{
    public function __construct(
        private readonly LeaveService $leaves,
        private readonly LeaveCalendarService $calendar,
    ) {
    }

    #[Route('/leaves', name: 'hr_leaves_list', methods: ['GET'])]
    public function leaves(Request $request): JsonResponse
    {
        return $this->json($this->leaves->list($request->query->get('status')));
    }

    // Sans lien User↔Consultant, poser un congé pour le compte d'un consultant
    // arbitraire est une fonction back-office réservée aux managers (comme le CRA).
    #[Route('/leaves', name: 'hr_leaves_create', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function create(Request $request): JsonResponse
    {
        return $this->json($this->leaves->create($request->toArray()), 201);
    }

    #[Route('/leaves/{id}/approve', name: 'hr_leaves_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function approve(int $id): JsonResponse
    {
        return $this->json($this->leaves->decide($id, approve: true));
    }

    #[Route('/leaves/{id}/reject', name: 'hr_leaves_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function reject(int $id): JsonResponse
    {
        return $this->json($this->leaves->decide($id, approve: false));
    }

    #[Route('/calendar', name: 'hr_calendar', methods: ['GET'])]
    public function calendar(): JsonResponse
    {
        return $this->json($this->calendar->build());
    }
}
