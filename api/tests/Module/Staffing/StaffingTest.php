<?php

declare(strict_types=1);

namespace App\Tests\Module\Staffing;

use App\Tests\ApiTestCase;

final class StaffingTest extends ApiTestCase
{
    public function testConsultantsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/staffing/consultants');

        self::assertResponseStatusCodeSame(401);
    }

    public function testConsultantsExposeAllocationAndActiveMissions(): void
    {
        $this->login();
        $this->client->request('GET', '/api/staffing/consultants');

        self::assertResponseIsSuccessful();
        $consultants = $this->json();
        self::assertCount(30, $consultants);

        $bench = array_filter($consultants, static fn (array $c): bool => 0 === $c['allocation']);
        self::assertCount(4, $bench, 'Quatre consultants sont en intercontrat dans les fixtures.');

        foreach ($consultants as $consultant) {
            self::assertArrayHasKey('activeMissions', $consultant);
            // Un consultant staffé a au moins une mission active ; un intercontrat n'en a aucune.
            if (0 === $consultant['allocation']) {
                self::assertSame([], $consultant['activeMissions']);
            }
        }
    }

    public function testMissionsAreListed(): void
    {
        $this->login();
        $this->client->request('GET', '/api/staffing/missions');

        self::assertResponseIsSuccessful();
        $missions = $this->json();
        self::assertNotEmpty($missions);
        self::assertArrayHasKey('teamSize', $missions[0]);
    }

    public function testMissionDetailIncludesTeamOr404(): void
    {
        $this->login();
        $this->client->request('GET', '/api/staffing/missions');
        // Prend une mission avec une équipe pour vérifier le détail des affectations.
        $withTeam = null;
        foreach ($this->json() as $mission) {
            if ($mission['teamSize'] > 0) {
                $withTeam = $mission;
                break;
            }
        }
        self::assertNotNull($withTeam);

        $this->client->request('GET', "/api/staffing/missions/{$withTeam['id']}");
        self::assertResponseIsSuccessful();
        $detail = $this->json();
        self::assertArrayHasKey('assignments', $detail);
        self::assertCount($withTeam['teamSize'], $detail['assignments']);

        $this->client->request('GET', '/api/staffing/missions/999999');
        self::assertResponseStatusCodeSame(404);
    }
}
