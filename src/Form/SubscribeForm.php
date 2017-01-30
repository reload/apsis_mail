<?php

namespace Drupal\apsis_mail\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Newsletter subscription form.
 */
class SubscribeForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apsis_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $apsis = \Drupal::service('apsis');
    // Ajax container with unique id for multiple instances.
    // TODO: Ajax container ID is static and prevents multiple forms on a page.
    $ajax_container = $this->getFormId() . '_ajax_container';
    $form['ajax_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_container,
      ],
    ];

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#attributes' => [
        'placeholder' => t('Name'),
      ],
      '#required' => TRUE,
    );

    $form['email'] = array(
      '#type' => 'email',
      '#title' => t('Email address'),
      '#attributes' => [
        'placeholder' => t('Email'),
      ],
      '#required' => TRUE,
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => array(get_class($this), 'ajaxCallback'),
        'wrapper' => $ajax_container,
        'effect' => 'fade',
        'method' => 'replace',
        'progress' => array(
          'type' => 'throbber',
          'message' => $this->t('Submitting'),
        ),
      ],
    ];

    // If exposed option is selected for lists, we pass the options on to the
    // block form or if there is no specific args, then we default to use the
    // exposed option.
    $build_info = $form_state->getBuildInfo();
    $allowedMailingLists = $apsis->getAllowedMailingLists();
    $allowedDemographicData = $apsis->getAllowedDemographicData();

    if (
      (empty($build_info['args']) && count($allowedMailingLists) > 1) ||
      (!empty($build_info['args'][0]) && $build_info['args'][0] === 'exposed')
    ) {
      $form['exposed_lists'] = [
        '#type' => 'checkboxes',
        '#title' => t('Mailing lists'),
        '#description' => t('Mailing lists to subscribe to.'),
        '#options' => $allowedMailingLists,
        '#default_value' => [],
        '#required' => TRUE,
      ];

      foreach ($allowedDemographicData as $demographic) {
        $alternatives = $demographic['alternatives'];

        $options = [];
        $type = 'textfield';
        if (count($alternatives) == 2) {
          $type = 'checkbox';
        }
        elseif (count($alternatives) > 2) {
          $type = 'select';
          foreach ($alternatives as $alternative) {
            $options[] = $alternative;
          }
        }
        $form[$demographic['index']] = [
          '#type' => $type,
          '#title' => $demographic['key'],
          '#options' => $options,
          '#required' => $demographic['required'],
        ];
      }
    }

    // If there is only one mailinglist selected, and no explict exposed setting
    // set, we'll not expose controls to the user.
    if (empty($build_info['args'][0]) && count($allowedMailingLists) == 1) {
      $build_info['args'][0] = key($allowedMailingLists);
      $form_state->setBuildInfo($build_info);
    }

    return $form;
  }

  /**
   * Form submit ajax callback.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    // Just return the ajax container, not the form.
    $element = $form['ajax_container'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $apsis = \Drupal::service('apsis');

    // Get list id passed from build info.
    $build_info = $form_state->getBuildInfo();
    $list_id = $build_info['args'][0];

    // Populate array with list id´s to subscribe.
    $subscribe_lists = [];
    if (!empty($form_state->getValue('exposed_lists'))) {
      $subscribe_lists = array_filter($form_state->getValue('exposed_lists'));
    }
    elseif ($list_id != 'exposed') {
      $subscribe_lists = [$list_id];
    }

    // Get subscriber info.
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');

    // Add subscriber(s).
    foreach ($subscribe_lists as $list) {
      $submit = $apsis->addSubscriber($list, $email, $name, $demographic_data);
      drupal_set_message(t($submit->Message));
    }
  }

}
