<?php

require_once('MySQLConcentratorClientConnection.php');
require_once('MySQLConcentratorConnection.php');
require_once('MySQLConcentratorLog.php');
require_once('MySQLConcentratorMySQLConnection.php');
require_once('MySQLConcentratorSocket.php');

class MySQLConcentratorFatalException extends Exception {}

class MySQLConcentrator
{
  public $connections = array();
  public $listen_socket;
  public $listen_address = '127.0.0.1';
  public $listen_port = 3307;
  public $log_file_name = 'mysql-concentrator.log';
  public $mysql_connection;
  public $mysql_address = '127.0.0.1';
  public $mysql_port = 3306;
  public static $original_error_handler = NULL;
  public static $error_handler_set = FALSE;

  function __construct()
  {
    if (!self::$error_handler_set)
    {
      error_reporting(E_ALL);
      self::$error_handler_set = TRUE;
      self::$original_error_handler = set_error_handler(array('MySQLConcentrator', 'error_handler'));
    }
    $this->log = new MySQLConcentratorLog($this->log_file_name);
  }

  function create_mysql_connection()
  {
    $socket = $this->create_socket("mysql socket", '0.0.0.0');
    $this->mysql_connection = new MySQLConcentratorMySQLConnection($this, "mysql socket", $socket, FALSE, $this->mysql_address, $this->mysql_port);
    $this->connections[$socket] = $this->mysql_connection;
  }

  function create_socket($socket_name, $address, $port = 0)
  {
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === FALSE)
    {
      throw new MySQLConcentratorSocketException("Error creating $socket_name socket", $socket);
    }
    $result = @socket_set_nonblock($socket);
    if ($result === FALSE)
    {
      throw new MySQLConcentratorSocketException("Error setting $socket_name socket nonblocking", $socket);
    }
    $result = @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
    if ($result === FALSE)
    {
      throw new MySQLConcentratorSocketException("Error setting option SOL_SOCKET SO_REUSEADDR to 1 for $socket_name socket", $socket);
    }
    $result = @socket_bind($socket, $address, $port);
    if ($result === FALSE)
    {
      throw new MySQLConcentratorSocketException("Error binding $socket_name socket to {$address}:{$port}", $socket);
    }
    return $socket;
  }

  static function error_handler($errno, $errstr, $errfile, $errline, $context)
  {
    if (self::$original_error_handler != NULL)
    {
      $function_name = self::$original_error_handler;
      $function_name($errno, $errstr, $errfile, $errline, $context);
    }
    if ((error_reporting() & $errno) > 0)
    {
      throw new MySQLConcentratorFatalException("$errno: $errstr: $errfile: $errline");
    }
  }

  function listen()
  {
    $this->listen_socket = $this->create_socket("listen socket", $this->listen_address, $this->listen_port);
    $result = @socket_listen($this->listen_socket);
    if ($result === FALSE)
    {
      throw new MySQLConcentratorSocketException("Error listening to listen socket on {$this->listen_address}:{$this->listen_port}", $this->listen_socket);
    }
  }

  function run()
  {
    $this->listen();
    $read_sockets = array();
    $write_sockets = array();
    while (1)
    {
      $read_sockets = array($this->listen_socket);
      $write_sockets = array();
      foreach ($this->connections as $connection)
      {
        $read_sockets[] = $connection->socket;
        if ($connection->wants_to_write())
        {
          $write_sockets[] = $connection->socket;
        }
      }
      $exception_sockets = NULL;
      $num_changed_sockets = @socket_select($read_sockets, $write_sockets, $exception_sockets, NULL);
      if ($num_changed_sockets === FALSE)
      {
        throw new MySQLConcentratorSocketException("Error selecting on read sockets " . print_r($read_sockets, TRUE) . ", write sockets " . print_r($write_sockets, TRUE), FALSE);
      }
      elseif ($num_changed_sockets > 0)
      {
        foreach ($write_sockets as $write_socket)
        {
          $connection = $this->connections[$write_socket];
          $connection->write();
        }
        foreach ($read_sockets as $read_socket)
        {
          if ($read_socket == $this->listen_socket)
          {
            $socket = socket_accept($this->listen_socket);
            if ($socket === FALSE)
            {
              throw new MySQLConcentratorSocketException("Error accepting connection on listen socket", $this->listen_socket);
            }
            if ($this->mysql_connection == NULL)
            {
              $this->create_mysql_connection();
            }
            $client_connection = new MySQLConcentratorClientConnection($this, "client", $socket, TRUE);
            $this->connections[$socket] = $client_connection;
          }
          else
          {
            $connection = $this->connections[$read_socket];
            $connection->read();
            if ($connection->closed)
            {
              unset($this->connections[$read_socket]);
            }
          }
        }
      }
    }
  }
}
