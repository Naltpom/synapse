<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Core\Entity\AuditLog;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Staffing\Entity\Consultant;
use App\Module\Staffing\Entity\Mission;
use App\Module\Staffing\Enum\MissionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue transverse en lecture seule : le dashboard consomme les données des modules
 * sans passer par leurs API (CQRS allégé — les écritures restent dans chaque module).
 */
final class DashboardController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $today = new \DateTimeImmutable('today');

        return $this->json([
            'staffing' => $this->staffingKpis($today),
            'revenue' => $this->revenueKpis($today),
            'pipeline' => $this->pipelineKpis(),
            'missions' => $this->missionKpis(),
            'revenueByMonth' => $this->revenueByMonth($today),
            'practiceDistribution' => $this->practiceDistribution($today),
            'recentActivity' => $this->recentActivity(),
        ]);
    }

    /** @return array<string, mixed> */
    private function staffingKpis(\DateTimeImmutable $today): array
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->getQuery()->getResult();

        $total = count($consultants);
        $allocationSum = 0;
        $bench = 0;
        foreach ($consultants as $consultant) {
            $allocation = $consultant->allocationAt($today);
            $allocationSum += $allocation;
            if (0 === $allocation) {
                ++$bench;
            }
        }

        return [
            'consultants' => $total,
            'staffingRate' => $total > 0 ? (int) round($allocationSum / $total) : 0,
            'bench' => $bench,
        ];
    }

    /** @return array<string, mixed> */
    private function revenueKpis(\DateTimeImmutable $today): array
    {
        /** @var list<Invoice> $invoices */
        $invoices = $this->em->getRepository(Invoice::class)->findAll();

        $yearStart = $today->modify('first day of january this year');
        $collectedYtd = 0.0;
        $overdueCount = 0;
        $overdueAmount = 0.0;

        foreach ($invoices as $invoice) {
            if (InvoiceStatus::Payee === $invoice->getStatus() && $invoice->getIssuedAt() >= $yearStart) {
                $collectedYtd += (float) $invoice->getAmountHt();
            }
            if (InvoiceStatus::EnRetard === $invoice->getStatus()) {
                ++$overdueCount;
                $overdueAmount += (float) $invoice->getAmountHt();
            }
        }

        return [
            'collectedYtd' => round($collectedYtd, 2),
            'overdueCount' => $overdueCount,
            'overdueAmount' => round($overdueAmount, 2),
        ];
    }

    /** @return array<string, mixed> */
    private function pipelineKpis(): array
    {
        /** @var list<Opportunity> $opportunities */
        $opportunities = $this->em->getRepository(Opportunity::class)->findAll();

        $weighted = 0.0;
        $open = 0;
        foreach ($opportunities as $opportunity) {
            if ($opportunity->getStage()->isOpen()) {
                ++$open;
                $weighted += (float) $opportunity->getAmount() * $opportunity->getProbability() / 100;
            }
        }

        return ['openCount' => $open, 'weightedAmount' => round($weighted, 2)];
    }

    /** @return array<string, mixed> */
    private function missionKpis(): array
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Mission::class, 'm')
            ->where('m.status = :status')
            ->setParameter('status', MissionStatus::EnCours)
            ->getQuery()->getSingleScalarResult();

        return ['active' => (int) $count];
    }

    /**
     * Groupement par mois fait en PHP : portable SQLite/PostgreSQL, volumétrie faible.
     *
     * @return list<array<string, mixed>>
     */
    private function revenueByMonth(\DateTimeImmutable $today): array
    {
        /** @var list<Invoice> $invoices */
        $invoices = $this->em->getRepository(Invoice::class)->findAll();

        $months = [];
        for ($i = 11; $i >= 0; --$i) {
            $months[$today->modify("first day of -{$i} months")->format('Y-m')] = 0.0;
        }

        foreach ($invoices as $invoice) {
            if (InvoiceStatus::Brouillon === $invoice->getStatus()) {
                continue;
            }
            $key = $invoice->getIssuedAt()->format('Y-m');
            if (array_key_exists($key, $months)) {
                $months[$key] += (float) $invoice->getAmountHt();
            }
        }

        $result = [];
        foreach ($months as $month => $amount) {
            $result[] = ['month' => $month, 'amount' => round($amount, 2)];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function practiceDistribution(\DateTimeImmutable $today): array
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->getQuery()->getResult();

        $byPractice = [];
        foreach ($consultants as $consultant) {
            $label = $consultant->getPractice()->label();
            $byPractice[$label] ??= ['practice' => $label, 'consultants' => 0, 'staffed' => 0];
            ++$byPractice[$label]['consultants'];
            if ($consultant->allocationAt($today) > 0) {
                ++$byPractice[$label]['staffed'];
            }
        }
        ksort($byPractice);

        return array_values($byPractice);
    }

    /** @return list<array<string, mixed>> */
    private function recentActivity(): array
    {
        /** @var list<AuditLog> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(8)
            ->getQuery()->getResult();

        return array_map(static fn (AuditLog $a): array => $a->toArray(), $entries);
    }
}
