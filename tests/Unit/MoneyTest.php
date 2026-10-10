<?php

declare(strict_types=1);

namespace PrroCloud\Prro\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrroCloud\Prro\Exception\InvalidArgumentException;
use PrroCloud\Prro\Internal\DateTimes;
use PrroCloud\Prro\Money\Amount;
use PrroCloud\Prro\Money\Kopecks;

final class MoneyTest extends TestCase
{
    /**
     * @return iterable<array{int, string}>
     */
    public static function fromKopCases(): iterable
    {
        yield [0, '0.00'];
        yield [5, '0.05'];
        yield [6500, '65.00'];
        yield [25990, '259.90'];
        yield [-1230, '-12.30'];
        yield [-5, '-0.05'];
        yield [PHP_INT_MAX, '92233720368547758.07'];
        yield [PHP_INT_MIN, '-92233720368547758.08'];
    }

    #[DataProvider('fromKopCases')]
    public function testAmountFromKop(int $kop, string $want): void
    {
        self::assertSame($want, (string) Amount::fromKop($kop));
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function validAmounts(): iterable
    {
        yield ['65.00', 6500];
        yield ['65.5', 6550];
        yield ['65', 6500];
        yield ['0.05', 5];
        yield ['-12.30', -1230];
        yield ['007.10', 710];
        yield ['92233720368547757.99', 9223372036854775799];
    }

    #[DataProvider('validAmounts')]
    public function testAmountKop(string $amount, int $want): void
    {
        self::assertSame($want, Amount::of($amount)->kop());
        self::assertSame($amount, (string) Amount::of($amount), 'value is kept as given');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function invalidAmounts(): iterable
    {
        foreach (['', '-', '.5', '1.', '1.234', '1,50', '+1', '1e3', ' 1', '--1', '99999999999999999999', '92233720368547758.00', '0.30000000000000004'] as $a) {
            yield $a => [$a];
        }
    }

    #[DataProvider('invalidAmounts')]
    public function testAmountRejects(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        Amount::of($amount);
    }

    public function testAmountRoundTrip(): void
    {
        foreach ([0, 1, -1, 6500, intdiv(PHP_INT_MAX, 100), intdiv(PHP_INT_MIN, 100)] as $kop) {
            self::assertSame($kop, Amount::fromKop($kop)->kop());
        }
        for ($i = 0; $i < 1000; $i++) {
            $kop = random_int(-(PHP_INT_MAX - 99), PHP_INT_MAX - 99);
            self::assertSame($kop, Amount::fromKop($kop)->kop(), (string) $kop);
        }
    }

    public function testAmountFromResponseIsLenient(): void
    {
        $a = Amount::fromResponse('12.345');
        self::assertSame('12.345', (string) $a);
        self::assertSame('"12.345"', json_encode($a));
        $this->expectException(InvalidArgumentException::class);
        $a->kop();
    }

    public function testAmountOfKeepsInstance(): void
    {
        $a = new Amount('1.00');
        self::assertSame($a, Amount::of($a));
    }

    public function testKopecks(): void
    {
        self::assertSame('150.0000', (string) Kopecks::of('150.0000'));
        self::assertSame('"50"', json_encode(new Kopecks('50')));
        self::assertSame('1.23456', (string) Kopecks::fromResponse('1.23456'));
        $this->expectException(InvalidArgumentException::class);
        new Kopecks('1.23456');
    }

    public function testDate(): void
    {
        $kyiv = new \DateTimeImmutable('2026-07-14 23:30:00', new \DateTimeZone('+03:00'));
        self::assertSame('2026-07-14', DateTimes::date($kyiv), 'date in its own time zone');
        self::assertSame('2026-07-14', DateTimes::date('2026-07-14'));
        foreach (['2026-7-14', '2026-02-30', '14.07.2026', '2026-07-14T00:00:00Z', ''] as $bad) {
            try {
                DateTimes::date($bad);
                self::fail("accepted {$bad}");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testParseNanoseconds(): void
    {
        $t = DateTimes::parse('2026-08-03T01:07:54.123456789Z');
        self::assertSame('2026-08-03T01:07:54.123456+00:00', $t->format('Y-m-d\TH:i:s.uP'));

        $t = DateTimes::parse('2026-08-03T04:07:54.5+03:00');
        self::assertSame('2026-08-03T01:07:54.500000+00:00', $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'));

        self::assertSame('2026-08-03T01:07:54+00:00', DateTimes::parse('2026-08-03T01:07:54Z')->format('c'));

        foreach (['2026-08-03', '2026-08-03T01:07:54', 'yesterday', '2026-13-03T01:07:54Z'] as $bad) {
            try {
                DateTimes::parse($bad);
                self::fail("accepted {$bad}");
            } catch (\UnexpectedValueException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFormat(): void
    {
        self::assertSame('2026-09-03T18:11:00Z', DateTimes::format(new \DateTimeImmutable('2026-09-03 18:11:00', new \DateTimeZone('UTC'))));
        self::assertSame('2026-09-03T18:11:00.25+03:00', DateTimes::format(new \DateTimeImmutable('2026-09-03 18:11:00.25', new \DateTimeZone('Europe/Kyiv'))));
    }
}
