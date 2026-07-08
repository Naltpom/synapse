<?php

declare(strict_types=1);

namespace App\Module\Hr\Service;

use App\Module\Core\Exception\ConflictException;
use App\Module\Core\Exception\NotFoundException;
use App\Module\Core\Exception\ValidationException;
use App\Module\Hr\Entity\LeaveRequest;
use App\Module\Hr\Enum\LeaveStatus;
use App\Module\Hr\Enum\LeaveType;
use App\Module\Staffing\Entity\Consultant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class LeaveService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $status): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(LeaveRequest::class, 'l')
            ->orderBy('l.createdAt', 'DESC');

        $filter = LeaveStatus::tryFrom((string) $status);
        if (null !== $filter) {
            $qb->where('l.status = :status')->setParameter('status', $filter);
        }

        /** @var list<LeaveRequest> $leaves */
        $leaves = $qb->getQuery()->getResult();

        return array_map(static fn (LeaveRequest $l): array => $l->toArray(), $leaves);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $consultantId = (int) ($payload['consultantId'] ?? 0);
        $type = LeaveType::tryFrom((string) ($payload['type'] ?? ''));
        $startDate = $this->parseDate((string) ($payload['startDate'] ?? ''));
        $endDate = $this->parseDate((string) ($payload['endDate'] ?? ''));

        $errors = [];
        $consultant = $consultantId > 0 ? $this->em->find(Consultant::class, $consultantId) : null;
        if (null === $consultant) {
            $errors['consultantId'] = 'Consultant introuvable.';
        }
        if (null === $type) {
            $errors['type'] = 'Type invalide (conge_paye, rtt ou teletravail).';
        }
        if (null === $startDate) {
            $errors['startDate'] = 'Date de début invalide (AAAA-MM-JJ).';
        }
        if (null === $endDate) {
            $errors['endDate'] = 'Date de fin invalide (AAAA-MM-JJ).';
        }
        if (null !== $startDate && null !== $endDate && $endDate < $startDate) {
            $errors['endDate'] = 'La fin doit être postérieure ou égale au début.';
        }
        if ([] !== $errors) {
            throw new ValidationException($errors);
        }
        \assert(null !== $consultant && null !== $type && null !== $startDate && null !== $endDate);

        $days = isset($payload['days']) ? max(1, (int) $payload['days']) : $this->businessDays($startDate, $endDate);

        // La provenance n'est jamais dictée par le client : une saisie via l'API est 'app'.
        // La provenance 'mcp' n'est posée que par le backend assistant (ici, les fixtures).
        $leave = new LeaveRequest($consultantId, $consultant->getFullName(), $type, $startDate, $endDate, $days, 'app');
        $this->em->persist($leave);
        $this->em->flush();

        return $leave->toArray();
    }

    /** @return array<string, mixed> */
    public function decide(int $id, bool $approve): array
    {
        $leave = $this->em->find(LeaveRequest::class, $id);
        if (null === $leave) {
            throw new NotFoundException('Demande introuvable.');
        }
        if (LeaveStatus::PendingApproval !== $leave->getStatus()) {
            throw new ConflictException('Cette demande a déjà été traitée.');
        }

        $decidedBy = $this->security->getUser()?->getUserIdentifier() ?? 'inconnu';
        $approve ? $leave->approve($decidedBy) : $leave->reject($decidedBy);
        $this->em->flush();

        return $leave->toArray();
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null;
    }

    private function businessDays(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        $days = 0;
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') < 6) {
                ++$days;
            }
        }

        return max(1, $days);
    }
}
