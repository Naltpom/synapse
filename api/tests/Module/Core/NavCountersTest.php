<?php

declare(strict_types=1);

namespace App\Tests\Module\Core;

use App\Tests\ApiTestCase;

final class NavCountersTest extends ApiTestCase
{
    public function testCountersRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/nav-counters');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCountersMatchFixtures(): void
    {
        $this->login();
        $this->client->request('GET', '/api/nav-counters');

        self::assertResponseIsSuccessful();
        $counters = $this->json();

        self::assertSame(4, $counters['staffingBench']);
        self::assertGreaterThanOrEqual(1, $counters['billingOverdue']);
        self::assertGreaterThanOrEqual(1, $counters['hrPending']);
    }
}
