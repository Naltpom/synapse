<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class MarginsTest extends ApiTestCase
{
    public function testMarginsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/finance/margins');

        self::assertResponseStatusCodeSame(401);
    }

    public function testMarginsAreReservedToManagers(): void
    {
        $this->login('commerce@synapse.demo');
        $this->client->request('GET', '/api/finance/margins');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMarginsAreConsistent(): void
    {
        $this->login('staffing@synapse.demo');
        $this->client->request('GET', '/api/finance/margins');

        self::assertResponseIsSuccessful();
        $data = $this->json();

        self::assertNotEmpty($data['missions']);
        self::assertNotEmpty($data['clients']);
        self::assertGreaterThan(0, $data['totals']['revenue']);

        foreach ($data['missions'] as $row) {
            self::assertEqualsWithDelta($row['revenue'] - $row['cost'], $row['margin'], 0.01);
        }
        self::assertEqualsWithDelta(
            $data['totals']['revenue'] - $data['totals']['cost'],
            $data['totals']['margin'],
            0.01,
        );
    }
}
