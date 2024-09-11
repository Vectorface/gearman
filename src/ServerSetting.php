<?php

namespace Vectorface\Gearman;

use InvalidArgumentException;

interface ServerSetting
{
    /**
     * @return string[]
     */
    public function getServers(): array;

    /**
     * @throws InvalidArgumentException
     */
    public function addServer(string $host = 'localhost', ?int $port = null): self;

    /**
     * @param string|array $servers Servers separated with comma, or array of servers
     *
     * @throws InvalidArgumentException
     */
    public function addServers($servers): self;
}
