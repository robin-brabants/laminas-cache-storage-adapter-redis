<?php

declare(strict_types=1);

namespace LaminasTest\Cache\Storage\Adapter;

use Laminas\Cache\Storage\Adapter\SslContext;
use PHPUnit\Framework\TestCase;
use TypeError;

final class SslContextTest extends TestCase
{
    private const SSL_CONTEXT = [
        'peer_name'           => 'some peer name',
        'verify_peer'         => true,
        'verify_peer_name'    => true,
        'allow_self_signed'   => true,
        'cafile'              => '/some/path/to/cafile.pem',
        'capath'              => '/some/path/to/ca',
        'local_cert'          => '/some/path/to/local.certificate.pem',
        'local_pk'            => '/some/path/to/local.key',
        'passphrase'          => 'secret',
        'verify_depth'        => 10,
        'ciphers'             => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:" .
"ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:" .
"DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:" .
"ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:" .
"ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:" .
"DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:" .
"AES256-GCM-SHA384:AES128:AES256:HIGH:!SSLv2:!aNULL:!eNULL:!EXPORT:!DES:!MD5:!RC4:!ADH',
        'SNI_enabled'         => true,
        'disable_compression' => true,
        'peer_fingerprint'    => ['md5' => 'some fingerprint'],
        'security_level'      => 5,
    ];

    public function testWillNotGenerateContextIfNoneProvided(): void
    {
        $context = new SslContext();
        self::assertSame([], $context->toSslContextArray());
    }

    public function testSetFromArraySetsPropertiesCorrectly(): void
    {
        $context = SslContext::fromSslContextArray(self::SSL_CONTEXT);

        self::assertSame(self::SSL_CONTEXT['peer_name'], $context->expectedPeerName);
        self::assertSame(self::SSL_CONTEXT['verify_peer'], $context->verifyPeer);
        self::assertSame(self::SSL_CONTEXT['verify_peer_name'], $context->verifyPeerName);
        self::assertSame(self::SSL_CONTEXT['allow_self_signed'], $context->allowSelfSignedCertificates);
        self::assertSame(self::SSL_CONTEXT['cafile'], $context->certificateAuthorityFile);
        self::assertSame(self::SSL_CONTEXT['capath'], $context->certificateAuthorityPath);
        self::assertSame(self::SSL_CONTEXT['local_cert'], $context->localCertificatePath);
        self::assertSame(self::SSL_CONTEXT['local_pk'], $context->localPrivateKeyPath);
        self::assertSame(self::SSL_CONTEXT['passphrase'], $context->passphrase);
        self::assertSame(self::SSL_CONTEXT['verify_depth'], $context->verifyDepth);
        self::assertSame(self::SSL_CONTEXT['ciphers'], $context->ciphers);
        self::assertSame(self::SSL_CONTEXT['SNI_enabled'], $context->serverNameIndicationEnabled);
        self::assertSame(self::SSL_CONTEXT['disable_compression'], $context->disableCompression);
        self::assertSame(self::SSL_CONTEXT['peer_fingerprint'], $context->peerFingerprint);
        self::assertSame(self::SSL_CONTEXT['security_level'], $context->securityLevel);
    }

    public function testSetFromArrayThrowsTypeErrorWhenProvidingInvalidValueType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/\(\$verifyPeer\) must be of type \?bool, string given/');

        /** @psalm-suppress InvalidArgument We do want to verify what happens when invalid types are passed. */
        SslContext::fromSslContextArray(['verify_peer' => 'invalid type']);
    }
}
