<?php

namespace Vectorface\Gearman;

use InvalidArgumentException;

interface ServerSetting
{
    /**
     * @return string[]
     */
    public function getServers();

    /**
     * @param string $host
     * @param int    $port
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function addServer($host = 'localhost', $port = null);

    /**
     * @param string|array $servers Servers separated with comma, or array of servers
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function addServers($servers);
}
