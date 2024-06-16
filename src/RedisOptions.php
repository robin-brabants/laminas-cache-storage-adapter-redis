<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Exception;

use function sprintf;
use function strlen;

final class RedisOptions extends AdapterOptions
{
    // @codingStandardsIgnoreStart
    /**
     * Prioritized properties ordered by prio to be set first
     * in case a bulk of options sets set at once
     *
     * @var string[]
     */
    protected array $__prioritizedProperties__ = ['resource_manager', 'resource_id', 'server'];
    // @codingStandardsIgnoreEnd
    /**
     * The namespace separator
     */
    private string $namespaceSeparator = ':';

    /**
     * The redis resource manager
     */
    private ?RedisResourceManager $resourceManager = null;

    /**
     * The resource id of the resource manager
     */
    private string $resourceId = 'default';

    /**
     * {@inheritDoc}
     */
    public function setNamespace(string $namespace): self
    {
        if (128 < strlen($namespace)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects a prefix key of no longer than 128 characters',
                __METHOD__
            ));
        }

        parent::setNamespace($namespace);
        return $this;
    }

    /**
     * @return RedisOptions Provides a fluent interface
     */
    public function setNamespaceSeparator(string $namespaceSeparator): self
    {
        if ($this->namespaceSeparator !== $namespaceSeparator) {
            $this->triggerOptionEvent('namespace_separator', $namespaceSeparator);
            $this->namespaceSeparator = $namespaceSeparator;
        }

        return $this;
    }

    public function getNamespaceSeparator(): string
    {
        return $this->namespaceSeparator;
    }

    public function setResourceManager(?RedisResourceManager $resourceManager = null): self
    {
        if ($this->resourceManager !== $resourceManager) {
            $this->triggerOptionEvent('resource_manager', $resourceManager);
            $this->resourceManager = $resourceManager;
        }

        return $this;
    }

    public function getResourceManager(): RedisResourceManager
    {
        if (! $this->resourceManager) {
            $this->resourceManager = new RedisResourceManager();
        }
        return $this->resourceManager;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): self
    {
        if ($this->resourceId !== $resourceId) {
            $this->triggerOptionEvent('resource_id', $resourceId);
            $this->resourceId = $resourceId;
        }

        return $this;
    }

    public function getPersistentId(): string
    {
        return $this->getResourceManager()->getPersistentId($this->getResourceId());
    }

    public function setPersistentId(string $persistentId): self
    {
        $this->triggerOptionEvent('persistent_id', $persistentId);
        $this->getResourceManager()->setPersistentId($this->getResourceId(), $persistentId);
        return $this;
    }

    public function setLibOptions(array $libOptions): self
    {
        $this->triggerOptionEvent('lib_option', $libOptions);
        $this->getResourceManager()->setLibOptions($this->getResourceId(), $libOptions);
        return $this;
    }

    public function getLibOptions(): array
    {
        return $this->getResourceManager()->getLibOptions($this->getResourceId());
    }

    /**
     * Server can be described as follows:
     * - URI:   /path/to/sock.sock
     * - Assoc: array('host' => <host>[, 'port' => <port>[, 'timeout' => <timeout>]])
     * - List:  array(<host>[, <port>, [, <timeout>]])
     */
    public function setServer(string|array $server): self
    {
        $this->getResourceManager()->setServer($this->getResourceId(), $server);
        return $this;
    }

    /**
     * @return array array('host' => <host>[, 'port' => <port>[, 'timeout' => <timeout>]])
     */
    public function getServer(): array
    {
        return $this->getResourceManager()->getServer($this->getResourceId());
    }

    public function setDatabase(int $database): self
    {
        $this->getResourceManager()->setDatabase($this->getResourceId(), $database);
        return $this;
    }

    public function getDatabase(): int
    {
        return $this->getResourceManager()->getDatabase($this->getResourceId());
    }

    public function setPassword(string $password): self
    {
        $this->getResourceManager()->setPassword($this->getResourceId(), $password);
        return $this;
    }

    public function getPassword(): string|null
    {
        return $this->getResourceManager()->getPassword($this->getResourceId());
    }

    /**
     * @param string $user ACL User
     */
    public function setUser(string $user): RedisOptions
    {
        if ($user === '') {
            return $this;
        }

        $this->getResourceManager()->setUser($this->getResourceId(), $user);
        return $this;
    }

    public function getUser(): ?string
    {
        return $this->getResourceManager()->getUser($this->getResourceId());
    }
}
