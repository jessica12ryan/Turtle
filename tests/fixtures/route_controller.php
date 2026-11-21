<?php

namespace App\Controllers;

/** Stand-in controller used by RouterTest to observe dispatched calls. */
final class TurtleTestController
{
    /** @var list<array{0: string, 1?: string}> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function show(string $id): void
    {
        self::$calls[] = ['show', $id];
    }

    public function index(): void
    {
        self::$calls[] = ['index'];
    }
}
