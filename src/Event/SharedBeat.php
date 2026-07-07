<?php
declare(strict_types=1);

namespace Crustum\Rhythm\Event;

use Cake\Event\Event;
use Cake\I18n\FrozenTime;

/**
 * Shared Beat Event
 *
 * Represents a shared metric beat event.
 *
 * @extends \Cake\Event\Event<\Crustum\Rhythm\Rhythm>
 */
class SharedBeat extends Event
{
    /**
     * Constructor.
     *
     * @param \Cake\I18n\FrozenTime $timestamp The timestamp of the event.
     * @param string $instance The instance identifier.
     * @param mixed $subject The event subject (optional)
     */
    public function __construct(FrozenTime $timestamp, string $instance, mixed $subject = null)
    {
        parent::__construct(self::class, $subject, [
            'timestamp' => $timestamp,
            'instance' => $instance,
        ]);
    }

    /**
     * Get the timestamp of the event.
     *
     * @return \Cake\I18n\FrozenTime
     */
    public function getTimestamp(): FrozenTime
    {
        return $this->getData('timestamp');
    }

    /**
     * Get the instance identifier.
     *
     * @return string
     */
    public function getInstance(): string
    {
        return $this->getData('instance');
    }
}
