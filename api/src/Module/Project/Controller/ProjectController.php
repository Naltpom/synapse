<?php

declare(strict_types=1);

namespace App\Module\Project\Controller;

use App\Module\Project\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    #[Route('', name: 'projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->projects->list());
    }

    #[Route('/{id}', name: 'projects_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return $this->json($this->projects->get($id));
    }
}
