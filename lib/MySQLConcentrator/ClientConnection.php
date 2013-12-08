<?php

namespace MySQLConcentrator;

class ClientConnection extends Connection
{
  public $savepoint_name = NULL;
  public $got_first_packet = false;
  public $queued = false;
  public $state = null;
  public $mysql_connection;
  public $write_packets = array();
  static $statements_that_cause_implicit_commits = array
  (
    'ALTER DATABASE',
    'ALTER EVENT',
    'ALTER PROCEDURE',
    'ALTER SERVER',
    'ALTER TABLE',
    'CREATE DATABASE',
    'CREATE EVENT',
    'CREATE INDEX',
    'CREATE PROCEDURE',
    'CREATE SERVER',
    'CREATE TABLE',
    'DROP DATABASE',
    'DROP EVENT',
    'DROP INDEX',
    'DROP PROCEDURE',
    'DROP SERVER',
    'DROP TABLE',
    'RENAME TABLE',
    'TRUNCATE',
    'ALTER FUNCTION',
    'CREATE FUNCTION',
    'DROP FUNCTION',
    'CREATE USER',
    'DROP USER',
    'RENAME USER',
    'GRANT',
    'REVOKE',
    'SET PASSWORD',
    'LOCK TABLES',
    'UNLOCK TABLES',
    'CACHE INDEX',
    'LOAD INDEX INTO CACHE',
    'ANALYZE TABLE',
    'CHECK TABLE',
    'OPTIMIZE TABLE',
    'REPAIR TABLE',
  );

