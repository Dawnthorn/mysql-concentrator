<?php

namespace MySQLConcentrator;

class Packet
{
  const HANDSHAKE_INITIALIZATION_PACKET = 0xffff;
  const CLIENT_AUTHENTICATION_PACKET = 0xfffe;
  const RESPONSE_OK = 0xfffd;
  const RESPONSE_ERROR = 0xfffc;
  const RESPONSE_RESULT_SET = 0xfffb;
  const RESPONSE_FIELD = 0xfffa;
  const RESPONSE_ROW_DATA = 0xfff9;
  const RESPONSE_EOF = 0xfff8;
  const COM_SLEEP = 0;
  const COM_QUIT = 1;
  const COM_INIT_DB = 2;
  const COM_QUERY = 3;
  const COM_FIELD_LIST = 4;
  const COM_CREATE_DB = 5;
  const COM_DROP_DB = 6; 
  const COM_REFRESH = 7;
  const COM_SHUTDOWN = 8; 
  const COM_STATISTICS = 9;
  const COM_PROCESS_INFO = 10;
  const COM_CONNECT = 11;
  const COM_PROCESS_KILL = 12;
  const COM_DEBUG = 13;
  const COM_PING = 14;
  const COM_TIME = 15;
  const COM_DELAYED_INSERT = 16;
  const COM_CHANGE_USER = 17;
  const COM_BINLOG_DUMP = 18;
  const COM_TABLE_DUMP = 19;
  const COM_CONNECT_OUT = 20;
  const COM_REGISTER_SLAVE = 21;
  const COM_STMT_PREPARE = 22;
  const COM_STMT_EXECUTE = 23;
  const COM_STMT_SEND_LONG_DATA = 24;
  const COM_STMT_CLOSE = 25;
  const COM_STMT_RESET = 26;
  const COM_SET_OPTION = 27;
  const COM_STMT_FETCH = 28;
  const COM_DAEMON = 29;

  static $type_to_string = array
  (
    self::HANDSHAKE_INITIALIZATION_PACKET => 'handshake_initialization',
    self::CLIENT_AUTHENTICATION_PACKET => 'client_authentication',
    self::RESPONSE_OK => 'ok',
    self::RESPONSE_ERROR => 'error',
    self::RESPONSE_RESULT_SET => 'result_set',
    self::RESPONSE_FIELD => 'field',
    self::RESPONSE_ROW_DATA => 'row_data',
    self::RESPONSE_EOF => 'eof',
    self::COM_SLEEP => 'sleep',
    self::COM_QUIT => 'quit',
    self::COM_INIT_DB => 'init_db',
    self::COM_QUERY => 'query',
    self::COM_FIELD_LIST => 'field_list',
    self::COM_CREATE_DB => 'create_db',
    self::COM_DROP_DB => 'drop_db',
    self::COM_REFRESH => 'refresh',
    self::COM_SHUTDOWN => 'shutdown',
    self::COM_STATISTICS => 'statistics',
    self::COM_PROCESS_INFO => 'process_info',
    self::COM_CONNECT => 'connect',
    self::COM_PROCESS_KILL => 'process_kill',
    self::COM_DEBUG => 'debug',
    self::COM_PING => 'ping',
    self::COM_TIME => 'time',
    self::COM_DELAYED_INSERT => 'delayed_insert',
    self::COM_CHANGE_USER => 'change_user',
    self::COM_BINLOG_DUMP => 'binlog_dump',
    self::COM_TABLE_DUMP => 'table_dump',
    self::COM_CONNECT_OUT => 'connect_out',
    self::COM_REGISTER_SLAVE => 'register_slave',
    self::COM_STMT_PREPARE => 'stmt_prepare',
    self::COM_STMT_EXECUTE => 'stmt_execute',
    self::COM_STMT_SEND_LONG_DATA => 'stmt_send_log_data',
    self::COM_STMT_CLOSE => 'stmt_close',
    self::COM_STMT_RESET => 'stmt_reset',
    self::COM_SET_OPTION => 'set_option',
    self::COM_STMT_FETCH => 'stmt_fetch',
    self::COM_DAEMON => 'daemon',
  );

