<?php declare(strict_types=1);
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagPayPal\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use SwagPayPal\PayPal\Api\Payment;
use SwagPayPal\PayPal\Api\Payment\ApplicationContext;
use SwagPayPal\PayPal\Exception\PayPalSettingsInvalidException;
use SwagPayPal\Test\Helper\ConstantsForTesting;
use SwagPayPal\Test\Helper\PaymentTransactionTrait;
use SwagPayPal\Test\Helper\ServicesTrait;
use SwagPayPal\Test\Mock\Repositories\OrderRepoMock;
use SwagPayPal\Test\Mock\Repositories\SalesChannelRepoMock;
use SwagPayPal\Test\Mock\Setting\Service\SettingsServiceMock;

class PaymentBuilderServiceTest extends TestCase
{
    use PaymentTransactionTrait,
        ServicesTrait;

    public const TEST_ORDER_NUMBER = 'SW1234';
    public const TEST_ORDER_ID = 'test-order-id';

    public function testGetPayment(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = Context::createDefaultContext();

        $payment = $paymentBuilder->getPayment($paymentTransaction, $context);

        static::assertInstanceOf(Payment::class, $payment);

        $transaction = $payment->getTransactions()[0];
        static::assertInstanceOf(Payment\Transaction::class, $transaction);
    }

    public function testGetPaymentInvalidIntentThrowsException(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_INVALID_INTENT, new Entity());

