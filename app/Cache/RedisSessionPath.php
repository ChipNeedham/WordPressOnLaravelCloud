<?php

namespace App\Cache;

class RedisSessionPath
{
    /**
     * Build a phpredis session.save_path:
     *   {scheme}://host:port?database=N[&auth=...]
     *
     * With an ACL username (managed Valkey), phpredis expects the credential as
     * auth[user]/auth[pass]; with only a password it uses the legacy auth=.
     */
    public static function build(
        string $host,
        int $port,
        int $db,
        string $password = '',
        string $username = '',
        string $scheme = 'tcp',
    ): string {
        $path = "{$scheme}://{$host}:{$port}?database={$db}";

        if ($username !== '' && $password !== '') {
            $path .= '&auth[user]=' . rawurlencode($username) . '&auth[pass]=' . rawurlencode($password);
        } elseif ($password !== '') {
            $path .= '&auth=' . rawurlencode($password);
        }

        return $path;
    }
}
