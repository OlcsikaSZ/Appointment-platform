<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HungarianValidationMessageTest extends TestCase
{
    public function test_password_confirmation_validation_is_human_readable_in_hungarian(): void
    {
        app()->setLocale('hu');

        $validator = Validator::make([
            'password' => 'Admin1234!',
            'password_confirmation' => 'Admin1234',
        ], [
            'password' => ['required', 'confirmed'],
        ]);

        $message = $validator->errors()->first('password');

        $this->assertSame('A(z) jelszó megerősítése nem egyezik.', $message);
        $this->assertStringNotContainsString('validation.', $message);
    }
}
