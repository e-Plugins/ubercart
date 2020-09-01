<?php

namespace Drupal\uc_digiwallet\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

require_once(drupal_get_path('module', 'uc_digiwallet') . '/vendor/autoload.php');
/**
 * Returns responses for PayPal routes.
 */
class DigiwalletController extends ControllerBase
{
    /**
     * domain/digiwallet/returnurl?paymethod=...&trxid=...
     */
    public function returnurl()
    {
        global $base_url;

        $paymethod = \Drupal::request()->query->get('paymethod');   //GET

        switch ($paymethod) {
            case 'PYP':
                $trxid = \Drupal::request()->query->get('paypalid');
                break;
            case 'AFP':
                $trxid = \Drupal::request()->query->get('invoiceID');
                break;
            case 'EPS':
            case 'GIP':
                $trxid = \Drupal::request()->query->get('transactionID');
                break;
            default:
                $trxid = \Drupal::request()->query->get('trxid');
        }

        $message = '';

        if (uc_digiwallet_execute_report($paymethod, $trxid, $message)) {
            $returnPage = $base_url . '/cart/checkout/complete';
            drupal_set_message($message);
        } else {
            \Drupal::logger('uc_digiwallet')->warning($message);
            $returnPage = $base_url . '/cart/checkout';
            drupal_set_message($message, 'warning');
        }
        $response = new \Symfony\Component\HttpFoundation\RedirectResponse($returnPage);
        $response->send();
    }

    /**
     * This function is called from banking after processing payment
     * domain/digiwallet/reporturl?paymethod=...
     *
     * @param
     *            Post trxid
     *            Post paymethod: IDE, MRC,...
     *
     */
    public function reporturl()
    {
        $paymethod = \Drupal::request()->query->get('paymethod');   //GET
        switch ($paymethod) {
            case 'AFP':
                $trxid = \Drupal::request()->request->get('invoiceID');
                break;
            case 'PYP':
                $trxid = \Drupal::request()->request->get('acquirerID');
                break;
            case 'EPS':
            case 'GIP':
                $trxid = \Drupal::request()->request->get('transactionID');
                break;
            default:
                $trxid = \Drupal::request()->request->get('trxid');
        }
        $message = '';
        uc_digiwallet_execute_report($paymethod, $trxid, $message, true);
        echo($message);

        echo "|<br>nl Ubercart, " . date('d-m-Y');
        exit();
    }

    /**
     * Output the instruction for bankwire payment
     *
     * @return boolean|string[]
     */
    public function instruction($trxid)
    {
        $transaction = uc_digiwallet_transaction_get('BW', $trxid);
        if(!$transaction) {
            return $this->redirect('uc_cart.cart');
            exit();
        }
        
        $css = '.tm-highlight {  color: #c94c4c;}';

        list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $transaction->more_information);
        \Drupal::service('uc_cart.manager')->emptyCart();
        $html = '
            <div class="bankwire-info">
            <h4>' . t('Thank you for ordering in our webshop!') . '</h4>
            <p>' . t('You will receive your order as soon as we receive payment from the bank. <br>
                Would you be so friendly to transfer the total amount of @order_total to the bank account <b class="tm-highlight">@iban</b> in name of @beneficiary*?', array(
                '@order_total' => uc_currency_format($data['order_total']),
                '@iban' => $iban,
                '@beneficiary' => $beneficiary)) .

            '</p>
            <p>
                ' . t('State the payment feature <b>@trxid</b>, this way the payment can be automatically processed.<br>
                As soon as this happens you shall receive a confirmation mail on @customer_email .', array(
                '@trxid' => $trxid,
                '@customer_email' => $data['customer_email'])) . '
            </p>
            <p>' . t('If it is necessary for payments abroad, then the BIC code from the bank <span class="tm-highlight">@bic</span> and the name of the bank is @bank.', array(
                '@bic' => $bic,
                '@bank' => $bank)) . '</p>
            <p>
                <i>' . t('* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.') . '</i>
            </p>
        </div>';

        $build = [];
        $build['#type'] = 'markup';
        $build['#markup'] = $html;

        $build['#attached']['html_head'][] = [
            [
                '#tag' => 'style',
                '#value' => $css
            ]
        ];

        return $build;
    }
}
