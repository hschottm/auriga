<?php
require __DIR__.'/contao57-integration.php';
Contao\Controller::loadDataContainer('tl_auriga_lexicon');
Contao\System::loadLanguageFile('tl_auriga_lexicon', 'de');
$db->executeStatement('DELETE FROM tl_auriga_lexicon');
class LexiconView extends Hschottm\AurigaBundle\ModuleLexicon {
 public function render() { $this->Template=new Contao\FrontendTemplate($this->strTemplate); $this->compile(); return $this->Template->parse(); }
}
$dc = new class extends Contao\DataContainer { public function getPalette() {} protected function save($varValue) {} };
$callback = new Hschottm\AurigaBundle\LexiconAlias();
$db->insert('tl_auriga_lexicon', ['term'=>'Major Renault']);
$id=(int)$db->lastInsertId(); $dc->id=$id;
$alias=$callback->save('', $dc);
check($alias==='major-renault', 'Automatic alias');
$db->update('tl_auriga_lexicon', ['alias'=>$alias,'published'=>'1','description'=>"**Pilot**\n\n<script>alert(1)</script>"], ['id'=>$id]);
$db->insert('tl_auriga_lexicon', ['term'=>'Major Renault']);
$other=(int)$db->lastInsertId(); $dc->id=$other;
check($callback->save('', $dc)==='major-renault-'.$other, 'Duplicate automatic alias');
foreach (['major-renault','../escape'] as $bad) {
 try { $callback->save($bad, $dc); throw new RuntimeException('Accepted invalid/duplicate alias'); }
 catch (InvalidArgumentException $e) {}
}
$dc->id=$id;
$db->update('tl_auriga_lexicon', ['term'=>'Renault <script>'], ['id'=>$id]);
check($callback->save($alias,$dc)===$alias,'Alias changed with term');
$db->update('tl_auriga_lexicon', ['alias'=>'draft','term'=>'Hidden draft'], ['id'=>$other]);
Contao\Input::setGet('lexicon', null);
$html=(new LexiconView($model))->render();
check(str_contains($html,'/lexicon/major-renault') && !str_contains($html,'Hidden draft'), 'Overview publication/links');
check(str_contains($html,'&lt;script&gt;'), 'Term escaping');
Contao\Input::setGet('lexicon', $alias);
$html=(new LexiconView($model))->render();
check(str_contains($html,'<strong>Pilot</strong>') && !str_contains($html,'<script>'), 'Safe Markdown detail');
foreach (['draft','missing','../bad'] as $bad) {
 Contao\Input::setGet('lexicon',$bad);
 try { (new LexiconView($model))->render(); throw new RuntimeException('Missing 404'); }
 catch (Contao\CoreBundle\Exception\PageNotFoundException $e) {}
}
$db->executeStatement('DELETE FROM tl_auriga_lexicon');
Contao\Input::setGet('lexicon',null);
check(str_contains((new LexiconView($model))->render(),'Noch keine'), 'Empty overview');
echo "OK: lexicon aliases, published overview/detail, safe Markdown, escaping and 404\n";
