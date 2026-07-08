<?php

declare(strict_types=1);

namespace App\Tests\Module\Hr;

use App\Tests\ApiTestCase;

final class HrTest extends ApiTestCase
{
    public function testLeavesRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/hr/leaves');

        self::assertResponseStatusCodeSame(401);
    }

    public function testPendingLeavesAreListedWithProvenance(): void
    {
        $this->login();
        $this->client->request('GET', '/api/hr/leaves?status=pending_approval');

        self::assertResponseIsSuccessful();
        $leaves = $this->json();
        self::assertGreaterThanOrEqual(2, count($leaves));

        $sources = array_column($leaves, 'source');
        self::assertContains('mcp', $sources, 'La demande créée via assistant doit porter sa provenance.');
    }

    public function testCreateValidatesPayload(): void
    {
        $this->login();
        $this->client->jsonRequest('POST', '/api/hr/leaves', [
            'consultantId' => 999999,
            'type' => 'sabbatique',
            'startDate' => 'pas-une-date',
            'endDate' => '2026-01-01',
        ]);

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json()['errors'];
        self::assertArrayHasKey('consultantId', $errors);
        self::assertArrayHasKey('type', $errors);
        self::assertArrayHasKey('startDate', $errors);
    }

    public function testCreateIsAudited(): void
    {
        $this->login();
        $consultantId = $this->firstCalendarConsultantId();

        $this->client->jsonRequest('POST', '/api/hr/leaves', [
            'consultantId' => $consultantId,
            'type' => 'rtt',
            'startDate' => '2026-08-03',
            'endDate' => '2026-08-04',
        ]);

        self::assertResponseStatusCodeSame(201);
        $leave = $this->json();
        self::assertSame('pending_approval', $leave['status']);
        self::assertSame(2, $leave['days']);

        $this->client->request('GET', '/api/audit');
        $match = array_filter(
            $this->json(),
            static fn (array $entry): bool => 'create' === $entry['action']
                && 'LeaveRequest' === $entry['subjectType']
                && (string) $leave['id'] === $entry['subjectId'],
        );
        self::assertNotEmpty($match, 'La création de congé doit apparaître au journal d\'audit.');
    }

    public function testCreationIsReservedToManagers(): void
    {
        // Anti-usurpation : sans lien User↔Consultant, un simple utilisateur ne peut pas
        // poser un congé pour le compte d'un consultant arbitraire.
        $this->login('commerce@synapse.demo');
        $this->client->jsonRequest('POST', '/api/hr/leaves', [
            'consultantId' => 1,
            'type' => 'rtt',
            'startDate' => '2026-09-07',
            'endDate' => '2026-09-07',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testProvenanceCannotBeSpoofedByClient(): void
    {
        $this->login();
        $consultantId = $this->firstCalendarConsultantId();

        // Le client tente de se déclarer « mcp » : l'API force 'app'.
        $this->client->jsonRequest('POST', '/api/hr/leaves', [
            'consultantId' => $consultantId,
            'type' => 'rtt',
            'startDate' => '2026-09-14',
            'endDate' => '2026-09-14',
            'source' => 'mcp',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('app', $this->json()['source']);
    }

    public function testApprovalIsReservedToManagers(): void
    {
        $this->login('commerce@synapse.demo');
        $this->client->request('GET', '/api/hr/leaves?status=pending_approval');
        $pending = $this->json();
        self::assertNotEmpty($pending);

        $this->client->jsonRequest('POST', "/api/hr/leaves/{$pending[0]['id']}/approve", []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testManagerCanApproveAndDecisionIsAudited(): void
    {
        $this->login('staffing@synapse.demo');
        $this->client->request('GET', '/api/hr/leaves?status=pending_approval');
        $pending = $this->json();
        self::assertNotEmpty($pending);
        $id = $pending[0]['id'];

        $this->client->jsonRequest('POST', "/api/hr/leaves/{$id}/approve", []);

        self::assertResponseIsSuccessful();
        $leave = $this->json();
        self::assertSame('approved', $leave['status']);
        self::assertSame('staffing@synapse.demo', $leave['decidedBy']);

        // Une demande déjà traitée ne peut pas être revalidée.
        $this->client->jsonRequest('POST', "/api/hr/leaves/{$id}/approve", []);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRejectionWorks(): void
    {
        $this->login('staffing@synapse.demo');
        $this->client->request('GET', '/api/hr/leaves?status=pending_approval');
        $pending = $this->json();
        self::assertNotEmpty($pending);

        $this->client->jsonRequest('POST', "/api/hr/leaves/{$pending[0]['id']}/reject", []);

        self::assertResponseIsSuccessful();
        self::assertSame('rejected', $this->json()['status']);
    }

    public function testCalendarShape(): void
    {
        $this->login();
        $this->client->request('GET', '/api/hr/calendar');

        self::assertResponseIsSuccessful();
        $calendar = $this->json();

        self::assertCount(10, $calendar['days']);
        self::assertCount(8, $calendar['rows']);
        foreach ($calendar['rows'] as $row) {
            self::assertCount(10, $row['cells']);
            foreach ($row['cells'] as $cell) {
                self::assertContains($cell['state'], ['mission', 'conge_valide', 'conge_attente', 'weekend', 'dispo']);
            }
        }
    }

    private function firstCalendarConsultantId(): int
    {
        $this->client->request('GET', '/api/staffing/consultants');

        return (int) $this->json()[0]['id'];
    }
}
