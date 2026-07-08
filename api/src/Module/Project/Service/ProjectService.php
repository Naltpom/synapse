<?php

declare(strict_types=1);

namespace App\Module\Project\Service;

use App\Module\Core\Exception\NotFoundException;
use App\Module\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du module Projet. Le contrôleur ne fait qu'appeler ces méthodes
 * et rendre le résultat ; les cas d'erreur remontent en exceptions de domaine.
 */
final class ProjectService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        /** @var list<Project> $projects */
        $projects = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->orderBy('p.dueDate', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (Project $p): array => $p->toArray(), $projects);
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        $project = $this->em->find(Project::class, $id);
        if (null === $project) {
            throw new NotFoundException('Projet introuvable.');
        }

        return $project->toArray();
    }
}
