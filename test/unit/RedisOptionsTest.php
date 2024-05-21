<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\AdapterOptions;
use Laminas\Cache\Storage\Adapter\RedisOptions;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use Redis as RedisResource;

/**
 * @template-extends AbstractAdapterOptionsTest<RedisOptions>
 */
final class RedisOptionsTest extends AbstractAdapterOptionsTest
{
    protected function createAdapterOptions(): AdapterOptions
    {
        return new RedisOptions(['server' => ['host' => 'localhost']]);
    }

    public function testGetSetNamespace(): void
    {
        $namespace = 'testNamespace';
        $this->options->setNamespace($namespace);
        $this->assertEquals($namespace, $this->options->getNamespace(), 'Namespace was not set correctly');
    }

    public function testGetSetNamespaceSeparator(): void
    {
        $separator = '/';
        $this->options->setNamespaceSeparator($separator);
        $this->assertEquals($separator, $this->options->getNamespaceSeparator(), 'Separator was not set correctly');
    }

    public function testGetSetResourceManager(): void
    {
        $resourceManager = new RedisResourceManager();
        $options         = new RedisOptions();
        $options->setResourceManager($resourceManager);
        $this->assertInstanceOf(
            RedisResourceManager::class,
            $options->getResourceManager(),
            'Wrong resource manager retuned, it should of type RedisResourceManager'
        );

        $this->assertEquals($resourceManager, $options->getResourceManager());
    }

    public function testGetSetResourceId(): void
    {
        $resourceId = '1';
        $options    = new RedisOptions();
        $options->setResourceId($resourceId);
        $this->assertEquals($resourceId, $options->getResourceId(), 'Resource id was not set correctly');
    }

    public function testGetSetPersistentId(): void
    {
        $persistentId = '1';
        $this->options->setPersistentId($persistentId);
        $this->assertEquals($persistentId, $this->options->getPersistentId(), 'Persistent id was not set correctly');
    }

    public function testOptionsGetSetLibOptions(): void
    {
        $options = ['serializer' => RedisResource::SERIALIZER_PHP];
        $this->options->setLibOptions($options);
        $this->assertEquals(
            $options,
            $this->options->getLibOptions(),
            'Lib Options were not set correctly through RedisOptions'
        );
    }

    public function testGetSetServer(): void
    {
        $server = [
            'host'    => '127.0.0.1',
            'port'    => 6379,
            'timeout' => 0,
        ];
        $this->options->setServer($server);
        $this->assertEquals($server, $this->options->getServer(), 'Server was not set correctly through RedisOptions');
    }

    public function testOptionsGetSetDatabase(): void
    {
        $database = 1;
        $this->options->setDatabase($database);
        $this->assertEquals($database, $this->options->getDatabase(), 'Database not set correctly using RedisOptions');
    }

    public function testOptionsGetSetPassword(): void
    {
        $password = 'my-secret';
        $this->options->setPassword($password);
        $this->assertEquals(
            $password,
            $this->options->getPassword(),
            'Password was set incorrectly using RedisOptions'
        );
    }

    public function testOptionsGetSetUser(): void
    {
        $user = 'dummyuser';
        $this->options->setUser($user);
        $this->assertEquals(
            $user,
            $this->options->getUser(),
            'User was set incorrectly using RedisOptions'
        );
    }
}
