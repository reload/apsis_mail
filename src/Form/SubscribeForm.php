<?php

namespace Drupal\apsis_mail\Form;

use Drupal\apsis_mail\Apsis;
use Drupal\apsis_mail\Exception\ApsisException;
use Drupal\apsis_mail\Exception\InvalidSubscriberException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Newsletter subscription form.
 */
class SubscribeForm extends FormBase {

  /**
   * The Apsis client object which is used to communicate with the system.
   *
   * @var \Drupal\apsis_mail\Apsis
   */
  protected $apsis;

  /**
   * The queue for which to store and process newly created subscriptions.
   *
   * Apsis can be slow to respond. We store
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $subscriptionQueue;

  /**
   * SubscribeForm constructor.
   *
   * @param Apsis $apsis
   *   The Apsis client object which is used to communicate with the system.
   * @param QueueInterface $queue
   *   The queue for which to store and process newly created subscriptions.
   */
  public function __construct(Apsis $apsis, QueueInterface $queue) {
    $this->apsis = $apsis;
    $this->subscriptionQueue = $queue;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('apsis.prequeue'),
      $container->get('apsis.queue.add_subscriber')
    );
  }

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

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#attributes' => [
        'placeholder' => $this->t('Name'),
      ],
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#attributes' => [
        'placeholder' => $this->t('Email'),
      ],
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxCallback'],
        'wrapper' => $ajax_container,
        'effect' => 'fade',
        'method' => 'replace',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Submitting'),
        ],
      ],
    ];

    // If exposed option is selected for lists, we pass the options on to the
    // block form or if there is no specific args, then we default to use the
    // exposed option.
    $build_info = $form_state->getBuildInfo();
    try {
      $allowedMailingLists = $apsis->getAllowedMailingLists();
      $allowedDemographicData = $apsis->getAllowedDemographicData();
    } catch (ApsisException $e) {
      $allowedMailingLists = [];
      $allowedDemographicData = [];
    }

    if (count($allowedDemographicData > 0)) {
      $form['apsis_demographic_data'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    // Demographics.
    if (($build_info['args'] && $build_info['args'][1]) || \Drupal::state()->get('apsis_mail.demographic_data.always_show')) {
      foreach ($allowedDemographicData as $key => $demographic) {
        $alternatives = $demographic['alternatives'];
        $required = $demographic['required'];
        $state = \Drupal::state()->get('apsis_mail.demographic_data', []);
        $label = !empty($state[$key]['label']) ? $state[$key]['label'] : $key;
        $checkbox = $state[$key]['checkbox'];
        $return_value = !empty($state[$key]['return_value']) ? $state[$key]['return_value'] : NULL;

        $form['apsis_demographic_data'][$key] = $apsis->demographicFormElement($alternatives, $label, $required, $checkbox, $return_value);
      }
    }

    // Hide mailing lists if there's more than one.
    if (
      (empty($build_info['args']) && count($allowedMailingLists) > 1) ||
      (!empty($build_info['args'][0]) && $build_info['args'][0] === 'exposed')
    ) {
      $form['exposed_lists'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Mailing lists'),
        '#description' => $this->t('Mailing lists to subscribe to.'),
        '#options' => $allowedMailingLists,
        '#default_value' => [],
        '#required' => TRUE,
      ];
    }
    // If there's only one allowed mailing list, make it checked and hidden.
    else {
      $form['exposed_lists'] = [
        '#type' => 'hidden',
        '#options' => $allowedMailingLists,
        '#default_value' => array_keys($allowedMailingLists),
        '#required' => TRUE,
      ];
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
    // Get list id passed from build info.
    $build_info = $form_state->getBuildInfo();
    if (!empty($build_info['args'])) {
      $list_id = $build_info['args'][0];
    }

    // Populate array with list id´s to subscribe.
    $subscribe_lists = [];
    if (!empty($form_state->getValue('exposed_lists')) && count($form_state->getValue('exposed_lists')) > 1) {
      $subscribe_lists = array_filter($form_state->getValue('exposed_lists'));
    }
    else {
      $subscribe_lists = [$list_id];
    }

    // Get subscriber info.
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');

    // Format demographic data.
    $demographics = [];
    foreach ($form_state->getValue('apsis_demographic_data') as $key => $value) {
      // If it's a checkbox, the value is an integer.
      // The alternatives from Apsis can be anything.
      if (is_int($value)) {
        $return_value = \Drupal::state()->get('apsis_mail.demographic_data')[$key]['return_value'];

        try {
          $alternatives = $this->apsis->getDemographicData()[$key]['alternatives'];

          if (!$value) {
            unset($alternatives[$return_value]);
            $value = reset($alternatives);
          }
          else {
            $value = $alternatives[$return_value];
          }
        } catch (ApsisException $e) {
          // Do nothing.
        }
      }
      $demographics[] = [
        'Key' => $key,
        'Value' => $value,
      ];
    }

    $new_subscription_lists = $subscribe_lists;

    // Determine which lists the user has already subscribed to.
    try {
      $subscriber_id = $this->apsis->getSubscriberIdByEmail($email);
      $subscriber_lists = $this->apsis->getSubscriberMailingLists($subscriber_id);
      $subscriber_list_ids = array_map(function($list) {
        return $list->Id;
      }, $subscriber_lists);
      $new_subscription_lists = array_diff($subscribe_lists, $subscriber_list_ids);
    } catch (InvalidSubscriberException $e) {
      // The email does not belong to any current subscribers. Add the email to all originally intended mailing lists
      // and a subscriber will automatically be created.
    } catch (ApsisException $e) {
      // Log error but still try to queue up subscription. Further error handling should be handled by the queue worker.
      \Drupal::logger('apsis_mail')->warning(
        sprintf('Unable to determine subscription status for %s: %s', $email, $e->getMessage())
      );
    }

    // Add new subscriptions.
    foreach ($new_subscription_lists as $list) {
      /* @var \Drupal\Core\Queue\QueueInterface $queue */
      $queue = \Drupal::service('apsis.queue.add_subscriber');
      $queue->createItem([$list, $email, $name, $demographics]);
    }

    // Add feedback for all the lists the user wanted to subscribe to.
    foreach ($subscribe_lists as $list) {
      if (in_array($list, $new_subscription_lists)) {
        $message = t('Subscriber successfully created and/or added to list');
      } else {
        $message = t('Subscriber with e-mail @email already exists on the list', ['@email' => $email]);
      }
      drupal_set_message($message);
    }
  }

}
