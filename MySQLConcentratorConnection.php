<?php

require_once('MySQLConcentratorBuffer.php');
require_once('MySQLConcentratorHex.php');
require_once('MySQLConcentratorPacket.php');
require_once('MySQLConcentratorSocket.php');

class MySQLConcentratorConnection
{
  public $address;
  public $concentrator;
  public $connected = FALSE;
  public $closed = FALSE;
  public $name;
  public $packets_read = array();
  public $port;
  public $proxy;
  public $read_buffer;
  public $write_buffer;
  public $socket;

  function __construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL)
  {
    $this->address = $address;
    $this->concentrator = $concentrator;
    $this->connected = $connected;
    $this->name = $name;
    $this->port = $port;
    $this->read_buffer = new MySQLConcentratorBuffer();
    $this->socket = $socket;
    $this->write_buffer = new MySQLConcentratorBuffer();
    if ($address == NULL)
    {
      $this->get_socket_info();
    }
  }

  function connect()
  {
    $result = @socket_connect($this->socket, $this->address, $this->port);
    if ($result === FALSE)
    {
      $error_code = socket_last_error($this->socket);
      if ($error_code !== SOCKET_EINPROGRESS)
      {
        throw new MySQLConcentratorSocketException("Error connecting to MySQL server at {$this->address}:{$this->port}", $this->socket);
      }
    }
    else
    {
      $this->connected = TRUE;
    }
  }

  function get_socket_info()
  {
    $result = @socket_getpeername($this->socket, $this->address, $this->port);
    if ($result === FALSE)
    {
      $error_code = socket_last_error($this->socket);
      if ($error_code !== SOCKET_EINPROGRESS)
      {
        throw new MySQLConcentratorSocketException("Error requsting address info (socket_getpeername)", $this->socket);
      }
    }
  }

  function log($str)
  {
    $str = "(" . $this->log_name() . ") $str";
    $this->concentrator->log->log($str);
  }

  function log_name()
  {
    return "{$this->name}:{$this->address}:{$this->port}";
  }

  function queue_read_packet($packet)
  {
    $this->packets_read[] = $packet;
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
        throw new MySQLConcentratorSocketException("Error reading from {$this->name} ({$this->address}:{$this->port})", $this->socket);
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
//        $this->log("Read:\n" . hex_pretty_print($result) . "\n");
      }
    }
  }  

  function read_packets()
  {
    while ($this->read_buffer->length() > 4)
    {
//      $this->log("reading packets\n");
//      $this->log("Read Buffer:\n" . hex_pretty_print($this->read_buffer->buffer) . "\n");
      list($length, $number) = MySQLConcentratorPacket::parse_header($this->read_buffer->buffer);
//      $this->log("got packet {$number} of length {$length} read buffer length: " . $this->read_buffer->length() . "\n");
      if ($length + 4 <= $this->read_buffer->length())
      {
//        $this->log("read packet of length {$length}.\n");
        $binary = $this->read_buffer->pop($length + 4);
        $packet = new MySQLConcentratorPacket($binary);
//        $this->log("Packet:\n" . hex_pretty_print($binary) . "\n");
//        $this->log(hex_php_string($binary) . "\n");
        $this->queue_read_packet($packet);
      }
      else
      {
        return;
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
      throw new MySQLConcentratorSocketException("Error writing to {$this->name} ({$this->address}:{$this->port})", $this->socket);
    }
//    $this->log("Wrote:\n" . hex_pretty_print($this->write_buffer->buffer) . "\n");
    $this->write_buffer->pop($result);
  }
}
