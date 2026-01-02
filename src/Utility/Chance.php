<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Utility;

use RuntimeException;

/**
 * Chance utility for probabilistic sampling.
 *
 * This class provides chance-based functionality for determining
 * whether events should be sampled based on probability rates.
 *
 * Usage:
 * ```php
 * $chance = new Chance(0.1); // 10% chance
 * if ($chance->isWin()) {
 *     // Sample this event
 * }
 *
 * // Or with odds
 * $chance = Chance::createWithOdds(1, 10); // 1 in 10 chance
 * if ($chance->isWin()) {
 *     // Sample this event
 * }
 * ```
 */
class Chance
{
    /**
     * The number of expected wins.
     *
     * @var float|int
     */
    protected int|float $chances;

    /**
     * The number of potential opportunities to win.
     *
     * @var int|null
     */
    protected ?int $outOf = null;

    /**
     * The winning callback.
     *
     * @var callable|null
     */
    protected $winner;

    /**
     * The losing callback.
     *
     * @var callable|null
     */
    protected $loser;

    /**
     * The builder that should be used to generate results.
     *
     * @var callable|null
     */
    protected static $resultBuilder;

    /**
     * Create a new Chance instance.
     *
     * @param float|int $chances The probability (0.0 to 1.0) or number of chances
     * @param int|null $outOf The total number of opportunities (for odds-based sampling)
     * @throws \RuntimeException When invalid parameters are provided
     */
    public function __construct(int|float $chances, ?int $outOf = null)
    {
        if ($outOf === null && is_float($chances) && $chances > 1) {
            throw new RuntimeException('Float must not be greater than 1.');
        }

        if ($outOf !== null && $outOf < 1) {
            throw new RuntimeException('Chance "out of" value must be greater than or equal to 1.');
        }

        $this->chances = $chances;
        $this->outOf = $outOf;
    }

    /**
     * Create a new chance instance with odds.
     *
     * @param float|int $chances The number of chances
     * @param int|null $outOf The total number of opportunities
     * @return self
     */
    public static function createWithOdds(int|float $chances, ?int $outOf = null): self
    {
        return new self($chances, $outOf);
    }

    /**
     * Set the winner callback.
     *
     * @param callable $callback The callback to execute when chance wins
     * @return $this
     */
    public function withWinner(callable $callback)
    {
        $this->winner = $callback;

        return $this;
    }

    /**
     * Set the loser callback.
     *
     * @param callable $callback The callback to execute when chance loses
     * @return $this
     */
    public function withLoser(callable $callback)
    {
        $this->loser = $callback;

        return $this;
    }

    /**
     * Run the chance.
     *
     * @param mixed ...$args Arguments to pass to the callback
     * @return mixed The result of the callback
     */
    public function __invoke(mixed ...$args): mixed
    {
        return $this->runCallback(...$args);
    }

    /**
     * Run the chance multiple times.
     *
     * @param int|null $times The number of times to run the chance
     * @return mixed|array The result(s) of the callback(s)
     */
    public function runMultiple(?int $times = null): mixed
    {
        if ($times === null) {
            return $this->runCallback();
        }

        $results = [];

        for ($i = 0; $i < $times; $i++) {
            $results[] = $this->runCallback();
        }

        return $results;
    }

    /**
     * Run the winner or loser callback, randomly.
     *
     * @param mixed ...$args Arguments to pass to the callback
     * @return mixed The result of the callback
     */
    protected function runCallback(mixed ...$args): mixed
    {
        return $this->isWin()
            ? ($this->winner ?? fn() => true)(...$args)
            : ($this->loser ?? fn() => false)(...$args);
    }

    /**
     * Determine if the chance "wins" or "loses".
     *
     * @return bool True if the chance wins, false otherwise
     */
    public function isWin(): bool
    {
        return static::resultBuilder()($this->chances, $this->outOf);
    }

    /**
     * The builder that determines the chance result.
     *
     * @return callable
     */
    protected static function resultBuilder(): callable
    {
        return static::$resultBuilder ?? fn($chances, $outOf) => $outOf === null
            ? random_int(0, PHP_INT_MAX) / PHP_INT_MAX <= $chances
            : random_int(1, $outOf) <= $chances;
    }

    /**
     * Force the chance to always result in a win.
     *
     * @param callable|null $callback Optional callback to execute
     * @return void
     */
    public static function alwaysWin(?callable $callback = null): void
    {
        self::setResultBuilder(fn() => true);

        if ($callback === null) {
            return;
        }

        $callback();

        static::resetResultBuilder();
    }

    /**
     * Force the chance to always result in a lose.
     *
     * @param callable|null $callback Optional callback to execute
     * @return void
     */
    public static function alwaysLose(?callable $callback = null): void
    {
        self::setResultBuilder(fn() => false);

        if ($callback === null) {
            return;
        }

        $callback();

        static::resetResultBuilder();
    }

    /**
     * Set the sequence that will be used to determine chance results.
     *
     * @param array $sequence Array of boolean results
     * @param callable|null $whenMissing Callback when sequence is exhausted
     * @return void
     */
    public static function setFixedResults(array $sequence, ?callable $whenMissing = null): void
    {
        static::setResultSequence($sequence, $whenMissing);
    }

    /**
     * Set the sequence that will be used to determine chance results.
     *
     * @param array $sequence Array of boolean results
     * @param callable|null $whenMissing Callback when sequence is exhausted
     * @return void
     */
    public static function setResultSequence(array $sequence, ?callable $whenMissing = null): void
    {
        $next = 0;

        $whenMissing ??= function ($chances, $outOf) use (&$next) {
            $builderCache = static::$resultBuilder;

            static::$resultBuilder = null;

            $result = static::resultBuilder()($chances, $outOf);

            static::$resultBuilder = $builderCache;

            $next++;

            return $result;
        };

        static::setResultBuilder(function ($chances, $outOf) use (&$next, $sequence, $whenMissing) {
            if (array_key_exists($next, $sequence)) {
                return $sequence[$next++];
            }

            return $whenMissing($chances, $outOf);
        });
    }

    /**
     * Indicate that the chance results should be determined normally.
     *
     * @return void
     */
    public static function resetResultBuilder(): void
    {
        static::$resultBuilder = null;
    }

    /**
     * Set the builder that should be used to determine the chance results.
     *
     * @param callable $builder The builder function
     * @return void
     */
    public static function setResultBuilder(callable $builder): void
    {
        self::$resultBuilder = $builder;
    }
}
