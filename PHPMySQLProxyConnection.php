<?php

require_once('PHPMySQLProxyBuffer.php');
require_once('PHPMySQLProxySocket.php');

class PHPMySQLProxyConnection
{
  public $address;
  public $connected = FALSE;
  public $closed = FALSE;
  public $name;
  public $port;
  public $proxy;
  public $read_buffer;
  public $write_buffer;
  public $socket;

  function __construct($name, $socket, $connected, $address = NULL, $port = NULL)
  {
    $this->address = $address;
    $this->connected = $connected;
    $this->name = $name;
    $this->port = $port;
    $this->read_buffer = new PHPMySQLProxyBuffer();
    $this->socket = $socket;
    $this->write_buffer = new PHPMySQLProxyBuffer();
  }

  function connect()
  {
    $result = @socket_connect($this->socket, $this->address, $this->port);
    if ($result === FALSE)
    {
      $error_code = socket_last_error($this->socket);
      if ($error_code !== SOCKET_EINPROGRESS)
      {
        throw new PHPMySQLProxySocketException("Error connecting to MySQL server at {$this->address}:{$this->port}", $this->socket);
      }
    }
    else
    {
      $this->connected = TRUE;
    }
  }

  function read()
  {
    if (!$this->connected)
    {
      $this->connect();
    }
    else
    {
      $result = socket_read($this->socket, $this->read_buffer->space_remaining());
      if ($result === FALSE)
      {
        throw new PHPMySQLProxySocketException("Error reading from {$this->name} ({$this->address}:{$this->port})", $this->socket);
      }
      elseif ($result === '')
      {
        $this->connected = FALSE;
        socket_close($this->socket);
        $this->socket = NULL;
        $this->closed = TRUE;
      }
      else
      {
        $this->read_buffer->append($result);
        print("Read ({$this->name}): $result\n");
      }
    }
  }  

  function wants_to_write()
  {
    return !$this->write_buffer->is_empty();
  }

  function write()
  {
    if (!$this->connected)
    {
      return;
    }
    $result = socket_write($this->socket, $this->write_buffer->buffer);
    if ($result === FALSE)
    {
      throw new PHPMySQLProxySocketException("Error writing to {$this->name} ({$this->address}:{$this->port})", $this->socket);
    }
    print("Wrote ({$this->name}: {$this->write_buffer->buffer}\n");
    $this->write_buffer->pop($result);
  }
}
