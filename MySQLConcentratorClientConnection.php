<?php

require_once('MySQLConcentratorConnection.php');

class MySQLConcentratorClientConnection extends MySQLConcentratorConnection
{
  public $got_first_packet = false;
  public $queued = false;
  public $state = null;
  public $mysql_connection;
  public $write_packets = array();

  function __construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL)
  {
    parent::__construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL);
    $this->state = 'waiting_for_handshake';
    $this->mysql_connection = $this->concentrator->mysql_connection;
    if ($this->mysql_connection->handshake_completed)
    {
      $this->queue_write_packet($this->mysql_connection->handshake_init_packet);
    }
    else
    {
      $this->mysql_connection->queue($this);
    }
  }

  function done_with_operation()
  {
    return ($this->state == 'waiting_for_command');
  }

  function queue_read_packet($packet)
  {
    parent::queue_read_packet($packet);
    $method_name = "state_{$this->state}";
    $this->log("executing state {$this->state} with read packet\n");
    $this->$method_name($packet);
  }

  function queue_write_packet($packet)
  {
    $this->write_packets[] = $packet;
    $method_name = "state_{$this->state}";
    $this->log("executing state {$this->state} with write packet\n");
    $this->$method_name($packet);
  }

  function read()
  {
    parent::read();
    $this->read_packets();
    if (!empty($this->packets_read))
    {
      $this->mysql_connection->queue($this);
    }
  }

  function remove_packet(&$queue, $packet)
  {
    $key = array_search($packet, $queue);
    array_splice($queue, $key, 1);
  }

  function state_waiting_for_auth_packet($packet)
  {
    $this->state = 'waiting_for_auth_response';
    if ($this->mysql_connection->client_authentication_response_packet == null)
    {
      $this->mysql_connection->queue($this);
    }
    else
    {
      array_shift($this->packets_read);
      $this->queue_write_packet($this->mysql_connection->client_authentication_response_packet);
    }
  }

  function state_waiting_for_auth_response($packet)
  {
    $packet->parse('result', 'ok');
    if ($packet->type != MySQLConcentratorPacket::RESPONSE_OK)
    {
      throw new MySQLConcentratorFatalException("tried to queue a '" . $packet->type_name() . "' packet, but should be queuing an ok response to the client authentication packet");
    }
    else
    {
      $this->state = 'waiting_for_command';
    }
  }

  function state_waiting_for_command($packet)
  {
    $packet->parse('command');
    if (!$packet->is_command())
    {
      throw new MySQLConcentratorFatalException("In waiting for command state, expecting a command packet, but got a '" . $packet->type_name() . "' packet");
    }
    if ($packet->type == MySQLConcentratorPacket::COM_QUIT)
    {
      $this->remove_packet($this->packets_read, $packet);
      $this->state = 'quit';
    }
    $this->state = 'waiting_for_result';
  }

  function state_waiting_for_handshake($packet)
  {
    if ($packet->type != MySQLConcentratorPacket::HANDSHAKE_INITIALIZATION_PACKET)
    {
      throw new MySQLConcentratorFatalException("tried to queue a " . $packet->type_name() . "' packet, but should be queuing a handshake initization packet");
    }
    else
    {
      $this->state = 'waiting_for_auth_packet';
    }
  }

  function state_waiting_for_result($packet)
  {
    $packet->parse('result', 'result_set');
    switch ($packet->type)
    {
      case MySQLConcentratorPacket::RESPONSE_OK:
        $this->state = 'waiting_for_command';
        break;
      case MySQLConcentratorPacket::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case MySQLConcentratorPacket::RESPONSE_RESULT_SET:
        $this->num_fields = $packet->attributes['field_count'];
        $this->state = 'waiting_for_field';
        break;
      default:
        throw new MySQLConcentratorFatalException("expecting an error or result set packet after command in waiting for result state, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_waiting_for_field($packet)
  {
    $packet->parse('result', 'field');
    switch ($packet->type)
    {
      case MySQLConcentratorPacket::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case MySQLConcentratorPacket::RESPONSE_FIELD:
        $this->state = 'waiting_for_field';
        break;
      case MySQLConcentratorPacket::RESPONSE_EOF:
        $this->state = 'waiting_for_row_data';
        break;
      default:
        throw new MySQLConcentratorFatalException("expecting a field response or an eof response, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_waiting_for_row_data($packet)
  {
    $packet->parse('result', 'row_data', $this->num_fields);
    switch ($packet->type)
    {
      case MySQLConcentratorPacket::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case MySQLConcentratorPacket::RESPONSE_ROW_DATA:
        $this->state = 'waiting_for_row_data';
        break;
      case MySQLConcentratorPacket::RESPONSE_EOF:
        $this->state = 'waiting_for_command';
        break;
      default:
        throw new MySQLConcentratorFatalException("expecting a row data response or an eof response, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_quit($packet)
  {
    throw new MySQLConcentratorFatalException("shouldn't receive any packets in the quit state, but received a '" . $packet->type_name() . "' packet");
    $this->remove_packet($this->packets_read, $packet);
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
