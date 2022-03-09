<?php

namespace Vectorface\Gearman\tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vectorface\Gearman\Exception;
use Vectorface\Gearman\Worker;

class WorkerTest extends TestCase
{
    /**
     * @var Worker
     */
    protected $worker;

    public function setUp(): void
    {
        $this->worker = new Worker();
    }

    public function testAddFunction()
    {
        $gearmanFunctionName = 'reverse';
        $callback = function ($job) {
            return $job->workload();
        };

        $this->worker->addFunction($gearmanFunctionName, $callback);

        $expectedFunctions = [
            $gearmanFunctionName => [
                'callback' => $callback,
            ],
        ];

        $this->assertEquals($expectedFunctions, $this->worker->getFunctions());
    }

    public function testAddFunctionThrowsExceptionIfFunctionIsAlreadyRegistered()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->worker->addFunction('gearmanFunction', 'echo');
        $this->worker->addFunction('gearmanFunction', 'var_dump');
    }

    public function testUnregister()
    {
        $gearmanFunctionName = 'reverse';
        $gearmanFunctionNameSecond = 'reverse2';
        $callback = function ($job) {
            return $job->workload();
        };

        $timeout = 10;
        $this->worker
            ->addFunction($gearmanFunctionName, $callback)
            ->addFunction($gearmanFunctionNameSecond, $callback, $timeout)
        ;

        $this->assertCount(2, $this->worker->getFunctions());

        $this->worker->unregister($gearmanFunctionName);
        $expectedFunctions = [
            $gearmanFunctionNameSecond => [
                'callback' => $callback,
                'timeout' => $timeout,
            ],
        ];

        $this->assertCount(1, $this->worker->getFunctions());
        $this->assertEquals($expectedFunctions, $this->worker->getFunctions());
    }

    public function testUnregisterThrowsExceptionIfFunctionDoesNotExist()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->worker->unregister('gearmanFunction');
    }

    public function testUnregisterAll()
    {
        $gearmanFunctionName = 'reverse';
        $gearmanFunctionNameSecond = 'reverse2';

        $this->worker->addFunction($gearmanFunctionName, 'echo');
        $this->worker->addFunction($gearmanFunctionNameSecond, 'echo');

        $this->assertCount(2, $this->worker->getFunctions());

        $this->worker->unregisterAll();

        $this->assertCount(0, $this->worker->getFunctions());
    }

    /**
     * @throws Exception
     */
    public function testWorker()
    {
        $this->markTestSkipped('Skipped. You can try this test on your machine with gearman running.');

        $function = function ($payload) {
            $result = str_replace('java', 'php', $payload);

            return str_replace('java', 'php', $payload);
        };

        $function2 = function ($payload) {
            while (false !== strpos($payload, 'java')) {
                $payload = preg_replace('/java/', 'php', $payload, 1);
                sleep(1);
            }

            return $payload;
        };

        $worker = new Worker();
        $worker->addServer();
        $worker->addFunction('replace', $function);
        $worker->addFunction('long_task', $function2);

        $worker->work();
    }
}
