<?php

declare(strict_types=1);

namespace App\Tests\Module\Crm;

use App\Tests\ApiTestCase;

final class ClientOverviewTest extends ApiTestCase
{
    public function testOverviewAggregatesCrossModuleData(): void
    {
        $this->login();
        $this->client->request('GET', '/api/crm/clients');
        $clients = $this->json();
        // Un client actif a nécessairement des missions et des factures en fixtures.
        $active = array_values(array_filter($clients, static fn (array $c): bool => 'actif' === $c['status']));
        self::assertNotEmpty($active);

        $this->client->request('GET', "/api/crm/clients/{$active[0]['id']}/overview");

        self::assertResponseIsSuccessful();
        $overview = $this->json();

        foreach (['contacts', 'opportunities', 'missions', 'invoices', 'kpis'] as $key) {
            self::assertArrayHasKey($key, $overview);
        }
        self::assertArrayHasKey('billedTotal', $overview['kpis']);
        self::assertGreaterThanOrEqual($overview['kpis']['paidTotal'], $overview['kpis']['billedTotal']);
    }

    public function testOverviewReturns404ForUnknownClient(): void
    {
        $this->login();
        $this->client->request('GET', '/api/crm/clients/999999/overview');

        self::assertResponseStatusCodeSame(404);
    }
}
