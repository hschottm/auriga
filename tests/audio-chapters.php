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
 $data = ['title'=>'Test', 'url'=>'/protected?file=1&play=1', 'type'=>'audio/mpeg', 'chapters'=>$chapters, 'chapterLabels'=>['previous'=>'Previous', 'next'=>'Next', 'list'=>'Chapters', 'play'=>'Play', 'pause'=>'Pause', 'mute'=>'Mute', 'position'=>'Position', 'volume'=>'Volume', 'loading'=>'Loading', 'error'=>'Error']];
 $html = $twig->render('@Contao/auriga_audio_player.html.twig', $data);
 verify(str_contains($html, 'data-chapter-start="195"') && !str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Render and escape chapter titles');
 $data['chapters'] = [];
 $html = $twig->render('@Contao/auriga_audio_player.html.twig', $data);
 verify(str_contains($html, '<audio') && str_contains($html, 'data-audio-play') && !str_contains($html, 'data-chapter-start'), 'Standard player fallback');
}
echo "OK: LRC parsing, time ordering, offsets, sidecar limits, symlink rejection and player template\n";

verify(count(AudioChapters::parseStory('[{"start":"03:15","end":"08:14","title":"Story"}]')) === 1, 'Story pair');
verify(AudioChapters::parseStory('[{"start":"01:02:03.250","end":"01:03:00","title":"Story"}]')[0]['start'] === 3723.25, 'Story hours');
foreach (['broken', '{}', '[{"start":"00:10","end":"00:05","title":"Bad"}]', '[{"start":"00:00","end":"00:10","title":"A"},{"start":"00:09","end":"00:20","title":"B"}]', '[{"start":"00:99","end":"01:10","title":"Bad"}]'] as $invalid) verify(AudioChapters::parseStory($invalid) === [], 'Reject invalid story');
if ($autoload) {
 $data['story'] = AudioChapters::parseStory('[{"start":"03:15","end":"08:14","title":"<Story>"}]');
 $data['chapterLabels'] += ['mode'=>'Mode', 'full'=>'Full broadcast', 'story'=>'Story only'];
 $html = $twig->render('@Contao/auriga_audio_player.html.twig', $data);
 verify(str_contains($html, 'data-story-start="195"') && str_contains($html, 'data-story-end="494"') && str_contains($html, '&lt;Story&gt;') && str_contains($html, 'data-audio-mode="story"'), 'Story-only template and escaping');
}
echo "OK: story JSON validation and mode switch template\n";
