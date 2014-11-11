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

    public function tearDown()
    {
        unset($this->process);
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
        @mkdir($filename);
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

    /**
     * @test
     */
    public function ReadStdout_NoOutput_ReturnsNull()
    {
        $this->process->setCommand('/bin/true')->execute();
        $this->process->waitToFinish();
        $result = $this->process->readStdout();

        $this->assertNull($result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function ReadStdout_ProcessNotStarted_ThrowsException()
    {
        $this->process->setCommand('/bin/echo')->readStdout();
    }

    /**
     * @test
     */
    public function ReadStdout_EchoSingleLine_ReturnsSingleLine()
    {
        $expected = 'single line';
        $this->process->setCommand('/bin/echo')->setArgs(array('-n', $expected))->execute();
        $this->process->waitToFinish();
        $result = $this->process->readStdout();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function ReadStdout_MultilineCat_ReturnsArray()
    {
        $asset = __DIR__ . '/TestAsset/multiline';
        $this->process->setCommand('/bin/cat')->setArgs(array($asset))->execute();
        $this->process->waitToFinish();
        $expected = file($asset, FILE_IGNORE_NEW_LINES);
        $result = $this->process->readStdout();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function ReadStderr_NoOutput_ReturnsNull()
    {
        $this->process->setCommand('/bin/true')->execute();
        $this->process->waitToFinish();
        $result = $this->process->readStderr();

        $this->assertNull($result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function ReadStderr_ProcessNotStarted_ThrowsException()
    {
        $this->process->readStderr();
    }

    /**
     * @test
     */
    public function ReadStderr_SingleLine_ReturnsSingleLine()
    {
        $asset = __DIR__ . '/TestAsset/echo_line_stderr.sh';
        chmod($asset, 0700);

        $this->process->setCommand($asset)->execute();
        $this->process->waitToFinish();
        $result = $this->process->readStderr();
        $expected = "single line stderr";

        $this->assertSame($expected, $result);
    }

    /**
     * @test
     */
    public function ReadStderr_MultilineCat_ReturnsArray()
    {
        $asset = __DIR__ . '/TestAsset/multiline_stderr.sh';
        chmod($asset, 0700);

        $this->process->setCommand($asset)->execute();
        $this->process->waitToFinish();
        $expected = file(strtok($asset, '_'), FILE_IGNORE_NEW_LINES);
        $result = $this->process->readStderr();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function WriteStdin_NBytes_ReturnsN()
    {
        $data = 'single line stdin';

        $this->process->setCommand('/bin/true')->execute();
        $expected = strlen($data);
        $result = $this->process->writeStdin($data);
        $this->process->waitToFinish();

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function WriteStdin_CatSingleLine_ReturnsSingleLineStdout()
    {
        $expected = 'single line stdin';

        $pid = $this->process->setCommand('/bin/cat')->execute();
        $this->process->writeStdin($expected);
        usleep(50000);
        $result = $this->process->readStdout();
        posix_kill($pid, SIGTERM);

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function WriteStdin_ProcessNotStarted_ThrowsException()
    {
        $this->process->writeStdin('');
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function WriteStdin_ProcessFinished_ThrowsException()
    {
        $this->process->setCommand('/bin/true')->execute();
        $this->process->waitToFinish();
        $this->process->writeStdin('');
    }

    /**
     * @test
     */
    public function Terminate_ProcessIsRunning_Terminates()
    {
        $pid = $this->process->setCommand('/bin/sleep')->setArgs(array(10))->execute();
        $this->process->terminate();
        $result = exec("ps --no-header $pid");

        $this->assertEmpty($result);
    }
}
