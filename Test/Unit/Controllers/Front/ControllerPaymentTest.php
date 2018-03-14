<?php

use WirecardEE\Prestashop\Models\PaymentPaypal;

require_once __DIR__ . '/../../../../wirecardpaymentgateway/controllers/front/payment.php';

class ControllerPaymentTest extends \PHPUnit_Framework_TestCase
{
    //Default controller test
    public function testExecuteTransaction()
    {
        $paymentController = new \WirecardPaymentGatewayPaymentModuleFrontController();
        $paymentController->setAmount(2.00);
        $paymentController->setCartId('123');
        $actual = $paymentController->postProcess();

        $this->assertTrue(!is_string($actual));
    }

    //Controller test with wrong basket and wrong additional data
    public function testExecuteTransactionFailed()
    {
        $paymentController = new \WirecardPaymentGatewayPaymentModuleFrontController();
        $paymentController->setAmount(2.00);
        $paymentController->setCartId('123');
        $products = array(
            array(
                'cart_quantity' => 1,
                'name'  => 'Test1',
                'total_wt' => 10.00,
                'total' => 10.00,
                'description_short' => 'Testproduct',
                'reference' => '003'
            )
        );
        Configuration::setBasketConfig(true);
        Configuration::setAdditionalConfig(true);
        $paymentController->setProducts($products);
        $paymentController->setCartAddress('invoice');
        $paymentController->setCartAddress('delivery');
        $actual = $paymentController->postProcess();

        $this->assertTrue(is_string($paymentController->errors));
    }
}
