<?php
namespace Kampaw\ProcessManager;

interface ProcessManagerInterface
{
    /**
     * @param $command
     * @param array $args
     * @param array $env
     * @return int
     */
    public function execute($command, $args = array(), $env = array());

    /**
     * @codeCoverageIgnore
     * @return Process[]
     */
    public function getProcesses();

    /**
     * @param int $pid
     * @return Process|null
     */
    public function getProcess($pid);

    /**
     * @return array
     */
    public function getRunning();

    /**
     * @return array
     */
    public function getFinished();

    /**
     * @return $this
     */
    public function dispatchSignals();
}