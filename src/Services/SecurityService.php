<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Exceptions\SsrfException;

/**
 * Security service — SSRF protection, host validation.
 * Prevents connections to internal IP ranges and reserved address spaces.
 */
class SecurityService
{
    /**
     * Validate that a host is not in a blocked IP range.
     *
     * @throws SsrfException If host resolves to a blocked IP
     */
    public function validateHost(?string $host): void
    {
        if (empty($host)) {
            return; // File-based connectors have no host
        }

        // Resolve hostname to IP(s)
        $ips = $this->resolveHost($host);

        $blockedRanges = config('relova.blocked_ip_ranges', []);

        foreach ($ips as $ip) {
            if ($this->isIpBlocked($ip, $blockedRanges)) {
                throw new SsrfException(
                    message: "Connection to host '{$host}' (resolved to {$ip}) is blocked by SSRF protection policy. ".
                        'Internal and reserved IP ranges are not allowed.',
                    host: $host,
                );
            }
        }
    }

    /**
     * Resolve a hostname to its IP addresses.
     *
     * @return array<string>
     */
    protected function resolveHost(string $host): array
    {
        // If it's already an IP, return as-is
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || empty($records)) {
            // Can't resolve — block by default for safety
            throw new SsrfException(
                message: "Cannot resolve hostname '{$host}'. Connection blocked for security.",
                host: $host,
            );
        }

        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    /**
     * Check if an IP falls within any blocked CIDR range.
     *
     * @param  array<string>  $blockedRanges  CIDR notation ranges
     */
    protected function isIpBlocked(string $ip, array $blockedRanges): bool
    {
        foreach ($blockedRanges as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP address falls within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        // Handle IPv6
        if (str_contains($cidr, ':')) {
            return $this->ipv6InCidr($ip, $cidr);
        }

        // IPv4
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if an IPv6 address falls within a CIDR range.
     */
    protected function ipv6InCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remainingBits = $mask % 8;

        // Compare full bytes
        for ($i = 0; $i < $fullBytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Compare remaining bits
        if ($remainingBits > 0 && $fullBytes < strlen($ipBin)) {
            $maskByte = 0xFF << (8 - $remainingBits);

            return (ord($ipBin[$fullBytes]) & $maskByte) === (ord($subnetBin[$fullBytes]) & $maskByte);
        }

        return true;
    }
}
