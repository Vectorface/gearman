<?php

namespace Vectorface\Gearman;

use InvalidArgumentException;

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
 * A client for submitting jobs to Gearman.
 *
 * This class is used by code submitting jobs to the Gearman server. It handles
 * taking tasks and sets of tasks and submitting them to the Gearman server.
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
 */
class Client implements ServerSetting
{
    /** Our randomly selected connection to Gearman */
    protected array $conn = [];

    /** List of gearman servers */
    protected array $servers = [];

    /**The timeout for Gearman connections */
    protected int $timeout = 1000;

    /**
     * @param int $timeout Timeout in microseconds
     *
     * @see Connection
     */
    public function __construct(int $timeout = 1000)
    {
        $this->timeout = $timeout;
    }

    public function getServers(): array
    {
        return array_keys($this->servers);
    }

    public function addServers($servers): self
    {
        if (!is_array($servers)) {
            $servers = explode(',', $servers);
        }

        foreach ($servers as $server) {
            $explodedServer = explode(':', $server);
            $port = $explodedServer[1] ?? null;

            $this->addServer($explodedServer[0], $port);
        }

        return $this;
    }

    public function addServer(string $host = 'localhost', ?int $port = null): self
    {
        $host = trim($host);
        if (empty($host)) {
            throw new InvalidArgumentException("Invalid host '{$host}' given");
        }

        if ($port === null) {
            $port = $this->getDefaultPort();
        } else {
            if (!$port > 0) {
                throw new InvalidArgumentException("Invalid port '{$port}' given");
            }
        }

        $server = $host . ':' . $port;

        if (isset($this->servers[$server])) {
            throw new InvalidArgumentException("Server '{$server}' is already registered");
        }

        $this->servers[$server] = true;

        return $this;
    }

    /**
     * @return int
     */
    protected function getDefaultPort()
    {
        return Connection::DEFAULT_PORT;
    }

    /**
     * Get a connection to a Gearman server.
     *
     * @return resource A connection to a Gearman server
     */
    protected function getConnection()
    {
        return $this->conn[array_rand($this->conn)];
    }

    /**
     * Runs a single task and returns a string representation of the result.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return string
     * @throws Exception
     */
    public function doNormal($functionName, $workload, $unique = null)
    {
        return $this->runSingleTaskSet($this->createSet($functionName, $workload, $unique));
    }

    /**
     * Runs a single high priority task and returns a string representation of the result.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return string
     * @throws Exception
     */
    public function doHigh($functionName, $workload, $unique = null)
    {
        return $this->runSingleTaskSet($this->createSet($functionName, $workload, $unique, Task::JOB_HIGH));
    }

    /**
     * Runs a single low priority task and returns a string representation of the result.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return string
     * @throws Exception
     */
    public function doLow($functionName, $workload, $unique = null)
    {
        return $this->runSingleTaskSet($this->createSet($functionName, $workload, $unique, Task::JOB_LOW));
    }

    /**
     * @param Set $set
     *
     * @return string
     * @throws Exception
     */
    protected function runSingleTaskSet(Set $set)
    {
        $this->runSet($set);
        $task = current($set->tasks);

        return $task->result;
    }

    /**
     * Runs a task in the background, returning a job handle which can be used
     * to get the status of the running task.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return Task
     * @throws Exception
     */
    public function doBackground($functionName, $workload, $unique = null)
    {
        $set = $this->createSet($functionName, $workload, $unique, Task::JOB_BACKGROUND);
        $this->runSet($set);

        return current($set->tasks);
    }

    /**
     * Runs a task in the background, returning a job handle which can be used
     * to get the status of the running task.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return Task
     * @throws Exception
     */
    public function doHighBackground($functionName, $workload, $unique = null)
    {
        $set = $this->createSet($functionName, $workload, $unique, Task::JOB_HIGH_BACKGROUND);
        $this->runSet($set);

        return current($set->tasks);
    }

    /**
     * Runs a task in the background, returning a job handle which can be used
     * to get the status of the running task.
     *
     * @param string $functionName
     * @param string $workload
     * @param string $unique
     *
     * @return Task
     * @throws Exception
     */
    public function doLowBackground($functionName, $workload, $unique = null)
    {
        $set = $this->createSet($functionName, $workload, $unique, Task::JOB_LOW_BACKGROUND);
        $this->runSet($set);

        return current($set->tasks);
    }

    /**
     * Schedule a background task, returning a job handle which can be used
     * to get the status of the running task.
     *
     * @param string $functionName
     * @param string $workload
     * @param int $epoch
     * @param string $unique
     *
     * @return Task
     * @throws Exception
     */
    public function doEpoch($functionName, $workload, $epoch, $unique = null)
    {
        $set = $this->createSet($functionName, $workload, $unique, Task::JOB_EPOCH, $epoch);
        $this->runSet($set);

        return current($set->tasks);
    }

