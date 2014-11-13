<?php

namespace Kampaw\ProcessManager;

class ProcessManager implements ProcessManagerInterface
{
    /**
     * @var Process[] $processes
     */
    protected $processes = array();

    /**
     * @param $command
     * @param array $args
     * @param array $env
     * @return int
     */
    public function execute($command, $args = array(), $env = array())
    {
        $process = new Process($command, $args, $env);
        $pid = $process->execute();

        $this->processes[$pid] = $process;

        return $pid;
    }

    /**
     * @codeCoverageIgnore
     * @return Process[]
     */
    public function getProcesses()
    {
        return $this->processes;
    }

    /**
     * @codeCoverageIgnore
     * @param int $pid
     * @return Process|null
     */
    public function getProcess($pid)
    {
        if (isset($this->processes[$pid])) {
            return $this->processes[$pid];
        }
    }

    /**
     * @return array
     */
    public function getRunning()
    {
        return $this->getByState(true);
    }

    /**
     * @return array
     */
    public function getFinished()
    {
        return $this->getByState(false);
    }

    /**
     * @return $this
     */
    public function dispatchSignals()
    {
        foreach ($this->processes as $process) {
            $process->dispatchSignals();
        }

        return $this;
    }

    /**
     * @param bool $isRunning
     * @return array
     */
    protected function getByState($isRunning)
    {
        $result = array();

        foreach ($this->processes as $pid => $process) {
            if ($process->dispatchSignals() !== $isRunning) {
                $result[$pid] = $process;
            }
        }

        return $result;
    }
}
