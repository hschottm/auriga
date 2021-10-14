<?php

declare(strict_types=1);

/*
 * @copyright  Helmut Schottmüller 2021 <http://github.com/hschottm>
 * @author     Helmut Schottmüller (hschottm)
 * @package    auriga
 * @license    LGPL-3.0+, CC-BY-NC-3.0
 * @see	      https://github.com/hschottm/auriga
 */

namespace Hschottm\AurigaBundle;

use Hschottm\AurigaBundle\DependencyInjection\AurigaExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HschottmAurigaBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new AurigaExtension();
    }
}
