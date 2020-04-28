<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\IZettle\Api;

use Swag\PayPal\IZettle\Api\Common\IZettleStruct;
use Swag\PayPal\IZettle\Api\Error\Violation;

class Error extends IZettleStruct
{
    public const ERROR_TYPE_ITEM_ALREADY_EXISTS = 'ITEM_ALREADY_EXIST';
    public const ERROR_TYPE_ENTITY_NOT_FOUND = 'ENTITY_NOT_FOUND';

    /**
     * @var string
     */
    private $developerMessage;

    /**
     * @var string|null
     */
    private $errorType;

    /**
     * @var Violation[]
     */
    private $violations;

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function toString(): string
    {
        $message = $this->developerMessage;

        if ($this->violations === []) {
            return $message;
        }

        $message .= ":\n";

        foreach ($this->violations as $violation) {
            $message .= $violation->toString();
        }

        return $message;
    }

    protected function setDeveloperMessage(string $developerMessage): void
    {
        $this->developerMessage = $developerMessage;
    }

    protected function setErrorType(?string $errorType): void
    {
        $this->errorType = $errorType;
    }

    protected function setViolations(array $violations): void
    {
        $this->violations = $violations;
    }
}
