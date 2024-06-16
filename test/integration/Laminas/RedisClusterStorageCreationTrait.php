<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter\Laminas;

use Laminas\Cache\Storage\Adapter\RedisCluster;
use Laminas\Cache\Storage\Adapter\RedisClusterOptions;
use Laminas\Cache\Storage\Plugin\Serializer;
use Laminas\Serializer\AdapterPluginManager;
use Laminas\ServiceManager\ServiceManager;
use Redis as RedisFromExtension;
use RuntimeException;

use function implode;
use function str_shuffle;

trait RedisClusterStorageCreationTrait
{
    use RedisConfigurationFromEnvironmentTrait;

    private function createRedisClusterStorage(int $serializerOption, bool $serializerPlugin): RedisCluster
    {
        $node = $this->getClusterNameFromEnvironment();

        if ($node === '') {
            throw new RuntimeException('Could not find named config environment configuration.');
        }

        $options = new RedisClusterOptions([
            'name'        => $node,
            'lib_options' => [
                RedisFromExtension::OPT_SERIALIZER => $serializerOption,
            ],
            'namespace'   => str_shuffle(implode('', ['a', 'b', 'c', 'd', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'])),
        ]);

        $storage = new RedisCluster($options);
        if ($serializerOption === RedisFromExtension::SERIALIZER_NONE && $serializerPlugin) {
            $storage->addPlugin(new Serializer(new AdapterPluginManager(new ServiceManager())));
        }

        return $storage;
    }
}
