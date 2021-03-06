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
     * @var resource $stdout
     */
    protected $stdout;

    /**
     * @var resource $stderr
     */
    protected $stderr;

    /**
     * @var resource $stdin
     */
    protected $stdin;

    /**
     * @param $command
     * @param array $args
     * @param array $env
     * @throws FileAccess
     * @codeCoverageIgnore
     */
    public function __construct($command = null, $args = array(), array $env = array())
    {
        if ($command) {
            $this->setCommand($command);
        }

        $this->setArgs($args);
        $this->setEnv($env);
    }

    public function __destruct()
    {
        if ($this->pid) {
            $this->terminate();
        }

        if (!is_null($this->status)) {
            unlink($this->stdin);
            unlink($this->stdout);
            unlink($this->stderr);
        }
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
     * @codeCoverageIgnore
     * @param array $env
     */
    public function setEnv(array $env)
    {
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
        if ($this->pid) {
            throw new RuntimeException("Process {$this->pid} already running");
        }

        if (is_null($this->status)) {
            $this->stdin  = tempnam(sys_get_temp_dir(), '');
            $this->stdout = tempnam(sys_get_temp_dir(), '');
            $this->stderr = tempnam(sys_get_temp_dir(), '');
        }

        switch ($this->pid = pcntl_fork()) {
            // @codeCoverageIgnoreStart
            case 0:
                fclose(STDIN);
                fclose(STDOUT);
                fclose(STDERR);

                $stdin  = fopen($this->stdin,  'r');
                $stdout = fopen($this->stdout, 'w');
                $stderr = fopen($this->stderr, 'w');

                posix_setsid();
                posix_setpgid(getmypid(), getmypid());

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
     * @return bool
     */
    public function dispatchSignals()
    {
        if (!$this->pid) {
            return true;
        } elseif (pcntl_waitpid($this->pid, $this->status, WNOHANG)) {
            $this->pid = null;
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return int
     */
    public function waitToFinish()
    {
        if (!$this->pid) {
            throw new RuntimeException('Process is not started yet');
        }

        pcntl_waitpid($this->pid, $this->status);
        $this->pid = null;

        return $this->status;
    }

    /**
     * @return array|mixed|null
     */
    public function readStdout()
    {
        return $this->readStream($this->stdout);
    }

    /**
     * @return array|mixed|null
     */
    public function readStderr()
    {
        return $this->readStream($this->stderr);
    }

    /**
     * @param string $data
     * @return int
     */
    public function writeStdin($data)
    {
        if (!$this->pid) {
            throw new RuntimeException('Process have to be executing');
        }

        $stdin = fopen($this->stdin, 'w+');
        $bytes = fwrite($stdin, $data);
        fclose($stdin);

        return $bytes;
    }

    /**
     * @return $this
     */
    public function terminate()
    {
        if (!$this->pid) {
            throw new RuntimeException('Process is not started yet');
        }

        if (posix_kill($this->pid, SIGTERM)) {
            pcntl_waitpid($this->pid, $this->status);
            $this->pid = null;
        };

        return $this;
    }

    /**
     * @param $file
     * @return array|mixed|null
     */
    protected function readStream($file)
    {
        if (!$file) {
            throw new RuntimeException('Process is not started yet');
        }

        $output = file($file, FILE_IGNORE_NEW_LINES);

        switch (count($output)) {
            case 0:
                return null;
            case 1:
                return current($output);
            default:
                return $output;
        }
    }
}