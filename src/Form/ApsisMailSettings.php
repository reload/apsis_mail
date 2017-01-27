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
    $demographic_data = \Drupal::state()->get('apsis_mail_demographic_data');

    // Invoke Apsis service.
    $apsis = \Drupal::service('apsis');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('API key'),
      '#description' => t('API key goes here.'),
      '#default_value' => $api_key,
    ];

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => t('API endpoint'),
      '#description' => t('URL to API method.'),
      '#default_value' => $config->get('api_url'),
    ];

    $form['api_ssl'] = [
      '#type' => 'checkbox',
      '#title' => t('Use SSL'),
      '#description' => t('Use secure connection.'),
      '#default_value' => $config->get('api_ssl'),
    ];

    $form['api_port'] = [
      '#type' => 'textfield',
      '#title' => t('API endpoint SSL port'),
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
      '#type' => 'checkboxes',
      '#title' => $this->t('User edit form subscription settings'),
      '#description' => $this->t(
        'Enables users with corresponding role selected to subscribe
        and usubscribe to mailing lists vi their user edit page.'
      ),
      '#options' => $roles,
      '#default_value' => $config->get('user_roles') ? $config->get('user_roles') : [],
    ];

    if ($apsis->getMailingLists()) {
      $form['mailing_lists'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Mailing lists'),
        '#description' => t('Globally allowed mailing lists on site'),
        '#options' => $apsis->getMailingLists(),
        '#default_value' => $mailing_lists ? $mailing_lists : [],
      ];
    }

    if ($apsis->getDemographicData()) {
      $form['demographic_data'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Demographic data'),
        '#description' => t('Globally allowed demographic data on site'),
        '#options' => $apsis->getDemographicData(),
        '#default_value' => $demographic_data ? $demographic_data : [],
      ];
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
      'apsis_mail_mailing_lists' => $form_state->getValue('mailing_lists') ? array_filter($form_state->getValue('mailing_lists')) : [],
      'apsis_mail_demographic_data' => $form_state->getValue('demographic_data') ? array_filter($form_state->getValue('demographic_data')) : [],
    ]);

    // Save settings.
    $this->config('apsis_mail.admin')
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_ssl', $form_state->getValue('api_ssl'))
      ->set('api_port', $form_state->getValue('api_port'))
      ->set('user_roles', $form_state->getValue('user_roles'))
      ->save();

    drupal_set_message($this->t('Settings saved.'));
  }

}
