<?php

declare(strict_types=1);

namespace App\Module\Crm\Service;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Core\Exception\NotFoundException;
use App\Module\Core\Exception\ValidationException;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\Contact;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Crm\Enum\ClientStatus;
use App\Module\Staffing\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;

final class ClientService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $search): array
    {
        // Fetch-join contacts et opportunités : la sérialisation les parcourt pour
        // chaque client — sans join on émettrait 2-3 requêtes SQL par ligne (N+1).
        $qb = $this->em->createQueryBuilder()
            ->select('c', 'ct', 'o')
            ->from(Client::class, 'c')
            ->leftJoin('c.contacts', 'ct')
            ->leftJoin('c.opportunities', 'o')
            ->orderBy('c.name', 'ASC');

        $needle = trim((string) $search);
        if ('' !== $needle) {
            $qb->where('LOWER(c.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($needle).'%');
        }

        /** @var list<Client> $clients */
        $clients = $qb->getQuery()->getResult();

        return array_map($this->serialize(...), $clients);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $client = $this->find($id);

        $data = $this->serialize($client);
        $data['contacts'] = array_map(static fn (Contact $c): array => $c->toArray(), $client->getContacts()->toArray());
        $data['opportunities'] = array_map(static fn (Opportunity $o): array => $o->toArray(), $client->getOpportunities()->toArray());

        return $data;
    }

    /**
     * Vue 360° : données CRM + missions (Staffing) et factures (Billing) lues par
     * identifiant — lecture transverse, sans relation Doctrine entre modules.
     *
     * @return array<string, mixed>
     */
    public function overview(int $id): array
    {
        $this->find($id); // 404 si le client n'existe pas, avant toute autre requête.

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

        $data = $this->get($id);
        $data['missions'] = array_map(static fn (Mission $m): array => $m->toArray(), $missions);
        $data['invoices'] = array_map(static fn (Invoice $i): array => $i->toArray(), $invoices);
        $data['kpis'] = [
            'billedTotal' => round($billed, 2),
            'paidTotal' => round($paid, 2),
            'overdueAmount' => round($overdue, 2),
            'activeMissions' => count(array_filter($missions, static fn (Mission $m): bool => 'en_cours' === $m->getStatus()->value)),
        ];

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
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
            throw new ValidationException($errors);
        }
        \assert(null !== $status);

        $client = new Client($name, $sector, $city, $status);
        $this->em->persist($client);
        $this->em->flush();

        return $this->serialize($client);
    }

    private function find(int $id): Client
    {
        $client = $this->em->find(Client::class, $id);
        if (null === $client) {
            throw new NotFoundException('Client introuvable.');
        }

        return $client;
    }

    /** @return array<string, mixed> */
    private function serialize(Client $client): array
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
