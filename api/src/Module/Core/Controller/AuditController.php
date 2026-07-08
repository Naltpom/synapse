<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AuditController extends AbstractController
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    #[Route('/api/audit', name: 'audit_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->audit->recent());
    }
}
