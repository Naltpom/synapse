<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Staffing\Entity\Consultant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Compteurs des badges de navigation — lecture transverse légère,
 * même règle que le dashboard : jamais d'écriture hors du module d'origine.
 */
final class NavCountersController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/nav-counters', name: 'api_nav_counters', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'staffingBench' => $this->benchCount(),
            'billingOverdue' => $this->overdueCount(),
            'hrPending' => $this->pendingLeaveCount(),
        ]);
    }

    private function pendingLeaveCount(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(LeaveRequest::class, 'l')
            ->where('l.status = :status')
            ->setParameter('status', LeaveStatus::PendingApproval)
            ->getQuery()->getSingleScalarResult();
    }

    private function benchCount(): int
    {
        /** @var list<Consultant> $consultants */
        $consultants = $this->em->createQueryBuilder()
            ->select('c', 'a')
            ->from(Consultant::class, 'c')
            ->leftJoin('c.assignments', 'a')
            ->getQuery()->getResult();

        $today = new \DateTimeImmutable('today');
        $bench = 0;
        foreach ($consultants as $consultant) {
            if (0 === $consultant->allocationAt($today)) {
                ++$bench;
            }
        }

        return $bench;
    }

    private function overdueCount(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Invoice::class, 'i')
            ->where('i.status = :status')
            ->setParameter('status', InvoiceStatus::EnRetard)
            ->getQuery()->getSingleScalarResult();
    }
}
