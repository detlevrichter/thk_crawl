<pre><?php 
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../autoload.php';

$a = new Prompt();
echo htmlentities( $a->get(1));