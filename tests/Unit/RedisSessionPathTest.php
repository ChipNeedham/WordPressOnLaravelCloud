<?php

namespace Tests\Unit;

use App\Cache\RedisSessionPath;
use PHPUnit\Framework\TestCase;

class RedisSessionPathTest extends TestCase
{
    public function test_builds_path_without_password(): void
    {
        $this->assertSame(
            'tcp://127.0.0.1:6379?database=2',
            RedisSessionPath::build('127.0.0.1', 6379, 2)
        );
    }

    public function test_builds_path_with_url_encoded_password(): void
    {
        $this->assertSame(
            'tcp://10.0.0.5:6380?database=3&auth=p%40ss%2Fword',
            RedisSessionPath::build('10.0.0.5', 6380, 3, 'p@ss/word')
        );
    }

    public function test_builds_acl_path_with_username_and_tls_scheme(): void
    {
        $this->assertSame(
            'tls://cache.laravel.cloud:6379?database=0&auth[user]=application&auth[pass]=secret%2F1',
            RedisSessionPath::build('cache.laravel.cloud', 6379, 0, 'secret/1', 'application', 'tls')
        );
    }

    public function test_username_without_password_is_ignored(): void
    {
        $this->assertSame(
            'tcp://127.0.0.1:6379?database=0',
            RedisSessionPath::build('127.0.0.1', 6379, 0, '', 'application')
        );
    }
}
