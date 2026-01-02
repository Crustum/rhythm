<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Event;

use Cake\Event\Event;
use Cake\I18n\DateTime;

/**
 * Isolated Beat Event
 *
 * Represents an isolated metric beat event.
 *
 * @extends \Cake\Event\Event<\Crustum\Rhythm\Rhythm>
 */
class IsolatedBeat extends Event
{
    /**
     * Constructor.
     *
     * @param \Cake\I18n\DateTime $timestamp The timestamp of the event.
     * @param mixed $subject The event subject (optional)
     */
    public function __construct(DateTime $timestamp, mixed $subject = null)
    {
        parent::__construct(self::class, $subject, [
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Get the time of the event.
     *
     * @return \Cake\I18n\DateTime
     */
    public function getTimestamp(): DateTime
    {
        return $this->getData('timestamp');
    }
}
