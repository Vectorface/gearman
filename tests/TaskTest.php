<?php

namespace Vectorface\Gearman\tests;

use PHPUnit\Framework\TestCase;
use Vectorface\Gearman\Exception;
use Vectorface\Gearman\Task;

/**
 * Net_Gearman_ConnectionTest.
 *
 * PHP version 5
 *
 * @category   Testing
 *
 * @author     Till Klampaeckel <till@php.net>
 * @license    http://www.opensource.org/licenses/bsd-license.php New BSD License
 *
 * @version    CVS: $Id$
 *
 * @link       http://pear.php.net/package/Net_Gearman
 * @since      0.2.4
 */
class TaskTest extends TestCase
{
    /**
     * Unknown job type.
     *
     *
     */
    public function testExceptionFromConstruct()
    {
        $this->expectException(Exception::class);
        new Task('foo', [], null, 8);
    }

    /**
     * Test parameters.
     *
     * @throws Exception
     */
    public function testParameters()
    {
        $uniq = uniqid();
        $task = new Task('foo', ['bar'], $uniq, 1);

        $this->assertEquals('foo', $task->func);
        $this->assertEquals(['bar'], $task->arg);
        $this->assertEquals($uniq, $task->uniq);
    }

    public function testAttachInvalidCallback()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid callback specified');
        $task = new Task('foo', []);
        $task->attachCallback('func_bar');
    }

    public function testAttachInvalidCallbackType()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid callback type specified');
        $task = new Task('foo', []);
        $task->attachCallback('strlen', 666);
    }

    public static function callbackProvider()
    {
        return [
            ['strlen',  Task::TASK_FAIL],
            ['intval',  Task::TASK_COMPLETE],
            ['explode', Task::TASK_STATUS],
        ];
    }

    /**
     * @dataProvider callbackProvider
     * @throws Exception
     */
    public function testAttachCallback($func, $type)
    {
        $task = new Task('foo', []);
        $task->attachCallback($func, $type);

        $callbacks = $task->getCallbacks();

        $this->assertEquals($func, $callbacks[$type][0]);
    }

    /**
     * Run the complete callback.
     *
     * @throws Exception
     */
    public function testCompleteCallback()
    {
        $task = new Task('foo', ['foo' => 'bar']);

        $task->complete((object)['foo']);

        // Attach a callback for real
        $task->attachCallback([$this, 'Net_Gearman_TaskTest_testCallBack']);

        // build result and call complete again
        $json = json_decode('{"foo":"bar"}');
        $task->complete($json);

        $this->assertEquals($json, $task->result);

        $this->assertEquals(
            ['func' => 'foo', 'handle' => '', 'result' => $json],
            $GLOBALS['Net_Gearman_TaskTest']
        );

        unset($GLOBALS['Net_Gearman_TaskTest']);
    }

    /**
     * A test callback.
     *
     * @param string $func
     * @param string $handle
     * @param mixed  $result
     */
    public function Net_Gearman_TaskTest_testCallBack($func, $handle, $result)
    {
        $GLOBALS['Net_Gearman_TaskTest'] = [
            'func' => $func,
            'handle' => $handle,
            'result' => $result,
        ];
    }
}
