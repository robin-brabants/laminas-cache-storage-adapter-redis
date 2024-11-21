<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\Exception\InvalidRedisClusterConfigurationException;
use Laminas\Cache\Storage\Adapter\RedisAuthProvider;
use PHPUnit\Framework\TestCase;

final class RedisAuthProviderTest extends TestCase
{
    private const DUMMY_USER     = 'user';
    private const DUMMY_PASSWORD = 'password';

    /**
     * @dataProvider invalidAuthenticationProvider
     */
    public function testWillThrowExceptionWhenUserWithoutPasswordProvided(string $user, ?string $password): void
    {
        $this->expectException(InvalidRedisClusterConfigurationException::class);
        $this->expectExceptionMessage(
            InvalidRedisClusterConfigurationException::fromMissingRequiredPassword()->getMessage()
        );
        RedisAuthProvider::createAuthenticationObject($user, $password);
    }

    /**
     * @dataProvider validAuthenticationProvider
     */
    public function testValidUserAndPasswordProvided(
        ?string $user,
        ?string $password,
        array|null $expectedAuthentication
    ): void {
        $actualAuthentication = RedisAuthProvider::createAuthenticationObject($user, $password);
        $this->assertEquals($expectedAuthentication, $actualAuthentication);
    }

    /**
     * @psalm-return non-empty-array<non-empty-string,array{0:string|null,1:string|null,
     * 2:array{0: non-empty-string, 1?: non-empty-string}|null}>
     */
    public static function validAuthenticationProvider(): array
    {
        return [
            'user and password'                          => [
                self::DUMMY_USER,
                self::DUMMY_PASSWORD,
                [self::DUMMY_USER, self::DUMMY_PASSWORD],
            ],
            'only password (user is empty string)'       => [
                '',
                self::DUMMY_PASSWORD,
                [self::DUMMY_PASSWORD],
            ],
            'no authentication provided (empty strings)' => [
                '',
                '',
                null,
            ],
            'only password (user is null)'               => [
                null,
                self::DUMMY_PASSWORD,
                [self::DUMMY_PASSWORD],
            ],
            'no authentication provided (null values)'   => [
                null,
                null,
                null,
            ],
        ];
    }

    /**
     * @psalm-return non-empty-array<non-empty-string,array{0:non-empty-string,1:string|null}>
     */
    public static function invalidAuthenticationProvider(): array
    {
        return [
            'user without a password (password is empty string)' => [
                self::DUMMY_USER,
                '',
            ],
            'user without a password (password is null)'         => [
                self::DUMMY_USER,
                null,
            ],
        ];
    }
}
