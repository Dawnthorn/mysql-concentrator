<?php

namespace MySQLConcentrator;

class Launcher
{
  public $concentrator_pid = null;
  public $settings = null;
  public $bin_path = null;

  function __construct($settings)
  {
    $this->settings = $settings;
    $this->bin_path = \GR\Path::join(dirname(dirname(__DIR__)), 'bin', 'mysql_concentrator.php');
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
      $cmd = "/usr/bin/php";
      $args = array("{$this->bin_path}", "-h", $this->settings['host'], '-p', $this->settings['port']);
      if (array_key_exists('listen_port', $this->settings))
      {
        $args[] = '-l';
        $args[] = $this->settings['listen_port'];
      }
      chdir(dirname(__FILE__));
      pcntl_exec("/usr/bin/php", $args);
      throw new Exception("Error executing '$cmd " . implode(" ", $args) . "'");
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
