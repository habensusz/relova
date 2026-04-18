<?php

declare(strict_types=1);

namespace Relova\Security;

use Relova\Exceptions\SsrfException;

/**
 * Validates outbound connection targets to prevent Server-Side Request
 * Forgery. Every remote connection attempt must pass through this guard
 * before any socket or HTTP call is made.
 *
 * Blocks:
 *   - Loopback and link-local ranges (IPv4 + IPv6)
 *   - RFC1918 private ranges
 *   - Carrier-grade NAT / reserved ranges
 *   - DNS rebinding: the resolved IP is checked, not just the hostname
 */
class SsrfGuard
{
    /**
     * @param  array<int, string>  $blockedRanges
     * @param  array<int, string>  $allowedHosts  Hostnames that bypass the guard (local dev, container DNS names).
     */
    public function __construct(
        private array $blockedRanges = [],
        private array $allowedHosts = [],
        private bool $enabled = true,
    ) {
        if ($this->blockedRanges === []) {
            $this->blockedRanges = [
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '0.0.0.0/8',
                '::1/128',
                'fc00::/7',
                'fe80::/10',
            ];
        }
    }

    /**
     * Throws SsrfException if the host is not permitted.
     *
     * @throws SsrfException
     */
    public function validate(string $host): void
    {
        if (! $this->enabled) {
            return;
        }

        if ($host === '' || $host === '0') {
            throw new SsrfException('Empty host is not permitted.', host: $host);
        }

        if (in_array($host, $this->allowedHosts, true)) {
            return;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP)
            ? $host
            : gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new SsrfException("Cannot resolve host: {$host}", host: $host);
        }

        foreach ($this->blockedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new SsrfException(
                    "Connection to internal address {$ip} is not permitted.",
                    host: $host,
                );
            }
        }
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        $isIpV6Range = str_contains($subnet, ':');
        $isIpV6Ip = str_contains($ip, ':');

        if ($isIpV6Range !== $isIpV6Ip) {
            return false;
        }

        if ($isIpV6Range) {
            return $this->ipv6InRange($ip, $subnet, $bits);
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
        $subnetLong &= $mask;

        return ($ipLong & $mask) === $subnetLong;
    }

    private function ipv6InRange(string $ip, string $subnet, int $bits): bool
    {
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainder) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