  static $command_id_to_string = array
  (
    self::COM_SLEEP => 'sleep',
    self::COM_QUIT => 'quit',
    self::COM_INIT_DB => 'init_db',
    self::COM_QUERY => 'query',
    self::COM_FIELD_LIST => 'field_list',
    self::COM_CREATE_DB => 'create_db',
    self::COM_DROP_DB => 'drop_db',
    self::COM_REFRESH => 'refresh',
    self::COM_SHUTDOWN => 'shutdown',
    self::COM_STATISTICS => 'statistics',
    self::COM_PROCESS_INFO => 'process_info',
    self::COM_CONNECT => 'connect',
    self::COM_PROCESS_KILL => 'process_kill',
    self::COM_DEBUG => 'debug',
    self::COM_PING => 'ping',
    self::COM_TIME => 'time',
    self::COM_DELAYED_INSERT => 'delayed_insert',
    self::COM_CHANGE_USER => 'change_user',
    self::COM_BINLOG_DUMP => 'binlog_dump',
    self::COM_TABLE_DUMP => 'table_dump',
    self::COM_CONNECT_OUT => 'connect_out',
    self::COM_REGISTER_SLAVE => 'register_slave',
    self::COM_STMT_PREPARE => 'stmt_prepare',
    self::COM_STMT_EXECUTE => 'stmt_execute',
    self::COM_STMT_SEND_LONG_DATA => 'stmt_send_log_data',
    self::COM_STMT_CLOSE => 'stmt_close',
    self::COM_STMT_RESET => 'stmt_reset',
    self::COM_SET_OPTION => 'set_option',
    self::COM_STMT_FETCH => 'stmt_fetch',
    self::COM_DAEMON => 'daemon',
  );

  public $attributes = array();
  public $binary = null;
  public $length = null;
  public $number = null;
  public $parsed = false;
  public $type = null;

  function __construct($binary = null)
  {
    $this->binary = $binary;
  }

  function is_command()
  {
    return ($this->type >= self::COM_SLEEP && $this->type <= self::COM_DAEMON);
  }

  function is_query()
  {
    return ($this->type == self::COM_QUERY);
  }

  static function marshall_little_endian_integer($value, $length)
  {
    $result = '';
    for ($i = 0; $i < $length; $i++)
    {
      $result .= chr($value & 0xff);
      $value = $value >> 8;
    }
    return $result;
  }

  function parse($expected)
  {
    if ($this->parsed)
    {
      return;
    }
    list($this->length, $this->number) = self::parse_header($this->binary);
    $method = "parse_$expected";
    $this->$method(func_get_args());
    $this->parsed = true;
  }

  function parse_closing_string($attribute_name)
  {
    $this->attributes[$attribute_name] = substr($this->binary, $this->parse_position);
    $this->parse_position = $this->length;
  }

  function parse_null_terminated_string($attribute_name)
  {
    list($result, $length) = Packet::unmarshall_null_terminated_string(substr($this->binary, $this->parse_position));
    $this->attributes[$attribute_name] = substr($this->binary, $this->parse_position, $length - 1);
    $this->parse_position += $length;
  }

  function parse_com_field_list()
  {
    $this->parse_null_terminated_string('table_name');
    $this->parse_closing_string('column_name');
  }

  function parse_com_init_db()
  {
    $this->parse_closing_string('database_name');
  }

  function parse_com_ping()
  {
  }

  function parse_com_set_option()
  {
    $this->parse_next_2_byte_integer('option_id');
  }

  function parse_com_query()
  {
    $this->parse_closing_string('statement');
  }

  function parse_com_quit()
  {
  }

  function parse_command()
  {
    $first_byte = ord($this->binary{4});
    $this->type = $first_byte;
    $this->parse_position = 5;
    if (array_key_exists($first_byte, self::$command_id_to_string))
    {
      $command_name = self::$command_id_to_string[$first_byte];
    }
    else
    {
      return "don't know how to parse command with id '$first_byte'";
    }
    $method_name = "parse_com_$command_name";
    $this->$method_name(); 
  }

  function parse_eof()
  {
    $this->parse_position = 5;
    $this->parse_next_2_byte_integer('warning_count');
    $this->parse_next_2_byte_integer('status_flags');
  }

  function parse_error()
  {
    $this->parse_position = 5;
    $this->parse_next_2_byte_integer('errno');
    $this->parse_next_1_byte_integer('sqlstate_marker');
    $this->attributes['sqlstate'] = substr($this->binary, $this->parse_position, 5);
    $this->parse_closing_string('message');
  }

  function parse_field()
  {
    $this->type = self::RESPONSE_FIELD;
    $this->parse_position = 4;
    $this->parse_next_length_coded_string('catalog'); 
    $this->parse_next_length_coded_string('db'); 
    $this->parse_next_length_coded_string('table'); 
    $this->parse_next_length_coded_string('org_table'); 
    $this->parse_next_length_coded_string('name'); 
    $this->parse_next_length_coded_string('org_name'); 
    $this->parse_position += 1;
    $this->parse_next_2_byte_integer('charsetnr'); 
    $this->parse_next_4_byte_integer('length'); 
    $this->parse_next_1_byte_integer('type'); 
    $this->parse_next_2_byte_integer('flags'); 
    $this->parse_next_1_byte_integer('decimals'); 
    $this->parse_position += 2;
    $this->parse_next_length_coded_binary('default'); 
  }

  static function parse_header($binary)
  {
    $length = self::unmarshall_little_endian_integer($binary, 3);
    $number = ord($binary{3});
    return array($length, $number);
  }

  function parse_next_1_byte_integer($attribute_name)
  {
    $value = ord($this->binary{$this->parse_position});
    $this->attributes[$attribute_name] = $value;
    $this->parse_position += 1;
  }

