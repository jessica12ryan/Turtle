<?php

namespace Tests\Unit;

use App\Core\Validator;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRejectsEmptyAndMissingValues(): void
    {
        $validator = new Validator();

        self::assertFalse($validator->validate(['name' => ''], ['name' => 'required']));
        self::assertSame('Name is required.', $validator->firstError('name'));

        self::assertFalse($validator->validate([], ['name' => 'required']));
        self::assertTrue($validator->hasErrors());
    }

    public function testEmailRule(): void
    {
        $validator = new Validator();

        self::assertTrue($validator->validate(['email' => 'user@example.com'], ['email' => 'email']));
        self::assertFalse($validator->validate(['email' => 'not-an-email'], ['email' => 'email']));
        self::assertSame('Email must be a valid email.', $validator->firstError('email'));
    }

    public function testIntegerLimitsMeasureStringLengthNotValue(): void
    {
        $validator = new Validator();

        // Regression: numeric-looking strings used to be compared by value.
        self::assertFalse($validator->validate(['password' => '1234567'], ['password' => 'min:8']));
        self::assertSame('Password must be at least 8 characters.', $validator->firstError('password'));

        self::assertTrue($validator->validate(['postal_code' => '12345'], ['postal_code' => 'max:20']));
        self::assertFalse($validator->validate(['postal_code' => str_repeat('x', 21)], ['postal_code' => 'max:20']));
    }

    public function testDecimalLimitsRemainNumeric(): void
    {
        $validator = new Validator();

        self::assertFalse($validator->validate(['amount' => '0'], ['amount' => 'min:0.01']));
        self::assertTrue($validator->validate(['amount' => '0.01'], ['amount' => 'min:0.01']));
        self::assertTrue($validator->validate(['amount' => '100'], ['amount' => 'max:100.00']));
        self::assertFalse($validator->validate(['amount' => '100.01'], ['amount' => 'max:100.00']));
        // Decimal limits only compare numeric values; rejecting text is the
        // job of the numeric rule, which is why callers combine the two.
        self::assertTrue($validator->validate(['amount' => 'abc'], ['amount' => 'min:0.01']));
        self::assertFalse($validator->validate(['amount' => 'abc'], ['amount' => 'numeric|min:0.01']));
    }

    public function testConfirmedRule(): void
    {
        $validator = new Validator();
        $rules = ['password' => 'required|confirmed'];

        self::assertTrue($validator->validate(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            $rules
        ));
        self::assertFalse($validator->validate(
            ['password' => 'secret123', 'password_confirmation' => 'nope'],
            $rules
        ));
        self::assertSame('Password confirmation does not match.', $validator->firstError('password'));
    }

    public function testNumericAndInRules(): void
    {
        $validator = new Validator();

        self::assertTrue($validator->validate(['amount' => '12.50'], ['amount' => 'numeric']));
        self::assertFalse($validator->validate(['amount' => 'twelve'], ['amount' => 'numeric']));
        self::assertTrue($validator->validate(['status' => 'open'], ['status' => 'in:open,closed']));
        self::assertFalse($validator->validate(['status' => 'unknown'], ['status' => 'in:open,closed']));
        self::assertSame('Status is invalid.', $validator->firstError('status'));
    }

    public function testRulesMayBeProvidedAsArrays(): void
    {
        $validator = new Validator();

        self::assertTrue($validator->validate(
            ['age' => '42'],
            ['age' => ['required', 'numeric']]
        ));
        self::assertFalse($validator->validate(
            ['age' => 'old'],
            ['age' => ['required', 'numeric']]
        ));
    }

    public function testErrorsAreResetOnEveryValidation(): void
    {
        $validator = new Validator();

        self::assertFalse($validator->validate(['name' => ''], ['name' => 'required']));
        self::assertCount(1, $validator->errors()['name']);

        self::assertTrue($validator->validate(['name' => 'Ada'], ['name' => 'required']));
        self::assertSame([], $validator->errors());
        self::assertFalse($validator->hasErrors());
        self::assertNull($validator->firstError('name'));
    }

    public function testFirstRuleFailureStopsCheckingThatField(): void
    {
        $validator = new Validator();

        self::assertFalse($validator->validate(
            ['email' => ''],
            ['email' => 'required|email']
        ));
        self::assertSame(['Email is required.'], $validator->errors()['email']);
    }

    public function testUnknownRulesAreIgnored(): void
    {
        $validator = new Validator();

        self::assertTrue($validator->validate(['field' => 'anything'], ['field' => 'not_a_real_rule']));
    }
}
