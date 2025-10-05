<?php
declare(strict_types=1);

namespace Rhythm\Event;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Throwable;

class RhythmExceptionListener implements EventListenerInterface
{
    /**
     * @inheritDoc
     */
    public function implementedEvents(): array
    {
        return [
            'Exception.beforeRender' => 'logException',
        ];
    }

    /**
     * Handles the Exception.beforeRender event to record exceptions in Rhythm.
     *
     * @param \Cake\Event\EventInterface<\Rhythm\Rhythm> $event The event instance.
     * @param \Throwable $exception The exception that was thrown.
     * @return void
     */
    public function logException(EventInterface $event, Throwable $exception): void
    {
        try {
            $request = $event->getData('request');
            if (!$request || !$request->getAttribute('rhythm')) {
                return;
            }

            /** @var \Rhythm\Rhythm $rhythm */
            $rhythm = $request->getAttribute('rhythm');
            $rhythm->report($exception);
        } catch (Throwable) {
        }
    }
}
