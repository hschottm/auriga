<?php
require __DIR__.'/../src/AudioChapters.php';
use Hschottm\AurigaBundle\AudioChapters;
function verify(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$chapters = AudioChapters::parse("\xEF\xBB\xBF[00:00.00] Begrüßung\n\n[53:24.00] Inseln\n[03:15.00] Was bisher geschah\n[08:14.00] <script> & Titel\n[ar:Artist]\n[99:99] invalid\n[04:00]\n");
verify(count($chapters) === 4, 'Ignore metadata and invalid/empty lines');
verify(array_column($chapters, 'start') === [0, 195.0, 494.0, 3204.0], 'Sorted timestamps');
verify(AudioChapters::parse('[65:20.125] Long recording')[0]['start'] === 3920.125, 'Long minutes and milliseconds');
verify(count(AudioChapters::parse('[00:01][00:02] Repeated')) === 2, 'Multiple timestamps');
verify(AudioChapters::parse("[offset:500]\n[00:02] Offset")[0]['start'] === 1.5, 'LRC offset');
$folder = sys_get_temp_dir().'/auriga-lrc-'.bin2hex(random_bytes(6));
mkdir($folder);
try {
 verify(AudioChapters::read($folder.'/test.mp3') === [], 'Missing sidecar');
 file_put_contents($folder.'/test.lrc', '[00:00] Start');
 verify(count(AudioChapters::read($folder.'/test.mp3')) === 1, 'Matching sidecar');
 unlink($folder.'/test.lrc');
 symlink(__FILE__, $folder.'/test.lrc');
 verify(AudioChapters::read($folder.'/test.mp3') === [], 'Reject symlink');
 unlink($folder.'/test.lrc');
 file_put_contents($folder.'/test.lrc', str_repeat('x', 262145));
 verify(AudioChapters::read($folder.'/test.mp3') === [], 'Bounded read');
} finally { @unlink($folder.'/test.lrc'); rmdir($folder); }
$autoload = getenv('AURIGA_VENDOR_AUTOLOAD');
if ($autoload) {
 require $autoload;
 $loader = new Twig\Loader\FilesystemLoader();
 $loader->addPath(__DIR__.'/../src/Resources/contao/templates/content', 'Contao');
 $twig = new Twig\Environment($loader, ['autoescape' => 'html', 'strict_variables' => true]);
 $data = ['title'=>'Test', 'url'=>'/protected?file=1&play=1', 'type'=>'audio/mpeg', 'chapters'=>$chapters, 'chapterLabels'=>['previous'=>'Previous', 'next'=>'Next', 'list'=>'Chapters']];
 $html = $twig->render('@Contao/auriga_audio_player.html.twig', $data);
 verify(str_contains($html, 'data-chapter-start="195"') && !str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Render and escape chapter titles');
 $data['chapters'] = [];
 $html = $twig->render('@Contao/auriga_audio_player.html.twig', $data);
 verify(str_contains($html, '<audio') && !str_contains($html, 'data-auriga-audio-player'), 'Standard player fallback');
}
echo "OK: LRC parsing, time ordering, offsets, sidecar limits, symlink rejection and player template\n";
