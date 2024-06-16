<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;
use Laminas\Cache\Storage\AbstractMetadataCapableAdapter;
use Laminas\Cache\Storage\Adapter\Redis\Metadata;
use Laminas\Cache\Storage\Adapter\RedisResourceManager;
use Laminas\Cache\Storage\Capabilities;
use Laminas\Cache\Storage\ClearByNamespaceInterface;
use Laminas\Cache\Storage\ClearByPrefixInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\TotalSpaceCapableInterface;
use Redis as RedisResource;
use RedisException as RedisResourceException;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function assert;
use function round;
use function version_compare;

/**
 * @template-extends AbstractMetadataCapableAdapter<RedisOptions, Metadata>
 */
final class Redis extends AbstractMetadataCapableAdapter implements
    ClearByNamespaceInterface,
    ClearByPrefixInterface,
    FlushableInterface,
    TotalSpaceCapableInterface
{
    /**
     * Has this instance be initialized
     */
    private bool $initialized = false;

    /**
     * The redis resource manager
     */
    private ?RedisResourceManager $resourceManager = null;

    /**
     * The redis resource id
     */
    private ?string $resourceId = null;

    /**
     * The namespace prefix
     */
    private string $namespacePrefix = '';

    /**
     * @param null|iterable<string,mixed>|RedisOptions $options
     */
    public function __construct(iterable|RedisOptions|null $options = null)
    {
        parent::__construct($options);

        // reset initialized flag on update option(s)
        $initialized = &$this->initialized;
        $this->getEventManager()->attach('option', static function () use (&$initialized): void {
            $initialized = false;
        });
    }

    private function getRedisResource(): RedisResource
    {
        if ($this->initialized) {
            return $this->resourceManager->getResource($this->resourceId);
        }

        $options = $this->getOptions();

        // get resource manager and resource id
        $this->resourceManager = $options->getResourceManager();
        $this->resourceId      = $options->getResourceId();

        // init namespace prefix
        $namespace = $options->getNamespace();
        if ($namespace !== '') {
            $this->namespacePrefix = $namespace . $options->getNamespaceSeparator();
        } else {
            $this->namespacePrefix = '';
        }

        // update initialized flag
        $this->initialized = true;

        return $this->resourceManager->getResource($this->resourceId);
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(iterable|AdapterOptions $options): self
    {
        if (! $options instanceof RedisOptions) {
            $options = new RedisOptions($options);
        }

        parent::setOptions($options);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): RedisOptions
    {
        if (! $this->options) {
            $this->setOptions(new RedisOptions());
        }
        assert($this->options instanceof RedisOptions);
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetItem(
        string $normalizedKey,
        bool|null &$success = null,
        mixed &$casToken = null
    ): mixed {
        $redis = $this->getRedisResource();
        try {
            $value = $redis->get($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }

        if ($value === false) {
            $success = false;
            return null;
        }

        $success  = true;
        $casToken = $value;
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetItems(array $normalizedKeys): array
    {
        $redis = $this->getRedisResource();

        $namespacedKeys = [];
        foreach ($normalizedKeys as $normalizedKey) {
            $namespacedKeys[] = $this->namespacePrefix . $normalizedKey;
        }

        try {
            $results = $redis->mGet($namespacedKeys);
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
        //combine the key => value pairs and remove all missing values
        return array_filter(
            array_combine($normalizedKeys, $results),
            static fn($value): bool => $value !== false
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function internalHasItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            return (bool) $redis->exists($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItem(string $normalizedKey, mixed $value): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        try {
            if ($ttl) {
                if ($options->getResourceManager()->getMajorVersion($options->getResourceId()) < 2) {
                    throw new Exception\UnsupportedMethodCallException("To use ttl you need version >= 2.0.0");
                }
                $success = $redis->setex(
                    $this->namespacePrefix . $normalizedKey,
                    (int) $ttl,
                    $this->preSerialize($value)
                ) !== false;
            } else {
                $success = $redis->set($this->namespacePrefix . $normalizedKey, $this->preSerialize($value)) !== false;
            }
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    protected function internalSetItems(array $normalizedKeyValuePairs): array
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        $namespacedKeyValuePairs = [];
        foreach ($normalizedKeyValuePairs as $normalizedKey => $value) {
            $namespacedKeyValuePairs[$this->namespacePrefix . $normalizedKey] = $this->preSerialize($value);
        }

        try {
            if ($ttl > 0) {
                //check if ttl is supported
                if ($options->getResourceManager()->getMajorVersion($options->getResourceId()) < 2) {
                    throw new Exception\UnsupportedMethodCallException("To use ttl you need version >= 2.0.0");
                }
                //mSet does not allow ttl, so use transaction
                $transaction = $redis->multi();
                foreach ($namespacedKeyValuePairs as $key => $value) {
                    $transaction->setex($key, (int) $ttl, $value);
                }
                $success = $transaction->exec() !== false;
            } else {
                $success = $redis->mSet($namespacedKeyValuePairs) !== false;
            }
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
        if (! $success) {
            throw new Exception\RuntimeException($redis->getLastError() ?? 'no last error');
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function internalAddItem(string $normalizedKey, mixed $value): bool
    {
        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $ttl     = $options->getTtl();

        try {
            if ($ttl > 0) {
                if ($options->getResourceManager()->getMajorVersion($options->getResourceId()) < 2) {
                    throw new Exception\UnsupportedMethodCallException("To use ttl you need version >= 2.0.0");
                }

                /**
                 * To ensure expected behaviour, we stick with the "setnx" method.
                 * This means we only set the ttl after the key/value has been successfully set.
                 */
                $success = $redis->setnx(
                    $this->namespacePrefix . $normalizedKey,
                    $this->preSerialize($value)
                ) !== false;
                if ($success) {
                    $redis->expire($this->namespacePrefix . $normalizedKey, (int) $ttl);
                }

                return $success;
            }

            return $redis->setnx($this->namespacePrefix . $normalizedKey, $this->preSerialize($value)) !== false;
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalTouchItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            $ttl = $this->getOptions()->getTtl();
            return (bool) $redis->expire($this->namespacePrefix . $normalizedKey, (int) $ttl);
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function internalRemoveItem(string $normalizedKey): bool
    {
        $redis = $this->getRedisResource();
        try {
            return (bool) $redis->del($this->namespacePrefix . $normalizedKey);
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        $redis = $this->getRedisResource();
        try {
            return $redis->flushDB();
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function clearByNamespace(string $namespace): bool
    {
        /** @psalm-suppress TypeDoesNotContainType Psalm type does not prevent from injecting empty string */
        if ($namespace === '') {
            throw new Exception\InvalidArgumentException('No namespace given');
        }

        $redis   = $this->getRedisResource();
        $options = $this->getOptions();
        $prefix  = $namespace . $options->getNamespaceSeparator();

        $redis->del($redis->keys($prefix . '*'));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function clearByPrefix(string $prefix): bool
    {
        /** @psalm-suppress TypeDoesNotContainType Psalm type does not prevent from injecting empty string */
        if ($prefix === '') {
            throw new Exception\InvalidArgumentException('No prefix given');
        }

        $redis     = $this->getRedisResource();
        $options   = $this->getOptions();
        $namespace = $options->getNamespace();
        $prefix    = $namespace === '' ? '' : $namespace . $options->getNamespaceSeparator() . $prefix;

        $redis->del($redis->keys($prefix . '*'));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalSpace(): int
    {
        $redis = $this->getRedisResource();
        try {
            $info = $redis->info();
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }

        Assert::isMap($info);
        Assert::keyExists($info, 'used_memory');
        assert(array_key_exists('used_memory', $info), 'Provide info to psalm');
        Assert::natural($info['used_memory']);
        return $info['used_memory'];
    }

    /**
     * {@inheritDoc}
     */
    protected function internalGetCapabilities(): Capabilities
    {
        if ($this->capabilities !== null) {
            return $this->capabilities;
        }

        $options      = $this->getOptions();
        $resourceMgr  = $options->getResourceManager();
        $serializer   = $resourceMgr->getLibOption($options->getResourceId(), RedisResource::OPT_SERIALIZER);
        $redisVersion = $resourceMgr->getMajorVersion($options->getResourceId());
        $maxKeyLength = version_compare((string) $redisVersion, '3', '<') ? 255 : 512_000_000;

        $supportedDataTypes = [
            'NULL'     => 'string',
            'boolean'  => 'string',
            'integer'  => 'string',
            'double'   => 'string',
            'string'   => true,
            'array'    => false,
            'object'   => false,
            'resource' => false,
        ];

        if ($serializer !== null) {
            $supportedDataTypes = [
                'NULL'     => true,
                'boolean'  => true,
                'integer'  => true,
                'double'   => true,
                'string'   => true,
                'array'    => 'array',
                'object'   => 'object',
                'resource' => false,
            ];
        }

        $this->capabilities = new Capabilities(
            maxKeyLength: $maxKeyLength,
            ttlSupported: $redisVersion >= 2,
            namespaceIsPrefix: true,
            supportedDataTypes: $supportedDataTypes,
            ttlPrecision: 1,
            usesRequestTime: false,
        );

        return $this->capabilities;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception\ExceptionInterface
     */
    protected function internalGetMetadata(string $normalizedKey): Metadata|null
    {
        $redis = $this->getRedisResource();

        try {
            $redisVersion = $this->resourceManager->getVersion($this->resourceId);

            if (version_compare($redisVersion, '2.8', '>=')) {
                // redis >= 2.8
                // The command 'pttl' returns -2 if the item does not exist
                // and -1 if the item has no associated expire
                $pttl = $redis->pttl($this->namespacePrefix . $normalizedKey);
                if ($pttl <= -2) {
                    return null;
                }

                if ($pttl === -1) {
                    return new Metadata(ttl: Metadata::TTL_UNLIMITED);
                }

                $ttl = (int) round($pttl / 1000);
                Assert::natural($ttl);
                return new Metadata(ttl: $ttl);
            }

            if (version_compare($redisVersion, '2.6', '>=')) {
                // redis >= 2.6, < 2.8
                // The command 'pttl' returns -1 if the item does not exist or the item has no associated expire
                $pttl = $redis->pttl($this->namespacePrefix . $normalizedKey);
                if ($pttl <= -1) {
                    if (! $this->internalHasItem($normalizedKey)) {
                        return null;
                    }

                    return new Metadata(ttl: Metadata::TTL_UNLIMITED);
                }

                $ttl = (int) round($pttl / 1000);
                Assert::natural($ttl);
                return new Metadata(ttl: $ttl);
            }

            if (version_compare($redisVersion, '2', '>=')) {
                // redis >= 2, < 2.6
                // The command 'pttl' is not supported but 'ttl'
                // The command 'ttl' returns 0 if the item does not exist same as if the item is going to be expired
                // NOTE: In case of ttl=0 we return false because the item is going to be expired in a very near future
                //       and then doesn't exist anymore
                $ttl = $redis->ttl($this->namespacePrefix . $normalizedKey);
                if ($ttl <= -1) {
                    if (! $this->internalHasItem($normalizedKey)) {
                        return null;
                    }

                    return new Metadata(ttl: Metadata::TTL_UNLIMITED);
                }

                Assert::natural($ttl);
                return new Metadata(ttl: $ttl);
            } elseif (! $this->internalHasItem($normalizedKey)) {
                return null;
            }
        } catch (RedisResourceException $e) {
            throw new Exception\RuntimeException($redis->getLastError() ?? $e->getMessage(), $e->getCode(), $e);
        }

        return new Metadata(ttl: null);
    }

    /**
     * Pre-Serialize value before putting it to the redis extension
     * The reason for this is the buggy extension version < 2.5.7
     * which is producing a segfault on storing NULL as long as no serializer was configured.
     *
     * @link https://github.com/zendframework/zend-cache/issues/88
     */
    private function preSerialize(mixed $value): mixed
    {
        $options     = $this->getOptions();
        $resourceMgr = $options->getResourceManager();
        $serializer  = $resourceMgr->getLibOption($options->getResourceId(), RedisResource::OPT_SERIALIZER);
        if ($serializer === null) {
            return (string) $value;
        }

        return $value;
    }
}
