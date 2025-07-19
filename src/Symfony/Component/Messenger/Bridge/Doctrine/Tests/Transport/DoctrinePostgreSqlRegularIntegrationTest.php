<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * This tests a using Doctrine PostgreSql connection without using PostgreSqlConnection
 * that gets used when use_notify is enabled.
 *
 * @requires extension pdo_pgsql
 *
 * @group integration
 */
class DoctrinePostgreSqlRegularIntegrationTest extends TestCase
{
    private \Doctrine\DBAL\Connection $driverConnection;
    private Connection $connection;

    public function testSendAndGetWithAutoSetupEnabledAndNotSetupAlready()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $this->connection->get();
        $this->assertSame('{"message": "Hi"}', $encoded['body']);
        $this->assertSame(['type' => DummyMessage::class], $encoded['headers']);

        $this->assertNull($this->connection->get());
    }

    public function testSendAndGetWithAutoSetupEnabledAndSetupAlready()
    {
        $this->connection->setup();

        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $this->connection->get();
        $this->assertSame('{"message": "Hi"}', $encoded['body']);
        $this->assertSame(['type' => DummyMessage::class], $encoded['headers']);

        $this->assertNull($this->connection->get());
    }

    public function testSendAndGetWithSkipLockedEnabled()
    {
        $connection = new Connection(['table_name' => 'queue_table', 'skip_locked' => true], $this->driverConnection);
        $connection->setup();

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $connection->get();
        $this->assertSame('{"message": "Hi"}', $encoded['body']);
        $this->assertSame(['type' => DummyMessage::class], $encoded['headers']);

        $this->assertNull($this->connection->get());
    }

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $url = "pdo-pgsql://postgres:password@$host";
        $params = (new DsnParser())->parse($url);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->connection = new Connection(['table_name' => 'queue_table'], $this->driverConnection);
    }

    protected function tearDown(): void
    {
        $this->driverConnection->createSchemaManager()->dropTable('queue_table');
        $this->driverConnection->close();
    }
}
