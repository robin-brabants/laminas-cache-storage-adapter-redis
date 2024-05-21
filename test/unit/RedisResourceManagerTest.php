<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\RedisRuntimeException;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

use function bin2hex;
use function getenv;
use function random_bytes;

/**
 * PHPUnit test case
 */

/**
 * @group      Laminas_Cache
 * @covers Laminas\Cache\Storage\Adapter\RedisResourceManager
 */
class RedisResourceManagerTest extends TestCase
{
    protected RedisResourceManager $resourceManager;
    private string $resourceId;

    public function setUp(): void
    {
        $this->resourceManager = new RedisResourceManager();
        $this->resourceId      = bin2hex(random_bytes(5));
        $this->resourceManager->setResource($this->resourceId, [
            'server' => [
                'host' => 'localhost',
            ],
        ]);
        parent::setUp();
    }

    /**
     * @group 6495
     */
    public function testSetServerWithPasswordInUri(): void
    {
        $dummyResId = '1234567890';
        $server     = 'redis://dummyuser:dummypass@testhost:1234';

        $this->resourceManager->setServer($dummyResId, $server);

        $server = $this->resourceManager->getServer($dummyResId);

        $this->assertEquals('testhost', $server['host']);
        $this->assertEquals(1234, $server['port']);
        $this->assertEquals('dummypass', $this->resourceManager->getPassword($dummyResId));
        $this->assertEquals('dummyuser', $this->resourceManager->getUser($dummyResId));
    }

    /**
     * @group 6495
     */
    public function testSetServerWithPasswordInParameters(): void
    {
        $server      = 'redis://dummyuser:dummypass@testhost:1234';
        $dummyResId2 = '12345678901';
        $resource    = [
            'persistent_id' => 'my_connection_name',
            'server'        => $server,
            'password'      => 'abcd1234',
        ];

        $this->resourceManager->setResource($dummyResId2, $resource);

        $server = $this->resourceManager->getServer($dummyResId2);

        $this->assertEquals('testhost', $server['host']);
        $this->assertEquals(1234, $server['port']);
        $this->assertEquals('abcd1234', $this->resourceManager->getPassword($dummyResId2));
        $this->assertEquals('dummyuser', $this->resourceManager->getUser($dummyResId2));
    }

    public function testSetServerWithPasswordInParametersAndNoUser(): void
    {
        $server      = 'redis://testhost:1234';
        $dummyResId2 = '12345678901';
        $resource    = [
            'persistent_id' => 'my_connection_name',
            'server'        => $server,
            'password'      => 'abcd1234',
        ];

        $this->resourceManager->setResource($dummyResId2, $resource);

        $server = $this->resourceManager->getServer($dummyResId2);

        $this->assertEquals('testhost', $server['host']);
        $this->assertEquals(1234, $server['port']);
        $this->assertEquals('abcd1234', $this->resourceManager->getPassword($dummyResId2));
        $this->assertEquals('', $this->resourceManager->getUser($dummyResId2));
    }

    public function testSetServerWithPasswordInParametersAndUser(): void
    {
        $server      = 'redis://testhost:1234';
        $dummyResId2 = '12345678901';
        $resource    = [
            'persistent_id' => 'my_connection_name',
            'server'        => $server,
            'password'      => 'abcd1234',
            'user'          => "dummyuser",
        ];

        $this->resourceManager->setResource($dummyResId2, $resource);

        $server = $this->resourceManager->getServer($dummyResId2);

        $this->assertEquals('testhost', $server['host']);
        $this->assertEquals(1234, $server['port']);
        $this->assertEquals('abcd1234', $this->resourceManager->getPassword($dummyResId2));
        $this->assertEquals('dummyuser', $this->resourceManager->getUser($dummyResId2));
    }

    /**
     * @group 6495
     */
    public function testSetServerWithPasswordInUriShouldNotOverridePreviousResource(): void
    {
        $server      = 'redis://dummyuser:dummypass@testhost:1234';
        $server2     = 'redis://dummyuser:dummypass@testhost2:1234';
        $dummyResId2 = '12345678901';
        $resource    = [
            'persistent_id' => 'my_connection_name',
            'server'        => $server,
            'password'      => 'abcd1234',
        ];

        $this->resourceManager->setResource($dummyResId2, $resource);
        $this->resourceManager->setServer($dummyResId2, $server2);

        $server = $this->resourceManager->getServer($dummyResId2);

        $this->assertEquals('testhost2', $server['host']);
        $this->assertEquals(1234, $server['port']);
        // Password should not be overridden
        $this->assertEquals('abcd1234', $this->resourceManager->getPassword($dummyResId2));
    }

    /**
     * Test with 'persistent_id'
     */
    public function testValidPersistentId(): void
    {
        $resourceId           = 'testValidPersistentId';
        $resource             = [
            'persistent_id' => 'my_connection_name',
            'server'        => [
                'host' => getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') ?: 'localhost',
                'port' => getenv('TESTS_LAMINAS_CACHE_REDIS_PORT') ?: 6379,
            ],
        ];
        $expectedPersistentId = 'my_connection_name';
        $this->resourceManager->setResource($resourceId, $resource);
        $this->assertSame($expectedPersistentId, $this->resourceManager->getPersistentId($resourceId));
        $this->assertInstanceOf('Redis', $this->resourceManager->getResource($resourceId));
    }

