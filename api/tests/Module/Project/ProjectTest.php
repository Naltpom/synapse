<?php

declare(strict_types=1);

namespace App\Tests\Module\Project;

use App\Tests\ApiTestCase;

final class ProjectTest extends ApiTestCase
{
    public function testProjectsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/projects');

        self::assertResponseStatusCodeSame(401);
    }

    public function testProjectsAreListedAndSortedByDueDate(): void
    {
        $this->login();
        $this->client->request('GET', '/api/projects');

        self::assertResponseIsSuccessful();
        $projects = $this->json();
        self::assertNotEmpty($projects);

        $dueDates = array_column($projects, 'dueDate');
        $sorted = $dueDates;
        sort($sorted);
        self::assertSame($sorted, $dueDates, 'Les projets doivent être triés par échéance croissante.');

        // Les fixtures contiennent au moins un projet en météo rouge (todo dashboard).
        self::assertContains('rouge', array_column($projects, 'health'));
    }

    public function testShowReturnsProjectOr404(): void
    {
        $this->login();
        $this->client->request('GET', '/api/projects');
        $first = $this->json()[0];

        $this->client->request('GET', "/api/projects/{$first['id']}");
        self::assertResponseIsSuccessful();
        self::assertSame($first['id'], $this->json()['id']);

        $this->client->request('GET', '/api/projects/999999');
        self::assertResponseStatusCodeSame(404);
    }
}
