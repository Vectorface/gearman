<?php

namespace Vectorface\Gearman;

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
 * Task class for creating Net_Gearman tasks.
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
 * @see       Set, Client
 */
class Task
{
    /** The function/job to run */
    public string $func = '';

    /** Arguments to pass to function/job */
    public array $arg = [];

    /**
     * Type of job.
     *
     * Which type of job you wish this task to be run as. Keep in mind that
     * background jobs are "fire and forget" and DO NOT return results to the
     * job server in a manner that you can actually retrieve.
     *
     * @see Task::JOB_NORMAL
     * @see Task::JOB_BACKGROUND
     * @see Task::JOB_EPOCH
     * @see Task::JOB_HIGH
     * @see Task::JOB_HIGH_BACKGROUND
     * @see Task::JOB_LOW
     * @see Task::JOB_LOW_BACKGROUND
     */
    public int $type = self::JOB_NORMAL;

    /** Handle returned from job server, @see Client */
    public string $handle = '';

    /**
     * The unique identifier for this job.
     *
     * Keep in mind that a unique job is only unique to the job server it is
     * submitted to. Gearman servers don't communicate with each other to
     * ensure a job is unique across all workers.
     *
     * That being said, Gearman does group identical jobs sent to it and runs
     * that job only once. If you send the job Sum with args 1, 2, 3 to the
     * server 10 times in a second Gearman will only run that job once and then
     * return the result 10 times.
     */
    public string $uniq = '';

    /**
     * Is this task finished?
     *
     * @var bool
     *
     * @see Set::finished()
     * @see Task::complete()
     * @see Task::fail()
     */
    public bool $finished = false;

    /**
     * The result returned from the worker.
     */
    public string|object $result = '';

    /**
     * Unix timestamp.
     *
     * This allows you to schedule a background job to run at
     * a specific moment in time
     */
    public int $epoch = 0;

    /**
     * Callbacks registered for each state.
     *
     * @see Task::attachCallback()
     * @see Task::complete()
     * @see Task::status()
     * @see Task::fail()
     */
    protected array $callback = [
        self::TASK_COMPLETE => [],
        self::TASK_FAIL => [],
        self::TASK_STATUS => [],
    ];

    /**
     * Normal job.
     *
     * Normal jobs are run against a worker with the result being returned
     * all in the same thread (e.g. Your page will sit there waiting for the
     * job to finish and return its result).
     */
    const JOB_NORMAL = 1;

    /**
     * Background job.
     *
     * Background jobs in Gearman are "fire and forget". You can check a job's
     * status periodically, but you can't get a result back from it.
     */
    const JOB_BACKGROUND = 2;

    /**
     * High priority job.
     */
    const JOB_HIGH = 3;

    /**
     * High priority, background job.
     */
    const JOB_HIGH_BACKGROUND = 4;

    /**
     * LOW priority job.
     */
    const JOB_LOW = 5;

    /**
     * Low priority, background job.
     */
    const JOB_LOW_BACKGROUND = 6;

    /**
     * Scheduled background job.
     *
     * Background jobs in Gearman are "fire and forget". You can check a job's
     * status periodically, but you can't get a result back from it.
     */
    const JOB_EPOCH = 7;

    /**
     * Callback of type complete.
     *
     * The callback provided should be run when the task has been completed. It
     * will be handed the result of the task as its only argument.
     *
     * @see Task::complete()
     */
    const TASK_COMPLETE = 1;

    /**
     * Callback of type fail.
     *
     * The callback provided should be run when the task has been reported to
     * have failed by Gearman. No arguments are provided.
     *
     * @see Task::fail()
     */
    const TASK_FAIL = 2;

    /**
     * Callback of type status.
     *
     * The callback provided should be run whenever the status of the task has
     * been updated. The numerator and denominator are passed as the only
     * two arguments.
     *
     * @see Task::status()
     */
    const TASK_STATUS = 3;

    /**
     * @param string $func Name of job to run
     * @param mixed $arg List of arguments for job
     * @param string|null $unique_id The unique id of the job
     * @param int|null $type Type of job to run task as
     * @param int $epoch Time of job to run at (unix timestamp)
     *
     * @throws Exception
     */
    public function __construct(
        string  $func,
        array   $arg,
        ?string $unique_id = null,
        ?int    $type = self::JOB_NORMAL,
        int     $epoch = 0
    ) {
        $this->func = $func;
        $this->arg = $arg;

        if ($unique_id === null) {
            $this->uniq = md5($func . serialize($arg) . $type);
        } else {
            $this->uniq = $unique_id;
        }

        $type = (int) $type;

        if ($type === self::JOB_EPOCH) {
            $this->epoch = $epoch;
        }

        if ($type > 7) {
            throw new Exception("Unknown job type: {$type}. Please see Task::JOB_* constants.");
        }

        $this->type = $type;
    }

    /**
     * Attach a callback to this task.
     *
     * @param callback $callback A valid PHP callback
     * @param int      $type     Type of callback
     *
     * @return $this
     * @throws Exception When the callback is invalid.
     */
    public function attachCallback($callback, $type = self::TASK_COMPLETE)
    {
        if (!is_callable($callback)) {
            throw new Exception('Invalid callback specified');
        }

        if (!in_array($type, [self::TASK_COMPLETE, self::TASK_FAIL, self::TASK_STATUS])) {
            throw new Exception('Invalid callback type specified');
        }

        $this->callback[$type][] = $callback;

        return $this;
    }

    /**
     * Return all callbacks.
     *
     * @return array
     */
    public function getCallbacks()
    {
        return $this->callback;
    }

    /**
     * Run the complete callbacks.
     *
     * Complete callbacks are passed the name of the job, the handle of the
     * job and the result of the job (in that order).
     *
     * @param object $result JSON decoded result passed back
     *
     * @see Task::attachCallback()
     */
    public function complete($result)
    {
        $this->finished = true;
        $this->result = $result;

        if (!count($this->callback[self::TASK_COMPLETE])) {
            return;
        }

        foreach ($this->callback[self::TASK_COMPLETE] as $callback) {
            call_user_func($callback, $this->func, $this->handle, $result);
        }
    }

    /**
     * Run the failure callbacks.
     *
     * Failure callbacks are passed the task object job that failed
     *
     * @see Task::attachCallback()
     */
    public function fail()
    {
        $this->finished = true;
        if (!count($this->callback[self::TASK_FAIL])) {
            return;
        }

        foreach ($this->callback[self::TASK_FAIL] as $callback) {
            call_user_func($callback, $this);
        }
    }

    /**
     * Run the status callbacks.
     *
     * Status callbacks are passed the name of the job, handle of the job and
     * the numerator/denominator as the arguments (in that order).
     *
     * @param int $numerator   The numerator from the status
     * @param int $denominator The denominator from the status
     *
     * @see Task::attachCallback()
     */
    public function status($numerator, $denominator)
    {
        if (!count($this->callback[self::TASK_STATUS])) {
            return;
        }

        foreach ($this->callback[self::TASK_STATUS] as $callback) {
            call_user_func(
                $callback,
                $this->func,
                $this->handle,
                $numerator,
                $denominator
            );
        }
    }
}
