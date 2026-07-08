<?php

declare(strict_types=1);

namespace App\Tests\Module\Crm;

use App\Tests\ApiTestCase;

final class CrmTest extends ApiTestCase
{
    public function testClientsAreListed(): void
    {
        $this->login();
        $this->client->request('GET', '/api/crm/clients');

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(14, count($this->json()));
    }

    public function testClientCreationValidatesPayload(): void
    {
        $this->login();
        $this->client->jsonRequest('POST', '/api/crm/clients', ['name' => '', 'sector' => '', 'city' => '', 'status' => 'invalide']);

        self::assertResponseStatusCodeSame(422);
        $errors = $this->json()['errors'];
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('status', $errors);
    }

    public function testClientCreationIsAudited(): void
    {
        $this->login();
        $this->client->jsonRequest('POST', '/api/crm/clients', [
            'name' => 'Nouvelle Industrie Test',
            'sector' => 'Industrie',
            'city' => 'Nantes',
            'status' => 'prospect',
        ]);
        self::assertResponseStatusCodeSame(201);
        $clientId = (string) $this->json()['id'];

        $this->client->request('GET', '/api/audit');
        $entries = $this->json();

        $match = array_filter(
            $entries,
            static fn (array $entry): bool => 'create' === $entry['action']
                && 'Client' === $entry['subjectType']
                && $clientId === $entry['subjectId']
                && 'direction@synapse.demo' === $entry['actor'],
        );
        self::assertNotEmpty($match, 'La création du client doit apparaître dans le journal d\'audit.');
    }

    public function testOpportunityStageUpdateRecordsDiff(): void
    {
        $this->login();
        $this->client->request('GET', '/api/crm/opportunities');
        $opportunity = $this->json()[0];

        $newStage = 'negociation' === $opportunity['stage'] ? 'proposition' : 'negociation';
        $this->client->jsonRequest('PATCH', "/api/crm/opportunities/{$opportunity['id']}", ['stage' => $newStage]);

        self::assertResponseIsSuccessful();
        self::assertSame($newStage, $this->json()['stage']);

        $this->client->request('GET', '/api/audit');
        $diffs = array_filter(
            $this->json(),
            static fn (array $entry): bool => 'update' === $entry['action'] && null !== $entry['changes'] && isset($entry['changes']['stage']),
        );
        self::assertNotEmpty($diffs, 'Le changement d\'étape doit être journalisé avec son diff.');
    }
}
