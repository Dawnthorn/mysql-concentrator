<?php

require_once('vendor/autoload.php');

class TestLauncher
{
  public $argv = null;
  public $concentrator_pid = null;

  function __construct($argv)
  {
    $this->argv = $argv;
  }

  function launch_concentrator()
  {
    $this->concentrator_pid = pcntl_fork();
    if ($this->concentrator_pid == -1)
    {
      $errno = posix_get_last_error();
      throw new Exception("Error forking off mysql concentrator: $errno: " . posix_strerror($errno) . "\n");
    }
    elseif ($this->concentrator_pid == 0)
    {
      $mysql_concentrator = new MySQLConcentrator\Server();
      $mysql_concentrator->run();
      exit;
    }
  }

  function kill_concentrator()
  {
    $result = posix_kill($this->concentrator_pid, SIGTERM);
    if ($result === FALSE)
    {
      $errno = posix_get_last_error();
      throw new Exception("Error killing off mysql concentrator: $errno: " . posix_strerror($errno) . "\n");
    }
    $status = null;
    $result = pcntl_waitpid($this->concentrator_pid, $status);
    if ($result == -1)
    {
      $errno = posix_get_last_error();
      throw new Exception("Error waiting for concentrator ({$this->concentrator_pid}): $errno: " . posix_strerror($errno) . "\n");
    }
  }

  function launch()
  {
    $this->launch_concentrator();
    $args = array_slice($this->argv, 1);
    $arg_str = implode(" ", $args);
    $command = "php $arg_str";
    system($command);
    $this->kill_concentrator();
  }
}

$test_launcher = new TestLauncher($argv);
$test_launcher->launch();
