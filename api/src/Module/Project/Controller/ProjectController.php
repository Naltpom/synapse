<?php

declare(strict_types=1);

namespace App\Module\Project\Controller;

use App\Module\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'projects_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var list<Project> $projects */
        $projects = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map(static fn (Project $p): array => $p->toArray(), $projects));
    }

    #[Route('/{id}', name: 'projects_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $project = $this->em->find(Project::class, $id);
        if (null === $project) {
            return $this->json(['error' => 'Projet introuvable.'], 404);
        }

        return $this->json($project->toArray());
    }
}
