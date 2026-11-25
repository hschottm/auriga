<?php
/** Dedicated disposable database only: creates and drops the four fixture tables. */
require getenv('AURIGA_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/../src/RecordingMigration.php';
require __DIR__.'/../src/Command/MigrateRecordingsCommand.php';
use Doctrine\DBAL\DriverManager;
use Hschottm\AurigaBundle\Command\MigrateRecordingsCommand;
use Symfony\Component\Console\Tester\CommandTester;
$name = getenv('AURIGA_RECORDING_TEST_DB');
if (!$name || !str_starts_with($name, 'auriga57_test_recordings_')) throw new RuntimeException('Dedicated AURIGA_RECORDING_TEST_DB required.');
$db = DriverManager::getConnection(['driver'=>'pdo_mysql', 'host'=>getenv('AURIGA_TEST_DB_HOST') ?: 'mariadb', 'user'=>'contao', 'password'=>'contao', 'dbname'=>$name]);
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$dir = sys_get_temp_dir().'/auriga-recordings-'.bin2hex(random_bytes(6));
mkdir($dir);
foreach (['tl_broadcast_file','tl_broadcast','tl_adventure','tl_files'] as $table) $db->executeStatement('DROP TABLE IF EXISTS '.$table);
$db->executeStatement('CREATE TABLE tl_files (uuid BINARY(16), path VARCHAR(1024), type VARCHAR(16)) ENGINE=InnoDB');
$db->executeStatement('CREATE TABLE tl_adventure (id INT PRIMARY KEY, numbering VARCHAR(8)) ENGINE=InnoDB');
$db->executeStatement('CREATE TABLE tl_broadcast (id INT PRIMARY KEY, pid INT, files BLOB NULL) ENGINE=InnoDB');
$db->executeStatement('CREATE TABLE tl_broadcast_file (id INT PRIMARY KEY, pid INT, sorting INT, filename VARCHAR(1024)) ENGINE=InnoDB');
$a = random_bytes(16); $b = random_bytes(16);
$db->insert('tl_adventure', ['id'=>1, 'numbering'=>'1']);
$db->insert('tl_files', ['uuid'=>$a,'path'=>'files/new/ptw/001/one.mp3','type'=>'file']);
$db->insert('tl_files', ['uuid'=>$b,'path'=>'files/new/ptw/001/two.mp3','type'=>'file']);
$db->insert('tl_broadcast', ['id'=>1,'pid'=>1,'files'=>serialize([$b])]);
$db->insert('tl_broadcast', ['id'=>2,'pid'=>1,'files'=>null]);
$db->insert('tl_broadcast_file', ['id'=>1,'pid'=>1,'sorting'=>1,'filename'=>'old/one.mp3']);
$db->insert('tl_broadcast_file', ['id'=>2,'pid'=>1,'sorting'=>2,'filename'=>'old/two.mp3']);
$db->insert('tl_broadcast_file', ['id'=>3,'pid'=>2,'sorting'=>1,'filename'=>'missing.mp3']);
$run = function (array $args) use ($db,$dir) {
 $tester = new CommandTester(new MigrateRecordingsCommand($db,$dir));
 check($tester->execute($args)===0, 'Command exit');
 return $tester->getDisplay();
};
$run(['--report'=>$dir.'/dry.json']);
check($db->fetchOne('SELECT files FROM tl_broadcast WHERE id=1') === serialize([$b]), 'Dry-run modified database');
$report=json_decode(file_get_contents($dir.'/dry.json'),true);
check($report['counts']===['change'=>1,'unchanged'=>0,'skipped'=>1], 'Dry-run report counts');
$run(['--apply'=>true,'--report'=>$dir.'/apply.json']);
check($db->fetchOne('SELECT files FROM tl_broadcast WHERE id=1') === serialize([$b,$a]), 'Merged UUIDs not stored');
check($db->fetchOne('SELECT files FROM tl_broadcast WHERE id=2') === null, 'Unresolved broadcast modified');
check((int)$db->fetchOne('SELECT COUNT(*) FROM tl_broadcast_file')===3, 'Legacy data lost');
$report=json_decode(file_get_contents($dir.'/apply.json'),true);
$backup=json_decode(file_get_contents($report['backup']),true);
check(base64_decode($backup['rows'][0]['before'])===serialize([$b]), 'Backup not byte-exact');
check((fileperms($report['backup']) & 0777)===0600, 'Backup permissions');
$run(['--apply'=>true,'--report'=>$dir.'/again.json']);
$report=json_decode(file_get_contents($dir.'/again.json'),true);
check($report['counts']['change']===0 && $report['backup']===null, 'Rerun not idempotent');
// A failed write must roll back previous broadcasts in the same migration.
$db->update('tl_broadcast',['files'=>null],['id'=>1]);
$db->update('tl_broadcast_file',['filename'=>'old/two.mp3'],['id'=>3]);
$db->executeStatement("CREATE TRIGGER fail_second BEFORE UPDATE ON tl_broadcast FOR EACH ROW BEGIN IF NEW.id = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'; END IF; END");
try { $run(['--apply'=>true,'--report'=>$dir.'/failed.json']); throw new RuntimeException('Expected database failure'); }
catch (Doctrine\DBAL\Exception $e) {}
check($db->fetchOne('SELECT files FROM tl_broadcast WHERE id=1')===null, 'Transaction did not roll back first update');
check(isset(json_decode(file_get_contents($dir.'/failed.json'),true)['error']), 'Failure absent from report');
foreach (['tl_broadcast_file','tl_broadcast','tl_adventure','tl_files'] as $table) $db->executeStatement('DROP TABLE '.$table);
echo "OK: MariaDB command dry-run, apply, binary preservation, backup, skipped records, idempotence, transactional rollback.\n";
// The shipped service YAML and Symfony command compiler must expose the command.
$container = new Symfony\Component\DependencyInjection\ContainerBuilder();
$container->setParameter('kernel.project_dir', $dir);
$container->register('database_connection', Doctrine\DBAL\Connection::class)->setSynthetic(true)->setPublic(true);
$loader = new Symfony\Component\DependencyInjection\Loader\YamlFileLoader($container, new Symfony\Component\Config\FileLocator(__DIR__.'/../src/Resources/config'));
$loader->load('services.yml');
$container->addCompilerPass(new Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass());
$container->compile();
$container->set('database_connection', $db);
$commands=$container->get('console.command_loader');
check($commands->has('auriga:migrate-recordings'), 'Command not registered');
check($commands->get('auriga:migrate-recordings')->getName()==='auriga:migrate-recordings', 'Command not instantiable');
echo "OK: shipped service configuration and Symfony command registration.\n";
