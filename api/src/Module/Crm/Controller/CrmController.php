<?php

declare(strict_types=1);

namespace App\Module\Crm\Controller;

use App\Module\Crm\Service\ClientService;
use App\Module\Crm\Service\OpportunityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm')]
final class CrmController extends AbstractController
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly OpportunityService $opportunities,
    ) {
    }

    #[Route('/clients', name: 'crm_clients_list', methods: ['GET'])]
    public function clients(Request $request): JsonResponse
    {
        return $this->json($this->clients->list($request->query->get('search')));
    }

    #[Route('/clients/{id}', name: 'crm_clients_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function client(int $id): JsonResponse
    {
        return $this->json($this->clients->get($id));
    }

    #[Route('/clients/{id}/overview', name: 'crm_clients_overview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function overview(int $id): JsonResponse
    {
        return $this->json($this->clients->overview($id));
    }

    #[Route('/clients', name: 'crm_clients_create', methods: ['POST'])]
    public function createClient(Request $request): JsonResponse
    {
        return $this->json($this->clients->create($request->toArray()), 201);
    }

    #[Route('/opportunities', name: 'crm_opportunities_list', methods: ['GET'])]
    public function opportunities(): JsonResponse
    {
        return $this->json($this->opportunities->list());
    }

    #[Route('/opportunities/{id}', name: 'crm_opportunities_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateOpportunity(int $id, Request $request): JsonResponse
    {
        return $this->json($this->opportunities->updateStage($id, $request->toArray()['stage'] ?? null));
    }
}
