<?php
require getenv('AURIGA_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
foreach (['MarkdownFile','MarkdownLinkResolver','StoryMarkdown','StoryTranscript'] as $class) require __DIR__.'/../src/'.$class.'.php';
use Hschottm\AurigaBundle\{MarkdownFile,MarkdownLinkResolver,StoryMarkdown,StoryTranscript};
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
class LinkTestPage extends Contao\PageModel {
    public function __construct(private string $base) {}
    public function getFrontendUrl($strParams=null) { return $this->base.$strParams; }
}
$db=Doctrine\DBAL\DriverManager::getConnection(['driver'=>'pdo_sqlite','memory'=>true]);
$db->executeStatement('CREATE TABLE tl_files (uuid BLOB, path TEXT)');
$db->executeStatement('CREATE TABLE tl_auriga_lexicon (alias TEXT, markdownFile BLOB, published TEXT)');
$db->executeStatement('CREATE TABLE tl_broadcast (id INTEGER, date INTEGER, files BLOB)');
$db->executeStatement('CREATE TABLE tl_broadcast_file (pid INTEGER, filename TEXT)');
$person=hex2bin('00112233445516778899aabbccddeeff');
$recording=hex2bin('112233445566178899aabbccddeeff00');
$db->insert('tl_files',['uuid'=>$person,'path'=>'files/ptw/Lexikon/Rita Stefanidis.md']);
$db->insert('tl_files',['uuid'=>$recording,'path'=>'files/ptw/030/ptw_3001_2.mp3']);
$db->insert('tl_auriga_lexicon',['alias'=>'rita','markdownFile'=>$person,'published'=>'1']);
$db->insert('tl_broadcast',['id'=>42,'date'=>730000000,'files'=>serialize([$recording])]);
$db->insert('tl_broadcast_file',['pid'=>42,'filename'=>'files/ptw/030/ptw_3001_2.mp3']);
$make=fn()=>new MarkdownLinkResolver($db,'files',new LinkTestPage('/lexikon'),new LinkTestPage('/sendung'));
$resolver=$make();
$source='files/ptw/030/ptw_3001_2.md';
check($resolver->resolve('../Lexikon/Rita%20Stefanidis.md#herkunft',$source)==='/lexikon/lexicon/rita#herkunft','Lexicon by UUID and alias');
check($resolver->resolve('../030/ptw_3001_2.md','files/ptw/Lexikon/Rita Stefanidis.md')==='/sendung/broadcast/730000000','Broadcast date route, duplicate references');
check($resolver->resolve('missing.md',$source)===null,'Unresolved file');
check($resolver->resolve('../../../../secret.md',$source)===null,'Path traversal');
foreach (['https://example.org/a.md','mailto:test@example.org','/lexikon','#herkunft'] as $url) check($resolver->resolve($url,$source)===$url,'External/site/anchor link');
$html=StoryMarkdown::render("# Herkunft\n\n[**Rita**](../Lexikon/Rita%20Stefanidis.md#herkunft) [unbekannt](missing.md)\n\n`[Code](missing.md)`\n\n<script>alert(1)</script>\n\n[X](javascript:alert%281%29)",fn($url)=>$resolver->resolve($url,$source));
check(str_contains($html,'id="herkunft"'),'Heading anchor');
check(str_contains($html,'href="/lexikon/lexicon/rita#herkunft"><strong>Rita</strong></a>'),'AST link rendering');
check(!str_contains($html,'href="missing.md"') && str_contains($html,'unbekannt'),'Unresolved link must be text');
check(str_contains($html,'<code>[Code](missing.md)</code>'),'Code unchanged');
check(!str_contains($html,'<script>') && !str_contains($html,'href="javascript:'),'Unsafe HTML/link');
$db->update('tl_broadcast',['files'=>serialize([Contao\StringUtil::binToUuid($recording)])],['id'=>42]);
$db->executeStatement('DELETE FROM tl_broadcast_file');
check($make()->resolve('ptw_3001_2.md',$source)==='/sendung/broadcast/730000000','Text UUID');
$db->update('tl_broadcast',['files'=>null],['id'=>42]);
$db->insert('tl_broadcast_file',['pid'=>42,'filename'=>'files/ptw/030/ptw_3001_2.mp3']);
check($make()->resolve('ptw_3001_2.md',$source)==='/sendung/broadcast/730000000','Legacy-only assignment');
$db->insert('tl_broadcast',['id'=>43,'date'=>730000001,'files'=>serialize([$recording])]);
check($make()->resolve('ptw_3001_2.md',$source)===null,'Ambiguous broadcast');
$withoutPage=new MarkdownLinkResolver($db,'files',null,null);
check($withoutPage->resolve('../Lexikon/Rita%20Stefanidis.md',$source)===null,'Missing target page');
$db->insert('tl_auriga_lexicon',['alias'=>'duplicate','markdownFile'=>$person,'published'=>'1']);
check($make()->resolve('../Lexikon/Rita%20Stefanidis.md',$source)===null,'Ambiguous lexicon');
$db->delete('tl_auriga_lexicon',['alias'=>'duplicate']);
$db->update('tl_auriga_lexicon',['published'=>''],['alias'=>'rita']);
check($make()->resolve('../Lexikon/Rita%20Stefanidis.md',$source)===null,'Unpublished lexicon');
$root=sys_get_temp_dir().'/auriga-markdown-'.bin2hex(random_bytes(5));
mkdir($root.'/files/030',0777,true);
file_put_contents($root.'/files/030/one.md',"\xEF\xBB\xBF# Titel\n\n## 01:00–02:00 · Abenteuer\n[Rita](../Lexikon/Rita.md)\n");
check(str_starts_with(MarkdownFile::read($root,'files','files/030/one.md'),'# Titel'),'Read BOM');
check(MarkdownFile::read($root,'files','files/../secret.md')===null,'Read boundary');
file_put_contents($root.'/outside.md','outside');
symlink($root.'/outside.md',$root.'/files/030/escape.md');
check(MarkdownFile::read($root,'files','files/030/escape.md')===null,'Symlink boundary');
$seen=[];
StoryTranscript::load($root,'files',['files/030/one.mp3'],static function($text,$path)use(&$seen){$seen[]=[$text,$path];});
check($seen[0][1]==='files/030/one.md' && str_contains($seen[0][0],'[Rita]'),'Transcript source context');
unlink($root.'/files/030/escape.md');unlink($root.'/files/030/one.md');unlink($root.'/outside.md');
rmdir($root.'/files/030');rmdir($root.'/files');rmdir($root);
echo "OK: Markdown crosslinks, actual file assignments, date routes, unsafe and unresolved links, headings, source paths and file boundaries.\n";
