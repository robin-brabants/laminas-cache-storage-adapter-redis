<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\Redis;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use Laminas\Cache\Storage\Plugin\Serializer;
use LaminasTest\Cache\Storage\Adapter\AbstractCommonAdapterTest;
use PHPUnit\Framework\MockObject\MockObject;
use Redis as RedisResource;

use function ceil;
use function count;
use function getenv;

/**
 * @covers Redis<extended>
 * @template-extends AbstractCommonAdapterTest<Redis, RedisOptions>
 */
final class RedisTest extends AbstractCommonAdapterTest
{
    /** @var RedisOptions */
    protected $options;

    /** @var Redis */
    protected $storage;

    public function setUp(): void
    {
        $options = ['resource_id' => self::class];

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') && getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST'), getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')];
        } elseif (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')];
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE')) {
            $options['database'] = getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD')) {
            $options['password'] = getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        }

        $this->options = new RedisOptions($options);
        $this->storage = new Redis($this->options);

        parent::setUp();
    }

    public function testLibOptionsFirst(): void
    {
        $options = [
            'resource_id' => self::class . '2',
            'liboptions'  => [
                RedisResource::OPT_SERIALIZER => RedisResource::SERIALIZER_PHP,
            ],
        ];

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') && getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST'), getenv('TESTS_LAMINAS_CACHE_REDIS_PORT')];
        } elseif (getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')) {
            $options['server'] = [getenv('TESTS_LAMINAS_CACHE_REDIS_HOST')];
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE')) {
            $options['database'] = getenv('TESTS_LAMINAS_CACHE_REDIS_DATABASE');
        }

        if (getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD')) {
            $options['password'] = getenv('TESTS_LAMINAS_CACHE_REDIS_PASSWORD');
        }

        $redisOptions = new RedisOptions($options);
        $storage      = new Redis($redisOptions);

        $this->assertInstanceOf(Redis::class, $storage);
    }

    public function testRedisSerializer(): void
    {
        $this->storage->addPlugin(new Serializer());
        $value = ['test', 'of', 'array'];
        $this->storage->setItem('key', $value);

        $this->assertCount(count($value), $this->storage->getItem('key'), 'Problem with Redis serialization');
    }

    public function testRedisSetInt(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123));
        $this->assertEquals('123', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetDouble(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, 123.12));
        $this->assertEquals('123.12', $this->storage->getItem($key), 'Integer should be cast to string');
    }

    public function testRedisSetNull(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, null));
        $this->assertEquals('', $this->storage->getItem($key), 'Null should be cast to string');
    }

    public function testRedisSetBoolean(): void
    {
        $key = 'key';
        $this->assertTrue($this->storage->setItem($key, true));
        $this->assertEquals('1', $this->storage->getItem($key), 'Boolean should be cast to string');
        $this->assertTrue($this->storage->setItem($key, false));
        $this->assertEquals('', $this->storage->getItem($key), 'Boolean should be cast to string');
    }

    public function testGetCapabilitiesTtl(): void
    {
        $resourceManager = $this->options->getResourceManager();
        $resourceId      = $this->options->getResourceId();
        $redis           = $resourceManager->getResource($resourceId);
        $majorVersion    = (int) $redis->info()['redis_version'];

        $this->assertEquals($majorVersion, $resourceManager->getMajorVersion($resourceId));

        $capabilities = $this->storage->getCapabilities();
        if ($majorVersion < 2) {
            $this->assertEquals(0, $capabilities->getMinTtl(), 'Redis version < 2.0.0 does not support key expiration');
        } else {
            $this->assertEquals(1, $capabilities->getMinTtl(), 'Redis version > 2.0.0 supports key expiration');
        }
    }

    public function testGetSetDatabase(): void
    {
        $this->assertTrue($this->storage->setItem('key', 'val'));
        $this->assertEquals('val', $this->storage->getItem('key'));

        $databaseNumber  = 1;
        $resourceManager = $this->options->getResourceManager();
        $resourceManager->setDatabase($this->options->getResourceId(), $databaseNumber);
        $this->assertNull(
            $this->storage->getItem('key'),
            'No value should be found because set was done on different database than get'
        );
        $this->assertEquals(
            $databaseNumber,
            $resourceManager->getDatabase($this->options->getResourceId()),
            'Incorrect database was returned'
        );
    }

    public function testGetSetLibOptionsOnExistingRedisResourceInstance(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);

        $value = ['value'];
        $key   = 'key';
        //test if it's still possible to set/get item and if lib serializer works
        $this->storage->setItem($key, $value);

        $this->assertEquals(
            $value,
            $this->storage->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $this->storage->setItem($key, $value);
        //should not serialize array correctly
        $this->assertIsNotArray(
            $this->storage->getItem($key),
            'Redis should not serialize automatically anymore, lib options were not set correctly'
        );
    }

    public function testGetSetLibOptionsWithCleanRedisResourceInstance(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);

        $redis = new Redis($this->options);
        $value = ['value'];
        $key   = 'key';
        //test if it's still possible to set/get item and if lib serializer works
        $redis->setItem($key, $value);
        $this->assertEquals(
            $value,
            $redis->getItem($key),
            'Redis should return an array, lib options were not set correctly'
        );

        $options = ['serializer' => RedisResource::SERIALIZER_NONE];
        $this->options->setLibOptions($options);
        $redis->setItem($key, $value);
        //should not serialize array correctly
        $this->assertIsNotArray(
            $redis->getItem($key),
            'Redis should not serialize automatically anymore, lib options were not set correctly'
        );
    }

    public function testTouchItem(): void
    {
        $key = 'key';

        // no TTL
        $this->storage->getOptions()->setTtl(0);
        $this->storage->setItem($key, 'val');
        $this->assertEquals(0, $this->storage->getMetadata($key)['ttl']);

        // touch with a specific TTL will add this TTL
        $ttl = 1000;
        $this->storage->getOptions()->setTtl($ttl);
        $this->assertTrue($this->storage->touchItem($key));
        $this->assertEquals($ttl, ceil($this->storage->getMetadata($key)['ttl']));
    }

    public function testHasItemReturnsFalseIfRedisExistsReturnsZero(): void
    {
        $redis = $this->mockInitializedRedisResource();
        $redis->method('exists')->willReturn(0);
        $adapter = $this->createAdapterFromResource($redis);

        $hasItem = $adapter->hasItem('does-not-exist');

        $this->assertFalse($hasItem);
    }

    public function testHasItemReturnsTrueIfRedisExistsReturnsNonZeroInt(): void
    {
        $redis = $this->mockInitializedRedisResource();
        $redis->method('exists')->willReturn(23);
        $adapter = $this->createAdapterFromResource($redis);

        $hasItem = $adapter->hasItem('does-not-exist');

        $this->assertTrue($hasItem);
    }

    /**
     * @return Redis
     */
    private function createAdapterFromResource(RedisResource $redis)
    {
        $resourceManager = new RedisResourceManager();
        $resourceId      = 'my-resource';
        $resourceManager->setResource($resourceId, $redis);
        $options = new RedisOptions(['resource_manager' => $resourceManager, 'resource_id' => $resourceId]);
        return new Redis($options);
    }

    /**
     * @return MockObject&RedisResource
     */
    private function mockInitializedRedisResource()
    {
        $redis         = $this->createMock(RedisFromExtensionAsset::class);
        $redis->socket = true;
        $redis->method('info')->willReturn(['redis_version' => '0.0.0-unknown']);
        return $redis;
    }
}
