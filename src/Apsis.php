<?php

namespace Drupal\apsis_mail;

use GuzzleHttp\Exception\RequestException;

/**
 * Apsis mail api.
 */
class Apsis {

  /**
   * Configuration object.
   *
   * @var class $config
   */
  public $config;

  /**
   * Constructor.
   *
   * @property class config
   */
  public function __construct() {
    $this->config = \Drupal::config('apsis_mail.admin');
  }

  /**
   * Build url for the REST call.
   *
   * @param string $path
   *   Request path.
   * @param array $args
   *   Request header arguments.
   */
  protected function request($method, $path, $args = []) {
    // Set options variables.
    $protocol = !empty($this->config->get('api_ssl')) ? 'https://' : 'http://';
    $key = !empty($this->config->get('api_key')) ? $this->config->get('api_key') . ':@' : '';
    $url = !empty($this->config->get('api_url')) ? $this->config->get('api_url') : '';
    $port = !empty($this->config->get('api_port')) && $this->config->get('api_ssl') ? ':' . $this->config->get('api_port') : '';
    $args['headers']['Authorization'] = 'Basic ' . base64_encode($key);
    $args['headers']['Content-type'] = 'application/json';
    $args['headers']['Accept'] = 'application/json';

    // Build request url.
    $request_url = $protocol . $url . $port . $path;

    if ($key && $url) {
      // Invoke client.
      $client = \Drupal::httpClient();
      // Try request.
      try {
        // Do http request.
        $response = $client->{$method}($request_url, $args);

        // Return response body.
        $body = $response->getBody();
        return json_decode($body->getContents());
      }
      catch (RequestException $e) {
        // Ignore bad request errors since 'user not found' is treated like one.
        if (in_array($e->getCode(), [400])) {
          return;
        }
        // Set db log message.
        \Drupal::logger('apsis_mail')->error($e->getMessage());
        return FALSE;
      }
    }
  }

  /**
   * Get single mailing list.
   *
   * @return array $list.
   *   Array containing allowed mailing lists.
   */
  public function getAllowedMailingLists() {
    // Get all lists.
    $mailing_lists = $this->getMailingLists();
    // Get allowed list settings.
    $allowed_lists = $this->config->get('mailing_lists');

    // Return list with allowed list items.
    return array_intersect_key($mailing_lists, array_flip($allowed_lists));;
  }

  /**
   * Fetch mailing lists.
   *
   * @return array $list
   *   Array containing all mailing lists.
   */
  public function getMailingLists() {
    // Request options.
    $method = 'post';
    $path = '/mailinglists/v2/all';
    $args = [
      'headers' => [
        'Content-length' => 0,
      ],
    ];

    // Get request content.
    $contents = $this->request($method, $path, $args);
    // Populate array for settings.
    $list = [];
    if (!empty($contents)) {
      foreach ($contents->Result as $result) {
        $list[$result->Id] = $result->Name;
      }
    }

    return $list;
  }

  /**
   * Get mailing list name from list id.
   */
  public function getMailingListInfo($list_id) {
    // Request options.
    $method = 'get';
    $path = '/v1/mailinglists/' . $list_id;
    $args = [
      'headers' => [
        'Content-length' => 0,
      ],
    ];

    // Get request content.
    $contents = $this->request($method, $path, $args);

    // Get result.
    $result = $contents->Result;

    return $result;
  }

  /**
   * Get subscribers from mailing list.
   *
   * @param string $id
   *   Apsis mailing list id.
   */
  public function getSubscribers($id) {
    // Request options.
    $method = 'post';
    $path = '/v1/mailinglists/' . $id . '/subscribers/all';
    $args = [
      'json' => [
        'AllDemographics' => TRUE,
      ],
    ];

    // @todo This REST method uses queueing, must figure out how to handle it.
    $contents = $this->request($method, $path, $args);

    return $contents;
  }

  /**
   * Get mailing lists by subscriber.
   */
  public function getSubscriberMailingLists($id) {
    // Request options.
    $method = 'get';
    $path = '/v1/subscribers/' . $id . '/mailinglists';

    $contents = $this->request($method, $path);

    return $contents ? $contents->Result : NULL;
  }

  /**
   * Get subscriber id from email address.
   *
   * @param string $email
   *   An email address.
   */
  public function getSubscriberIdByEmail($email) {
    // Request options.
    $method = 'post';
    $path = '/subscribers/v2/email';
    $args = [
      'json' => $email,
    ];

    // Do request.
    $contents = $this->request($method, $path, $args);

    return $contents ? $contents->Result : NULL;
  }

  /**
   * Delete subscriber.
   *
   * @param string $list_id
   *   Apsis mailing list id.
   * @param string $email
   *   Email address.
   */
  public function deleteSubscriber($list_id, $email) {
    // Get subscriber id.
    $subscriber_id = $this->getSubscriberIdByEmail($email);

    // Request options.
    $method = 'delete';
    $path = '/v1/mailinglists/' . $list_id . '/subscriptions/' . $subscriber_id;

    // Get list info for output.
    $list_info = $this->getMailingListInfo($list_id);

    // Do request.
    $contents = $this->request($method, $path);

    // Set log message.
    \Drupal::logger('apsis_mail')->info(
      t('User: @email unsubscribed from @list (@list_id).', [
        '@email' => $email,
        '@list' => $list_info->Name,
        '@list_id' => $list_id,
      ])
    );

    return $contents;

  }

  /**
   * Add subscription to mailing list.
   *
   * @param string $list_id
   *   Apsis mailing list id.
   * @param string $email
   *   Email address.
   * @param string $name
   *   Username.
   */
  public function addSubscriber($list_id, $email, $name) {
    // Request options.
    $method = 'post';
    $path = '/v1/subscribers/mailinglist/' . $list_id . '/create';
    $args = [
      'json' => [
        'Email' => $email,
        'Name' => $name,
      ],
    ];

    $list_info = $this->getMailingListInfo($list_id);

    // Do request.
    $contents = $this->request($method, $path, $args);

    \Drupal::logger('apsis_mail')->info(
      t('@name (@email) subscribed to @list (@list_id).', [
        '@name' => $name,
        '@email' => $email,
        '@list' => $list_info->Name,
        '@list_id' => $list_id,
      ])
    );

    return $contents;
  }

}
