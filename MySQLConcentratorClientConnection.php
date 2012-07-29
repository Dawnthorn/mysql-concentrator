<?php

require_once('MySQLConcentratorConnection.php');

class MySQLConcentratorClientConnection extends MySQLConcentratorConnection
{
  public $got_first_packet = false;
  public $mysql_connection;
  public $write_packets = array();

  function __construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL)
  {
    parent::__construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL);
    $this->mysql_connection = $this->concentrator->mysql_connection;
    if ($this->mysql_connection != null && $this->mysql_connection->handshake_completed)
    {
      $this->queue_packet($this->mysql_connection->handshake_init_packet);
    }
  }

  function queue_packet($packet)
  {
    $this->log("queuing packet\n");
    $this->write_packets[] = $packet;
  }

  function read()
  {
    parent::read();
    $this->read_packets();
    if (!empty($this->packets_read))
    {
      if ($this->mysql_connection != null && !$this->got_first_packet)
      {
        array_shift($this->packets_read);
        $this->queue_packet($this->mysql_connection->client_authentication_response_packet);
        $this->got_first_packet = true;
      }
      else
      {
        $this->concentrator->mysql_connection->queue($this);
      }
    }
  }

  function wants_to_write()
  {
    return !empty($this->write_packets);
  }

  function write()
  {
    while ($packet = array_shift($this->write_packets))
    {
      $this->write_buffer->append($packet->binary);
    }
    parent::write();
  }
}
