<?php

declare(strict_types=1);

namespace App\Module\Core\Service;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Staffing\Entity\Mission;
use App\Module\Staffing\Enum\MissionStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Marge par mission et par client. Coût estimé = jours ouvrés écoulés de chaque
 * affectation × allocation × coût jour chargé. Approximation de pilotage assumée
 * (le réel viendra des CRA validés) — la méthode est affichée à l'écran.
 */
final class MarginService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function margins(): array
    {
        $today = new \DateTimeImmutable('today');

        /** @var list<Mission> $missions */
        $missions = $this->em->createQueryBuilder()
            ->select('m', 'a', 'c')
            ->from(Mission::class, 'm')
            ->leftJoin('m.assignments', 'a')
            ->leftJoin('a.consultant', 'c')
            ->where('m.status != :upcoming')
            ->setParameter('upcoming', MissionStatus::AVenir)
            ->getQuery()->getResult();

        /** @var list<Invoice> $invoices */
        $invoices = $this->em->getRepository(Invoice::class)->findAll();
        $revenueByMission = [];
        foreach ($invoices as $invoice) {
            $data = $invoice->toArray();
            if (null === $data['missionId'] || InvoiceStatus::Brouillon === $invoice->getStatus()) {
                continue;
            }
            $revenueByMission[$data['missionId']] = ($revenueByMission[$data['missionId']] ?? 0.0) + $data['amountHt'];
        }

        $rows = [];
        $byClient = [];
        foreach ($missions as $mission) {
            $revenue = $revenueByMission[$mission->getId()] ?? 0.0;
            $cost = 0.0;
            foreach ($mission->getAssignments() as $assignment) {
                $cost += $this->assignmentCost($assignment->toArray(), $assignment->getConsultant()->getCostRate(), $today);
            }
            if ($revenue <= 0 && $cost <= 0) {
                continue;
            }

            $margin = $revenue - $cost;
            $data = $mission->toArray();
            $rows[] = [
                'id' => $data['id'],
                'title' => $data['title'],
                'clientName' => $data['clientName'],
                'practiceLabel' => $data['practiceLabel'],
                'status' => $data['status'],
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'margin' => round($margin, 2),
                'marginRate' => $revenue > 0 ? (int) round($margin / $revenue * 100) : null,
            ];

            $byClient[$data['clientName']] ??= ['clientName' => $data['clientName'], 'revenue' => 0.0, 'cost' => 0.0, 'margin' => 0.0];
            $byClient[$data['clientName']]['revenue'] += $revenue;
            $byClient[$data['clientName']]['cost'] += $cost;
            $byClient[$data['clientName']]['margin'] += $margin;
        }

        usort($rows, static fn (array $a, array $b): int => $b['margin'] <=> $a['margin']);
        $clients = array_values(array_map(static function (array $c): array {
            $c['revenue'] = round($c['revenue'], 2);
            $c['cost'] = round($c['cost'], 2);
            $c['margin'] = round($c['margin'], 2);
            $c['marginRate'] = $c['revenue'] > 0 ? (int) round($c['margin'] / $c['revenue'] * 100) : null;

            return $c;
        }, $byClient));
        usort($clients, static fn (array $a, array $b): int => $b['margin'] <=> $a['margin']);

        $totalRevenue = array_sum(array_column($rows, 'revenue'));
        $totalCost = array_sum(array_column($rows, 'cost'));

        return [
            'totals' => [
                'revenue' => round($totalRevenue, 2),
                'cost' => round($totalCost, 2),
                'margin' => round($totalRevenue - $totalCost, 2),
                'marginRate' => $totalRevenue > 0 ? (int) round(($totalRevenue - $totalCost) / $totalRevenue * 100) : null,
            ],
            'missions' => $rows,
            'clients' => $clients,
        ];
    }

    /** @param array<string, mixed> $assignment */
    private function assignmentCost(array $assignment, int $costRate, \DateTimeImmutable $today): float
    {
        $start = new \DateTimeImmutable($assignment['startDate']);
        $end = min(new \DateTimeImmutable($assignment['endDate']), $today);
        if ($end < $start) {
            return 0.0;
        }

        $businessDays = 0;
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') < 6) {
                ++$businessDays;
            }
        }

        return $businessDays * ($assignment['allocation'] / 100) * $costRate;
    }
}
