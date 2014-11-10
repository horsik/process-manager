<?php

namespace TestAsset;

use Kampaw\ProcessManager\Process;
use \PHPUnit_Framework_TestCase as TestCase;

class ProcessTest extends TestCase
{
    /**
     * @var Process $process
     */
    protected $process;

    public function setUp()
    {
        $this->process = new Process();
    }
    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function SetCommand_FileNotExists_ThrowsException()
    {
        $this->process->setCommand('bogus file');
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\FileAccess
     */
    public function SetCommand_FileNotExecutable_ThrowsException()
    {
        $filename = __DIR__ . '/TestAsset/no_execute_permission';
        chmod($filename, 0660);

        $this->process->setCommand($filename);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function SetCommand_ExecutableDirectory_ThrowsException()
    {
        $filename = __DIR__ . '/TestAsset/executable_directory';
        mkdir($filename);
        chmod($filename, 0770);

        $this->process->setCommand($filename);
    }

    public function invalidArgsTypeProvider()
    {
        return array(
            array(null),
            array(0xBAD),
            array(0.01),
            array(new \stdClass()),
            array(tmpfile()),
        );
    }

    /**
     * @test
     * @dataProvider invalidArgsTypeProvider
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function SetArgs_InvalidType_ThrowsException($args)
    {
        $this->process->setArgs($args);
    }

    /**
     * @test
     */
    public function SetArgs_String_Splits()
    {
        $result = $this->process->setArgs('arg1 arg2 arg3')->getArgs();

        $this->assertInternalType('array', $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function SetEnv_NotArray_ThrowsException()
    {
       $this->process->setEnv('not array');
    }

    /**
     * @test
     */
    public function Execute_ValidCommand_ReturnsPid()
    {
        $result = $this->process->setCommand('/bin/true')->execute();

        $this->assertGreaterThan(0, $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\UnexpectedValueException
     */
    public function Execute_CommandNotSet_ThrowsException()
    {
        $this->process->execute();
    }

    /**
     * @test
     */
    public function Execute_ValidCommand_IsRunning()
    {
        $pid = $this->process->setCommand('/bin/sleep')->setArgs(array(1))->execute();
        $result = file_exists("/proc/$pid");

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function DispatchSignals_ValidCommandFinish_NoZombie()
    {
        $pid = $this->process->setCommand('/bin/true')->execute();
        usleep(20000);
        $this->process->dispatchSignals();
        $result = file_exists("/proc/$pid");

        $this->assertFalse($result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function DispatchSignals_ProcessNotStarted_ThrowsException()
    {
        $this->process->dispatchSignals();
    }

    /**
     * @test
     */
    public function WaitToFinish_SleepOneSecond_ExecutionDelayed()
    {
        $start = time();

        $this->process->setCommand('/bin/sleep')->setArgs(array(1))->execute();
        $this->process->waitToFinish();

        $end = time();
        $result = $end - $start;

        $this->assertGreaterThan(0, $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function WaitToFinish_ProcessNotStarted_ThrowsException()
    {
        $this->process->waitToFinish();
    }

    /**
     * @test
     */
    public function GetStatus_TrueProgramFinished_ReturnsZero()
    {
        $this->process->setCommand('/bin/true')->execute();
        $this->process->waitToFinish();
        $result = $this->process->getStatus();

        $this->assertSame(0, $result);
    }

    /**
     * @test
     */
    public function GetStatus_FalseProgramFinished_ReturnsOne()
    {
        $this->process->setCommand('/bin/false')->execute();
        $this->process->waitToFinish();
        $result = $this->process->getStatus();

        $this->assertSame(1, $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function GetStatus_TrueProgramNotFinished_ThrowsException()
    {
        $this->process->setCommand('/bin/true')->execute();
        $this->process->getStatus();
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function GetStatus_FalseProgramNotFinished_ThrowsException()
    {
        $this->process->setCommand('/bin/false')->execute();
        $this->process->getStatus();
    }

    /**
     * @test
     */
    public function Execute_PhpScriptNoShebangDispatch_CannotRunStatus()
    {
        $filename = __DIR__ . '/TestAsset/php_script_no_shebang';
        chmod($filename, 0775);

        $this->process->setCommand($filename)->execute();
        $this->process->waitToFinish();
        $result = $this->process->getStatus();

        $this->assertEquals(126, $result);
    }
}
