<?php

declare(strict_types=1);

namespace App\Tests\Module\Timesheet;

use App\Tests\ApiTestCase;

final class CraTest extends ApiTestCase
{
    public function testGridRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/cra?consultantId=1');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCraIsReservedToManagers(): void
    {
        // Anti-IDOR : sans lien User↔Consultant, la saisie pour le compte d'un
        // consultant (lecture comprise) est réservée au back-office managers/direction.
        $this->login('commerce@synapse.demo');

        $this->client->request('GET', '/api/cra?consultantId=1');
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => 1, 'date' => '2026-08-03', 'lineKey' => 'interne', 'fraction' => 0.5,
        ]);
        self::assertResponseStatusCodeSame(403);

        $this->client->jsonRequest('POST', '/api/cra/submit', ['consultantId' => 1, 'week' => '2026-08-03']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testGridExposesMissionAndCategoryLines(): void
    {
        $this->login();
        $id = $this->staffedConsultantId();

        $this->client->request('GET', "/api/cra?consultantId={$id}&week=today");

        self::assertResponseIsSuccessful();
        $grid = $this->json();

        self::assertCount(5, $grid['days']);
        $categories = array_column($grid['lines'], 'category');
        self::assertContains('mission', $categories, 'Un consultant staffé doit voir ses lignes mission.');
        self::assertContains('conge', $categories);
        self::assertContains('interne', $categories);
        foreach ($grid['lines'] as $line) {
            self::assertCount(5, $line['cells']);
        }
    }

    public function testEntryUpsertEnforcesDailyCap(): void
    {
        $this->login();
        $id = $this->staffedConsultantId();
        $this->client->request('GET', "/api/cra?consultantId={$id}&week=today");
        $grid = $this->json();
        $missionLine = $this->missionLine($grid);
        $date = $grid['days'][0];

        // Journée complète sur la mission.
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $date, 'lineKey' => $missionLine['key'], 'fraction' => 1,
        ]);
        self::assertResponseIsSuccessful();

        // Une demi-journée en plus sur une autre ligne dépasserait la journée.
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $date, 'lineKey' => 'interne', 'fraction' => 0.5,
        ]);
        self::assertResponseStatusCodeSame(422);

        // Redescendre à 0,5 puis compléter à 0,5 en interne : total = 1, accepté.
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $date, 'lineKey' => $missionLine['key'], 'fraction' => 0.5,
        ]);
        self::assertResponseIsSuccessful();
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $date, 'lineKey' => 'interne', 'fraction' => 0.5,
        ]);
        self::assertResponseIsSuccessful();
    }

    public function testSubmitLocksTheWeekAndValidationIsManagerOnly(): void
    {
        $this->login();
        $id = $this->staffedConsultantId();
        // Semaine future dédiée : garantie vierge (aucune autre méthode de test n'y écrit).
        $this->client->request('GET', "/api/cra?consultantId={$id}&week=2027-03-01");
        $grid = $this->json();
        $week = $grid['week']['weekStart'];

        // Une semaine vide ne peut pas être soumise.
        $this->client->jsonRequest('POST', '/api/cra/submit', ['consultantId' => $id, 'week' => $week]);
        self::assertResponseStatusCodeSame(422);

        // On saisit au moins une demi-journée, puis la soumission passe.
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $grid['days'][0], 'lineKey' => 'interne', 'fraction' => 0.5,
        ]);
        self::assertResponseIsSuccessful();

        $this->client->jsonRequest('POST', '/api/cra/submit', ['consultantId' => $id, 'week' => $week]);
        self::assertResponseIsSuccessful();
        self::assertSame('submitted', $this->json()['status']);

        // Saisie verrouillée après soumission.
        $this->client->jsonRequest('PUT', '/api/cra/entries', [
            'consultantId' => $id, 'date' => $grid['days'][1], 'lineKey' => 'interne', 'fraction' => 0.5,
        ]);
        self::assertResponseStatusCodeSame(409);

        // La validation est refusée à un commercial…
        $this->login('commerce@synapse.demo');
        $this->client->jsonRequest('POST', '/api/cra/validate', ['consultantId' => $id, 'week' => $week]);
        self::assertResponseStatusCodeSame(403);

        // …et acceptée pour un manager.
        $this->login('staffing@synapse.demo');
        $this->client->jsonRequest('POST', '/api/cra/validate', ['consultantId' => $id, 'week' => $week]);
        self::assertResponseIsSuccessful();
        self::assertSame('validated', $this->json()['status']);
    }

    public function testFixturesSeedASubmittedWeek(): void
    {
        $this->login();
        $this->client->request('GET', '/api/nav-counters');

        self::assertGreaterThanOrEqual(1, $this->json()['craPending']);
    }

    private function staffedConsultantId(): int
    {
        $this->client->request('GET', '/api/staffing/consultants');
        foreach ($this->json() as $consultant) {
            if ($consultant['allocation'] > 0) {
                return (int) $consultant['id'];
            }
        }
        self::fail('Aucun consultant staffé en fixtures.');
    }

    /**
     * @param array<string, mixed> $grid
     *
     * @return array<string, mixed>
     */
    private function missionLine(array $grid): array
    {
        foreach ($grid['lines'] as $line) {
            if ('mission' === $line['category']) {
                return $line;
            }
        }
        self::fail('Pas de ligne mission dans la grille.');
    }
}
