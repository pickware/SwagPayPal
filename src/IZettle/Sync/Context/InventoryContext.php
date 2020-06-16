<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\IZettle\Sync\Context;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Swag\PayPal\IZettle\Api\Inventory\Status;
use Swag\PayPal\IZettle\Api\Inventory\Status\Variant;
use Swag\PayPal\IZettle\Api\Service\Converter\UuidConverter;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelEntity;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelInventoryCollection;
use Swag\PayPal\IZettle\DataAbstractionLayer\Entity\IZettleSalesChannelInventoryEntity;
use Swag\PayPal\IZettle\Resource\InventoryResource;

class InventoryContext
{
    /**
     * @var IZettleSalesChannelInventoryCollection
     */
    private $localInventory;

    /**
     * @var InventoryResource
     */
    private $inventoryResource;

    /**
     * @var UuidConverter
     */
    private $uuidConverter;

    /**
     * @var SalesChannelEntity
     */
    private $salesChannel;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Status
     */
    private $iZettleInventory;

    /**
     * @var string
     */
    private $storeUuid;

    /**
     * @var string
     */
    private $supplierUuid;

    /**
     * @var string
     */
    private $binUuid;

    /**
     * @var string
     */
    private $soldUuid;

    /**
     * @var string[]
     */
    private $productsUuidsWithStartedTracking = [];

    public function __construct(
        InventoryResource $inventoryResource,
        UuidConverter $uuidConverter,
        SalesChannelEntity $salesChannel,
        string $storeUuid,
        string $supplierUuid,
        string $binUuid,
        string $soldUuid,
        Status $iZettleInventory,
        IZettleSalesChannelInventoryCollection $localInventory,
        Context $context
    ) {
        $this->inventoryResource = $inventoryResource;
        $this->uuidConverter = $uuidConverter;
        $this->salesChannel = $salesChannel;
        $this->storeUuid = $storeUuid;
        $this->supplierUuid = $supplierUuid;
        $this->binUuid = $binUuid;
        $this->soldUuid = $soldUuid;
        $this->iZettleInventory = $iZettleInventory;
        $this->localInventory = $localInventory;
        $this->context = $context;
    }

    public function getIZettleInventory(ProductEntity $productEntity, bool $ignoreTracking = false): ?int
    {
        $productUuid = $productEntity->getParentId();
        $variantUuid = $productEntity->getId();
        if ($productUuid === null) {
            $productUuid = $variantUuid;
            $variantUuid = $this->uuidConverter->incrementUuid($variantUuid);
        }
        $productUuid = $this->uuidConverter->convertUuidToV1($productUuid);
        $variantUuid = $this->uuidConverter->convertUuidToV1($variantUuid);

        $variant = $this->findIZettleInventory($productUuid, $variantUuid);

        if ($variant === null || !($ignoreTracking || $this->isIZettleTracked($productEntity))) {
            return null;
        }

        return $variant->getBalance();
    }

    public function isIZettleTracked(ProductEntity $productEntity): bool
    {
        $productUuid = $productEntity->getParentId() ?? $productEntity->getId();
        $productUuid = $this->uuidConverter->convertUuidToV1($productUuid);

        return \in_array($productUuid, $this->iZettleInventory->getTrackedProducts(), true);
    }

    public function startIZettleTracking(ProductEntity $productEntity): void
    {
        $productUuid = $productEntity->getParentId() ?? $productEntity->getId();
        $productUuid = $this->uuidConverter->convertUuidToV1($productUuid);

        if (\in_array($productUuid, $this->productsUuidsWithStartedTracking, true)) {
            return;
        }

        $newStatus = $this->inventoryResource->startTracking($this->getIZettleSalesChannel(), $productUuid);
        $this->productsUuidsWithStartedTracking[] = $productUuid;
        if ($newStatus === null) {
            return;
        }
        $variants = $newStatus->getVariants();

        if (\count($variants) === 0) {
            return;
        }

        foreach ($variants as $variant) {
            $this->addIZettleInventory($variant);
        }
    }

    public function getLocalInventory(ProductEntity $productEntity): int
    {
        $inventory = $this->localInventory->filter(
            static function (IZettleSalesChannelInventoryEntity $entity) use ($productEntity) {
                return $entity->getProductId() === $productEntity->getId()
                    && $entity->getProductVersionId() === $productEntity->getVersionId();
            }
        );

        $inventoryEntry = $inventory->first();
        if ($inventoryEntry === null) {
            return 0;
        }

        return $inventoryEntry->getStock();
    }

    public function getStoreUuid(): string
    {
        return $this->storeUuid;
    }

    public function getSupplierUuid(): string
    {
        return $this->supplierUuid;
    }

    public function getBinUuid(): string
    {
        return $this->binUuid;
    }

    public function getSoldUuid(): string
    {
        return $this->soldUuid;
    }

    public function addIZettleInventory(Variant $newVariant): void
    {
        $variant = $this->findIZettleInventory($newVariant->getProductUuid(), $newVariant->getVariantUuid());
        if ($variant !== null) {
            $variant->setBalance((string) $newVariant->getBalance());

            return;
        }
        $this->iZettleInventory->addVariant($newVariant);
    }

    public function getSalesChannel(): SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function getIZettleSalesChannel(): IZettleSalesChannelEntity
    {
        /** @var IZettleSalesChannelEntity $iZettleSalesChannel */
        $iZettleSalesChannel = $this->salesChannel->getExtension('paypalIZettleSalesChannel');

        return $iZettleSalesChannel;
    }

    public function updateLocalInventory(IZettleSalesChannelInventoryCollection $localInventory): void
    {
        foreach ($localInventory->getElements() as $element) {
            $this->localInventory->add($element);
        }
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    private function findIZettleInventory(string $productUuid, string $variantUuid): ?Variant
    {
        foreach ($this->iZettleInventory->getVariants() as $variant) {
            if ($variant->getProductUuid() === $productUuid && $variant->getVariantUuid() === $variantUuid) {
                return $variant;
            }
        }

        return null;
    }
}
