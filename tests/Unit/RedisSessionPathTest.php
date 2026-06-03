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
}
