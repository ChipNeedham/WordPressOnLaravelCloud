<?php

namespace App\Cache;

class RedisSessionPath
{
    /**
     * Build a phpredis session.save_path (tcp://host:port?database=N[&auth=...]).
     */
    public static function build(string $host, int $port, int $db, string $password = ''): string
    {
        $path = "tcp://{$host}:{$port}?database={$db}";

        if ($password !== '') {
            $path .= '&auth=' . rawurlencode($password);
        }

        return $path;
    }
}
