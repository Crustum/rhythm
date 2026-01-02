<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Event;

use Cake\Database\Log\LoggedQuery;
use Cake\Event\Event;

/**
 * Slow Query Event
 *
 * Event dispatched when a slow database query is detected.
 *
 * @extends \Cake\Event\Event<\Crustum\Rhythm\Rhythm>
 */
class SlowQueryEvent extends Event
{
    /**
     * Constructor.
     *
     * @param string $sql SQL query
     * @param int $duration Duration in milliseconds
     * @param string|null $location Query location
     * @param \Cake\Database\Log\LoggedQuery|null $loggedQuery Original logged query
     */
    public function __construct(
        string $sql,
        int $duration,
        ?string $location = null,
        ?LoggedQuery $loggedQuery = null,
    ) {
        parent::__construct('Rhythm.slowQuery', null, [
            'sql' => $sql,
            'duration' => $duration,
            'location' => $location,
            'loggedQuery' => $loggedQuery,
        ]);
    }

    /**
     * Get the SQL query.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->getData('sql');
    }

    /**
     * Get the query duration.
     *
     * @return int
     */
    public function getDuration(): int
    {
        return $this->getData('duration');
    }

    /**
     * Get the query location.
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->getData('location');
    }

    /**
     * Get the original logged query.
     *
     * @return \Cake\Database\Log\LoggedQuery|null
     */
    public function getLoggedQuery(): ?LoggedQuery
    {
        return $this->getData('loggedQuery');
    }
}
