<?php

declare(strict_types=1);

namespace App\Module\Billing\Controller;

use App\Module\Billing\Service\BillingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/billing')]
final class BillingController extends AbstractController
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    #[Route('/invoices', name: 'billing_invoices_list', methods: ['GET'])]
    public function invoices(Request $request): JsonResponse
    {
        return $this->json($this->billing->invoices($request->query->get('status')));
    }
}
