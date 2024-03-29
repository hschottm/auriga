<?php

namespace Hschottm\AurigaBundle\EventListener;

/**
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    auriga
 * @license    LGPL
 */


/**
 * Class AddCustomRegexpListener
 *
 * Provide helper methods for auriga
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class AddCustomRegexpListener
{
	public function addCustomRegexp($strRegexp, $varValue, $objWidget)
	{
		if ($strRegexp == 'length')
		{
			if (!preg_match("/^\d+:\d{2}$/", $varValue))
			{
				$objWidget->addError(sprintf($GLOBALS['TL_LANG']['tl_broadcast']['error_length'], $objWidget->label));
			}
			return true;
		}
		return false;
	}
}