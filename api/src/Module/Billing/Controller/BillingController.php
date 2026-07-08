<?php

declare(strict_types=1);

namespace App\Module\Billing\Controller;

use App\Module\Billing\Entity\Invoice;
use App\Module\Billing\Enum\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/billing')]
final class BillingController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/invoices', name: 'billing_invoices_list', methods: ['GET'])]
    public function invoices(Request $request): JsonResponse
    {
        $qb = $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invoice::class, 'i')
            ->orderBy('i.issuedAt', 'DESC');

        $status = InvoiceStatus::tryFrom((string) $request->query->get('status', ''));
        if (null !== $status) {
            $qb->where('i.status = :status')->setParameter('status', $status);
        }

        /** @var list<Invoice> $invoices */
        $invoices = $qb->getQuery()->getResult();

        $totals = ['count' => count($invoices), 'amountHt' => 0.0];
        foreach ($invoices as $invoice) {
            $totals['amountHt'] += (float) $invoice->getAmountHt();
        }
        $totals['amountHt'] = round($totals['amountHt'], 2);

        return $this->json([
            'totals' => $totals,
            'items' => array_map(static fn (Invoice $i): array => $i->toArray(), $invoices),
        ]);
    }
}
