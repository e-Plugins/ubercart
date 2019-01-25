<?php

namespace Drupal\uc_targetpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for PayPal routes.
 */
class TargetpayController extends ControllerBase
{

    /**
     * domain/targetpay/returnurl?paymethod=...&trxid=...
     */
    public function returnurl()
    {
        global $base_url;

        $paymethod = \Drupal::request()->query->get('paymethod');   //GET

        switch ($paymethod) {
            case 'PYP':
                $trxid = \Drupal::request()->query->get('paypalid');
                break;
            default:
                $trxid = \Drupal::request()->query->get('trxid');
        }

        $message = '';

        if (executeReport($paymethod, $trxid, $message)) {
            $returnPage = $base_url . '/cart/checkout/complete';
            drupal_set_message($message);
        } else {
            \Drupal::logger('uc_targetpay')->warning($message);
            $returnPage = $base_url . '/cart/checkout';
            drupal_set_message($message, 'warning');
        }
        $response = new \Symfony\Component\HttpFoundation\RedirectResponse($returnPage);
        $response->send();
    }

    /**
     * This function is called from banking after processing payment
     * domain/targetpay/reporturl?paymethod=...
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
                $trxid = \Drupal::request()->request->get('invoiceID'); //POST
                break;
            case 'PYP':
                $trxid = \Drupal::request()->request->get('acquirerID'); //POST old paymentID
                break;
            default:
                $trxid = \Drupal::request()->request->get('trxid'); //POST
        }
        $message = '';
        executeReport($paymethod, $trxid, $message);
        echo($message);

        echo "|<br>nl Ubercart, " . date('d-m-Y');
        exit();
    }

    /**
     * Output the instruction for bankwire payment
     *
     * @return boolean|string[]
     */
    public function instruction()
    {
        error_reporting(E_ALL & ~E_NOTICE);
        if (empty($_SESSION['bw_info']))
            return false;

        $data = $_SESSION['bw_info'];
        $css = '.tm-highlight {  color: #c94c4c;}';

        list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $data['bw_data']);

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