    /**
     * Test with 'persistend_id' instead of 'persistent_id'
     */
    public function testNotValidPersistentIdOptionName(): void
    {
        $resourceId           = 'testNotValidPersistentId';
        $resource             = [
            'persistend_id' => 'my_connection_name',
            'server'        => [
                'host' => getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') ?: 'localhost',
                'port' => getenv('TESTS_LAMINAS_CACHE_REDIS_PORT') ?: 6379,
            ],
        ];
        $expectedPersistentId = 'my_connection_name';
        $this->resourceManager->setResource($resourceId, $resource);

        $this->assertNotSame($expectedPersistentId, $this->resourceManager->getPersistentId($resourceId));
        $this->assertEmpty($this->resourceManager->getPersistentId($resourceId));
        $this->assertInstanceOf('Redis', $this->resourceManager->getResource($resourceId));
    }

    public function testGetVersion(): void
    {
        $resourceId = __FUNCTION__;
        $resource   = [
            'server' => [
                'host' => getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') ?: 'localhost',
                'port' => getenv('TESTS_LAMINAS_CACHE_REDIS_PORT') ?: 6379,
            ],
        ];
        $this->resourceManager->setResource($resourceId, $resource);

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $this->resourceManager->getVersion($resourceId));
    }

    public function testGetMajorVersion(): void
    {
        $resourceId = __FUNCTION__;
        $resource   = [
            'server' => [
                'host' => getenv('TESTS_LAMINAS_CACHE_REDIS_HOST') ?: 'localhost',
                'port' => getenv('TESTS_LAMINAS_CACHE_REDIS_PORT') ?: 6379,
            ],
        ];
        $this->resourceManager->setResource($resourceId, $resource);

        $this->assertGreaterThan(0, $this->resourceManager->getMajorVersion($resourceId));
    }

    public function testWillCatchConnectExceptions(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis
            ->expects(self::atLeastOnce())
            ->method('connect')
            ->willThrowException(new RedisException('test'));

        $this->resourceManager->setResource('default', ['resource' => $redis, 'server' => 'localhost:6379']);

        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->getResource('default');
    }

    public function testWillCatchPConnectExceptions(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis
            ->expects(self::atLeastOnce())
            ->method('pconnect')
            ->willThrowException(new RedisException('test'));

        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->setResource(
            'default',
            [
                'resource'      => $redis,
                'server'        => 'localhost:6379',
                'persistent_id' => 'test',
            ]
        );
        $this->resourceManager->getResource('default');
    }

    public function testWillCatchAuthExceptions(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis
            ->method('connect')
            ->willReturn(true);

        $redis
            ->method('info')
            ->willReturn(['redis_version' => '1.2.3']);

        $redis
            ->expects(self::atLeastOnce())
            ->method('auth')
            ->with(['foobar'])
            ->willThrowException(new RedisException('test'));

        $this->resourceManager->setResource(
            'default',
            [
                'resource' => $redis,
                'server'   => 'whatever:6379',
                'password' => 'foobar',
            ]
        );
        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->getResource('default');
    }

    public function testWillAuthenticateWithUserAndPassword(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis
            ->method('connect')
            ->willReturn(true);

        $redis
            ->method('info')
            ->willReturn(['redis_version' => '1.2.3']);

        $redis
            ->expects(self::atLeastOnce())
            ->method('auth')
            ->with(['default', 'foobar']);

        $this->resourceManager->setResource(
            'default',
            [
                'resource' => $redis,
                'server'   => 'whatever:6379',
                'password' => 'foobar',
                'user'     => 'default',
            ]
        );

        $this->resourceManager->getResource('default');
    }

    public function testWillCatchInfoExceptions(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis
            ->method('connect')
            ->willReturn(true);

        $redis
            ->expects(self::atLeastOnce())
            ->method('info')
            ->willThrowException(new RedisException('test'));

        $this->resourceManager->setResource(
            'default',
            [
                'resource'    => $redis,
                'initialized' => true,
                'server'      => 'somewhere:6379',
            ]
        );

        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->getResource('default');
    }

    public function testWillCatchAuthDuringConnectException(): void
    {
        $redis = $this->createMock(Redis::class);

        $redis
            ->method('connect')
            ->willReturn(true);

        $redis
            ->expects(self::atLeastOnce())
            ->method('auth')
            ->with(['secret'])
            ->willThrowException(new RedisException('test'));

        $this->resourceManager->setResource(
            'default',
            [
                'resource'    => $redis,
                'initialized' => false,
                'server'      => 'somewhere:6379',
                'password'    => 'secret',
            ]
        );

        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->getResource('default');
    }

    public function testWillCatchSelectDatabaseException(): void
    {
        $redis = $this->createMock(Redis::class);

        $redis
            ->expects(self::atLeastOnce())
            ->method('select')
            ->willThrowException(new RedisException('test'));

        $this->resourceManager->setResource(
            'default',
            [
                'resource'    => $redis,
                'initialized' => true,
                'server'      => 'somewhere:6379',
            ]
        );

        $this->expectException(RedisRuntimeException::class);
        $this->expectExceptionMessage('test');
        $this->resourceManager->setDatabase('default', 0);
    }

    public function testGetSetPassword(): void
    {
        $pass = 'super secret';
        $this->resourceManager->setPassword($this->resourceId, $pass);
        $this->assertEquals(
            $pass,
            $this->resourceManager->getPassword($this->resourceId),
            'Password was not correctly set'
        );
    }

    public function testSocketConnection(): void
    {
        $socket = '/tmp/redis.sock';
        $this->resourceManager->setServer($this->resourceId, $socket);
        $normalized = $this->resourceManager->getServer($this->resourceId);
        $this->assertEquals($socket, $normalized['host'], 'Host should equal to socket {$socket}');
    }
}
