MySQL Concentrator
==================

This is a MySQL proxy server that takes several MySQL connections and
"concentrates" them into a single connection to a single server. This
may not seem very useful until you think about tests. One of the
annoying things when doing tests on web applications is that you
frequently have to do bunch of TRUNCATE or DROP TABLE statements between
each test to get your database back into a known state. Doing that
really slows your tests down.

Ruby on Rails gets around this to some extent by wrapping each test in a
transaction (BEGIN ... ROLLBACK). It's very fast and works really well,
but it only works because Rails tests all run in one process with one
database connection. This technique breaks down in Rails when you
introduce Cucumber tests because it launches separate web server and
browser processes, so you can't wrap all the database calls in a
transaction.

MySQL Concentrator helps with this problem. You can start a connection
to MySQL through MySQL Concentrator in your test suite and configure
your web application to also run MySQL commands through MySQL
Concentrator. Then have your test suite fire a BEGIN before each test
and a ROLLBACK after each test. Even if you fire off a bunch of separate
processes, MySQL Concentrator will funnel all those connections into
that same connection where you sent the BEGIN command and so all the
database activity will happen in a transaction.

Installation
------------

Just grab it from the github repository. It just runs out of its directory.

Usage
-----

    php mysql-concentrator.php -h <mysql server host name> -p <mysql server port>


Configure your web application to connect to mysql on 127.0.0.1 at port 3307
instead of its normal host and port. It's normal host and port are what
you should use for the command above.

Now if you are going to use it for a testing framework, just add some
lines to start the transaction. Here's an example of what I did for some
Behat tests in the Behat Context class:

    /**
     * @BeforeScenario
     */
    public function setupDB($event)
    {
      $this->db = new PDO("mysql:host=127.0.0.1;port=3307;dbname=foo_test;", "foo", "foo");
      $this->db->exec("DROP TABLE IF EXISTS automated_testing");
      $this->db->exec("CREATE TABLE automated_testing (pristine INTEGER)");
      $this->db->exec("INSERT INTO automated_testing VALUES (1)");
      $this->db->exec("BEGIN");
      $this->db->exec("UPDATE automated_testing SET pristine = 0");
    }

    /**
     * @AfterScenario
     */
    public function resetDB($event)
    {
      $this->db->exec("ROLLBACK");
    }

The extra stuff with "pristine" is a sort of hack so I can check and see
if the web app did something to mess up the wrapping transaction. If the
transaction didn't get rolled back properly "pristine" won't get set
back to 1. Some statements in MySQL will cause an implicit commit. This
includes pretty much all of the DDL statements like CREATE TABLE, ALTER
TABLE, CREATE INDEX etc...



