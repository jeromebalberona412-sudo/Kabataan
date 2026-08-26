<?php

namespace Tests\Unit;

use App\Rules\ValidEmailAddress;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidEmailAddressTest extends TestCase
{
    private function messagesFor(?string $email): array
    {
        $validator = Validator::make(
            ['email' => $email],
            ['email' => ValidEmailAddress::profilingRules()],
            ValidEmailAddress::profilingMessages()
        );

        return $validator->fails() ? $validator->errors()->get('email') : [];
    }

    public function test_empty_email_is_required(): void
    {
        $this->assertSame([ValidEmailAddress::MSG_REQUIRED], $this->messagesFor(''));
    }

    public function test_rejects_short_local_part(): void
    {
        $this->assertSame([ValidEmailAddress::MSG_LOCAL_MIN], $this->messagesFor('juan@gmail.com'));
        $this->assertSame([ValidEmailAddress::MSG_LOCAL_MIN], $this->messagesFor('juan@yahoo.com'));
    }

    public function test_rejects_long_local_part(): void
    {
        // 31 characters before @
        $local = str_repeat('a', 31);
        $email = $local.'@gmail.com';
        $this->assertSame(31, strlen($local));
        $this->assertSame([ValidEmailAddress::MSG_LOCAL_MAX], $this->messagesFor($email));
    }

    public function test_rejects_total_above_64(): void
    {
        // 65-character complete address (local still within 30)
        $email = str_repeat('a', 30).'@'.str_repeat('b', 30).'.com';
        $this->assertSame(65, strlen($email));
        $this->assertSame(30, strpos($email, '@'));

        $messages = $this->messagesFor($email);
        $this->assertSame([ValidEmailAddress::MSG_MAX], $messages);
    }

    public function test_accepts_valid_emails_within_limits(): void
    {
        $valid = [
            'juandel@gmail.com',
            'juandel@yahoo.com',
            'abcdef@outlook.com',
            'juandel@rtu.edu.ph',
            'juandel@up.edu.ph',
        ];

        foreach ($valid as $email) {
            $at = strpos($email, '@');
            $this->assertGreaterThanOrEqual(ValidEmailAddress::LOCAL_MIN_LENGTH, $at);
            $this->assertLessThanOrEqual(ValidEmailAddress::LOCAL_MAX_LENGTH, $at);
            $this->assertLessThanOrEqual(ValidEmailAddress::MAX_LENGTH, strlen($email));
            $this->assertSame([], $this->messagesFor($email), "Expected pass for: {$email}");
        }
    }

    public function test_rejects_unresolvable_domain(): void
    {
        $email = 'abcdef@no-such-x.invalid';
        $messages = $this->messagesFor($email);
        $this->assertNotEmpty($messages);
        $this->assertSame(ValidEmailAddress::MSG_FORMAT, $messages[0]);
    }
}
