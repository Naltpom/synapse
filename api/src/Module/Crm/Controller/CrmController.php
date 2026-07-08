<?php

declare(strict_types=1);

namespace App\Module\Crm\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Core\Enum\Practice;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\Contact;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Crm\Enum\ClientStatus;
use App\Module\Crm\Enum\OpportunityStage;
use App\Module\Staffing\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/crm')]
final class CrmController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/clients', name: 'crm_clients_list', methods: ['GET'])]
    public function clients(Request $request): JsonResponse
    {
        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Client::class, 'c')
            ->orderBy('c.name', 'ASC');

        $search = trim((string) $request->query->get('search', ''));
        if ('' !== $search) {
            $qb->where('LOWER(c.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        /** @var list<Client> $clients */
        $clients = $qb->getQuery()->getResult();

        return $this->json(array_map($this->serializeClient(...), $clients));
    }

    #[Route('/clients/{id}', name: 'crm_clients_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function client(int $id): JsonResponse
    {
        $client = $this->em->find(Client::class, $id);
        if (null === $client) {
            return $this->json(['error' => 'Client introuvable.'], 404);
        }

        $data = $this->serializeClient($client);
        $data['contacts'] = array_map(static fn (Contact $c): array => $c->toArray(), $client->getContacts()->toArray());
        $data['opportunities'] = array_map(static fn (Opportunity $o): array => $o->toArray(), $client->getOpportunities()->toArray());

        return $this->json($data);
    }

    /**
     * Vue 360° d'un client : ses données CRM et, en lecture transverse par identifiant,
     * ses missions (Staffing) et ses factures (Billing) — même pattern que le dashboard.
     */
    #[Route('/clients/{id}/overview', name: 'crm_clients_overview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function overview(int $id): JsonResponse
    {
        $client = $this->em->find(Client::class, $id);
        if (null === $client) {
            return $this->json(['error' => 'Client introuvable.'], 404);
        }

        /** @var list<Mission> $missions */
        $missions = $this->em->createQueryBuilder()
            ->select('m', 'a')
            ->from(Mission::class, 'm')
            ->leftJoin('m.assignments', 'a')
            ->where('m.clientId = :id')->setParameter('id', $id)
            ->orderBy('m.startDate', 'DESC')
            ->getQuery()->getResult();

        /** @var list<Invoice> $invoices */
        $invoices = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.clientId = :id')->setParameter('id', $id)
            ->orderBy('i.issuedAt', 'DESC')
            ->getQuery()->getResult();

        $billed = 0.0;
        $paid = 0.0;
        $overdue = 0.0;
        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->getAmountHt();
            if (InvoiceStatus::Brouillon !== $invoice->getStatus()) {
                $billed += $amount;
            }
            if (InvoiceStatus::Payee === $invoice->getStatus()) {
                $paid += $amount;
            }
            if (InvoiceStatus::EnRetard === $invoice->getStatus()) {
                $overdue += $amount;
            }
        }

        $data = $this->serializeClient($client);
        $data['contacts'] = array_map(static fn (Contact $c): array => $c->toArray(), $client->getContacts()->toArray());
        $data['opportunities'] = array_map(static fn (Opportunity $o): array => $o->toArray(), $client->getOpportunities()->toArray());
        $data['missions'] = array_map(static fn (Mission $m): array => $m->toArray(), $missions);
        $data['invoices'] = array_map(static fn (Invoice $i): array => $i->toArray(), $invoices);
        $data['kpis'] = [
            'billedTotal' => round($billed, 2),
            'paidTotal' => round($paid, 2),
            'overdueAmount' => round($overdue, 2),
            'activeMissions' => count(array_filter($missions, static fn (Mission $m): bool => 'en_cours' === $m->getStatus()->value)),
        ];

        return $this->json($data);
    }

    #[Route('/clients', name: 'crm_clients_create', methods: ['POST'])]
    public function createClient(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $name = trim((string) ($payload['name'] ?? ''));
        $sector = trim((string) ($payload['sector'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $status = ClientStatus::tryFrom((string) ($payload['status'] ?? ''));

        $errors = [];
        if ('' === $name) {
            $errors['name'] = 'Le nom est obligatoire.';
        }
        if ('' === $sector) {
            $errors['sector'] = 'Le secteur est obligatoire.';
        }
        if ('' === $city) {
            $errors['city'] = 'La ville est obligatoire.';
        }
        if (null === $status) {
            $errors['status'] = 'Statut invalide (prospect, actif ou inactif).';
        }
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], 422);
        }

        $client = new Client($name, $sector, $city, $status);
        $this->em->persist($client);
        $this->em->flush();

        return $this->json($this->serializeClient($client), 201);
    }

    #[Route('/opportunities', name: 'crm_opportunities_list', methods: ['GET'])]
    public function opportunities(): JsonResponse
    {
        /** @var list<Opportunity> $opportunities */
        $opportunities = $this->em->createQueryBuilder()
            ->select('o', 'c')
            ->from(Opportunity::class, 'o')
            ->join('o.client', 'c')
            ->orderBy('o.expectedCloseAt', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map(static fn (Opportunity $o): array => $o->toArray(), $opportunities));
    }

    #[Route('/opportunities/{id}', name: 'crm_opportunities_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateOpportunity(int $id, Request $request): JsonResponse
    {
        $opportunity = $this->em->find(Opportunity::class, $id);
        if (null === $opportunity) {
            return $this->json(['error' => 'Opportunité introuvable.'], 404);
        }

        $stage = OpportunityStage::tryFrom((string) ($request->toArray()['stage'] ?? ''));
        if (null === $stage) {
            return $this->json(['errors' => ['stage' => 'Étape invalide.']], 422);
        }

        $opportunity->setStage($stage);
        $this->em->flush();

        return $this->json($opportunity->toArray());
    }

    /** @return array<string, mixed> */
    private function serializeClient(Client $client): array
    {
        $pipeline = 0.0;
        foreach ($client->getOpportunities() as $opportunity) {
            if ($opportunity->getStage()->isOpen()) {
                $pipeline += (float) $opportunity->getAmount() * $opportunity->getProbability() / 100;
            }
        }

        return [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'sector' => $client->getSector(),
            'city' => $client->getCity(),
            'status' => $client->getStatus()->value,
            'createdAt' => $client->getCreatedAt()->format('Y-m-d'),
            'contactCount' => $client->getContacts()->count(),
            'opportunityCount' => $client->getOpportunities()->count(),
            'weightedPipeline' => round($pipeline, 2),
        ];
    }
}
