<?php

declare(strict_types=1);

namespace App\Module\Core\Controller;

use App\Module\Core\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(private readonly SearchService $search)
    {
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        return $this->json(['results' => $this->search->search((string) $request->query->get('q', ''))]);
    }
}
