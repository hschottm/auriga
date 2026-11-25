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