        $this->expectException(PayPalSettingsInvalidException::class);
        $this->expectExceptionMessage('Required setting "intent" is missing or invalid');
        $paymentBuilder->getPayment($paymentTransaction, $context);
    }

    public function testGetPaymentWithoutBrandName(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITHOUT_BRAND_NAME, new Entity());

        $payment = json_encode($paymentBuilder->getPayment($paymentTransaction, $context));
        static::assertNotFalse($payment);
        if ($payment === false) {
            return;
        }

        $payment = json_decode($payment, true);

        static::assertSame(SalesChannelRepoMock::SALES_CHANNEL_NAME, $payment['application_context']['brand_name']);
    }

    public function testGetPaymentWithoutBrandNameAndSalesChannel(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = $this->createContextWithoutSalesChannel();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITHOUT_BRAND_NAME, new Entity());

        $payment = json_encode($paymentBuilder->getPayment($paymentTransaction, $context));
        static::assertNotFalse($payment);
        if ($payment === false) {
            return;
        }

        $payment = json_decode($payment, true);

        static::assertSame('', $payment['application_context']['brand_name']);
    }

    public function testGetPaymentWithItemList(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_SUBMIT_CART, new Entity());
        $paymentTransaction = $this->createPaymentTransactionStruct(ConstantsForTesting::VALID_ORDER_ID);

        $payment = $paymentBuilder->getPayment($paymentTransaction, $context);

        $transaction = json_encode($payment->getTransactions()[0]);

        static::assertNotFalse($transaction);
        if ($transaction === false) {
            return;
        }

        $transaction = json_decode($transaction, true);
        $item = $transaction['item_list']['items'][0];

        static::assertSame(OrderRepoMock::EXPECTED_ITEM_NAME, $item['name']);
        static::assertSame(OrderRepoMock::EXPECTED_ITEM_CURRENCY, $item['currency']);
        static::assertSame(OrderRepoMock::EXPECTED_ITEM_PRICE, $item['price']);
        static::assertSame(OrderRepoMock::EXPECTED_ITEM_QUANTITY, $item['quantity']);
        static::assertSame(OrderRepoMock::EXPECTED_ITEM_SKU, $item['sku']);
        static::assertSame(OrderRepoMock::EXPECTED_ITEM_TAX, $item['tax']);
    }

    public function testGetPaymentWithoutPrice(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_SUBMIT_CART, new Entity());
        $paymentTransaction = $this->createPaymentTransactionStruct(ConstantsForTesting::ORDER_ID_MISSING_PRICE);

        $payment = $paymentBuilder->getPayment($paymentTransaction, $context);
        $transaction = json_encode($payment->getTransactions()[0]);

        static::assertNotFalse($transaction);
        if ($transaction === false) {
            return;
        }

        $transaction = json_decode($transaction, true)['item_list'];

        static::assertNull($transaction);
    }

    public function testGetPaymentWithoutLineItems(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();
        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_SUBMIT_CART, new Entity());
        $paymentTransaction = $this->createPaymentTransactionStruct(ConstantsForTesting::ORDER_ID_MISSING_LINE_ITEMS);

        $payment = $paymentBuilder->getPayment($paymentTransaction, $context);
        $transaction = json_encode($payment->getTransactions()[0]);

        static::assertNotFalse($transaction);
        if ($transaction === false) {
            return;
        }

        $transaction = json_decode($transaction, true)['item_list'];

        static::assertNull($transaction);
    }

    /**
     * @dataProvider dataProviderTestApplicationContext
     */
    public function testApplicationContext(string $extensionName, string $expectedResult): void
    {
        $paymentBuilder = $this->createPaymentBuilder();
        $context = Context::createDefaultContext();
        $context->addExtension($extensionName, new Entity());
        $paymentTransaction = $this->createPaymentTransactionStruct();

        $payment = $paymentBuilder->getPayment($paymentTransaction, $context);
        $paymentJsonString = json_encode($payment);
        static::assertNotFalse($paymentJsonString);
        if ($paymentJsonString === false) {
            return;
        }

        $applicationContext = json_decode($paymentJsonString, true)['application_context'];
        static::assertSame($expectedResult, $applicationContext['landing_page']);
    }

    public function dataProviderTestApplicationContext(): array
    {
        return [
            [
                SettingsServiceMock::PAYPAL_SETTING_WITHOUT_TOKEN,
                ApplicationContext::LANDINGPAGE_TYPE_BILLING,
            ],
            [
                SettingsServiceMock::PAYPAL_SETTING_WITHOUT_TOKEN_AND_ID,
                ApplicationContext::LANDINGPAGE_TYPE_LOGIN,
            ],
            [
                SettingsServiceMock::PAYPAL_SETTING_WITH_SUBMIT_CART,
                ApplicationContext::LANDINGPAGE_TYPE_LOGIN,
            ],
        ];
    }

    public function testGetPaymentWithOrderNumber(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_ORDER_NUMBER, new Entity());

        $payment = json_encode($paymentBuilder->getPayment($paymentTransaction, $context));
        static::assertNotFalse($payment);
        if ($payment === false) {
            return;
        }

        $payment = json_decode($payment, true);

        static::assertSame(SettingsServiceMock::PAYPAL_SETTING_ORDER_NUMBER_PREFIX . self::TEST_ORDER_NUMBER, $payment['transactions'][0]['invoice_number']);
    }

    public function testGetPaymentWithOrderNumberWithoutPrefix(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct();
        $context = Context::createDefaultContext();
        $context->addExtension(SettingsServiceMock::PAYPAL_SETTING_WITH_ORDER_NUMBER_WITHOUT_PREFIX, new Entity());

        $payment = json_encode($paymentBuilder->getPayment($paymentTransaction, $context));
        static::assertNotFalse($payment);
        if ($payment === false) {
            return;
        }

        $payment = json_decode($payment, true);

        static::assertSame(self::TEST_ORDER_NUMBER, $payment['transactions'][0]['invoice_number']);
    }

    public function testGetPaymentInvalidOrder(): void
    {
        $paymentBuilder = $this->createPaymentBuilder();

        $paymentTransaction = $this->createPaymentTransactionStruct(null);
        $context = Context::createDefaultContext();

        $this->expectException(InvalidOrderException::class);
        $this->expectExceptionMessage('The order with id test-order-id is invalid or could not be found.');
        $paymentBuilder->getPayment($paymentTransaction, $context);
    }

    private function createContextWithoutSalesChannel(): Context
    {
        $defaultContext = Context::createDefaultContext();
        $sourceContext = new SalesChannelApiSource('foo');

        return new Context(
            $sourceContext,
            $defaultContext->getRules(),
            $defaultContext->getCurrencyId(),
            $defaultContext->getLanguageIdChain(),
            $defaultContext->getVersionId(),
            $defaultContext->getCurrencyFactor()
        );
    }
}
