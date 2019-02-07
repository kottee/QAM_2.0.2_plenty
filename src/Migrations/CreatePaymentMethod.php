<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License
 *
 * @author Novalnet <technic@novalnet.de>
 * @copyright Novalnet
 * @license GNU General Public License
 *
 * Script : CreatePaymentMethod.php
 *
 */
 
namespace Novalnet\Migrations;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Novalnet\Helper\PaymentHelper;

/**
 * Migration to create payment mehtods
 *
 * Class CreatePaymentMethod
 *
 * @package Novalnet\Migrations
 */
 
class CreatePaymentMethod
{
    /**
     * @var PaymentMethodRepositoryContract
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * CreatePaymentMethod constructor.
     *
     * @param PaymentMethodRepositoryContract $paymentMethodRepository
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository,
                                PaymentHelper $paymentHelper)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Run on plugin build
     *
     * Create Method of Payment ID for Novalnet payment if they don't exist
     */
    public function run()
    {
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_INVOICE', 'Invoice');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PREPAYMENT', 'Prepayment');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_CC', 'Credit Card');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_SEPA', 'Direct Debit SEPA');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_SOFORT', 'Instant Bank Transfer');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PAYPAL', 'PayPal');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_IDEAL', 'iDEAL');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_EPS', 'eps');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_GIROPAY', 'giropay');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_PRZELEWY', 'Przelewy24');
        $this->createNovalnetPaymentMethodByPaymentKey('NOVALNET_CASHPAYMENT', 'Barzahlen');
    }


    /**
     * Create payment method with given parameters if it doesn't exist
     *
     * @param string $paymentKey
     * @param string $name
     */
    private function createNovalnetPaymentMethodByPaymentKey($paymentKey, $name)
    {
        if ($this->paymentHelper->getPaymentMethodByKey($paymentKey) == 'no_paymentmethod_found')
        {
            $paymentMethodData = ['pluginKey'  => 'plenty_novalnet',
                              'paymentKey' => $paymentKey,
                              'name'       => $name];
            $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
        }
    }
}
