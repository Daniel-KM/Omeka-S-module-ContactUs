<?php declare(strict_types=1);

namespace ContactUs\Stdlib;

/**
 * Get the ip of the client, handling the common proxy headers.
 *
 * The headers X-Forwarded-For and X-Real-IP can be written by anybody, so they
 * are used only when the direct peer is a proxy with a private or loopback
 * address, like a reverse proxy in the same host or network (Traefik, nginx,
 * Docker). Without proxy, the direct peer is a public address and the headers
 * are ignored, so a spammer cannot bypass the checks by ip.
 */
class ClientIp
{
    public static function fromServer(array $server): string
    {
        $remoteIp = (string) ($server['REMOTE_ADDR'] ?? '');
        if (!static::isValidIp($remoteIp)) {
            return '::';
        }
        if (!static::isTrusted($remoteIp)) {
            return $remoteIp;
        }

        // Each proxy appends the ip of its peer, so the client is the first ip
        // from the right that is not a trusted proxy. The left part may be
        // forged by the client.
        $forwarded = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $clientIp = $remoteIp;
            foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $ip) {
                if (!static::isValidIp($ip)) {
                    break;
                }
                $clientIp = $ip;
                if (!static::isTrusted($ip)) {
                    break;
                }
            }
            return $clientIp;
        }

        $realIp = trim((string) ($server['HTTP_X_REAL_IP'] ?? ''));
        return static::isValidIp($realIp) ? $realIp : $remoteIp;
    }

    /**
     * A private, loopback or reserved address is a trusted proxy.
     */
    protected static function isTrusted(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected static function isValidIp(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
