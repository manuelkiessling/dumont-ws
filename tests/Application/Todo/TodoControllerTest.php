<?php

declare(strict_types=1);

namespace App\Tests\Application\Todo;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TodoControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $file = self::getContainer()->getParameter('app.todo_items_file');
        self::assertIsString($file);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function testAnEmptyListSaysSo(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test-id="empty-list"]');
    }

    public function testAnAddedItemIsShownOnTheList(): void
    {
        $this->addItem('Buy milk');

        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="item"]', 'Buy milk');
    }

    public function testARefusedItemShowsTheReasonAndKeepsTheTextAsSent(): void
    {
        $tooLong = str_repeat('x', 201);

        $this->addItem($tooLong);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test-id="refusal"]', 'at most 200 characters');
        self::assertInputValueSame('text', $tooLong);
    }

    public function testAnItemCanBeMarkedDoneAndThenDeleted(): void
    {
        $this->addItem('Buy milk');
        $crawler = $this->client->followRedirect();

        $this->client->submit($crawler->filter('[data-test-id="mark-done"]')->form());
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('[data-test-id="item"] s');
        self::assertSelectorNotExists('[data-test-id="mark-done"]');

        $this->client->submit($crawler->filter('[data-test-id="delete"]')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('[data-test-id="empty-list"]');
    }

    public function testAnItemThatIsNoLongerOnTheListIsReported(): void
    {
        $this->client->request('POST', '/items/0195f0a2-5c3b-7d4e-8f00-123456789abc/done');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test-id="refusal"]', 'no longer on your list');
    }

    public function testAMalformedItemIdIsNotFound(): void
    {
        $this->client->request('POST', '/items/not-an-id/done');

        self::assertResponseStatusCodeSame(404);
    }

    private function addItem(string $text): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->client->submit($crawler->filter('[data-test-id="add-item-form"]')->form(['text' => $text]));
    }
}
