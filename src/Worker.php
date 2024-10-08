<?php

namespace Vectorface\Gearman;

use InvalidArgumentException;
use Vectorface\Gearman\Job\JobException;

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
 * Gearman worker class.
 *
 * Run an instance of a worker to listen for jobs. It then manages the running
 * of jobs, etc.
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
 * @see       Job, Connection
 */
class Worker implements ServerSetting
{
    /**Unique id for this worker */
    protected ?string $id = null;

    /**Pool of connections to Gearman servers */
    protected array $connection = [];

    /** Pool of retry connections */
    protected array $retryConn = [];

    /** @var string[] */
    protected array $functions = [];

    /** @var string[] List of gearman servers */
    protected array $servers = [];

    /**
     * Callbacks registered for this worker.
     *
     * @see Worker::JOB_START
     * @see Worker::JOB_COMPLETE
     * @see Worker::JOB_FAIL
     */
    protected array $callback = [
        self::JOB_START => [],
        self::JOB_COMPLETE => [],
        self::JOB_FAIL => [],
    ];

    /**
     * Callback types.
     *
     * @const integer JOB_START    Ran when a job is started
     * @const integer JOB_COMPLETE Ran when a job is finished
     * @const integer JOB_FAIL     Ran when a job fails
     */
    const JOB_START = 1;
    const JOB_COMPLETE = 2;
    const JOB_FAIL = 3;

