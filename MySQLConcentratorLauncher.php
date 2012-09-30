<?php

require_once('MySQLConcentrator.php');

class MySQLConcentratorLauncher
{
  public $concentrator_pid = null;
  public $settings = null;

  function __construct($settings)
  {
    $this->settings = $settings;
  }

  function launch()
  {
    $this->concentrator_pid = pcntl_fork();
    if ($this->concentrator_pid == -1)
    {
      $errno = posix_get_last_error();
      throw new Exception("Error forking off mysql concentrator: $errno: " . posix_strerror($errno) . "\n");
    }
    elseif ($this->concentrator_pid == 0)
    {
      $mysql_concentrator = new MySQLConcentrator($this->settings);
      $mysql_concentrator->run();
      exit;
    }
  }

  function kill()
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
}
