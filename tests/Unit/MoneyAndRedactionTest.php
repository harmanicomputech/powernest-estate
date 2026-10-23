<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\Redactor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyAndRedactionTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function amounts(): array
    {
        return [
            'whole' => ['5000', 500_000],
            'commas' => ['12,500.50', 1_250_050],
            'one decimal' => ['0.1', 10],
            'symbol' => ['₦ 2,000', 200_000],
            'float trap' => ['0.29', 29],
        ];
    }

    #[DataProvider('amounts')]
    public function test_parses_naira_into_kobo_without_floats(string $input, int $kobo): void
    {
        $this->assertSame($kobo, Money::parse($input));
    }

    public function test_rejects_invalid_amounts(): void
    {
        foreach (['-5', '1.234', 'abc', '1e5', ''] as $bad) {
            try {
                Money::parse($bad);
                $this->fail("Accepted {$bad}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_formats(): void
    {
        $this->assertSame('₦1,250,050.75', Money::format(125_005_075));
        $this->assertSame('₦5,000', Money::format(500_000, showKobo: false));
        $this->assertSame('5000.00', Money::toDecimalString(500_000));
        $this->assertSame('0.05', Money::toDecimalString(5));
    }

    public function test_redacts_secrets_and_masks_personal_data(): void
    {
        $out = Redactor::redact(['PassWord' => 'x', 'nested' => ['Authorization' => 'Bearer y', 'account_number' => '7032739249'], 'email_address' => 'ada@example.com', 'amount' => 100]);

        $this->assertSame('[REDACTED]', $out['PassWord']);
        $this->assertSame('[REDACTED]', $out['nested']['Authorization']);
        $this->assertSame('70••••••49', $out['nested']['account_number']);
        $this->assertStringNotContainsString('ada@example', $out['email_address']);
        $this->assertSame(100, $out['amount']);
    }
}
