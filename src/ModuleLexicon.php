<?php

namespace Hschottm\AurigaBundle;

use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Input;
use Contao\System;

class ModuleLexicon extends ModulePTW
{
    protected $strTemplate = 'mod_auriga_lexicon';

    public function generate()
    {
        if ($this->isBackendRequest()) {
            $template = new BackendTemplate('be_wildcard');
            $template->wildcard = '### AURIGA LEXIKON ###';
            $template->title = $this->headline;
            $template->id = $this->id;
            $template->link = $this->name;
            $template->href = System::getContainer()->get('router')->generate('contao_backend', ['do'=>'themes', 'table'=>'tl_module', 'act'=>'edit', 'id'=>$this->id]);
            return $template->parse();
        }
        return parent::generate();
    }

    protected function compile()
    {
        global $objPage;
        $this->loadLanguageFile('tl_auriga_lexicon');
        $db = System::getContainer()->get('database_connection');
        $alias = Input::get('lexicon');
        $this->Template->entry = null;
        $this->Template->entries = [];
        $this->Template->labels = $GLOBALS['TL_LANG']['tl_auriga_lexicon'];
        $this->Template->overviewUrl = $objPage->getFrontendUrl();
        $objPage->cache = 0;
        $objPage->clientCache = 0;
        if ($alias !== null && $alias !== '') {
            if (!is_string($alias) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,127}$/D', $alias)) throw new PageNotFoundException('Invalid lexicon alias');
            $entry = $db->fetchAssociative("SELECT term, alias, description FROM tl_auriga_lexicon WHERE alias = ? AND published = '1'", [$alias]);
            if (!$entry) throw new PageNotFoundException('Lexicon entry not found');
            $entry['html'] = StoryMarkdown::render((string) $entry['description']);
            $this->Template->entry = $entry;
            return;
        }
        $entries = $db->fetchAllAssociative("SELECT term, alias FROM tl_auriga_lexicon WHERE published = '1' AND alias IS NOT NULL AND alias != '' ORDER BY term, id");
        foreach ($entries as &$entry) $entry['url'] = $objPage->getFrontendUrl('/lexicon/'.rawurlencode($entry['alias']));
        unset($entry);
        $this->Template->entries = $entries;
    }
}
