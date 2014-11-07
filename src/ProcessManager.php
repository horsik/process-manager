<?php

namespace Kampaw\ProcessManager;

use Kampaw\ProcessManager\Exception\FileAccess;
use Kampaw\ProcessManager\Exception\FileNotFoundException;
use Kampaw\ProcessManager\Exception\InvalidArgumentException;
use Kampaw\ProcessManager\Exception\RuntimeException;

class ProcessManager
{
    /**
     * @var array $processes
     */
    protected $processes = array();

    /**
     * @param string $command
     * @param array $args
     * @throws FileAccess
     * @throws FileNotFoundException
     * @return int
     */
    public function execute($command, $args = array())
    {
        if (!is_file($command)) {
            throw new FileNotFoundException('Supplied file not exists');
        }
        if (!is_executable($command)) {
            throw new FileAccess('File is not executable');
        }
        if (!is_array($args)) {
            if (is_string($args)) {
                $args = explode(' ', $args);
            } else {
                throw new InvalidArgumentException('Second argument must be an array or a string');
            }
        }

        switch ($pid = pcntl_fork()) {
            // @codeCoverageIgnoreStart
            case 0:
                posix_setsid();
                try {
                    pcntl_exec($command, $args);
                } catch (\Exception $e) {
                    exit(pcntl_get_last_error());
                }
            case -1:
                throw new RuntimeException('fork() failed see PHP warning for error code');
            // @codeCoverageIgnoreEnd
            default:
                $this->processes[$pid] = $command;
                break;
        }

        return $pid;
    }

    /**
     * @return array
     */
    public function listProcesses()
    {
        $this->dispatchSignals();

        return $this->processes;
    }

    /**
     * @return $this
     */
    public function dispatchSignals()
    {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            if ($error = pcntl_wexitstatus($status)) {
                $message = pcntl_strerror($error);
                throw new RuntimeException("Process $pid ended with error: $message");
            }
            unset($this->processes[$pid]);
        }

        return $this;
    }
}