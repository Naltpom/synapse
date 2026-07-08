<?php

declare(strict_types=1);

namespace App\Module\Billing\Service;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;

final class BillingService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Liste des factures, filtrable par statut, avec l'agrégat des montants.
     *
     * @return array{totals: array{count: int, amountHt: float}, items: list<array<string, mixed>>}
     */
    public function invoices(?string $status): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invoice::class, 'i')
            ->orderBy('i.issuedAt', 'DESC');

        $filter = InvoiceStatus::tryFrom((string) $status);
        if (null !== $filter) {
            $qb->where('i.status = :status')->setParameter('status', $filter);
        }

        /** @var list<Invoice> $invoices */
        $invoices = $qb->getQuery()->getResult();

        $amountHt = 0.0;
        foreach ($invoices as $invoice) {
            $amountHt += (float) $invoice->getAmountHt();
        }

        return [
            'totals' => ['count' => count($invoices), 'amountHt' => round($amountHt, 2)],
            'items' => array_map(static fn (Invoice $i): array => $i->toArray(), $invoices),
        ];
    }
}
