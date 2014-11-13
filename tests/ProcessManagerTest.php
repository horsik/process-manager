<?php

use PHPUnit_Framework_TestCase as TestCase;

class ProcessManagerTest extends TestCase
{
    /**
     * @var \Kampaw\ProcessManager\ProcessManager $manager
     */
    protected $manager;

    public function setUp()
    {
        $this->manager = new \Kampaw\ProcessManager\ProcessManager();
    }

    public function tearDown()
    {
        unset($this->manager);
    }

    /**
     * @test
     */
    public function Execute_ValidCommand_ReturnsPid()
    {
        $result = $this->manager->execute('/bin/sleep', array('1'));

        $this->assertGreaterThan(0, $result);
    }

    /**
     * @test
     * @expectedException \Kampaw\ProcessManager\Exception\InvalidArgumentException
     */
    public function Execute_InvalidCommand_ThrowsException()
    {
        $this->manager->execute('%$#@//////');
    }

    /**
     * @test
     */
    public function Execute_ValidCommand_IsRunning()
    {
        $pid = $this->manager->execute('/bin/sleep', array('1'));
        $result = file_exists("/proc/$pid");

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function getRunning_NoProcesses_ReturnsEmptyArray()
    {
        $result = $this->manager->getRunning();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getRunning_OneProcessNotFinished_ReturnsOneElement()
    {
        $this->manager->execute('/bin/sleep', array('1'));
        $result = $this->manager->getRunning();

        $this->assertCount(1, $result);
    }

    /**
     * @test
     */
    public function getRunning_OneProcessFinished_ReturnsEmptyArray()
    {
        $pid = $this->manager->execute('/bin/sleep', array('1'));
        $this->manager->getProcess($pid)->waitToFinish();
        $result = $this->manager->getRunning();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getRunning_OneProcessRunningOneProcessFinished_ReturnsRunningProcess()
    {
        $running = $this->manager->execute('/bin/sleep', array('2'));
        $finished = $this->manager->execute('/bin/sleep', array('1'));
        $this->manager->getProcess($finished)->waitToFinish();
        $expected = $this->manager->getProcess($running);
        $result = $this->manager->getRunning();

        $this->assertContains($expected, $result);
    }

    /**
     * @test
     */
    public function getFinished_NoProcesses_ReturnsEmptyArray()
    {
        $result = $this->manager->getFinished();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getFinished_OneProcessNotFinished_ReturnsEmptyArray()
    {
        $this->manager->execute('/bin/sleep', array('1'));
        $result = $this->manager->getFinished();

        $this->assertCount(0, $result);
    }

    /**
     * @test
     */
    public function getFinished_OneProcessFinished_ReturnsFinishedProcess()
    {
        $pid = $this->manager->execute('/bin/sleep', array('1'));
        $expected = $this->manager->getProcess($pid);
        $expected->waitToFinish();
        $result = $this->manager->getFinished();

        $this->assertContains($expected, $result);
    }

    /**
     * @test
     */
    public function getFinished_OneProcessFinishedOneProcessRunning_ReturnsFinishedProcess()
    {
        $this->manager->execute('/bin/sleep', array('2'));
        $finished = $this->manager->execute('/bin/sleep', array('1'));
        $this->manager->getProcess($finished)->waitToFinish();
        $expected = $this->manager->getProcess($finished);
        $result = $this->manager->getFinished();

        $this->assertContains($expected, $result);
    }

    /**
     * @test
     */
    public function DispatchSignals_TwoProcessesFinished_NoZombies()
    {
        $pid1 = $this->manager->execute('/bin/true');
        $pid2 = $this->manager->execute('/bin/true');
        usleep(10000);
        $this->manager->dispatchSignals();
        $result1 = file_exists("/proc/$pid1");
        $result2 = file_exists("/proc/$pid2");

        $this->assertFalse($result1);
        $this->assertFalse($result2);
    }
}
