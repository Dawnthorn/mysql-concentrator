<?php

require_once('PHPMySQLProxyConnection.php');
require_once('PHPMySQLProxySocket.php');

class PHPMySQLProxyFatalException extends Exception {}

class PHPMySQLProxy
{
  public $clients = array();
  public $listen_socket;
  public $listen_address = '127.0.0.1';
  public $listen_port = 3307;
  public $log_file_name = 'php-mysql-proxy.log';
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
      self::$original_error_handler = set_error_handler(array('PHPMySQLProxy', 'error_handler'));
    }
  }

  function create_mysql_connection()
  {
    $socket = $this->create_socket("mysql socket", '0.0.0.0');
    $this->mysql_connection = new PHPMySQLProxyConnection("mysql socket", $socket, FALSE, $this->mysql_address, $this->mysql_port);
  }

  function create_socket($socket_name, $address, $port = 0)
  {
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error creating $socket_name socket", $socket);
    }
    $result = @socket_set_nonblock($socket);
    if ($result === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error setting $socket_name socket nonblocking", $socket);
    }
    $result = @socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
    if ($result === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error setting option SOL_SOCKET SO_REUSEADDR to 1 for $socket_name socket", $socket);
    }
    $result = @socket_bind($socket, $address, $port);
    if ($result === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error binding $socket_name socket to {$address}:{$port}", $socket);
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
      throw new PHPMySQLProxyFatalException("$errno: $errstr: $errfile: $errline");
    }
  }

  function listen()
  {
    $this->listen_socket = $this->create_socket("listen socket", $this->listen_address, $this->listen_port);
    $result = @socket_listen($this->listen_socket);
    if ($result === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error listening to listen socket on {$this->listen_address}:{$this->listen_port}", $this->listen_socket);
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
      if ($this->mysql_connection != NULL)
      {
        $read_sockets[] = $this->mysql_connection->socket;
        if ($this->mysql_connection->wants_to_write())
        {
          $write_sockets[] = $this->mysql_connection->socket;
        }
      }
      foreach ($this->clients as $client)
      {
        $read_sockets[] = $client->socket;
        if ($client->wants_to_write())
        {
          $write_sockets[] = $client->socket;
        }
      }
      $exception_sockets = NULL;
      print_r($read_sockets);
      $num_changed_sockets = @socket_select($read_sockets, $write_sockets, $exception_sockets, NULL);
      if ($num_changed_sockets === FALSE)
      {
        throw new PHPMySQLProxySocketException("Error selecting on read sockets " . print_r($read_sockets, TRUE) . ", write sockets " . print_r($write_sockets, TRUE), FALSE);
      }
      elseif ($num_changed_sockets > 0)
      {
        foreach ($write_sockets as $write_socket)
        {
          if ($write_socket == $this->mysql_connection->socket)
          {
            $this->mysql_connection->write();
          }
          else
          {
            $connection = $this->clients[$write_socket];
            $connection->write();
          }
        }
        foreach ($read_sockets as $read_socket)
        {
          if ($read_socket == $this->listen_socket)
          {
            $socket = socket_accept($this->listen_socket);
            if ($socket === FALSE)
            {
              throw new PHPMySQLProxySocketException("Error accepting connection on listen socket", $this->listen_socket);
            }
            $this->clients[$socket] = new PHPMySQLProxyConnection("client", $socket, TRUE);
            if ($this->mysql_connection == NULL)
            {
              $this->create_mysql_connection();
            }
          }
          elseif ($read_socket == $this->mysql_connection->socket)
          {
            $this->mysql_connection->read();
            if (!$this->mysql_connection->read_buffer->is_empty())
            {
              $data = $this->mysql_connection->read_buffer->pop();
              foreach ($this->clients as $client)
              {
                $client->write_buffer->append($data);
              }
            }
          }
          else
          {
            $connection = $this->clients[$read_socket];
            $connection->read();
            if ($connection->closed)
            {
              unset($this->clients[$read_socket]);
            }
            elseif (!$connection->read_buffer->is_empty())
            {
              $this->mysql_connection->write_buffer->append($connection->read_buffer->pop());
            }
          }
        }
      }
    }
  }
}
