<?php

require_once('MySQLConcentratorConnection.php');
require_once('contrib/php-util/string.php');

class MySQLConcentratorClientConnection extends MySQLConcentratorConnection
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
    if ($this->mysql_connection->handshake_completed)
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
      if (string_starts_with($statement, $implicit_commit_statement))
      {
	$this->log("WARNING: A statement that causes an implicit commit was called within a transaction:\n$statement\n");
	if ($this->concentrator->throw_exception_on_implicit_commits)
	{
	  throw new MySQLConcentratorFatalException("A statement that causes and implicit commit was called within a transaction:\n$statement\n");
	} 
      }
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
//    $this->log("executing state {$this->state} with read packet\n");
    $this->$method_name($packet);
  }

  function queue_write_packet($packet)
  {
    $this->write_packets[] = $packet;
    $method_name = "state_{$this->state}";
//    $this->log("executing state {$this->state} with write packet\n");
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
    switch ($packet->type)
    {
      case MySQLConcentratorPacket::COM_QUIT:
        $this->remove_packet($this->packets_read, $packet);
        $this->state = 'quit';
        break;
      case MySQLConcentratorPacket::COM_SET_OPTION:
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

  function state_waiting_for_row_data($packet)
  {
    $first_byte = ord($packet->binary{4});
    $packet->parse('query_response', 'row_data', $this->num_fields);

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

  function state_waiting_for_set_option_response($packet)
  {
    $packet->parse('result', 'eof');
    switch ($packet->type)
    {
      case MySQLConcentratorPacket::RESPONSE_ERROR:
        $this->state = 'waiting_for_command';
        break;
      case MySQLConcentratorPacket::RESPONSE_EOF:
        $this->state = 'waiting_for_command';
        break;
      default:
        throw new MySQLConcentratorFatalException("expecting a an eof response to set_option, but got a '" . $packet->type_name() . "' packet");
    }
  }

  function state_quit($packet)
  {
    throw new MySQLConcentratorFatalException("shouldn't receive any packets in the quit state, but received a '" . $packet->type_name() . "' packet");
    $this->remove_packet($this->packets_read, $packet);
  }

  function transform_transaction($packet)
  {
    if (!$packet->is_query())
    {
      return;
    }
    $statement = strtoupper(trim($packet->attributes['statement']));;
    if ($this->concentrator->check_for_implicit_commits)
    {
      if ($this->mysql_connection->transaction_count > 0)
      {
	if ($this->concentrator->transform_truncates && string_starts_with($statement, 'TRUNCATE'))
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
    if (string_starts_with($statement, 'BEGIN') || string_starts_with($statement, 'START TRANSACTION'))
    {
      if ($statement != 'BEGIN' && $statement != 'START TRANSACTION')
      {
        throw new MySQLConcentratorFatalException("Currently we can't handle a BEGIN statement with arguments like '$statement'");
      }
      if ($this->mysql_connection->transaction_count > 0)
      {
        $packet->replace_statement_with("SAVEPOINT {$this->savepoint_name}");
      }
      $this->mysql_connection->transaction_count++;
    }
    elseif (string_starts_with($statement, 'ROLLBACK') && !string_starts_with($statement, 'ROLLBACK TO SAVEPOINT')) 
    {
      if ($statement != 'ROLLBACK')
      {
        throw new MySQLConcentratorFatalException("Currently we can't handle a ROLLBACK statement with arguments like '$statement'");
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
    elseif (string_starts_with($statement, 'COMMIT'))
    {
      if ($statement != 'COMMIT')
      {
        throw new MySQLConcentratorFatalException("Currently we can't handle a ROLLBACK statement with arguments like '$statement'");
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
    return (!empty($this->write_packets)) || (!$this->write_buffer->is_empty());
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
