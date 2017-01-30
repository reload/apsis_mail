<?php

namespace Drupal\apsis_mail\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Settings form.
 */
class ApsisMailSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apsis_mail_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'apsis_mail.admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get config and states.
    $config = $this->config('apsis_mail.admin');
    $api_key = \Drupal::state()->get('apsis_mail_api_key');
    $mailing_lists = \Drupal::state()->get('apsis_mail_mailing_lists');

    // Invoke Apsis service.
    $apsis = \Drupal::service('apsis');

    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => t('API'),
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('Key'),
      '#description' => t('API key goes here.'),
      '#default_value' => $api_key,
    ];

    $form['api']['endpoint'] = [
      '#type' => 'details',
      '#title' => t('Endpoint'),
      '#open' => FALSE,
    ];

    $form['api']['endpoint']['api_url'] = [
      '#type' => 'textfield',
      '#title' => t('URL'),
      '#description' => t('URL to API method.'),
      '#default_value' => $config->get('api_url'),
    ];

    $form['api']['endpoint']['api_ssl'] = [
      '#type' => 'checkbox',
      '#title' => t('Use SSL'),
      '#description' => t('Use secure connection.'),
      '#default_value' => $config->get('api_ssl'),
    ];

    $form['api']['endpoint']['api_port'] = [
      '#type' => 'textfield',
      '#title' => t('SSL port'),
      '#description' => t('API endpoint SSL port number.'),
      '#default_value' => $config->get('api_port'),
      '#states' => [
        'visible' => [
          ':input[name="api_ssl"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    // Get user roles.
    $user_roles = user_roles(TRUE);
    $roles = [];
    foreach ($user_roles as $role) {
      $roles[$role->id()] = $role->label();
    }

    $form['user_roles'] = [
      '#type' => 'details',
      '#title' => $this->t('Control subscriptions on user profile'),
      '#description' => $this->t(
        'Enables users with corresponding role(s) selected to subscribe
        and unsubscribe to mailing lists via their user edit page.'
      ),
    ];

    $form['user_roles']['user_roles_allowed'] = [
      '#type' => 'checkboxes',
      '#title' => t('Roles'),
      '#options' => $roles,
      '#default_value' => $config->get('user_roles_allowed') ? $config->get('user_roles_allowed') : [],
    ];

    if ($apsis->getMailingLists()) {
      $form['mailing_lists'] = [
        '#type' => 'details',
        '#title' => t('Mailing lists'),
        '#description' => t('Globally allowed mailing lists on site'),
      ];

      $form['mailing_lists']['mailing_lists_available'] = [
        '#type' => 'checkboxes',
        '#title' => t('Allowed mailing lists'),
        '#options' => $apsis->getMailingLists(),
        '#default_value' => $mailing_lists ? $mailing_lists : [],
      ];
    }

    if ($apsis->getDemographicData()) {
      $form['demographic_data'] = [
        '#type' => 'details',
        '#title' => t('Demographic data'),
        '#description' => t('Globally allowed demographic data on site'),
      ];

      foreach ($apsis->getDemographicData() as $demographic) {
        $key = $demographic['key'];

        $form['demographic_data'][$key] = [
          '#type' => 'fieldset',
          '#title' => $key,
        ];

        $form['demographic_data'][$key][$key . '_available'] = [
          '#type' => 'checkbox',
          '#title' => t('Available on block'),
          '#description' => t('Visible on the subscription block'),
          '#default_value' => $config->get($key . '_available'),
        ];

        if (count($demographic['alternatives']) == 2) {
          $form['demographic_data'][$key][$key . '_bool'] = [
            '#type' => 'checkbox',
            '#title' => t('Is boolean'),
            '#options' => $demographic['alternatives'],
            '#description' => t('Check this, if the value should be considered as a boolean'),
            '#default_value' => $config->get($key . '_bool'),
          ];
          $form['demographic_data'][$key][$key . '_true'] = [
            '#type' => 'select',
            '#title' => t('True value'),
            '#options' => $demographic['alternatives'],
            '#default_value' => $config->get($key . '_true'),
            '#states' => array(
              'invisible' => array(
                ':input[name="' . $key . '_bool"]' => array('checked' => FALSE),
              ),
            ),
          ];
        }
        else {
          $form['demographic_data'][$key][$key . '_required'] = [
            '#type' => 'checkbox',
            '#title' => t('Required'),
            '#description' => t('Required on the subscription block'),
            '#default_value' => $config->get($key . '_required'),
            '#states' => array(
              'invisible' => array(
                ':input[name="' . $key . '_available"]' => array('checked' => FALSE),
              ),
            ),
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save states.
    \Drupal::state()->setMultiple([
      'apsis_mail_api_key' => $form_state->getValue('api_key') ? $form_state->getValue('api_key') : '',
      'apsis_mail_mailing_lists' => $form_state->getValue('mailing_lists_available') ? array_filter($form_state->getValue('mailing_lists_available')) : [],
    ]);

    // Save demographic data settings.
    $apsis = \Drupal::service('apsis');
    $demographics = $apsis->getDemographicData();

    foreach ($demographics as $demographic) {
      $key = $demographic['key'];

      $this->config('apsis_mail.admin')
        ->set($key . '_available', $form_state->getValue($key . '_available'))
        ->set($key . '_required', $form_state->getValue($key . '_required'))
        ->set($key . '_bool', $form_state->getValue($key . '_bool'))
        ->set($key . '_true', $form_state->getValue($key . '_true'))
        ->save();
    }

    // Save settings.
    $this->config('apsis_mail.admin')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_ssl', $form_state->getValue('api_ssl'))
      ->set('api_port', $form_state->getValue('api_port'))
      ->set('user_roles_allowed', $form_state->getValue('user_roles_allowed'))
      ->save();

    drupal_set_message($this->t('Settings saved.'));
  }

}
