<?php

namespace Kampaw\ProcessManager;

use Kampaw\ProcessManager\Exception\FileAccess;
use Kampaw\ProcessManager\Exception\InvalidArgumentException;
use Kampaw\ProcessManager\Exception\RuntimeException;
use Kampaw\ProcessManager\Exception\UnexpectedValueException;

class Process
{
    /**
     * @var string $command
     */
    protected $command;

    /**
     * @var array $args
     */
    protected $args;

    /**
     * @var array $env
     */
    protected $env;

    /**
     * @var int $pid
     */
    protected $pid;

    /**
     * @var int $status
     */
    protected $status;

    /**
     * @param $command
     * @param array $args
     * @param array $env
     * @throws FileAccess
     * @codeCoverageIgnore
     */
    public function __construct($command = null, $args = array(), $env = array())
    {
        if ($command) {
            $this->setCommand($command);
        }

        $this->setArgs($args);
        $this->setEnv($env);
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @param array $args
     * @return $this
     */
    public function setArgs($args)
    {
        if (!is_array($args)) {
            if (is_string($args)) {
                $args = explode(' ', $args);
            } else {
                throw new InvalidArgumentException('Argument must be an array or a string');
            }
        }

        $this->args = $args;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param string $command
     * @return $this
     * @throws InvalidArgumentException
     * @throws FileAccess
     */
    public function setCommand($command)
    {
        if (!is_file($command)) {
            throw new InvalidArgumentException('Supplied file is not executable');
        }
        if (!is_executable($command)) {
            throw new FileAccess('File is not executable');
        }

        $this->command = $command;

        return $this;
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * @param array $env
     */
    public function setEnv($env)
    {
        if (!is_array($env)) {
            throw new InvalidArgumentException('Argument must be an array');
        }

        $this->env = $env;
    }

    /**
     * @codeCoverageIgnore
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        if ($this->pid) {
            throw new RuntimeException('Process is still running');
        }

        return pcntl_wexitstatus($this->status);
    }

    /**
     * @return int
     * @throws RuntimeException
     */
    public function execute()
    {
        if (!$this->command) {
            throw new UnexpectedValueException('Command have to be set prior to execution');
        }

        switch ($this->pid = pcntl_fork()) {
            // @codeCoverageIgnoreStart
            case 0:
                set_error_handler(function() { throw new RuntimeException(); });

                try {
                    pcntl_exec($this->command, $this->args, $this->env);
                } catch (RuntimeException $e) {
                    restore_error_handler();
                    exit(126);
                }
            case 1:
                throw new RuntimeException('fork() failed see PHP warning for error code');
            // @codeCoverageIgnoreEnd
            default:
        }

        return $this->pid;
    }

    /**
     * @return int
     */
    public function dispatchSignals()
    {
        if (!$this->pid) {
            throw new RuntimeException('Process is not running');
        }

        if (pcntl_waitpid($this->pid, $status, WNOHANG)) {
            $this->pid = null;
        };

        return $status;
    }

    /**
     * @return int
     */
    public function waitToFinish()
    {
        if (!$this->pid) {
            throw new RuntimeException('Process is not running');
        }

        pcntl_waitpid($this->pid, $this->status);
        $this->pid = null;

        return $this->status;
    }
}