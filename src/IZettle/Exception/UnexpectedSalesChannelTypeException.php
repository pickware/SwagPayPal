<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\IZettle\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UnexpectedSalesChannelTypeException extends ShopwareHttpException
{
    public function __construct(string $typeId)
    {
        parent::__construct(
            'Unexpected Sales Channel type id given "{{ typeId }}". Check your type id settings.',
            ['typeId' => $typeId]
        );
    }

    public function getErrorCode(): string
    {
        return 'SWAG_PAYPAL_IZETTLE__UNEXPECTED_SALES_CHANNEL_TYPE';
    }
}
