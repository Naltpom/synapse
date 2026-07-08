<?php

declare(strict_types=1);

namespace App\Module\Crm\Service;

use App\Module\Core\Exception\NotFoundException;
use App\Module\Core\Exception\ValidationException;
use App\Module\Crm\Entity\Opportunity;
use App\Module\Crm\Enum\OpportunityStage;
use Doctrine\ORM\EntityManagerInterface;

final class OpportunityService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        /** @var list<Opportunity> $opportunities */
        $opportunities = $this->em->createQueryBuilder()
            ->select('o', 'c')
            ->from(Opportunity::class, 'o')
            ->join('o.client', 'c')
            ->orderBy('o.expectedCloseAt', 'ASC')
            ->getQuery()->getResult();

        return array_map(static fn (Opportunity $o): array => $o->toArray(), $opportunities);
    }

    /** @return array<string, mixed> */
    public function updateStage(int $id, ?string $stage): array
    {
        $opportunity = $this->em->find(Opportunity::class, $id);
        if (null === $opportunity) {
            throw new NotFoundException('Opportunité introuvable.');
        }

        $target = OpportunityStage::tryFrom((string) $stage);
        if (null === $target) {
            throw new ValidationException(['stage' => 'Étape invalide.']);
        }

        $opportunity->setStage($target);
        $this->em->flush();

        return $opportunity->toArray();
    }
}
