<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\Test\IZettle\Api\Common\_fixtures\TestStruct;

use Swag\PayPal\IZettle\Api\Common\IZettleStruct;

class Bar extends IZettleStruct
{
    /**
     * @var string
     */
    protected $bar;

    protected function setBar(string $bar): void
    {
        $this->bar = $bar;
    }
}