  function __construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL)
  {
    parent::__construct($concentrator, $name, $socket, $connected, $address = NULL, $port = NULL);
    $this->state = 'waiting_for_handshake';
    $this->mysql_connection = $this->concentrator->mysql_connection;
    if ($this->mysql_connection->handshake_init_packet != NULL)
    {
      $this->queue_write_packet($this->mysql_connection->handshake_init_packet);
    }
    else
    {
      $this->mysql_connection->queue($this);
    }
    $this->savepoint_name = "mysql_conc_{$this->port}";
  }

  function check_for_implicit_commit($statement)
  {
    foreach (self::$statements_that_cause_implicit_commits as $implicit_commit_statement)
    {
      if (\GR\Str::starts_with($statement, $implicit_commit_statement))
      {
	$this->log("WARNING: A statement that causes an implicit commit was called within a transaction:\n$statement\n");
	if ($this->concentrator->throw_exception_on_implicit_commits)
	{
	  throw new FatalException("A statement that causes and implicit commit was called within a transaction:\n$statement\n");
	} 
      }
    }
  }

  function disconnect()
  {
    $this->mysql_connection->remove($this);
    parent::disconnect();
  }

  function done_with_operation()
  {
    return ($this->state == 'waiting_for_command');
  }

  function queue_read_packet($packet)
  {
    parent::queue_read_packet($packet);
    $method_name = "state_{$this->state}";
//    $this->log("executing state {$this->state} with read packet\n");
    $this->$method_name($packet);
  }

  function queue_write_packet($packet)
  {
    $this->write_packets[] = $packet;
    $method_name = "state_{$this->state}";
//    $this->log("executing state {$this->state} with write packet\n");
//    $this->log("Write packets: . " . print_r($this->write_packets, TRUE) . "\n");
    $this->$method_name($packet);
  }

  function read()
  {
    parent::read();
    $this->read_packets();
    if (!empty($this->packets_read))
    {
      foreach ($this->packets_read as $packet)
      {
//        $this->log("Client Read: " . $packet->type_name() . "\n");
        $this->transform_transaction($packet);
      }
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
      $auth_packet = array_shift($this->packets_read);
//      $this->log("Auth packet:\n" . Hex::pretty_print($auth_packet->binary) . "\n");
      $this->queue_write_packet($this->mysql_connection->client_authentication_response_packet);
    }
  }

  function state_waiting_for_auth_response($packet)
  {
    $packet->parse('result', 'ok');
    if ($packet->type == Packet::RESPONSE_OK)
    {
      $this->state = 'waiting_for_command';
    }
  }

  function state_waiting_for_command($packet)
  {
    $packet->parse('command');
    if (!$packet->is_command())
    {
      throw new FatalException("In waiting for command state, expecting a command packet, but got a '" . $packet->type_name() . "' packet");
    }
    switch ($packet->type)
    {
      case Packet::COM_QUIT:
        $this->remove_packet($this->packets_read, $packet);
        $this->state = 'quit';
        break;
      case Packet::COM_SET_OPTION:
        $this->state = 'waiting_for_set_option_response';
        break;
      default:
        $this->state = 'waiting_for_result';
    }
  }

  function state_waiting_for_field($packet)
  {
    $packet->parse('query_response', 'field');
    switch ($packet->type)
    {
      case Packet::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case Packet::RESPONSE_FIELD:
        $this->state = 'waiting_for_field';
        break;
      case Packet::RESPONSE_EOF:
        $this->state = 'waiting_for_row_data';
        break;
      default:
        throw new FatalException("expecting a field response or an eof response, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_waiting_for_handshake($packet)
  {
    if ($packet->type != Packet::HANDSHAKE_INITIALIZATION_PACKET)
    {
      throw new FatalException("tried to queue a " . $packet->type_name() . "' packet, but should be queuing a handshake initization packet");
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
      case Packet::RESPONSE_OK:
        $this->state = 'waiting_for_command';
        break;
      case Packet::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case Packet::RESPONSE_RESULT_SET:
        $this->num_fields = $packet->attributes['field_count'];
        $this->state = 'waiting_for_field';
        break;
      default:
        throw new FatalException("expecting an error or result set packet after command in waiting for result state, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_waiting_for_row_data($packet)
  {
    $first_byte = ord($packet->binary{4});
    $packet->parse('query_response', 'row_data', $this->num_fields);

    switch ($packet->type)
    {
      case Packet::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case Packet::RESPONSE_ROW_DATA:
        $this->state = 'waiting_for_row_data';
        break;
      case Packet::RESPONSE_EOF:
        $this->state = 'waiting_for_command';
        break;
      default:
        throw new FatalException("expecting a row data response or an eof response, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_waiting_for_set_option_response($packet)
  {
    $packet->parse('result', 'eof');
    switch ($packet->type)
    {
      case Packet::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case Packet::RESPONSE_EOF:
        $this->state = 'waiting_for_command';
        break;
      default:
        throw new FatalException("expecting a an eof response to set_option, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_quit($packet)
  {
    throw new FatalException("shouldn't receive any packets in the quit state, but received a '" . $packet->type_name() . "' packet");
    $this->remove_packet($this->packets_read, $packet);
  }

  function transform_transaction($packet)
  {
    if (!$packet->is_query())
    {
      return;
    }
    $statement = strtoupper(trim($packet->attributes['statement']));;
//    $this->log("$statement\n");
    if ($this->concentrator->check_for_implicit_commits)
    {
      if ($this->mysql_connection->transaction_count > 0)
      {
	if ($this->concentrator->transform_truncates && \GR\Str::starts_with($statement, 'TRUNCATE'))
	{
	  $new_statement = preg_replace("/TRUNCATE( TABLE)?(.*)/i", 'DELETE FROM$2', $packet->attributes['statement']);
	  $packet->replace_statement_with($new_statement);
	}
	else
	{
	  $this->check_for_implicit_commit($statement);
	}
      }
    }
    if (\GR\Str::starts_with($statement, 'BEGIN') || \Gr\Str::starts_with($statement, 'START TRANSACTION'))
    {
      if ($statement != 'BEGIN' && $statement != 'START TRANSACTION')
      {
        throw new FatalException("Currently we can't handle a BEGIN statement with arguments like '$statement'");
      }
      if ($this->mysql_connection->transaction_count > 0)
      {
        $packet->replace_statement_with("SAVEPOINT {$this->savepoint_name}");
      }
      $this->mysql_connection->transaction_count++;
    }
    elseif (\GR\Str::starts_with($statement, 'ROLLBACK') && !\GR\Str::starts_with($statement, 'ROLLBACK TO SAVEPOINT')) 
    {
      if ($statement != 'ROLLBACK')
      {
        throw new FatalException("Currently we can't handle a ROLLBACK statement with arguments like '$statement'");
      }
      if ($this->mysql_connection->transaction_count > 1)
      {
        $packet->replace_statement_with("ROLLBACK TO SAVEPOINT {$this->savepoint_name}");
      }
      if ($this->mysql_connection->transaction_count > 0)
      {
        $this->mysql_connection->transaction_count--;
      }
    }
    elseif (\GR\Str::starts_with($statement, 'COMMIT'))
    {
      if ($statement != 'COMMIT')
      {
        throw new FatalException("Currently we can't handle a ROLLBACK statement with arguments like '$statement'");
      }
      if ($this->mysql_connection->transaction_count > 1)
      {
        $packet->replace_statement_with("RELEASE SAVEPOINT {$this->savepoint_name}");
      }
      if ($this->mysql_connection->transaction_count > 0)
      {
        $this->mysql_connection->transaction_count--;
      }
    }
    elseif (preg_match("/SET\s*AUTOCOMMIT.*/", $statement))
    {
      $packet->replace_statement_with("SET AUTOCOMMIT=0");
    }
  }

  function wants_to_write()
  {
//    $this->log("Wants to write: " . print_r($this->write_packets, TRUE) . " || " . !$this->write_buffer->is_empty() . "\n");
    return (!empty($this->write_packets)) || (!$this->write_buffer->is_empty());
  }

  function write()
  {
//    $this->log("Writing to client.\n");
    while ($packet = array_shift($this->write_packets))
    {
      $this->write_buffer->append($packet->binary);
    }
    parent::write();
  }
}
