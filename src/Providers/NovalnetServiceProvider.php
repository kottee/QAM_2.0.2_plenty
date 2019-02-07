<?php
/**
 * This module is used for real time processing of
 * Novalnet payment module of customers.
 * Released under the GNU General Public License.
 * This free contribution made by request.
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @author       Novalnet
 * @copyright(C) Novalnet. All rights reserved. <https://www.novalnet.de/>
 */

namespace Novalnet\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Modules\Payment\Events\Checkout\ExecutePayment;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Basket\Events\Basket\AfterBasketCreate;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Events\BasketItem\AfterBasketItemAdd;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodContainer;
use Plenty\Modules\Basket\Contracts\BasketRepositoryContract;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;
use Novalnet\Helper\PaymentHelper;
use Novalnet\Services\PaymentService;
use Novalnet\Services\TransactionService;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\ConfigRepository;

use Novalnet\Methods\NovalnetInvoicePaymentMethod;
use Novalnet\Methods\NovalnetPrepaymentPaymentMethod;
use Novalnet\Methods\NovalnetCcPaymentMethod;
use Novalnet\Methods\NovalnetSepaPaymentMethod;
use Novalnet\Methods\NovalnetSofortPaymentMethod;
use Novalnet\Methods\NovalnetPaypalPaymentMethod;
use Novalnet\Methods\NovalnetIdealPaymentMethod;
use Novalnet\Methods\NovalnetEpsPaymentMethod;
use Novalnet\Methods\NovalnetGiropayPaymentMethod;
use Novalnet\Methods\NovalnetPrzelewyPaymentMethod;
use Novalnet\Methods\NovalnetCashPaymentMethod;

use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;

/**
 * Class NovalnetServiceProvider
 *
 * @package Novalnet\Providers
 */
class NovalnetServiceProvider extends ServiceProvider
{
    use Loggable;

    /**
     * Register the route service provider
     */
    public function register()
    {
        $this->getApplication()->register(NovalnetRouteServiceProvider::class);
    }

