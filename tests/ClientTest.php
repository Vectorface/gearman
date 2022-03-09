<?php

namespace Vectorface\Gearman\tests;

use Vectorface\Gearman\Client;
use Vectorface\Gearman\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Vectorface\Gearman\Exception\CouldNotConnectException;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp(): void
    {
        $this->client = new Client();
        $this->client->addServer();
    }

    public function testClient()
    {
        $process = new Process(["gearman",  "-w", "-f", "replace", "--", "sed 's/__replace__/the best/g'"]);
        $process->start();
        try {
            $this->assertEquals('php is the best', $this->client->doNormal('replace', 'php is __replace__'));
            $this->assertEquals('php is the best', $this->client->doLow('replace', 'php is __replace__'));
            $this->assertEquals('php is the best', $this->client->doHigh('replace', 'php is __replace__'));
            $this->client->doBackground('replace', 'php is __replace__');
            $this->client->doHighBackground('replace', 'php is __replace__');
            $this->client->doLowBackground('replace', 'php is __replace__');
        } catch (CouldNotConnectException $e) {
            $this->markTestSkipped('Skipped, please start Gearman on port ' . Connection::DEFAULT_PORT . ' to be able to run this test');
        }

        $process->stop();
    }
}
