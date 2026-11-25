<?php
/** Uses a disposable, fully installed Contao database and cleans its own fixtures. */
$project = getenv('AURIGA_TEST_PROJECT');
if (!$project || !str_contains((string) getenv('DATABASE_URL'), '/auriga57_test_')) throw new RuntimeException('Disposable Contao test project required');
require $project.'/vendor/autoload.php';
foreach (['RecordingMigration','ArchiveRecordingLocator','RecordingRegistrar','ContaoRecordingRegistrar','Command/MigrateRecordingsCommand'] as $class) require __DIR__.'/../src/'.$class.'.php';
$kernel = Contao\ManagerBundle\HttpKernel\ContaoKernel::fromInput($project, new Symfony\Component\Console\Input\ArrayInput(['--env'=>'dev']));
$kernel->boot();
$c = $kernel->getContainer();
$db = $c->get('database_connection');
$framework = $c->get('contao.framework');
$framework->initialize();
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$unique = 'auriga-register-test-'.bin2hex(random_bytes(5));
$archive = 'files/'.$unique;
$path = $archive.'/ptw/014/ptw_1403_2.mp3';
mkdir($project.'/'.dirname($path),0777,true);
file_put_contents($project.'/'.$path, 'ID3 test fixture');
file_put_contents($project.'/'.dirname($path).'/unrelated.mp3', 'unrelated');
$db->insert('tl_adventure',['title'=>$unique,'numbering'=>'14']);
$adventure = $db->lastInsertId();
$db->insert('tl_broadcast',['pid'=>$adventure,'title'=>$unique,'files'=>serialize(['files/old/ptw/014/ptw_1403_2.mp3'])]);
$id = $db->lastInsertId();
$registrar = new Hschottm\AurigaBundle\ContaoRecordingRegistrar($framework);
$run = function ($apply,$report) use($db,$project,$registrar,$archive) {
 $tester = new Symfony\Component\Console\Tester\CommandTester(new Hschottm\AurigaBundle\Command\MigrateRecordingsCommand($db,$project,$registrar));
 $args=['--archive-root'=>$archive,'--report'=>$report];
 if($apply) $args['--apply']=true;
 check($tester->execute($args)===0,'command failed');
};
$reportDir = sys_get_temp_dir().'/'.$unique;
mkdir($reportDir);
try {
 $run(false,$reportDir.'/dry.json');
 check(!$db->fetchOne('SELECT uuid FROM tl_files WHERE path=?',[$path]), 'Dry-run registered file');
 $run(true,$reportDir.'/apply.json');
 $uuid=$db->fetchOne('SELECT uuid FROM tl_files WHERE path=?',[$path]);
 check(is_string($uuid) && strlen($uuid)===16,'Contao UUID missing');
 check(unserialize($db->fetchOne('SELECT files FROM tl_broadcast WHERE id=?',[$id]))===[$uuid], 'Backend selection does not match UUID');
 check(!$db->fetchOne('SELECT uuid FROM tl_files WHERE path=?',[$archive.'/ptw/014/unrelated.mp3']), 'Unrelated file scanned');
 $run(true,$reportDir.'/again.json');
 check((int)$db->fetchOne('SELECT COUNT(*) FROM tl_files WHERE path=?',[$path])===1,'Duplicate registration');
 // Prove Dbafs and command share the transaction connection.
 $second=$archive.'/ptw/014/second.mp3';
 file_put_contents($project.'/'.$second,'second');
 $db->beginTransaction();
 $registrar->register($second);
 $db->rollBack();
 check(!$db->fetchOne('SELECT uuid FROM tl_files WHERE path=?',[$second]), 'Contao registration escaped rollback');
 unlink($project.'/'.$second);
 echo "OK: real Contao Dbafs registration, ancestor folders, dry-run, UUID selection, idempotence, no directory scan, transaction rollback.\n";
} finally {
 $db->delete('tl_broadcast',['id'=>$id]);
 $db->delete('tl_adventure',['id'=>$adventure]);
 $db->executeStatement('DELETE FROM tl_files WHERE path=? OR path LIKE ?',[$archive,$archive.'/%']);
 unlink($project.'/'.$path);
 unlink($project.'/'.dirname($path).'/unrelated.mp3');
 rmdir($project.'/'.dirname($path)); rmdir($project.'/'.$archive.'/ptw'); rmdir($project.'/'.$archive);
}
