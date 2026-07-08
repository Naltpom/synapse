<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class SearchTest extends ApiTestCase
{
    public function testSearchRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/search?q=banque');

        self::assertResponseStatusCodeSame(401);
    }

    public function testShortQueriesReturnNothing(): void
    {
        $this->login();
        $this->client->request('GET', '/api/search?q=b');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json()['results']);
    }

    public function testSearchFindsAcrossModules(): void
    {
        $this->login();
        $this->client->request('GET', '/api/search?q=héliard');

        self::assertResponseIsSuccessful();
        $results = $this->json()['results'];
        self::assertNotEmpty($results);

        $types = array_unique(array_column($results, 'type'));
        // « Banque Héliard » ressort au moins comme client, et via ses factures.
        self::assertContains('client', $types);

        foreach ($results as $result) {
            self::assertNotSame('', $result['title']);
            self::assertContains($result['type'], ['client', 'consultant', 'mission', 'invoice']);
        }
    }

    public function testSearchIsCappedPerType(): void
    {
        $this->login();
        // « a » élargi : chaque type est plafonné à 3 résultats.
        $this->client->request('GET', '/api/search?q=an');

        self::assertResponseIsSuccessful();
        $byType = array_count_values(array_column($this->json()['results'], 'type'));
        foreach ($byType as $count) {
            self::assertLessThanOrEqual(3, $count);
        }
    }
}
