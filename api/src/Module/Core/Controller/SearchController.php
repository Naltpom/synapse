<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Crm\Entity\Client;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche globale de la palette ⌘K : lecture transverse, 3 résultats par type.
 */
final class SearchController extends AbstractController
{
    private const LIMIT_PER_TYPE = 3;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->json(['results' => []]);
        }
        $needle = '%'.mb_strtolower($query).'%';

        $results = [
            ...$this->clients($needle),
            ...$this->consultants($needle),
            ...$this->missions($needle),
            ...$this->invoices($needle),
        ];

        return $this->json(['results' => $results]);
    }

    /** @return list<array<string, mixed>> */
    private function clients(string $needle): array
    {
        /** @var list<Client> $clients */
        $clients = $this->em->createQueryBuilder()
            ->select('c')->from(Client::class, 'c')
            ->where('LOWER(c.name) LIKE :q')->setParameter('q', $needle)
            ->setMaxResults(self::LIMIT_PER_TYPE)
            ->getQuery()->getResult();

        return array_map(static fn (Client $c): array => [
            'type' => 'client',
            'id' => $c->getId(),
            'title' => $c->getName(),
            'subtitle' => $c->getSector().' · '.$c->getCity(),
            'target' => 'client',
        ], $clients);
    }

    /** @return list<array<string, mixed>> */
    private function consultants(string $needle): array
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c')->from(Consultant::class, 'c')
            ->where('LOWER(c.firstName) LIKE :q OR LOWER(c.lastName) LIKE :q')->setParameter('q', $needle)
            ->setMaxResults(self::LIMIT_PER_TYPE)
            ->getQuery()->getResult();

        return array_map(static fn (Consultant $c): array => [
            'type' => 'consultant',
            'id' => $c->getId(),
            'title' => $c->getFullName(),
            'subtitle' => $c->getPractice()->label(),
            'target' => 'staffing',
        ], $consultants);
    }

    /** @return list<array<string, mixed>> */
    private function missions(string $needle): array
    {
        /** @var list<Mission> $missions */
        $missions = $this->em->createQueryBuilder()
            ->select('m')->from(Mission::class, 'm')
            ->where('LOWER(m.title) LIKE :q OR LOWER(m.clientName) LIKE :q')->setParameter('q', $needle)
            ->setMaxResults(self::LIMIT_PER_TYPE)
            ->getQuery()->getResult();

        return array_map(static fn (Mission $m): array => [
            'type' => 'mission',
            'id' => $m->getId(),
            'title' => $m->getTitle(),
            'subtitle' => $m->getClientName(),
            'target' => 'staffing',
        ], $missions);
    }

    /** @return list<array<string, mixed>> */
    private function invoices(string $needle): array
    {
        /** @var list<Invoice> $invoices */
        $invoices = $this->em->createQueryBuilder()
            ->select('i')->from(Invoice::class, 'i')
            ->where('LOWER(i.number) LIKE :q OR LOWER(i.clientName) LIKE :q')->setParameter('q', $needle)
            ->orderBy('i.issuedAt', 'DESC')
            ->setMaxResults(self::LIMIT_PER_TYPE)
            ->getQuery()->getResult();

        return array_map(static function (Invoice $i): array {
            $data = $i->toArray();

            return [
                'type' => 'invoice',
                'id' => $data['id'],
                'title' => $data['number'],
                'subtitle' => $data['clientName'].' · '.number_format($data['amountHt'], 0, ',', ' ').' € HT',
                'target' => 'billing',
            ];
        }, $invoices);
    }
}