    /**
     * @param string $id Optional unique id for this worker
     */
    public function __construct($id = null)
    {
        if ($id === null) {
            $id = 'pid_' . getmypid() . '_' . uniqid();
        }

        $this->id = $id;
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

    public function addServer(string $host = 'localhost', ?int $port = null) : ServerSetting
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
     * @return string[]
     */
    public function getFunctions()
    {
        return $this->functions;
    }

    /**
     * @param string   $functionName
     * @param callback $callback
     * @param int      $timeout
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function addFunction($functionName, $callback, $timeout = null)
    {
        if (isset($this->functions[$functionName])) {
            throw new InvalidArgumentException("Function {$functionName} is already registered");
        }

        $this->functions[$functionName] = ['callback' => $callback];
        if ($timeout !== null) {
            $this->functions[$functionName]['timeout'] = $timeout;
        }

        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function unregister(string $functionName): self
    {
        if (!isset($this->functions[$functionName])) {
            throw new InvalidArgumentException("Function {$functionName} is not registered");
        }

        unset($this->functions[$functionName]);

        return $this;
    }

    /**
     * @return self
     */
    public function unregisterAll()
    {
        $this->functions = [];

        return $this;
    }

    /**
     * @throws Exception
     */
    public function work($monitor = null)
    {
        $this->connectToAllServers();
        $this->registerFunctionsToOpenedConnections();

        if (!is_callable($monitor)) {
            $monitor = [$this, 'stopWork'];
        }

        $write = null;
        $except = null;
        $working = true;
        $lastJob = time();
        $retryTime = 5;

        while ($working) {
            $sleep = true;
            $currentTime = time();

            foreach ($this->connection as $server => $socket) {
                $worked = false;
                try {
                    $worked = $this->doWork($socket);
                } catch (\Exception) {
                    unset($this->connection[$server]);
                    $this->retryConn[$server] = $currentTime;
                }
                if ($worked) {
                    $lastJob = time();
                    $sleep = false;
                }
            }

            $idle = false;
            if ($sleep && count($this->connection)) {
                foreach ($this->connection as $socket) {
                    Connection::send($socket, 'pre_sleep');
                }

                $read = $this->connection;
                socket_select($read, $write, $except, 60);
                $idle = (count($read) == 0);
            }

            $retryChange = false;
            foreach ($this->retryConn as $s => $lastTry) {
                if (($lastTry + $retryTime) < $currentTime) {
                    try {
                        $conn = Connection::connect($s);
                        $this->connection[$s] = $conn;
                        $retryChange = true;
                        unset($this->retryConn[$s]);
                        Connection::send($conn, 'set_client_id', ['client_id' => $this->id]);
                    } catch (Exception) {
                        $this->retryConn[$s] = $currentTime;
                    }
                }
            }

            if (count($this->connection) == 0) {
                // sleep to avoid wasted cpu cycles if no connections to block on using socket_select
                sleep(1);
            }

            if ($retryChange === true) {
                $this->registerFunctionsToOpenedConnections();
            }

            if (call_user_func($monitor, $idle, $lastJob)) {
                $working = false;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function connectToAllServers()
    {
        foreach ($this->getServers() as $server) {
            try {
                $connection = Connection::connect($server);
                Connection::send($connection, 'set_client_id', ['client_id' => $this->id]);
                $this->connection[$server] = $connection;
            } catch (\Exception) {
                $this->retryConn[$server] = time();
            }
        }

        if (empty($this->connection)) {
            throw new Exception("Couldn't connect to any available servers");
        }
    }

    /**
     * @throws Exception
     */
    public function registerFunctionsToOpenedConnections()
    {
        foreach (array_keys($this->functions) as $gearmanFunction) {
            $params = ['func' => $gearmanFunction];
            $call = isset($params['timeout']) ? 'can_do_timeout' : 'can_do';

            foreach ($this->connection as $connection) {
                Connection::send($connection, $call, $params);
            }
        }
    }

    /**
     * Listen on the socket for work.
     *
     * Sends the 'grab_job' command and then listens for either the 'noop' or
     * the 'no_job' command to come back. If the 'job_assign' comes down the
     * pipe then we run that job.
     *
     * @param resource $socket The socket to work on
     *
     * @return bool Returns true if work was done, false if not
     *
     * @throws Exception
     *
     * @see Net_Gearman_Connection::send()
     */
    protected function doWork($socket)
    {
        Connection::send($socket, 'grab_job');

        $resp = ['function' => 'noop'];
        while (count($resp) && $resp['function'] == 'noop') {
            $resp = Connection::blockingRead($socket);
        }

        if (in_array($resp['function'], ['noop', 'no_job'])) {
            return false;
        }

        if ($resp['function'] != 'job_assign') {
            throw new Exception('Internal error - Job was not assigned after it was grabbed by this worker');
        }

        $name = $resp['data']['func'];
        $handle = $resp['data']['handle'];
        $arg = [];

        if (isset($resp['data']['arg']) && Connection::stringLength($resp['data']['arg'])) {
            $arg = json_decode($resp['data']['arg'], true);
            if ($arg === null) {
                $arg = $resp['data']['arg'];
            }
        }

        try {
            $this->callStartCallbacks($handle, $name, $arg);

            $functionCallback = $this->functions[$name]['callback'];
            $result = call_user_func($functionCallback, $arg);
            if (!$result) {
                $result = '';
            }

            $this->jobComplete($socket, $handle, $result);
            $this->callCompleteCallbacks($handle, $name, $result);
        } catch (JobException $e) {
            $this->jobFail($socket, $handle);
            $this->callFailCallbacks($handle, $name, $e);
        }

        return true;
    }

    /**
     * Update Gearman with your job's status.
     *
     * @param resource $socket
     * @param string $handle
     * @param int $numerator The numerator (e.g. 1)
     * @param int $denominator The denominator (e.g. 100)
     *
     * @throws Exception
     * @see Connection::send
     */
    public function jobStatus($socket, $handle, $numerator, $denominator)
    {
        Connection::send($socket, 'work_status', [
            'handle' => $handle,
            'numerator' => $numerator,
            'denominator' => $denominator,
        ]);
    }

    /**
     * Mark your job as complete with its status.
     *
     * @param resource $socket
     * @param string $handle
     * @param array $result Result of your job
     *
     * @throws Exception
     * @see Connection::send
     */
    private function jobComplete($socket, $handle, $result)
    {
        Connection::send($socket, 'work_complete', [
            'handle' => $handle,
            'result' => $result,
        ]);
    }

    /**
     * Mark your job as failing.
     *
     * If your job fails for some reason (e.g. a query fails) you need to run
     * this function and exit from your run() method. This will tell Gearman
     * (and the client by proxy) that the job has failed.
     *
     * @param resource $socket
     * @param string $handle
     *
     * @throws Exception
     * @see Connection::send
     */
    private function jobFail($socket, $handle)
    {
        Connection::send($socket, 'work_fail', [
            'handle' => $handle,
        ]);
    }

    /**
     * @param callback $callback
     * @param int      $type
     *
     * @throws Exception When an invalid callback or type is specified.
     */
    public function attachCallback($callback, $type = self::JOB_COMPLETE)
    {
        if (!is_callable($callback)) {
            throw new Exception('Invalid callback specified');
        }
        if (!isset($this->callback[$type])) {
            throw new Exception('Invalid callback type specified.');
        }
        $this->callback[$type][] = $callback;
    }

    /**
     * @param string $handle The job's Gearman handle
     * @param string $job    The name of the job
     * @param mixed  $args   The job's argument list
     */
    protected function callStartCallbacks($handle, $job, $args)
    {
        foreach ($this->callback[self::JOB_START] as $callback) {
            call_user_func($callback, $handle, $job, $args);
        }
    }

    /**
     * @param string $handle The job's Gearman handle
     * @param string $job    The name of the job
     * @param string $result The job's returned result
     */
    protected function callCompleteCallbacks($handle, $job, $result)
    {
        foreach ($this->callback[self::JOB_COMPLETE] as $callback) {
            call_user_func($callback, $handle, $job, $result);
        }
    }

    /**
     * @param string     $handle The job's Gearman handle
     * @param string     $job    The name of the job
     * @param \Exception $error  The exception thrown
     */
    protected function callFailCallbacks($handle, $job, \Exception $error)
    {
        foreach ($this->callback[self::JOB_FAIL] as $callback) {
            call_user_func($callback, $handle, $job, $error);
        }
    }

    public function __destruct()
    {
        $this->endWork();
    }

    public function endWork()
    {
        foreach ($this->connection as $conn) {
            Connection::close($conn);
        }
    }

    /**
     * Should we stop work?
     */
    public function stopWork(): bool
    {
        return false;
    }
}
