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

        foreach (['hero', 'revenueByMonth', 'practiceDistribution', 'todos', 'recentActivity'] as $key) {
            self::assertArrayHasKey($key, $dashboard);
        }

        $hero = $dashboard['hero'];
        self::assertSame(30, $hero['staffing']['consultants']);
        self::assertSame(4, $hero['staffing']['bench']);
        self::assertCount(12, $hero['staffing']['series']);
        self::assertSame(2500000, $hero['revenue']['annualTarget']);
        self::assertGreaterThan(0, $hero['pipeline']['weightedAmount']);
        self::assertGreaterThanOrEqual(1, $hero['overdue']['count']);

        self::assertCount(12, $dashboard['revenueByMonth']);
        self::assertArrayHasKey('paid', $dashboard['revenueByMonth'][0]);
        self::assertArrayHasKey('pending', $dashboard['revenueByMonth'][0]);

        self::assertCount(6, $dashboard['practiceDistribution']);
        self::assertArrayHasKey('occupancyRate', $dashboard['practiceDistribution'][0]);
    }

    public function testTodosPointToActionableScreens(): void
    {
        $this->login();
        $this->client->request('GET', '/api/dashboard');

        $todos = $this->json()['todos'];
        self::assertNotEmpty($todos);
        $targets = array_column($todos, 'target');
        self::assertContains('billing', $targets, 'Une facture en retard doit produire une action de relance.');
        self::assertContains('staffing', $targets, 'Les intercontrats doivent produire une action staffing.');

        foreach ($todos as $todo) {
            self::assertContains($todo['severity'], ['alert', 'warn', 'info']);
            self::assertNotSame('', $todo['action']);
        }
    }

    public function testRecentActivityIsHiddenFromNonAdmins(): void
    {
        $this->login('commerce@synapse.demo');
        $this->client->request('GET', '/api/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['recentActivity']);
    }
}
