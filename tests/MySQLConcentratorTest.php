<?php
require_once(dirname(__FILE__) . "/simpletest/autorun.php");
require_once(dirname(__FILE__) . "/MySQLConcentratorBaseTest.php");
require_once(dirname(__FILE__) . "/../MySQLConcentrator.php");
require_once(dirname(__FILE__) . "/../MySQLConcentratorPDO.php");


class MySQLConcentratorTest extends MySQLConcentratorBaseTest
{
  public $databases_config = null;
  public $db = null;
  public $dsn = null;
  public $concentrator_dsn = null;
  public static $dsn_components  = array
  (
    'mysql' => array
    (
      'dbname' => 'database',
      'host' => 'host',
      'port' => 'port',
    ),
  );

  function build_dsn($database_config)
  {
    $driver = $database_config['driver'];
    $components = array();
    foreach (self::$dsn_components[$driver] as $dsn_name => $config_name)
    {
      if (array_key_exists($config_name, $database_config))
      {
        $value = $database_config[$config_name];
        $components[] = "$dsn_name=$value";
      }
    }
    return "$driver:" . implode(';', $components);
  }

  function connect_to_concentrator()
  {
    $database_config = $this->databases_config['test'];
    $connection = new MySQLConcentratorPDO($this->concentrator_dsn, $database_config['user_name'], $database_config['password']);
    return $connection;
  }

  function setUp()
  {
    parent::setUp();
    if ($this->databases_config == null)
    {
      require_once(dirname(__FILE__) . "/../conf/database.php");
      $this->databases_config = $databases;
    }
    $database_config = $this->databases_config['test'];
    $this->dsn = $this->build_dsn($database_config);
    $this->db = new MySQLConcentratorPDO($this->dsn, $database_config['user_name'], $database_config['password']);
    $this->db->query("DROP TABLE IF EXISTS foo");
    $this->db->query("CREATE TABLE foo (id INTEGER AUTO_INCREMENT PRIMARY KEY, value VARCHAR(255))");
    $this->db->query("INSERT INTO foo (value) VALUES ('first')");
    $database_config = $this->databases_config['test'];
    $database_config['port'] = 3307;
    $database_config['host'] = '127.0.0.1';
    $this->concentrator_dsn = $this->build_dsn($database_config);
  }

  function testSimpleQueryOnSeparateConnections()
  {
    $db_conn_1 = $this->connect_to_concentrator();
    $result_1 = $db_conn_1->query("SELECT * FROM foo WHERE value = 'first'");
    $this->assertEqual(1, $result_1->rowCount());
    $db_conn_2 = $this->connect_to_concentrator();
    $result_2 = $db_conn_2->query("SELECT * FROM foo WHERE value = 'first'");
    $this->assertEqual(1, $result_2->rowCount());
  }

  function testTransactionOnOneConnectionWrapsOtherConnection()
  {
    $db_conn_1 = $this->connect_to_concentrator();
    $db_conn_2 = $this->connect_to_concentrator();
    $db_conn_1->query("BEGIN");
    $db_conn_2->query("INSERT INTO foo (value) VALUES ('second')");
    $result = $db_conn_2->query("SELECT * FROM foo WHERE value = 'second'");
    $this->assertEqual(1, $result->rowCount());
    $db_conn_1->query("ROLLBACK");
    $result = $db_conn_2->query("SELECT * FROM foo WHERE value = 'second'");
    $this->assertEqual(0, $result->rowCount());
  }

  function testInterlevedQueriesOnOneConnection()
  {
    $db_conn_1 = $this->connect_to_concentrator();
    $db_conn_1->query("INSERT INTO foo (value) VALUES ('second')");
    $db_conn_1->query("INSERT INTO foo (value) VALUES ('third')");
    $result_1 = $db_conn_1->query("SELECT * FROM foo ORDER BY id");
    $this->assertEqual(3, $result_1->rowCount());
    $row = $result_1->fetch();
    $this->assertEqual('first', $row['value']);
    $row = $result_1->fetch();
    $this->assertEqual('second', $row['value']);
    $db_conn_2 = $this->connect_to_concentrator();
    $result_2 = $db_conn_2->query("SELECT * FROM foo ORDER BY id");
    $row = $result_2->fetch();
    $this->assertEqual('first', $row['value']);
    $row = $result_1->fetch();
    $this->assertEqual('third', $row['value']);
    $row = $result_2->fetch();
    $this->assertEqual('second', $row['value']);
  }

}
