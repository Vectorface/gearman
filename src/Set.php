<?php

namespace Vectorface\Gearman;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Interface for Danga's Gearman job scheduling system.
 *
 * PHP version 5.3.0+
 *
 * LICENSE: This source file is subject to the New BSD license that is
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php. If you did not receive
 * a copy of the New BSD License and are unable to obtain it through the web,
 * please send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Net
 *
 * @author    Joe Stump <joe@joestump.net>
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 *
 * @version   CVS: $Id$
 *
 * @link      http://pear.php.net/package/Net_Gearman
 * @link      http://www.danga.com/gearman/
 */

/**
 * A class for creating sets of tasks.
 *
 * <code>
 * <?php
 * require_once 'Net/Gearman/Client.php';
 *
 * // This is the callback function for our tasks
 * function echoResult($result) {
 *     echo "The result was: {$result}\n";
 * }
 *
 * // Job name is the key, arguments to job are in the value array
 * $jobs = array(
 *     'AddTwoNumbers' => ['1', '2'],
 *     'Multiply' => ['3', '4']
 * );
 *
 * $set = new \Vectorface\Gearman\Set();
 * foreach ($jobs as $job => $args) {
 *     $task = new \Vectorface\Gearman\Task($job, $args);
 *     $task->attachCallback('echoResult');
 *     $set->addTask($task);
 * }
 *
 * $client = new \Vectorface\Gearman\Client([
 *     '127.0.0.1:7003', '127.0.0.1:7004'
 * ]);
 *
 * $client->runSet($set);
 *
 * ?>
 * </code>
 *
 * @category  Net
 *
 * @author    Joe Stump <joe@joestump.net>
 * @copyright 2007-2008 Digg.com, Inc.
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD License
 *
 * @version   Release: @package_version@
 *
 * @link      http://www.danga.com/gearman/
 * @see       \Vectorface\Gearman\Job\CommonJob, Worker
 */
class Set implements IteratorAggregate, Countable
{
    /** Tasks count */
    public int $tasksCount = 0;

    /** @var Task[] Tasks to run */
    public array $tasks = [];

    /** Handle to task mapping */
    public array $handles = [];

    /** Callback registered for set */
    protected mixed $callback = null;

    /**
     * @param array $tasks Array of tasks to run
     * @see Task
     */
    public function __construct(array $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->addTask($task);
        }
    }

    /**
     * Add a task to the set.
     * @see Task, Set
     */
    public function addTask(Task $task)
    {
        if (!isset($this->tasks[$task->uniq])) {
            $this->tasks[$task->uniq] = $task;
            $this->tasksCount++;
        }
    }

    /**
     * Get a task.
     *
     * @throws Exception
     */
    public function getTask(string $handle): Task
    {
        if (!isset($this->handles[$handle])) {
            throw new Exception('Unknown handle');
        }

        if (!isset($this->tasks[$this->handles[$handle]])) {
            throw new Exception('No task by that handle');
        }

        return $this->tasks[$this->handles[$handle]];
    }

    /**
     * Is this set finished running?
     *
     * This function will return true if all the tasks in the set have
     * finished running. If they have we also run the set callbacks if
     * there is one.
     */
    public function finished(): bool
    {
        if ($this->tasksCount !== 0) {
            return false;
        }

        if (isset($this->callback)) {
            $results = [];
            foreach ($this->tasks as $task) {
                $results[] = $task->result;
            }

            call_user_func($this->callback, $results);
        }

        return true;
    }

    /**
     * Attach a callback to this set.
     *
     * @throws Exception
     */
    public function attachCallback(callable $callback)
    {
        if (!is_callable($callback)) {
            throw new Exception('Invalid callback specified');
        }
        $this->callback = $callback;
    }

    /**
     * Get the iterator.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->tasks);
    }

    /**
     * Get the task count.
     *
     * @see Countable::count()
     */
    public function count(): int
    {
        return $this->tasksCount;
    }
}
