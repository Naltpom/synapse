<?php

declare(strict_types=1);

namespace App\Module\Timesheet\Controller;

use App\Module\Timesheet\Service\TimesheetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

// Tout /api/cra est réservé aux managers/direction (access_control) : saisie
// back-office pour le compte des consultants, tant qu'il n'y a pas de lien User↔Consultant.
#[Route('/api/cra')]
final class TimesheetController extends AbstractController
{
    public function __construct(private readonly TimesheetService $timesheets)
    {
    }

    #[Route('', name: 'cra_grid', methods: ['GET'])]
    public function grid(Request $request): JsonResponse
    {
        return $this->json($this->timesheets->grid(
            (int) $request->query->get('consultantId', 0),
            (string) $request->query->get('week', 'today'),
        ));
    }

    #[Route('/entries', name: 'cra_entry_upsert', methods: ['PUT'])]
    public function upsert(Request $request): JsonResponse
    {
        $this->timesheets->upsertEntry($request->toArray());

        return $this->json(['ok' => true]);
    }

    #[Route('/submit', name: 'cra_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        return $this->json($this->timesheets->submit($request->toArray()));
    }

    #[Route('/validate', name: 'cra_validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        return $this->json($this->timesheets->validateWeek($request->toArray()));
    }
}