    /**
     * Boot additional services for the payment method
     *
     * @param Dispatcher $eventDispatcher
     * @param paymentHelper $paymentHelper
     * @param PaymentService $paymentService
     * @param BasketRepositoryContract $basketRepository
     * @param PaymentMethodContainer $payContainer
     * @param PaymentMethodRepositoryContract $paymentMethodService
     * @param FrontendSessionStorageFactoryContract $sessionStorage
     * @param TransactionService $transactionLogData
     * @param Twig $twig
     * @param ConfigRepository $config
     */
    public function boot( Dispatcher $eventDispatcher,
                          PaymentHelper $paymentHelper,
                          PaymentService $paymentService,
                          BasketRepositoryContract $basketRepository,
                          PaymentMethodContainer $payContainer,
                          PaymentMethodRepositoryContract $paymentMethodService,
                          FrontendSessionStorageFactoryContract $sessionStorage,
                          TransactionService $transactionLogData,
                          Twig $twig,
                          ConfigRepository $config,
                          EventProceduresService $eventProceduresService)
    {

        // Register the Novalnet payment methods in the payment method container
        $payContainer->register('plenty_novalnet::NOVALNET_INVOICE', NovalnetInvoicePaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PREPAYMENT', NovalnetPrepaymentPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CC', NovalnetCcPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_SEPA', NovalnetSepaPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_SOFORT', NovalnetSofortPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PAYPAL', NovalnetPaypalPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_IDEAL', NovalnetIdealPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_EPS', NovalnetEpsPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_GIROPAY', NovalnetGiropayPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_PRZELEWY', NovalnetPrzelewyPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
        $payContainer->register('plenty_novalnet::NOVALNET_CASHPAYMENT', NovalnetCashPaymentMethod::class,
            [
                AfterBasketChanged::class,
                AfterBasketItemAdd::class,
                AfterBasketCreate::class
            ]);
            
		// Event for Onhold - Capture Process
		$captureProcedureTitle = [
            'de' => 'Novalnet | Bestellung erfassen',
            'en' => 'Novalnet | Capture order',
        ];
        $eventProceduresService->registerProcedure(
            'Novalnet',
            ProcedureEntry::EVENT_TYPE_ORDER,
            $captureProcedureTitle,
            '\Novalnet\Procedures\CaptureEventProcedure@run'
        );
        
        // Event for Onhold - Void Process
        $voidProcedureTitle = [
            'de' => 'Novalnet | Bestellung stornieren',
            'en' => 'Novalnet | cancel order',
        ];
        $eventProceduresService->registerProcedure(
            'Novalnet',
            ProcedureEntry::EVENT_TYPE_ORDER,
            $voidProcedureTitle,
            '\Novalnet\Procedures\VoidEventProcedure@run'
        );
        
        // Event for Onhold - Refund Process
        $refundProcedureTitle = [
            'de' =>  'Novalnet | Gutschrift erstellen',
            'en' =>  'Novalnet | Refund order',
        ];
        $eventProceduresService->registerProcedure(
            'Novalnet',
            ProcedureEntry::EVENT_TYPE_ORDER,
            $refundProcedureTitle,
            '\Novalnet\Procedures\RefundEventProcedure@run'
        );
        
        // Listen for the event that gets the payment method content
        $eventDispatcher->listen(GetPaymentMethodContent::class,
                function(GetPaymentMethodContent $event) use($paymentHelper, $paymentService, $basketRepository, $paymentMethodService, $sessionStorage, $twig)
                {
                    if($paymentHelper->getPaymentKeyByMop($event->getMop()))
                    {
                        $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                        if(in_array($paymentKey, ['NOVALNET_INVOICE', 'NOVALNET_PREPAYMENT', 'NOVALNET_CASHPAYMENT']))
                        {
                            $processDirect = true;
                            if($paymentKey == 'NOVALNET_INVOICE')
                            {
                                $guaranteeStatus = $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey);
                                if($guaranteeStatus == 'error')
                                {
                                    $processDirect = false;
                                    $contentType = 'errorCode';
                                    $content = $paymentHelper->getTranslatedText('guarantee_process_error');
                                }
                                else if($guaranteeStatus == 'guarantee')
                                {
                                    $processDirect = false;
                                    $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                    $content = $twig->render('Novalnet::PaymentForm.Invoice', [
                                                        'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                        'paymentMopKey'     =>  $paymentKey,
                                                        'nnDobValue' => ''
                                    ]);
                                    $contentType = 'htmlContent';
                                 }
                            }

                            if($processDirect)
                            {
                                $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);
                                $sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
                                $response = $paymentHelper->executeCurl($serverRequestData['data'], $serverRequestData['url']);
                                $responseData = $paymentHelper->convertStringToArray($response['response'], '&');
                                $responseData['payment_id'] = (!empty($responseData['payment_id'])) ? $responseData['payment_id'] : $responseData['key'];
                                $sessionStorage->getPlugin()->setValue('nnPaymentData', array_merge($serverRequestData['data'], $responseData));
                                $isPaymentSuccess = isset($responseData['status']) && in_array($responseData['status'], ['90','100']);
                                $notifications = json_decode($sessionStorage->getPlugin()->getValue('notifications'));
                                array_push($notifications,[
                                        'message' => $paymentHelper->getNovalnetStatusText($responseData),
                                        'type'    => $isPaymentSuccess ? 'success' : 'error',
                                        'code'    => ''
                                    ]);

                                $sessionStorage->getPlugin()->setValue('notifications', json_encode($notifications));
                                $content = '';
                                $contentType = 'continue';
                            }

                        } else if (in_array($paymentKey, ['NOVALNET_SEPA', 'NOVALNET_CC']))
                        {
                            if($paymentKey == 'NOVALNET_SEPA'){
                                $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                $nnDetails = [];
                                $contentType = 'htmlContent';
                                $nnDetails['sepadoberror'] = $paymentHelper->getTranslatedText('doberror');
                                $guaranteeStatus = $paymentService->getGuaranteeStatus($basketRepository->load(), $paymentKey);

                                if($guaranteeStatus == 'error')
                                {
                                    $contentType = 'errorCode';
                                    $content = $paymentHelper->getTranslatedText('guarantee_process_error');
                                }
                                else
                                {
                                $content = $twig->render('Novalnet::PaymentForm.Sepa', [
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey,
                                                                    'nnSepaHiddenValue' => $nnDetails,
                                                                    'nnGuaranteeStatus' => $guaranteeStatus,
                                                 ]);
                                }
                            }
                            else
                            {
                                $paymentProcessUrl = $paymentService->getProcessPaymentUrl();
                                $encodedKey = base64_encode('vendor='.$paymentHelper->getNovalnetConfig('novalnet_vendor_id').'&product='.$paymentHelper->getNovalnetConfig('novalnet_product_id').'&server_ip='.$paymentHelper->getServerAddress().'&lang='.$sessionStorage->getLocaleSettings()->language);
                                $nnIframeSource = 'https://secure.novalnet.de/cc?api=' . $encodedKey . '&ln=' . $sessionStorage->getLocaleSettings()->language;
                                $nnFormDesign = $paymentService->getCcDesignConfig();
                                $content = $twig->render('Novalnet::PaymentForm.Cc', [
                                                                    'nnCcFormUrl' => $nnIframeSource,
                                                                    'nnPaymentProcessUrl' => $paymentProcessUrl,
                                                                    'paymentMopKey'     =>  $paymentKey,
                                    'nnFormDesign'  =>  $nnFormDesign
                                       ]);
                                $contentType = 'htmlContent';
                            }
                        }
                        else
                        {
                            $serverRequestData = $paymentService->getRequestParameters($basketRepository->load(), $paymentKey);
                            $sessionStorage->getPlugin()->setValue('nnPaymentData', $serverRequestData['data']);
                            $sessionStorage->getPlugin()->setValue('nnPaymentUrl', $serverRequestData['url']);
                            $content='';
                            $contentType='continue';

                        }

                        $event->setValue($content);
                        $event->setType($contentType);
                    }
                });

        // Listen for the event that executes the payment
        $eventDispatcher->listen(ExecutePayment::class,
            function (ExecutePayment $event) use ($paymentHelper, $paymentService, $sessionStorage, $transactionLogData,$config,$basketRepository)
            {
                if($paymentHelper->getPaymentKeyByMop($event->getMop()))
                {
                    $sessionStorage->getPlugin()->setValue('nnOrderNo',$event->getOrderId());
                    $sessionStorage->getPlugin()->setValue('mop',$event->getMop());
                    $paymentKey = $paymentHelper->getPaymentKeyByMop($event->getMop());
                    $sessionStorage->getPlugin()->setValue('paymentkey', $paymentKey);

                    if(in_array($paymentKey,['NOVALNET_INVOICE','NOVALNET_SEPA','NOVALNET_PREPAYMENT','NOVALNET_CASHPAYMENT']) ||($paymentKey == 'NOVALNET_CC' && $config->get('Novalnet.novalnet_cc_3d') != 'true' && $config->get('Novalnet.novalnet_cc_3d_fraudcheck') != 'true'))
                    {
                        $paymentService->validateResponse();
                    } else {
                        $paymentProcessUrl = $paymentService->getRedirectPaymentUrl();
                        $event->setType('redirectUrl');
                        $event->setValue($paymentProcessUrl);
                    }
                }
            }
        );
    }
}
