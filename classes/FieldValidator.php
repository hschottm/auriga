<?php

namespace Contao;

/**
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    auriga
 * @license    LGPL
 */


/**
 * Class FieldValidator
 *
 * Provide helper methods for auriga
 * @copyright  Helmut Schottmüller 2009
 * @author     Helmut Schottmüller <typolight@aurealis.de>
 * @package    Controller
 */
class FieldValidator
{
	public function addCustomRegexp($strRegexp, $varValue, Widget $objWidget)
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