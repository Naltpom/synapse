<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class DashboardTest extends ApiTestCase
{
    public function testDashboardAggregatesAllModules(): void
    {
        $this->login();
        $this->client->request('GET', '/api/dashboard');

        self::assertResponseIsSuccessful();
        $dashboard = $this->json();

        foreach (['staffing', 'revenue', 'pipeline', 'missions', 'revenueByMonth', 'practiceDistribution', 'recentActivity'] as $key) {
            self::assertArrayHasKey($key, $dashboard);
        }

        self::assertSame(28, $dashboard['staffing']['consultants']);
        self::assertCount(12, $dashboard['revenueByMonth']);
        self::assertGreaterThan(0, $dashboard['pipeline']['weightedAmount']);
        self::assertCount(5, $dashboard['practiceDistribution']);
    }
}
