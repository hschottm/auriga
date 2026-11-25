<?php
require __DIR__.'/../src/StoryTranscript.php';
use Hschottm\AurigaBundle\StoryTranscript;
function check($value, $message) { if (!$value) throw new RuntimeException($message); }
$md="# Titel\n\n## 1. Kapitel\n\nQuelle: foo\n> Transkriptionshinweis\n\n## 00:00–00:30 · Moderation\nAnsage\n\n## 04:20–07:30 · Rückblick\nRückblicktext\n\n## 00:11:20–00:14:50 · Abenteuer\n**Geschichte**\n\nZweiter Absatz\n\n## 55:00–55:30 · Abmoderation\nAbspann\n\n## Hinweise\nNotiz\n";
$text=StoryTranscript::extract($md);
check(str_contains($text,'### 00:11:20–00:14:50') && str_contains($text,'**Geschichte**') && str_contains($text,'Rückblicktext'),'Narrative missing');
foreach(['Titel','Kapitel','Quelle','Transkriptionshinweis','Ansage','Abspann','Notiz'] as $excluded) check(!str_contains($text,$excluded),'Unwanted text: '.$excluded);
check(StoryTranscript::extract("# Titel\n## 17:34-19:12\nDocBook-Text\n") === "### 17:34-19:12\nDocBook-Text",'DocBook timestamp ranges');
check(StoryTranscript::extract("## 17:34\nText\n") === "### 17:34\nText",'Start-only timestamps');
$root=sys_get_temp_dir().'/auriga-story-'.bin2hex(random_bytes(5));
mkdir($root.'/files/archive',0777,true);
file_put_contents($root.'/files/archive/one.md',$md);
file_put_contents($root.'/files/archive/two.md',"## 20:00\nWeiter\n");
check(StoryTranscript::load($root,'files',['files/archive/missing.mp3'])===null,'Missing fallback');
$text=StoryTranscript::load($root,'files',['files/archive/one.mp3','files/archive/one.mp3','files/archive/two.mp3']);
check(substr_count($text,'**Geschichte**')===1 && str_ends_with($text,'Weiter'),'Order or duplicates');
file_put_contents($root.'/files/archive/one.md',"## 10:00\nKorrektur\n");
check(str_contains(StoryTranscript::load($root,'files',['files/archive/one.mp3']),'Korrektur'),'Edits not reflected');
check(StoryTranscript::load($root,'files',['https://example.org/one.mp3','files/../one.mp3'])===null,'Unsafe path accepted');
file_put_contents($root.'/files/archive/empty.md',"# Nur Metadaten\n");
check(StoryTranscript::load($root,'files',['files/archive/empty.mp3'])==='','Metadata-only file must not expose fallback');
foreach(glob($root.'/files/archive/*.md') as $file) unlink($file);
rmdir($root.'/files/archive');rmdir($root.'/files');rmdir($root);
echo "OK: narrative filtering, both timestamp formats, sidecar order, fallback, edits and path boundaries.\n";
