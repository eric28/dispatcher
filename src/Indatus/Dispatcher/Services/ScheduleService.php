<?php namespace Indatus\Dispatcher\Services;

/**
 * This file is part of Dispatcher
 *
 * (c) Ben Kuhl <bkuhl@indatus.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use App;
use Artisan;
use Indatus\Dispatcher\Scheduling\Schedulable;
use Indatus\Dispatcher\Scheduling\ScheduledCommandInterface;
use Indatus\Dispatcher\Scheduling\ScheduleException;
use Indatus\Dispatcher\Table;
use Symfony\Component\Process\Exception\InvalidArgumentException;

abstract class ScheduleService
{

    /** @var \Indatus\Dispatcher\Table */
    protected $table;

    public function __construct(Table $table)
    {
        $this->table = $table;
    }

    /**
     * Determine if a command is due to be run
     *
     * @param \Indatus\Dispatcher\Scheduling\Schedulable $scheduler
     *
     * @return bool
     */
    abstract public function isDue(Schedulable $scheduler);

    /**
     * Get all commands that are scheduled
     *
     * @return array
     */
    public function getScheduledCommands()
    {
        $scheduledCommands = array();
        foreach (Artisan::all() as $command) {
            if ($command instanceOf ScheduledCommandInterface) {
                $scheduledCommands[] = $command;
            }
        }

        return $scheduledCommands;
    }

    /**
     * Get all commands that are due to be run
     * @throws \InvalidArgumentException
     * @return \Indatus\Dispatcher\Queue
     */
    public function getQueue()
    {
        /** @var \Indatus\Dispatcher\Queue $queue */
        $queue = App::make('Indatus\Dispatcher\Queue');

        /** @var \Indatus\Dispatcher\Scheduling\Schedulable $queue */
        $scheduler = App::make('Indatus\Dispatcher\Scheduling\Schedulable');

        foreach ($this->getScheduledCommands() as $command) {

            //could be multiple schedules based on arguments
            $schedules = $command->schedule($scheduler);
            if (!is_array($schedules)) {
                $schedules = array($schedules);
            }

            foreach ($schedules as $schedule) {
                if (($schedule instanceOf Schedulable) === false) {
                    throw new \InvalidArgumentException('Schedule for "'.$command->getName().'" is not an instance of Schedulable');
                }

                if ($this->isDue($schedule)) {
                    /** @var \Indatus\Dispatcher\QueueItem $queueItem */
                    $queueItem = App::make('Indatus\Dispatcher\QueueItem');

                    $queueItem->setCommand($command);
                    $queueItem->setScheduler($schedule);

                    $queue->add($queueItem);
                }
            }
        }

        return $queue;
    }

    /**
     * Review scheduled commands schedule, active status, etc.
     * @return void
     */
    abstract public function printSummary();

}