    /**
     * @param int $epoch Time of job to run at (unix timestamp)
     * @throws Exception
     */
    private function createSet(
        string $functionName,
        string $workload,
        ?string $unique = null,
        int $type = Task::JOB_NORMAL,
        int $epoch = 0
    ): Set {
        if ($unique === null) {
            $unique = $this->generateUniqueId();
        }

        $task = new Task($functionName, $workload, $unique, $type, $epoch);
        $task->type = $type;
        $set = new Set();
        $set->addTask($task);

        return $set;
    }

    protected function generateUniqueId(): string
    {
        return getmypid() . '_' . uniqid();
    }

    /**
     * Submit a task to Gearman.
     *
     * @throws Exception
     * @see Task, Client::runSet()
     */
    protected function submitTask(Task $task): void
    {
        $type = match ($task->type) {
            Task::JOB_LOW => 'submit_job_low',
            Task::JOB_LOW_BACKGROUND => 'submit_job_low_bg',
            Task::JOB_HIGH_BACKGROUND => 'submit_job_high_bg',
            Task::JOB_BACKGROUND => 'submit_job_bg',
            Task::JOB_EPOCH => 'submit_job_epoch',
            Task::JOB_HIGH => 'submit_job_high',
            default => 'submit_job',
        };

        $arg = $task->arg;

        $params = [
            'func' => $task->func,
            'uniq' => $task->uniq,
            'arg' => $arg,
        ];

        if ($task->type == Task::JOB_EPOCH) {
            $params['epoch'] = $task->epoch;
        }

        $s = $this->getConnection();
        Connection::send($s, $type, $params);

        $key = is_object($s) ? spl_object_id($s) : intval($s);
        if (!is_array(Connection::$waiting[$key])) {
            Connection::$waiting[$key] = [];
        }

        Connection::$waiting[$key][] = $task;
    }

    /**
     * Run a set of tasks.
     * @param int|null $timeout Time in seconds for the socket timeout. Max is 10 seconds
     * @throws Exception
     * @see Set, Task
     */
    public function runSet(Set $set, ?int $timeout = null)
    {
        foreach ($this->getServers() as $server) {
            $conn = Connection::connect($server, $timeout);
            if (!Connection::isConnected($conn)) {
                unset($this->servers[$server]);
                continue;
            }

            $this->conn[] = $conn;
        }

        $totalTasks = $set->tasksCount;
        $taskKeys = array_keys($set->tasks);
        $t = 0;

        if ($timeout !== null) {
            $socket_timeout = min(10, (int) $timeout);
        } else {
            $socket_timeout = 10;
        }

        while (!$set->finished()) {
            if ($timeout !== null) {
                if (empty($start)) {
                    $start = microtime(true);
                } else {
                    $now = microtime(true);
                    if ($now - $start >= $timeout) {
                        break;
                    }
                }
            }

            if ($t < $totalTasks) {
                $k = $taskKeys[$t];
                $this->submitTask($set->tasks[$k]);
                if ($set->tasks[$k]->type == Task::JOB_BACKGROUND ||
                    $set->tasks[$k]->type == Task::JOB_HIGH_BACKGROUND ||
                    $set->tasks[$k]->type == Task::JOB_LOW_BACKGROUND ||
                    $set->tasks[$k]->type == Task::JOB_EPOCH) {
                    $set->tasks[$k]->finished = true;
                    $set->tasksCount--;
                }

                $t++;
            }

            $write = null;
            $except = null;
            $read = $this->conn;
            socket_select($read, $write, $except, $socket_timeout);
            foreach ($read as $socket) {
                $resp = Connection::read($socket);
                if (count($resp)) {
                    $this->handleResponse($resp, $socket, $set);
                }
            }
        }
    }

    /**
     * Handle the response read in.
     *
     * @param array    $resp   The raw array response
     * @param resource $socket The socket
     * @param Set      $tasks  The tasks being run
     *
     * @throws Exception
     */
    protected function handleResponse(array $resp, $socket, Set $tasks)
    {
        $task = null;
        if (isset($resp['data']['handle']) && $resp['function'] != 'job_created') {
            $task = $tasks->getTask($resp['data']['handle']);
        }

        switch ($resp['function']) {
        case 'work_complete':
            $tasks->tasksCount--;
            $task->complete($resp['data']['result']);
            break;
        case 'work_status':
            $n = (int) $resp['data']['numerator'];
            $d = (int) $resp['data']['denominator'];
            $task->status($n, $d);
            break;
        case 'work_fail':
            $tasks->tasksCount--;
            $task->fail();
            break;
        case 'job_created':
            $key = is_object($socket) ? spl_object_id($socket) : intval($socket);
            $task = array_shift(Connection::$waiting[$key]);
            $task->handle = $resp['data']['handle'];
            if ($task->type == Task::JOB_BACKGROUND) {
                $task->finished = true;
            }
            $tasks->handles[$task->handle] = $task->uniq;
            break;
        case 'error':
            throw new Exception("An error occurred");
        default:
            throw new Exception("Invalid function {$resp['function']}");
        }
    }

    /**
     * Disconnect from Gearman.
     */
    public function disconnect()
    {
        if (!is_array($this->conn) || !count($this->conn)) {
            return;
        }

        foreach ($this->conn as $conn) {
            Connection::close($conn);
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
