<?php

require_once('MySQLConcentratorConnection.php');

class MySQLConcentratorMySQLConnection extends MySQLConcentratorConnection
{
  public $client_authentication_response_packet = null;
  public $client_queue = array();
  public $current_client = null;
  public $handshake_completed = false;
  public $handshake_init_packet = null;

  function read()
  {
    parent::read();
    $this->read_packets();
    if (empty($this->packets_read))
    {
      $this->log("No packets\n");
      return;
    }
    if ($this->current_client == null)
    {
      throw new MySQLConcentratorFatalException("We have received packets on the MySQL connection, but we have no client expecting any packets.");
    }
    foreach ($this->packets_read as $packet)
    {
      if ($this->handshake_init_packet == null)
      {
        $this->handshake_init_packet = $packet;
      }
      elseif ($this->client_authentication_response_packet == null)
      {
        $this->client_authentication_response_packet = $packet;
        $this->handshake_completed = true;
      }
      $this->current_client->queue_packet($packet);
    }
    $this->packets_read = array();
    array_shift($this->client_queue);
    $this->current_client = null;
  }

  function queue($client_connection)
  {
    if (empty($this->client_queue))
    {
      $this->current_client = $client_connection;
    }
    $this->client_queue[] = $client_connection;
  }

  function wants_to_write()
  {
    return $this->current_client != NULL && !empty($this->current_client->packets_read);
  }

  function write()
  {
    if ($this->current_client == null)
    {
      if (empty($this->client_queue))
      {
        return;
      }
      $this->current_client = $this->client_queue[0];
    }
    while ($packet = array_shift($this->current_client->packets_read))
    {
      $this->write_buffer->append($packet->binary);
    }
    parent::write();
  }
}
