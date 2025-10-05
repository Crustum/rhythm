<?php
declare(strict_types=1);

namespace Rhythm\Test\TestCase\Utility;

use Cake\TestSuite\TestCase;
use Rhythm\Utility\Chance;
use RuntimeException;

/**
 * Chance Test Case
 */
class ChanceTest extends TestCase
{
    /**
     * Test basic chance creation with probability
     *
     * @return void
     */
    public function testBasicChanceCreation(): void
    {
        $chance = new Chance(0.5);
        $this->assertInstanceOf(Chance::class, $chance);
    }

    /**
     * Test chance creation with odds
     *
     * @return void
     */
    public function testChanceCreationWithOdds(): void
    {
        $chance = Chance::createWithOdds(1, 10);
        $this->assertInstanceOf(Chance::class, $chance);
    }

    /**
     * Test chance wins with high probability
     *
     * @return void
     */
    public function testChanceWinsWithHighProbability(): void
    {
        Chance::alwaysWin();
        $chance = new Chance(0.1);
        $this->assertTrue($chance->isWin());
        Chance::resetResultBuilder();
    }

    /**
     * Test chance loses with low probability
     *
     * @return void
     */
    public function testChanceLosesWithLowProbability(): void
    {
        Chance::alwaysLose();
        $chance = new Chance(0.9);
        $this->assertFalse($chance->isWin());
        Chance::resetResultBuilder();
    }

    /**
     * Test chance with callbacks
     *
     * @return void
     */
    public function testChanceWithCallbacks(): void
    {
        Chance::alwaysWin();
        $chance = new Chance(0.5);
        $chance->withWinner(fn() => 'win')->withLoser(fn() => 'lose');

        $result = $chance();
        $this->assertEquals('win', $result);

        Chance::resetResultBuilder();
    }

    /**
     * Test chance runMultiple method
     *
     * @return void
     */
    public function testChanceRunMultiple(): void
    {
        Chance::setFixedResults([true, false, true]);
        $chance = new Chance(0.5);

        $results = $chance->runMultiple(3);
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]);
        $this->assertFalse($results[1]);
        $this->assertTrue($results[2]);

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with sequence
     *
     * @return void
     */
    public function testChanceWithSequence(): void
    {
        Chance::setFixedResults([true, false, true, false]);
        $chance = new Chance(0.5);

        $this->assertTrue($chance->isWin());
        $this->assertFalse($chance->isWin());
        $this->assertTrue($chance->isWin());
        $this->assertFalse($chance->isWin());

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with alwaysWin callback
     *
     * @return void
     */
    public function testChanceAlwaysWinWithCallback(): void
    {
        $callbackExecuted = false;

        Chance::alwaysWin(function () use (&$callbackExecuted): void {
            $callbackExecuted = true;
        });

        $this->assertTrue($callbackExecuted);
        Chance::resetResultBuilder();
    }

    /**
     * Test chance with alwaysLose callback
     *
     * @return void
     */
    public function testChanceAlwaysLoseWithCallback(): void
    {
        $callbackExecuted = false;

        Chance::alwaysLose(function () use (&$callbackExecuted): void {
            $callbackExecuted = true;
        });

        $this->assertTrue($callbackExecuted);
        Chance::resetResultBuilder();
    }

    /**
     * Test chance with custom result builder
     *
     * @return void
     */
    public function testChanceWithCustomResultBuilder(): void
    {
        Chance::setResultBuilder(fn() => true);
        $chance = new Chance(0.1);

        $this->assertTrue($chance->isWin());

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with invalid float probability
     *
     * @return void
     */
    public function testChanceWithInvalidFloatProbability(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Float must not be greater than 1.');

        new Chance(1.5);
    }

    /**
     * Test chance with invalid outOf value
     *
     * @return void
     */
    public function testChanceWithInvalidOutOfValue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Chance "out of" value must be greater than or equal to 1.');

        new Chance(1, 0);
    }

    /**
     * Test chance with odds-based sampling
     *
     * @return void
     */
    public function testChanceWithOddsBasedSampling(): void
    {
        Chance::setFixedResults([true, false, true, false, true]);
        $chance = Chance::createWithOdds(1, 2);

        $this->assertTrue($chance->isWin());
        $this->assertFalse($chance->isWin());
        $this->assertTrue($chance->isWin());
        $this->assertFalse($chance->isWin());
        $this->assertTrue($chance->isWin());

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with invoke method
     *
     * @return void
     */
    public function testChanceInvoke(): void
    {
        Chance::alwaysWin();
        $chance = new Chance(0.5);
        $chance->withWinner(fn($arg) => "win: {$arg}")->withLoser(fn($arg) => "lose: {$arg}");

        $result = $chance('test');
        $this->assertEquals('win: test', $result);

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with multiple arguments
     *
     * @return void
     */
    public function testChanceWithMultipleArguments(): void
    {
        Chance::alwaysWin();
        $chance = new Chance(0.5);
        $chance->withWinner(fn($a, $b, $c) => "{$a}-{$b}-{$c}")->withLoser(fn($a, $b, $c) => "lose-{$a}-{$b}-{$c}");

        $result = $chance('a', 'b', 'c');
        $this->assertEquals('a-b-c', $result);

        Chance::resetResultBuilder();
    }

    /**
     * Test chance with sequence and whenMissing callback
     *
     * @return void
     */
    public function testChanceWithSequenceAndWhenMissing(): void
    {
        $whenMissingCalled = false;

        Chance::setFixedResults([true, false], function () use (&$whenMissingCalled) {
            $whenMissingCalled = true;

            return true;
        });

        $chance = new Chance(0.5);

        $this->assertTrue($chance->isWin());
        $this->assertFalse($chance->isWin());
        $this->assertTrue($chance->isWin());
        $this->assertTrue($whenMissingCalled);

        Chance::resetResultBuilder();
    }
}
