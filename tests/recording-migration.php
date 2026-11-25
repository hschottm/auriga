<?php
require __DIR__.'/../src/RecordingMigration.php';
use Hschottm\AurigaBundle\RecordingMigration;
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$a = hex2bin('123456781234123412341234567890ab');
$b = hex2bin('223456781234123412341234567890ab');
$c = hex2bin('323456781234123412341234567890ab');
$d = hex2bin('423456781234123412341234567890ab');
$files = [
 ['uuid'=>$a, 'path'=>'files/archive/ptw/001/ptw_0101_1.mp3'],
 ['uuid'=>$b, 'path'=>'files/archive/ptw/001/ptw_0101_2.mp3'],
 ['uuid'=>$c, 'path'=>'files/other/ptw/002/ambiguous.mp3'],
 ['uuid'=>$d, 'path'=>'files/copy/ptw/002/ambiguous.mp3'],
];
$p = new RecordingMigration($files);
$r = $p->plan(null, ['old/ptw_0101_1.mp3', 'https://old.example/ptw_0101_2.mp3'], '1');
check($r['status'] === 'change' && unserialize($r['value']) === [$a,$b], 'Legacy multiple files / relocated paths');
$r2 = $p->plan($r['value'], ['old/ptw_0101_1.mp3', 'old/ptw_0101_2.mp3'], '1');
check($r2['status'] === 'unchanged', 'Idempotence');
$r = $p->plan(serialize([$b]), ['old/ptw_0101_1.mp3', 'old/ptw_0101_2.mp3'], '1');
check(unserialize($r['value']) === [$b,$a], 'Existing selections and order preserved, duplicates removed');
check($p->plan(null, ['ambiguous.mp3'], 2)['status'] === 'skipped', 'Ambiguous archive roots must not be guessed');
check($p->plan(null, ['ptw_0101_1.mp3'], 2)['status'] === 'skipped', 'No cross-adventure basename matching');
check($p->plan(null, ['files/other/ptw/002/ambiguous.mp3'], 2)['status'] === 'change', 'Exact path wins');
check($p->plan(serialize([$a]), ['missing.mp3'], 1)['status'] === 'skipped', 'Entire broadcast skipped when a reference is missing');
check($p->plan('a:2:{broken', [], 1)['status'] === 'skipped', 'Malformed selections preserved');
check($p->plan(serialize([new stdClass()]), [], 1)['status'] === 'skipped', 'Objects never instantiated or accepted');
check($p->plan(serialize(['missing.mp3']), ['ptw_0101_1.mp3'], 1)['status'] === 'skipped', 'Unresolved existing selection preserved');
check($p->plan(serialize(['12345678-1234-1234-1234-1234567890ab']), [], 1)['value'] === serialize([$a]), 'Text UUID converted to binary');
check($p->plan(serialize(['files/archive/ptw/001/ptw_0101_1.mp3']), [], 1)['value'] === serialize([$a]), 'Existing path converted');
check($p->plan(null, [], 1)['status'] === 'unchanged', 'Empty broadcast remains null');
check($p->plan(serialize([$d]), [], 1)['status'] === 'unchanged', 'Existing valid UUID preserved regardless of adventure');
echo "OK: recording migration planner, ambiguity, preservation, binary UUIDs, idempotence.\n";

$short = new RecordingMigration([['uuid'=>$a, 'path'=>'files/ptw/001/abcdefghijkl.mp3']]);
check($short->plan(null, ['abcdefghijkl.mp3'], 1)['value'] === serialize([$a]), '16-byte filename mistaken for binary UUID');
require __DIR__.'/../src/ArchiveRecordingLocator.php';
$dir = sys_get_temp_dir().'/auriga-locator-'.bin2hex(random_bytes(5));
mkdir($dir.'/files/auriga/archiv/ptw/014', 0777, true);
$physical = $dir.'/files/auriga/archiv/ptw/014/ptw_1403_2.mp3';
file_put_contents($physical, 'fixture');
$locator = new Hschottm\AurigaBundle\ArchiveRecordingLocator($dir, 'files/auriga/archiv');
$p = new RecordingMigration([], $locator(...));
$legacy = ['files/auriga/recordings/ptw/014/ptw_1403_2.mp3'];
$r = $p->plan(null, $legacy, 14);
check($r['status']==='change' && $r['registrations']===['files/auriga/archiv/ptw/014/ptw_1403_2.mp3'], 'Unregistered NAS recording not planned');
check($p->plan(null, $legacy, 15)['status']==='skipped', 'Missing physical file must be skipped');
check($p->plan(null, array_merge($legacy,['missing.mp3']), 14)['status']==='skipped', 'Partial broadcast must not register files');
$pending = $p->plan(serialize($r['registrations']), [], 14);
check($pending['status']==='change', 'Existing path still needs registration');
symlink('/etc/hosts', $dir.'/files/auriga/archiv/ptw/014/outside.mp3');
check($locator('outside.mp3',14)===null, 'Symlink escaping archive accepted');
unlink($dir.'/files/auriga/archiv/ptw/014/outside.mp3');
unlink($physical);
echo "OK: targeted archive lookup, pending registration, absent files, boundary checks.\n";
