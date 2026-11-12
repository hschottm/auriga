<?php
require __DIR__.'/../src/MediafileBrowser.php';
use Hschottm\AurigaBundle\MediafileBrowser;
function ensureBrowser($ok) { if (!$ok) throw new RuntimeException('Browser assertion failed'); }
$base = sys_get_temp_dir().'/auriga-browser-'.bin2hex(random_bytes(6));
mkdir($base.'/files/archive/sub', 0755, true);
file_put_contents($base.'/files/archive/sub/recording.mp3', 'audio');
file_put_contents($base.'/files/archive/z.txt', 'hello');
file_put_contents($base.'/files/archive/.hidden', 'secret');
file_put_contents($base.'/outside', 'secret');
symlink($base.'/outside', $base.'/files/archive/escape');
try {
    $browser = new MediafileBrowser($base.'/files', $base.'/files/archive', 'mp3,txt');
    $mp3 = new MediafileBrowser($base.'/files', $base.'/files/archive');
    ensureBrowser(array_column($mp3->entries(''), 'name') === ['sub']);
    ensureBrowser(!$mp3->allows('z.txt') && $mp3->allows('TRACK.MP3'));
    $rows = $browser->entries('');
    ensureBrowser(array_column($rows, 'name') === ['sub', 'z.txt']);
    ensureBrowser($rows[1]['size'] === 5 && $rows[1]['modified'] > 0);
    ensureBrowser($browser->entries('sub')[0]['path'] === 'sub/recording.mp3');
    ensureBrowser(file_get_contents($browser->resolve('sub/recording.mp3')) === 'audio');
    foreach (['../outside', '/etc/passwd', 'sub/../../outside', '.hidden', 'escape', "bad\0file", 'sub\\..\\outside'] as $path) {
        $failed = false;
        try { $browser->resolve($path); } catch (InvalidArgumentException $e) { $failed = true; }
        ensureBrowser($failed);
    }
    $failed = false;
    try { new MediafileBrowser($base.'/files', $base); } catch (InvalidArgumentException $e) { $failed = true; }
    ensureBrowser($failed);
    echo "OK: listing, metadata, subdirectories, root confinement, traversal, hidden files and escaping symlinks\n";
} finally {
    unlink($base.'/files/archive/escape'); unlink($base.'/outside');
    unlink($base.'/files/archive/.hidden'); unlink($base.'/files/archive/z.txt');
    unlink($base.'/files/archive/sub/recording.mp3'); rmdir($base.'/files/archive/sub');
    rmdir($base.'/files/archive'); rmdir($base.'/files'); rmdir($base);
}