  function parse_next_2_byte_integer($attribute_name)
  {
    $value = self::unmarshall_little_endian_integer($this->binary, 2, $this->parse_position);
    $this->attributes[$attribute_name] = $value;
    $this->parse_position += 2;
  }

  function parse_next_4_byte_integer($attribute_name)
  {
    $value = self::unmarshall_little_endian_integer($this->binary, 2, $this->parse_position);
    $this->attributes[$attribute_name] = $value;
    $this->parse_position += 4;
  }

  function parse_next_length_coded_binary($attribute_name)
  {
    list($value, $length) = self::unmarshall_length_coded_binary(substr($this->binary, $this->parse_position));
    $this->attributes[$attribute_name] = $value;
    $this->parse_position += $length;
  }

  function parse_next_length_coded_string($attribute_name)
  {
    list($value, $length) = self::unmarshall_length_coded_string(substr($this->binary, $this->parse_position));
    $this->attributes[$attribute_name] = $value;
    $this->parse_position += $length;
  }

  function parse_ok()
  {
    $this->parse_next_length_coded_binary('field_count');
    $this->parse_next_length_coded_binary('affected_rows');
    $this->parse_next_length_coded_binary('insert_id');
    $this->parse_next_2_byte_integer('server_status');
    $this->parse_next_2_byte_integer('warning_count');
    $this->parse_closing_string('message');
  }

  function parse_query_response($args)
  {
    $result_expected = $args[1];
    $first_byte = ord($this->binary{4});
    $this->parse_position = 4;
    switch ($first_byte)
    {
     case 0xff:
       $this->type = self::RESPONSE_ERROR;
       $this->parse_error();
       break;
     default:
       if ($this->length < 6 && $first_byte == 0xfe)
       {
         $this->type = self::RESPONSE_EOF;
         $this->parse_eof();
       }
       else
       {
        $this->type = self::RESPONSE_RESULT_SET;
        $method_name = "parse_$result_expected";
        $this->$method_name($args);
       }
       break;
    }
  }

  function parse_result($args)
  {
    $result_expected = $args[1];
    $first_byte = ord($this->binary{4});
    $this->parse_position = 4;
    switch ($first_byte)
    {
      case 0x0:
        $this->type = self::RESPONSE_OK;
        $this->parse_ok();
        break;
      case 0xff:
        $this->type = self::RESPONSE_ERROR;
        $this->parse_error();
        break;
      case 0xfe:
        $this->type = self::RESPONSE_EOF;
        $this->parse_eof();
        break;
      default:
        $this->type = self::RESPONSE_RESULT_SET;
        $method_name = "parse_$result_expected";
        $this->$method_name($args);
        break;
    }
  }

  function parse_result_set()
  {
    $this->parse_next_length_coded_binary('field_count');
    $this->parse_next_length_coded_binary('extra');
  }

  function parse_row_data($args)
  {
    $this->type = self::RESPONSE_ROW_DATA;
    $this->parse_position = 4;
    $num_columns = $args[2];
    $result = array();
    for ($i = 0; $i < $num_columns; $i++)
    {
      list($value, $length) = self::unmarshall_length_coded_string(substr($this->binary, $this->parse_position));
      $result[] = $value;
      $this->parse_position += $length;
    }
    $this->attributes['column_data'] = $result;
  }

  function replace_statement_with($new_statement)
  {
    $old_binary = $this->binary;
    $this->binary = self::marshall_little_endian_integer(strlen($new_statement) + 1, 3) . chr($this->number) . chr(self::COM_QUERY) . $new_statement;
  }

  function type_name()
  {
    if (array_key_exists($this->type, self::$type_to_string))
    {
      return self::$type_to_string[$this->type];
    }
    return 'unknown';
  }

  static function unmarshall_little_endian_integer($binary, $length, $offset = 0)
  {
    $bits = 0;
    $result = 0;
    for ($i = 0 + $offset; $i < $offset + $length; $i++)
    {
      $result += ord($binary{$i}) << $bits;
      $bits += 8;
    }
    return $result;
  }

  static function unmarshall_length_coded_binary($binary)
  {
    $first_byte = ord($binary{0});
    switch ($first_byte)
    {
      case 252:
        $length = 3;
        break;
      case 253:
        $length = 4;
        break;
      case 254:
        $length = 9;
        break;
      default:
        $length = 1;
    }
    if ($length == 1)
    {
      if ($first_byte == 251)
      {
        return array(null, $length);
      }
      else
      {
        return array($first_byte, $length);
      }
    }
    $result = self::unmarshall_little_endian_integer($binary, $length - 1, 1);
    return array($result, $length);
  }

  static function unmarshall_length_coded_string($binary)
  {
    list($value, $length) = self::unmarshall_length_coded_binary($binary);
    $result = substr($binary, $length, $value);
    return array($result, $length + $value);
  }

  static function unmarshall_null_terminated_string($binary)
  {
    $pos = strpos($binary, "\x00");
    $result = substr($binary, 0, $pos);
    return array($result, $pos + 1);
  }
}
