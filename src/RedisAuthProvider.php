<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;

final class RedisAuthProvider
{
    /**
     * @psalm-return array{0: non-empty-string, 1?: non-empty-string}|null
     * @throws InvalidRedisClusterConfigurationException
     *
     * Psalm cannot infer that only when user/password is not null and not an empty string, it is returned in the array
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public static function createAuthenticationObject(?string $user, ?string $password): array|null
    {
        if (self::isSet($password) && self::isSet($user)) {
            return [$user, $password];
        } elseif (self::isSet($password) && ! self::isSet($user)) {
            return [$password];
        } elseif (! self::isSet($password) && self::isSet($user)) {
            throw InvalidRedisClusterConfigurationException::fromMissingRequiredPassword();
        }
        return null;
    }

    private static function isSet(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
