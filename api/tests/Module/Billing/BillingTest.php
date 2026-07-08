<?php

declare(strict_types=1);

namespace App\Tests\Module\Billing;

use App\Tests\ApiTestCase;

final class BillingTest extends ApiTestCase
{
    public function testInvoicesRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/billing/invoices');

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvoicesAreListedWithTotals(): void
    {
        $this->login();
        $this->client->request('GET', '/api/billing/invoices');

        self::assertResponseIsSuccessful();
        $data = $this->json();

        self::assertArrayHasKey('totals', $data);
        self::assertArrayHasKey('items', $data);
        self::assertSame(count($data['items']), $data['totals']['count']);

        // Le total annoncé correspond à la somme des lignes.
        $sum = array_sum(array_map(static fn (array $i): float => $i['amountHt'], $data['items']));
        self::assertEqualsWithDelta($sum, $data['totals']['amountHt'], 0.01);
    }

    public function testStatusFilterNarrowsResults(): void
    {
        $this->login();
        $this->client->request('GET', '/api/billing/invoices?status=en_retard');

        self::assertResponseIsSuccessful();
        $data = $this->json();

        self::assertNotEmpty($data['items'], 'Les fixtures contiennent au moins une facture en retard.');
        foreach ($data['items'] as $invoice) {
            self::assertSame('en_retard', $invoice['status']);
        }
    }

    public function testUnknownStatusReturnsEverything(): void
    {
        $this->login();
        $this->client->request('GET', '/api/billing/invoices?status=inexistant');
        $filtered = $this->json()['totals']['count'];

        $this->client->request('GET', '/api/billing/invoices');
        $all = $this->json()['totals']['count'];

        self::assertSame($all, $filtered, 'Un statut invalide ne doit pas filtrer.');
    }
}
