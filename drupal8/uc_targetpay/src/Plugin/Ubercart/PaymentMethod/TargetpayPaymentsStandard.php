<?php

namespace Drupal\uc_targetpay\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\uc_order\OrderInterface;
use Drupal\uc_payment\OffsitePaymentMethodPluginInterface;
use Drupal\uc_targetpay\Plugin\Ubercart\PaymentMethod\TargetPayCore;

/**
 * Defines the Targetpay Payments Standard payment method.
 *
 * @UbercartPaymentMethod(
 * id = "targetpay_wps",
 * name = @Translation("Targetpay Payments Plugin")
 * )
 */
class TargetpayPaymentsStandard extends TargetpayPaymentMethodPluginBase implements OffsitePaymentMethodPluginInterface
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
    ];

    protected function getEnabledTypes()
    {
        return $this->payMethod;
    }

    public function orderLoad(OrderInterface $order)
    {
        $data = db_query("SELECT * FROM {uc_payment_targetpay} WHERE order_id = :order_id", array(
            ':order_id' => $order->id()
        ))->fetchObject();

        $GLOBALS['paymentName'] = (!empty($data->payMethod)) ? $this->payMethod[$data->payMethod] : $this->payMethod[$_SESSION['uc_targetpay']['payMethod']];
    }

    /**
     * {@inheritdoc}
     */
    public function cartReviewTitle()
    {
        return $this->t($GLOBALS['paymentName']);
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
                $targetPay = new TargetPayCore('IDE');
                $bankList = $targetPay->getBankList();

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
                $targetPay = new TargetPayCore('DEB');
                $countryList = $targetPay->getCountryList();
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
//         $build['#attached']['library'][] = 'uc_targetpay/uc_targetpay.style';
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
            'testmode' => '1',
            'methods' => [
                'ide' => true,
                'mrc' => true,
                'wal' => true,
                'deb' => true,
                'cc' => true,
                'pyp' => true,
                'afp' => true,
                'bw' => true,
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
            '#title' => $this->t('Targetpay layout code'),
            '#description' => $this->t('Layout code can be get from targetpay.com'),
            '#default_value' => $this->configuration['rtlo']
        );

        $form['testmode'] = array(
            '#type' => 'radios',
            '#title' => $this->t('Test mode'),
            '#options' => array(
                '1' => $this->t('Test mode'),
                '0' => $this->t('Live mode')
            ),
            '#default_value' => $this->configuration['testmode']
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
        uc_targetpay_call($order, $this);
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
        $_SESSION['uc_targetpay']['bankId'] = $input['banking'];
        $_SESSION['uc_targetpay']['payMethod'] = $input['method'];
        $_SESSION['uc_targetpay']['countryId'] = $input['country'];

        return true;
    }
}
