<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\RedisCluster;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Adapter\RedisClusterResourceManagerInterface;
use PHPUnit\Framework\TestCase;

use function uniqid;

final class RedisClusterTest extends TestCase
{
    public function testCanDetectCapabilitiesWithSerializationSupport(): void
    {
        $resourceManager = $this->createMock(RedisClusterResourceManagerInterface::class);

        $adapter = new RedisCluster(new RedisClusterOptions([
            'name'          => 'bar',
            'redis_version' => '5.0.0',
        ]));

        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(true);

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => true,
            'boolean'  => true,
            'integer'  => true,
            'double'   => true,
            'string'   => true,
            'array'    => 'array',
            'object'   => 'object',
            'resource' => false,
        ], $datatypes);
    }

    public function testCanDetectCapabilitiesWithoutSerializationSupport(): void
    {
        $resourceManager = $this->createMock(RedisClusterResourceManagerInterface::class);

        $adapter = new RedisCluster(new RedisClusterOptions([
            'name'          => 'bar',
            'redis_version' => '5.0.0',
        ]));

        $adapter->setResourceManager($resourceManager);

        $resourceManager
            ->expects($this->once())
            ->method('hasSerializationSupport')
            ->with($adapter)
            ->willReturn(false);

        $capabilities = $adapter->getCapabilities();
        $datatypes    = $capabilities->supportedDataTypes;
        self::assertEquals([
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ], $datatypes);
    }

    public function testWillReturnVersionFromOptions(): void
    {
        $manager = new RedisCluster(new RedisClusterOptions([
            'name'          => uniqid('', true),
            'redis_version' => '1.0.0',
        ]));

        $version = $manager->getRedisVersion();
        self::assertEquals('1.0.0', $version);
    }
}
