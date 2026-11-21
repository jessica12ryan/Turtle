<?php

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Validator;

final class ValidatorDatabaseTest extends IntegrationTestCase
{
    public function testUniqueRuleRejectsActiveDuplicateButIgnoresArchivedRows(): void
    {
        $id = $this->insertUser('duplicate@example.com');
        $validator = new Validator();
        $rules = ['email' => 'required|email|unique:users,email'];

        self::assertFalse($validator->validate(['email' => 'duplicate@example.com'], $rules));
        self::assertSame('Email already exists.', $validator->firstError('email'));

        Database::execute('UPDATE users SET archived_at = NOW() WHERE id = ?', [$id]);

        self::assertTrue($validator->validate(['email' => 'duplicate@example.com'], $rules));
    }

    public function testUniqueRuleHonoursTheExceptParameter(): void
    {
        $id = $this->insertUser('self@example.com');
        $validator = new Validator();

        self::assertTrue($validator->validate(
            ['email' => 'self@example.com'],
            ['email' => 'unique:users,email,' . $id]
        ));
        self::assertFalse($validator->validate(
            ['email' => 'self@example.com'],
            ['email' => 'unique:users,email']
        ));
    }

    public function testExistsRule(): void
    {
        $this->insertUser('known@example.com');
        $validator = new Validator();
        $rules = ['email' => 'exists:users,email'];

        self::assertTrue($validator->validate(['email' => 'known@example.com'], $rules));
        self::assertFalse($validator->validate(['email' => 'unknown@example.com'], $rules));
        self::assertSame('Email does not exist.', $validator->firstError('email'));
    }
}
