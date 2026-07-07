<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Event;

use Cake\Event\Event;
use Cake\I18n\FrozenTime;

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
     * @param \Cake\I18n\FrozenTime $timestamp The timestamp of the event.
     * @param mixed $subject The event subject (optional)
     */
    public function __construct(FrozenTime $timestamp, mixed $subject = null)
    {
        parent::__construct(self::class, $subject, [
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Get the time of the event.
     *
     * @return \Cake\I18n\FrozenTime
     */
    public function getTimestamp(): FrozenTime
    {
        return $this->getData('timestamp');
    }
}
