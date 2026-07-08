<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Core\Entity\AuditLog;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Crm\Enum\OpportunityStage;
use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Project\Entity\Project;
use App\Module\Project\Enum\ProjectHealth;
use App\Module\Staffing\Entity\Consultant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue transverse en lecture seule : le dashboard consomme les données des modules
 * sans passer par leurs API (CQRS allégé — les écritures restent dans chaque module).
 */
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.dashboard_annual_target%')]
        private readonly int $annualTarget,
    ) {
    }

    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $today = new \DateTimeImmutable('today');
        $consultants = $this->loadConsultants();
        $invoices = $this->em->getRepository(Invoice::class)->findAll();
        $opportunities = $this->em->getRepository(Opportunity::class)->findAll();

        return $this->json([
            'hero' => $this->hero($today, $consultants, $invoices, $opportunities),
            'revenueByMonth' => $this->revenueByMonth($today, $invoices),
            'practiceDistribution' => $this->practiceDistribution($today, $consultants),
            'todos' => $this->todos($today, $consultants, $invoices, $opportunities),
            // Extrait du journal d'audit : soumis à la même règle d'accès que /api/audit.
            'recentActivity' => $this->isGranted('ROLE_ADMIN') ? $this->recentActivity() : [],
        ]);
    }

    /** @return list<Consultant> */
    private function loadConsultants(): array
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->getQuery()->getResult();

        return $consultants;
    }

    /** @return list<\DateTimeImmutable> Milieu de chacun des 12 derniers mois. */
    private function lastTwelveMonths(\DateTimeImmutable $today): array
    {
        $months = [];
        for ($i = 11; $i >= 0; --$i) {
            $months[] = $today->modify("first day of -{$i} months")->modify('+14 days');
        }

        return $months;
    }

    /**
     * @param list<Consultant>  $consultants
     * @param list<Invoice>     $invoices
     * @param list<Opportunity> $opportunities
     *
     * @return array<string, mixed>
     */
    private function hero(\DateTimeImmutable $today, array $consultants, array $invoices, array $opportunities): array
    {
        $months = $this->lastTwelveMonths($today);

        // Taux de staffing : moyenne d'allocation, aujourd'hui et au 15 de chaque mois.
        $staffingAt = function (\DateTimeImmutable $date) use ($consultants): int {
            $total = count($consultants);
            if (0 === $total) {
                return 0;
            }
            $sum = 0;
            foreach ($consultants as $consultant) {
                $sum += $consultant->allocationAt($date);
            }

            return (int) round($sum / $total);
        };
        $staffingSeries = array_map($staffingAt, $months);
        $bench = count(array_filter($consultants, static fn (Consultant $c): bool => 0 === $c->allocationAt($today)));

        // CA encaissé : factures payées, cumul annuel + série mensuelle.
        $yearStart = $today->modify('first day of january this year');
        $collectedYtd = 0.0;
        $revenueSeries = array_fill(0, 12, 0.0);
        $overdueSeries = array_fill(0, 12, 0.0);
        $overdueAmount = 0.0;
        $overdueCount = 0;
        foreach ($invoices as $invoice) {
            if (InvoiceStatus::Payee === $invoice->getStatus()) {
                if ($invoice->getIssuedAt() >= $yearStart) {
                    $collectedYtd += (float) $invoice->getAmountHt();
                }
                foreach ($months as $i => $month) {
                    if ($invoice->getIssuedAt()->format('Y-m') === $month->format('Y-m')) {
                        $revenueSeries[$i] += (float) $invoice->getAmountHt();
                    }
                }
            }
            if (InvoiceStatus::EnRetard === $invoice->getStatus()) {
                ++$overdueCount;
                $overdueAmount += (float) $invoice->getAmountHt();
                foreach ($months as $i => $month) {
                    if ($invoice->getIssuedAt()->format('Y-m') === $month->format('Y-m')) {
                        $overdueSeries[$i] += (float) $invoice->getAmountHt();
                    }
                }
            }
        }

        // Pipeline pondéré : montants ouverts, répartis par mois d'échéance (12 prochains mois).
        $weighted = 0.0;
        $openCount = 0;
        $negotiationCount = 0;
        $pipelineSeries = array_fill(0, 12, 0.0);
        foreach ($opportunities as $opportunity) {
            if (!$opportunity->getStage()->isOpen()) {
                continue;
            }
            ++$openCount;
            if (OpportunityStage::Negociation === $opportunity->getStage()) {
                ++$negotiationCount;
            }
            $amount = (float) $opportunity->getAmount() * $opportunity->getProbability() / 100;
            $weighted += $amount;
            $close = $opportunity->getExpectedCloseAt();
            $offset = ((int) $close->format('Y') - (int) $today->format('Y')) * 12
                + ((int) $close->format('n') - (int) $today->format('n'));
            $pipelineSeries[max(0, min(11, $offset))] += $amount;
        }

        return [
            'staffing' => [
                'value' => $staffingAt($today),
                'consultants' => count($consultants),
                'bench' => $bench,
                'deltaPts' => $staffingSeries[11] - $staffingSeries[10],
                'series' => $staffingSeries,
            ],
            'revenue' => [
                'collectedYtd' => round($collectedYtd, 2),
                'annualTarget' => $this->annualTarget,
                'targetPercent' => (int) round($collectedYtd / $this->annualTarget * 100),
                'series' => array_map(static fn (float $v): float => round($v, 2), $revenueSeries),
            ],
            'pipeline' => [
                'weightedAmount' => round($weighted, 2),
                'openCount' => $openCount,
                'negotiationCount' => $negotiationCount,
                'series' => array_map(static fn (float $v): float => round($v, 2), $pipelineSeries),
            ],
            'overdue' => [
                'amount' => round($overdueAmount, 2),
                'count' => $overdueCount,
                'series' => array_map(static fn (float $v): float => round($v, 2), $overdueSeries),
            ],
        ];
    }

    /**
     * Groupement par mois fait en PHP : portable SQLite/PostgreSQL, volumétrie faible.
     *
     * @param list<Invoice> $invoices
     *
     * @return list<array<string, mixed>>
     */
    private function revenueByMonth(\DateTimeImmutable $today, array $invoices): array
    {
        $months = [];
        for ($i = 11; $i >= 0; --$i) {
            $months[$today->modify("first day of -{$i} months")->format('Y-m')] = ['paid' => 0.0, 'pending' => 0.0];
        }

        foreach ($invoices as $invoice) {
            $key = $invoice->getIssuedAt()->format('Y-m');
            if (!array_key_exists($key, $months)) {
                continue;
            }
            match ($invoice->getStatus()) {
                InvoiceStatus::Payee => $months[$key]['paid'] += (float) $invoice->getAmountHt(),
                InvoiceStatus::Envoyee, InvoiceStatus::EnRetard => $months[$key]['pending'] += (float) $invoice->getAmountHt(),
                InvoiceStatus::Brouillon => null,
            };
        }

        $result = [];
        foreach ($months as $month => $amounts) {
            $result[] = [
                'month' => $month,
                'paid' => round($amounts['paid'], 2),
                'pending' => round($amounts['pending'], 2),
            ];
        }

        return $result;
    }

    /**
     * @param list<Consultant> $consultants
     *
     * @return list<array<string, mixed>>
     */
    private function practiceDistribution(\DateTimeImmutable $today, array $consultants): array
    {
        $byPractice = [];
        foreach ($consultants as $consultant) {
            $label = $consultant->getPractice()->label();
            $byPractice[$label] ??= ['practice' => $label, 'consultants' => 0, 'staffed' => 0, 'allocationSum' => 0];
            ++$byPractice[$label]['consultants'];
            $allocation = $consultant->allocationAt($today);
            $byPractice[$label]['allocationSum'] += $allocation;
            if ($allocation > 0) {
                ++$byPractice[$label]['staffed'];
            }
        }
        ksort($byPractice);

        return array_values(array_map(static function (array $row): array {
            $row['occupancyRate'] = (int) round($row['allocationSum'] / max(1, $row['consultants']));
            unset($row['allocationSum']);

            return $row;
        }, $byPractice));
    }

    /**
     * La colonne « À traiter » : une action concrète par signal métier.
     *
     * @param list<Consultant>  $consultants
     * @param list<Invoice>     $invoices
     * @param list<Opportunity> $opportunities
     *
     * @return list<array<string, string>>
     */
    private function todos(\DateTimeImmutable $today, array $consultants, array $invoices, array $opportunities): array
    {
        $todos = [];

        // Plus grosse facture en retard.
        $worst = null;
        foreach ($invoices as $invoice) {
            if (InvoiceStatus::EnRetard === $invoice->getStatus() && (null === $worst || (float) $invoice->getAmountHt() > (float) $worst->getAmountHt())) {
                $worst = $invoice;
            }
        }
        if (null !== $worst) {
            $data = $worst->toArray();
            $days = (int) (new \DateTimeImmutable($data['dueAt']))->diff($today)->format('%a');
            $todos[] = [
                'severity' => 'alert',
                'title' => sprintf('Facture %s · %s', $data['number'], $data['clientName']),
                'subtitle' => sprintf('%s € HT · %d jours de retard', number_format($data['amountHt'], 0, ',', ' '), $days),
                'action' => 'Relancer',
                'target' => 'billing',
            ];
        }

        // Intercontrats.
        $benchList = array_values(array_filter($consultants, static fn (Consultant $c): bool => 0 === $c->allocationAt($today)));
        if ([] !== $benchList) {
            $todos[] = [
                'severity' => 'warn',
                'title' => sprintf('%d consultants en intercontrat', count($benchList)),
                'subtitle' => sprintf('Dont %s (%s)', $benchList[0]->getFullName(), $benchList[0]->getPractice()->label()),
                'action' => 'Voir le staffing',
                'target' => 'staffing',
            ];
        }

        // Projets en météo rouge.
        /** @var list<Project> $redProjects */
        $redProjects = $this->em->getRepository(Project::class)->findBy(['health' => ProjectHealth::Rouge]);
        foreach (array_slice($redProjects, 0, 1) as $project) {
            $data = $project->toArray();
            $todos[] = [
                'severity' => 'warn',
                'title' => sprintf('Projet %s en météo rouge', $data['name']),
                'subtitle' => sprintf('Jalon « %s » · échéance %s', $data['nextMilestone'], (new \DateTimeImmutable($data['dueDate']))->format('d/m/Y')),
                'action' => 'Ouvrir le projet',
                'target' => 'projects',
            ];
        }

        // Opportunité ouverte à l'échéance la plus proche : à relancer.
        $soonest = null;
        foreach ($opportunities as $opportunity) {
            if ($opportunity->getStage()->isOpen()) {
                if (null === $soonest || $opportunity->getExpectedCloseAt() < $soonest->getExpectedCloseAt()) {
                    $soonest = $opportunity;
                }
            }
        }
        if (null !== $soonest) {
            $data = $soonest->toArray();
            $todos[] = [
                'severity' => 'info',
                'title' => sprintf('Opportunité « %s » à relancer', $data['title']),
                'subtitle' => sprintf('%s · %s € · échéance %s', $data['clientName'], number_format($data['amount'], 0, ',', ' '), (new \DateTimeImmutable($data['expectedCloseAt']))->format('d/m/Y')),
                'action' => 'Rappeler le contact',
                'target' => 'crm',
            ];
        }

        // Congés à valider.
        /** @var list<LeaveRequest> $pendingLeaves */
        $pendingLeaves = $this->em->getRepository(LeaveRequest::class)->findBy(['status' => LeaveStatus::PendingApproval]);
        if ([] !== $pendingLeaves) {
            $names = array_map(static fn (LeaveRequest $l): string => $l->toArray()['consultantName'], array_slice($pendingLeaves, 0, 2));
            $todos[] = [
                'severity' => 'info',
                'title' => sprintf('%d demandes de congé à valider', count($pendingLeaves)),
                'subtitle' => implode(' et ', $names),
                'action' => 'Valider',
                'target' => 'leave',
            ];
        }

        return $todos;
    }

    /** @return list<array<string, mixed>> */
    private function recentActivity(): array
    {
        /** @var list<AuditLog> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(4)
            ->getQuery()->getResult();

        return array_map(static fn (AuditLog $a): array => $a->toArray(), $entries);
    }
}
