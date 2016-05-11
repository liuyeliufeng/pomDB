# pomDB

PomDB is a very lightweight ORM arch for PHP. while it only support *MySQL* database for now, but in future it can be compatibly support kinds of SQL databases .

## Why choose PomDB?

- *Light*.
- *Easy-Understand*
- *Easy-Extend*
- *Secure*

## Get Started

```
require_once('PomDB.php');
$database = array(
    'database_type' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'username' => 'root',
    'password' => '',
    'database_name' => 'test',
    'charset' => 'utf-8',
);
$db = new PomDB($database);

```
you can easily implement select/update/insert/delete functions by this:

```
$result = $db->select('user',
        array(
            'user.id' => 'user_id',
            'name',
            'score',
            'class_name'),
        array(
            'left|score(s)' => array(
                'user.id' => 's.user_id',
            ),
            'left|class' => array(
                's.class_id' => 'class.id',
            ),
        ),
        array(
            'where' => array(
                'and' => array(
                    'user.id|!' => 1,
                    'class_name|!~' => 'Math"%',
                    'or' => array(
                        'user.id' => array(1, 2),
                        'class_name|~' => 'Ma_',
                    ),
                ),

            ),
            'order' => array(
                'score desc',
            ),

        )
    );

```