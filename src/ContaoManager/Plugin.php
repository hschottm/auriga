<?php

declare(strict_types=1);

/*
 * @copyright  Helmut Schottmüller 2021 <http://github.com/hschottm>
 * @author     Helmut Schottmüller (hschottm)
 * @package    auriga
 * @license    LGPL-3.0+, CC-BY-NC-3.0
 * @see	      https://github.com/hschottm/auriga
 */

namespace Hschottm\AurigaBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Hschottm\AurigaBundle\HschottmAurigaBundle;

/**
 * Plugin for the Contao Manager.
 *
 * @author Helmut Schottmüller (hschottm)
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
             BundleConfig::create(HschottmAurigaBundle::class)
              ->setLoadAfter([ContaoCoreBundle::class])
              ->setReplace(['auriga']),
         ];
    }
}
