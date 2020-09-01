<?php

namespace Drupal\uc_digiwallet\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_digiwallet\Plugin\Ubercart\PaymentMethod\DigiwalletCore;

require_once(drupal_get_path('module', 'uc_digiwallet') . '/vendor/autoload.php');
/**
 * Defines the Digiwallet Payments Standard payment method.
 *
 * @UbercartPaymentMethod(
 * id = "digiwallet_wps",
 * name = @Translation("Digiwallet For Ubercart")
 * )
 */
class DigiwalletPaymentsStandard extends DigiwalletPaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
{

    /**
     * Returns the set of card types which are used by this payment method.
     *
     * @return array An array with keys as needed by the chargeCard() method and values
     *         that can be displayed to the customer.
     */
    public $payMethod = [
        'IDE' => 'iDeal',
        'MRC' => 'Bancontact',
        'WAL' => 'Paysafecard',
        'DEB' => 'Sofort Banking ',
        'CC' => 'Visa/Mastercard',
        'PYP' => 'Paypal',
        'AFP' => 'Afterpay',
        'BW' => 'Bankwire',
        'EPS' => 'EPS',
        'GIP' => 'Giropay',
    ];

    protected function getEnabledTypes()
    {
        return $this->payMethod;
    }

//     public function orderLoad(OrderInterface $order)
//     {
//         $data = db_query("SELECT * FROM {uc_payment_digiwallet} WHERE order_id = :order_id ORDER BY id DESC", array(
//             ':order_id' => $order->id()
//         ))->fetchObject();

//         $GLOBALS['paymentName'] = (!empty($data->paymethod)) ? $this->payMethod[$data->paymethod] : $this->payMethod[$_SESSION['uc_digiwallet']['payMethod']];
//     }

    /**
     * {@inheritdoc}
     */
    public function cartReviewTitle()
    {
        return $this->payMethod[$_SESSION['uc_digiwallet']['payMethod']];
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function getDisplayLabel($label)
    {
        if (\Drupal::request()->query->get('cancel')) {
            drupal_set_message(t('Payment has been cancelled'), 'warning');
        }
        $build = [];

        $build['label'] = array(
            '#prefix' => ' ',
            '#plain_text' => $this->t($label),
            '#suffix' => '<br /> '
        );

        $payment_types = $this->getEnabledTypes();

        $build['methods'] = array(
            '#type' => 'fieldset',
            '#title' => t('Payment Methods')
        );

        foreach ($payment_types as $type => $description) {
            // Display only the activated payment methods based upon configuration
            if ($this->configuration['methods'][strtolower($type)] == 0) {
                continue;
            }

            $build['methods'][$type] = array(
                '#type' => 'radio',
                '#name' => "method",
                '#title' => $description,
                '#prefix' => '<div class="payment-item-wrapper ' . $type . '">',
                '#suffix' => '</div>',
                '#attributes' => array(
                    'class' => array(
                        'method-' . $type
                    ),
                    'value' => $type
                )
            );
            if ($type == 'IDE') {
                $digiwallet = new DigiwalletCore('IDE');
                $bankList = $digiwallet->getBankList();

                $build['methods']['banking'] = array(
                    '#title' => t('Select banking'),
                    '#type' => 'select',
                    '#name' => 'banking',
                    '#value' => 2,
                    '#options' => $bankList
                );
                $build['methods'][$type]['#attributes']['checked'] = 'checked'; // Auto check for iDeal radio button
            }
            if ($type == 'DEB') {
                $digiwallet = new DigiwalletCore('DEB');
                $countryList = $digiwallet->getCountryList();
                $_countryList = [];
                foreach ($countryList as $key => $value) {
                    $countryId = str_replace('DEB', '', $key);
                    $countryName = str_replace('Sofort Banking: ', '', $value);
                    $_countryList[$countryId] = $countryName;
                }

                $build['methods']['country'] = array(
                    '#title' => t('Select country'),
                    '#type' => 'select',
                    '#name' => 'country',
                    '#options' => $_countryList
                );
            }
        }
//         $build['#attached']['library'][] = 'uc_digiwallet/uc_digiwallet.style';
        return $build;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function defaultConfiguration()
    {
        return [
            'rtlo' => '39411',
            'token' => '',
            'methods' => [
                'ide' => true,
                'mrc' => true,
                'wal' => true,
                'deb' => true,
                'cc' => true,
                'pyp' => true,
                'afp' => true,
                'bw' => true,
                'eps' => true,
                'gip' => true,
            ]
        ];
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form['rtlo'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Digiwallet Outlet Identifier'),
            '#description' => $this->t('Your Digiwallet Outlet Identifier, You can find this in your organization dashboard under Websites & Outlets on https://www.digiwallet.nl'),
            '#default_value' => $this->configuration['rtlo']
        );
        
        $form['token'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Digiwallet token'),
            '#description' => $this->t('Obtain a token from http://digiwallet.nl'),
            '#default_value' => $this->configuration['token']
        );
        
        $form['methods'] = array(
            '#type' => 'fieldset',
            '#title' => t('Payment Methods'),
            '#collapsible' => true,
            '#collapsed' => false
        );

        $payment_types = $this->getEnabledTypes();
        foreach ($payment_types as $type => $description) {
            $form['methods'][strtolower($type)] = array(
                '#type' => 'checkbox',
                '#title' => $description,
                '#description' => $this->t('Check to enable'),
                '#default_value' => $this->configuration['methods'][strtolower($type)]
            );
        }

        return $form;
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function buildRedirectForm(array $form, FormStateInterface $form_state, OrderInterface $order = null)
    {
        return $form;
    }

    /**
     *
     * {@inheritdoc} Called when Submit order in /cart/checkout/review
     * @see \Drupal\uc_payment\PaymentMethodPluginBase::orderSubmit()
     */
    public function orderSubmit(OrderInterface $order)
    {
        uc_digiwallet_call($order, $this);
    }

    /**
     *
     * {@inheritdoc}
     *
     */
    public function cartProcess(OrderInterface $order, array $form, FormStateInterface $form_state)
    {
        $input = $form_state->getUserInput();

        // Store payment method, bank id or country id into session for later use
        $_SESSION['uc_digiwallet']['bankId'] = $input['banking'];
        $_SESSION['uc_digiwallet']['payMethod'] = $input['method'];
        $_SESSION['uc_digiwallet']['countryId'] = $input['country'];

        return true;
    }
}
