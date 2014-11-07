<?php

namespace KampawTest\ProcessManager;

use Kampaw\ProcessManager\ProcessManager;
use \PHPUnit_Framework_TestCase as TestCase;

class ProcessManagerTest extends TestCase
{
    /**
     * @var ProcessManager $ProcessManager
     */
    protected $ProcessManager;

    public function setUp()
    {
        $this->ProcessManager = new ProcessManager();

        if ($result = shell_exec('/bin/ps -f --no-headers -C sleep | grep $(whoami)')) {
            $this->fail("Terminate following processes: $result");
        }
    }

    /**
     * @test
     */
    public function Execute_ValidCommand_ReturnsPid()
    {
        $result = $this->ProcessManager->execute('/bin/true');

        $this->assertInternalType('integer', $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\FileNotFoundException
     */
    public function Execute_NonExistentFile_ThrowsException()
    {
        $this->ProcessManager->execute('/non_existent_file');
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\FileAccess
     */
    public function Execute_NoExecutePermission_ThrowsException()
    {
        $filename = __DIR__ . '/TestAsset/no_execute_permission';
        chmod($filename, 0644);

        $this->ProcessManager->execute($filename);
    }

    public function invalidArgsTypeProvider()
    {
        return array(
            array(1),
            array(1.1),
            array(new \stdClass()),
        );
    }

    /**
     * @test
     * @dataProvider invalidArgsTypeProvider
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function Execute_InvalidArgsType_ThrowsException($args)
    {
        $this->ProcessManager->execute('/bin/true', $args);
    }

    /**
     * @test
     */
    public function Execute_ValidProgram_IsRunning()
    {
        $pid = $this->ProcessManager->execute('/bin/sleep', array('0.2'));
        usleep(150000);
        $result = shell_exec("/bin/ps --no-headers $pid");
        posix_kill($pid, SIGKILL);
        usleep(150000);
        $this->ProcessManager->dispatchSignals();

        $this->assertNotEmpty($result);
    }

    /**
     * @test
     */
    public function ListProcesses_NoneRunning_ReturnsEmptyArray()
    {
        $result = $this->ProcessManager->listProcesses();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function ListProcesses_OneRunning_ReturnsOneElement()
    {
        $pid = $this->ProcessManager->execute('/bin/sleep', array('0.2'));
        usleep(150000);
        $result = $this->ProcessManager->listProcesses();
        posix_kill($pid, SIGKILL);
        usleep(150000);
        $this->ProcessManager->dispatchSignals();

        $this->assertCount(1, $result);
    }

    /**
     * @test
     */
    public function ListProcesses_ProcessLaunchedAndEnded_ReturnsEmptyArray()
    {
        $this->ProcessManager->execute('/bin/sleep', array('0.1'));
        usleep(150000);

        $result = $this->ProcessManager->listProcesses();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function Execute_ProcessLaunchedAndEnded_NotZombie()
    {
        $pid = $this->ProcessManager->execute('/bin/sleep', array('0.1'));
        usleep(150000);
        $this->ProcessManager->dispatchSignals();

        $result = exec("/bin/ps --no-headers -o state $pid");

        $this->assertNotEquals('Z', $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\RuntimeException
     */
    public function Execute_PhpScriptNoShebangDispatch_ThrowsException()
    {
        $filename = __DIR__ . '/TestAsset/php_script_no_shebang';
        chmod($filename, 0775);

        $this->ProcessManager->execute($filename);
        usleep(150000);
        $this->ProcessManager->dispatchSignals();
    }

    /**
     * @test
     */
    public function Execute_ArgsAsString_Success()
    {
        $pid = $this->ProcessManager->execute('/bin/sleep', '0.2');
        usleep(150000);
        $result = $this->ProcessManager->listProcesses();
        posix_kill($pid, SIGKILL);
        usleep(50000);
        $this->ProcessManager->dispatchSignals();

        $this->assertCount(1, $result);
    }
}
