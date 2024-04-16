<?php

declare(strict_types=1);

namespace Laminas\Cache\Storage\Adapter;

use SensitiveParameter;

/**
 * Class containing the SSL context options in its fields.
 *
 * @link https://www.php.net/manual/en/context.ssl.php
 *
 * @psalm-type SSLContextArrayShape = array{
 *     peer_name?: non-empty-string,
 *     verify_peer?: bool,
 *     verify_peer_name?: bool,
 *     allow_self_signed?: bool,
 *     cafile?: non-empty-string,
 *     capath?: non-empty-string,
 *     local_cert?: non-empty-string,
 *     local_pk?: non-empty-string,
 *     passphrase?: non-empty-string,
 *     verify_depth?: non-negative-int,
 *     ciphers?: non-empty-string,
 *     SNI_enabled?: bool,
 *     disable_compression?: bool,
 *     peer_fingerprint?: non-empty-string|array<non-empty-string,non-empty-string>,
 *     security_level?: non-negative-int
 * }
 */
final class SslContext
{
    /**
     * @param non-empty-string|null $expectedPeerName
     * @param non-empty-string|null $certificateAuthorityFile
     * @param non-empty-string|null $certificateAuthorityPath
     * @param non-empty-string|null $localCertificatePath
     * @param non-empty-string|null $localPrivateKeyPath
     * @param non-empty-string|null $passphrase
     * @param non-negative-int|null $verifyDepth
     * @param non-empty-string|null $ciphers
     * @param non-empty-string|array<non-empty-string,non-empty-string>|null $peerFingerprint
     * @param non-negative-int|null $securityLevel
     */
    public function __construct(
        /**
         * Peer name to be used.
         * If this value is not set, then the name is guessed based on the hostname used when opening the stream.
         */
        public readonly ?string $expectedPeerName = null,
        /**
         * Require verification of SSL certificate used.
         */
        public readonly ?bool $verifyPeer = null,
        /**
         * Require verification of peer name.
         */
        public readonly ?bool $verifyPeerName = null,
        /**
         * Allow self-signed certificates. Requires verifyPeer.
         */
        public readonly ?bool $allowSelfSignedCertificates = null,
        /**
         * Location of Certificate Authority file on local filesystem which should be used with the verifyPeer
         * context option to authenticate the identity of the remote peer.
         */
        public readonly ?string $certificateAuthorityFile = null,
        /**
         * If cafile is not specified or if the certificate is not found there, the directory pointed to by capath is
         * searched for a suitable certificate. capath must be a correctly hashed certificate directory.
         */
        public readonly ?string $certificateAuthorityPath = null,
        /**
         * Path to local certificate file on filesystem. It must be a PEM encoded file which contains your certificate
         * and private key. It can optionally contain the certificate chain of issuers.
         * The private key also may be contained in a separate file specified by localPk.
         */
        public readonly ?string $localCertificatePath = null,
        /**
         * Path to local private key file on filesystem in case of separate files for certificate (localCert)
         * and private key.
         */
        public readonly ?string $localPrivateKeyPath = null,
        /**
         * Passphrase with which your localCert file was encoded.
         */
        #[SensitiveParameter]
        public readonly ?string $passphrase = null,
        /**
         * Abort if the certificate chain is too deep.
         * If not set, defaults to no verification.
         */
        public readonly ?int $verifyDepth = null,
        /**
         * Sets the list of available ciphers. The format of the string is described in
         * https://www.openssl.org/docs/manmaster/man1/ciphers.html#CIPHER-LIST-FORMAT
         */
        public readonly ?string $ciphers = null,
        /**
         * If set to true server name indication will be enabled. Enabling SNI allows multiple certificates on the same
         * IP address.
         * If not set, will automatically be enabled if SNI support is available.
         */
        public readonly ?bool $serverNameIndicationEnabled = null,
        /**
         * If set, disable TLS compression. This can help mitigate the CRIME attack vector.
         */
        public readonly ?bool $disableCompression = null,
        /**
         * Aborts when the remote certificate digest doesn't match the specified hash.
         *
         * When a string is used, the length will determine which hashing algorithm is applied,
         * either "md5" (32) or "sha1" (40).
         *
         * When an array is used, the keys indicate the hashing algorithm name and each corresponding
         * value is the expected digest.
         */
        public readonly array|string|null $peerFingerprint = null,
        /**
         * Sets the security level. If not specified the library default security level is used. The security levels are
         * described in https://www.openssl.org/docs/man1.1.1/man3/SSL_CTX_get_security_level.html.
         */
        public readonly ?int $securityLevel = null,
    ) {
    }

    /**
     * @param SSLContextArrayShape $context
     */
    public static function fromSslContextArray(array $context): self
    {
        return new self(
            $context['peer_name'] ?? null,
            $context['verify_peer'] ?? null,
            $context['verify_peer_name'] ?? null,
            $context['allow_self_signed'] ?? null,
            $context['cafile'] ?? null,
            $context['capath'] ?? null,
            $context['local_cert'] ?? null,
            $context['local_pk'] ?? null,
            $context['passphrase'] ?? null,
            $context['verify_depth'] ?? null,
            $context['ciphers'] ?? null,
            $context['SNI_enabled'] ?? null,
            $context['disable_compression'] ?? null,
            $context['peer_fingerprint'] ?? null,
            $context['security_level'] ?? null,
        );
    }

    /**
     * @return SSLContextArrayShape
     */
    public function toSslContextArray(): array
    {
        $context = [];
        if ($this->expectedPeerName !== null) {
            $context['peer_name'] = $this->expectedPeerName;
        }

        if ($this->verifyPeer !== null) {
            $context['verify_peer'] = $this->verifyPeer;
        }

        if ($this->verifyPeerName !== null) {
            $context['verify_peer_name'] = $this->verifyPeerName;
        }

        if ($this->allowSelfSignedCertificates !== null) {
            $context['allow_self_signed'] = $this->allowSelfSignedCertificates;
        }

        if ($this->certificateAuthorityFile !== null) {
            $context['cafile'] = $this->certificateAuthorityFile;
        }

        if ($this->certificateAuthorityPath !== null) {
            $context['capath'] = $this->certificateAuthorityPath;
        }

        if ($this->localCertificatePath !== null) {
            $context['local_cert'] = $this->localCertificatePath;
        }

        if ($this->localPrivateKeyPath !== null) {
            $context['local_pk'] = $this->localPrivateKeyPath;
        }

        if ($this->passphrase !== null) {
            $context['passphrase'] = $this->passphrase;
        }

        if ($this->verifyDepth !== null) {
            $context['verify_depth'] = $this->verifyDepth;
        }

        if ($this->ciphers !== null) {
            $context['ciphers'] = $this->ciphers;
        }

        if ($this->serverNameIndicationEnabled !== null) {
            $context['SNI_enabled'] = $this->serverNameIndicationEnabled;
        }

        if ($this->disableCompression !== null) {
            $context['disable_compression'] = $this->disableCompression;
        }

        if ($this->peerFingerprint !== null) {
            $context['peer_fingerprint'] = $this->peerFingerprint;
        }

        if ($this->securityLevel !== null) {
            $context['security_level'] = $this->securityLevel;
        }

        return $context;
    }
}
