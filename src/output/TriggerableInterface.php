<?php
namespace phphound\output;

/**
 * Methods for triggering output events (analysis started, analyses ended, etc.).
 */
interface TriggerableInterface
{
    /**
     * Output event messages.
     * @param int $eventType Analyser class event constant.
     * @param mixed $data Optional message.
     * @return void
     */
    public function trigger(int $eventType, $data = null) : void;
}
