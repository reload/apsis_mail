<?php

namespace Drupal\apsis_mail;

use Drupal\apsis_mail\Exception\ApsisException;
use Drupal\apsis_mail\Exception\ExceptionMapper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ImmutableConfig;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Apsis mail api.
 */
class Apsis {

  /**
   * The cache backend used by Apsis.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * HTTP client used to interact with the Apsis API.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  public $config;

  /**
   * The current time as a unix timestamp.
   *
   * @var int
   */
  protected $requestTime;

  /**
   * The exception factory used to map HTTP exceptions to Apsis exceptions.
   *
   * @var ExceptionMapper
   */
  protected $exceptionMapper;

  /**
   * Apsis constructor.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   HTTP client used to interact with the Apsis API.
   * @param ImmutableConfig $config
   *   Configuration object.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend used by Apsis.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\apsis_mail\Exception\ExceptionMapper
   *   The exception factory.
   */
  public function __construct(
    ClientInterface $client,
    ImmutableConfig $config,
    CacheBackendInterface $cache,
    TimeInterface $time,
    ExceptionMapper $exceptionMapper
  ) {
    $this->client = $client;
    $this->config = $config;
    $this->cache = $cache;
    $this->requestTime = $time->getRequestTime();
    $this->exceptionMapper = $exceptionMapper;
  }

  /**
   * Build url for the REST call.
   *
   * @param string $method
   *   POST or GET.
   * @param string $path
   *   Request path.
   * @param array $args
   *   Request header arguments.
   *
   * @return object
   *   Object with response data.
   *
   * @throws ApsisException
   */
  protected function request($method, $path, array $args = []) {
    // Set options variables.
    $protocol = !empty($this->config->get('api_ssl')) ? 'https://' : 'http://';
    $key = !empty(\Drupal::state()->get('apsis_mail.api_key')) ? \Drupal::state()->get('apsis_mail.api_key') . ':@' : '';
    $url = !empty($this->config->get('api_url')) ? $this->config->get('api_url') : '';
    $port = !empty($this->config->get('api_port')) && $this->config->get('api_ssl') ? ':' . $this->config->get('api_port') : '';
    $args['headers']['Authorization'] = 'Basic ' . base64_encode($key);
    $args['headers']['Content-type'] = 'application/json';
    $args['headers']['Accept'] = 'application/json';

    // Build request url.
    $request_url = $protocol . $url . $port . $path;

    if ($key && $url) {
      // Try request.
      try {
        // Do http request.
        /* @var \Psr\Http\Message\ResponseInterface $response */
        $response = $this->client->{$method}($request_url, $args);

        // Return response body.
        $body = $response->getBody();
        return json_decode($body->getContents());
      }
      catch (RequestException $e) {
        // Set db log message.
        \Drupal::logger('apsis_mail')->error($e->getMessage());
        $exception = $this->exceptionMapper->map($e);
        throw $exception;
      }
    }
  }

  /**
   * Perform a cachable request against Apsis.
   *
   * If a cached response for the request already exists then that is used.
   * If not then the an actual request will be performed and the response
   * cached.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The endpoint path to use for the request.
   * @param array $args
   *   The HTTP request arguments.
   *
   * @return mixed
   *   The response from Apsis.
   *
   * @throws ApsisException
   */
  protected function cachableRequest($method, $path, array $args = []) {
    $cid = 'apsis_mail:api:' . hash('sha256', var_export(func_get_args(), TRUE));

    // First check static cache, to avoid unnecessary queries to cache backend.
    $response = drupal_static($cid, NULL);
    if ($response === NULL) {
      // Then check the cache backend.
      $cache = $this->cache->get($cid);
      if ($cache !== FALSE) {
        $response = $cache->data;
      }
    }

    // If we do not have a cached response then perform the actual request.
    if ($response === NULL) {
      $response = $this->request($method, $path, $args);

      // Make sure that static cache is set.
      $static_cache = &drupal_static($cid, NULL);
      // Handle bad requests, we allow using stale cache if possible.
      if ($response === FALSE) {
        $cache = $this->cache->get($cid, TRUE);
        if ($cache !== FALSE) {
          $response = $cache->data;
        }
      }
      else {
        $static_cache = $response;
        // Cache results for 30 seconds.
        $this->cache->set($cid, $response, $this->requestTime + 30);
      }
    }

    return $response;
  }

  /**
   * Get single mailing list.
   *
   * @return array
   *   Array containing allowed mailing lists.
   *
   * @throws ApsisException
   */
  public function getAllowedMailingLists() {
    // Get all lists.
    $mailing_lists = $this->getMailingLists();
    // Get allowed list settings.
    $allowed_lists = \Drupal::state()->get('apsis_mail.mailing_lists');

    // Return list with allowed list items.
    if (!empty($allowed_lists)) {
      return array_intersect_key($mailing_lists, array_flip($allowed_lists));
    }
  }

  /**
   * Fetch mailing lists.
   *
   * @return array
   *   Array containing all mailing lists.
   *
   * @throws ApsisException
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
    $contents = $this->cachableRequest($method, $path, $args);
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
   *
   * @throws ApsisException
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
    $contents = $this->cachableRequest($method, $path, $args);

    // Get result.
    $result = $contents->Result;

    return $result;
  }

  /**
   * Get subscribers from mailing list.
   *
   * @param string $id
   *   Apsis mailing list id.
   *
   * @throws ApsisException
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
    $contents = $this->cachableRequest($method, $path, $args);

    return $contents;
  }

  /**
   * Get mailing lists by subscriber.
   *
   * @param int $subscriber_id
   *   The id of the subscriber for which to retrieve mailing lists which the user subscribes to.
   *
   * @return object[]
   *   Array of objects of mailing list data from the subscribers subscriptions.
   *
   * @throws ApsisException
   */
  public function getSubscriberMailingLists($subscriber_id) {
    // Request options.
    $method = 'get';
    $path = '/v1/subscribers/' . $subscriber_id . '/mailinglists';

    $contents = $this->request($method, $path);

    return (!empty($contents->Result->Mailinglists)) ? $contents->Result->Mailinglists : [];
  }

  /**
   * Get subscriber id from email address.
   *
   * @param string $email
   *   An email address.
   *
   * @throws ApsisException
   */
  public function getSubscriberIdByEmail($email) {
    // Request options.
    $method = 'post';
    $path = '/subscribers/v2/email';
    $args = [
      'json' => $email,
    ];

    // Do request.
    $contents = $this->cachableRequest($method, $path, $args);

    return $contents->Result;
  }

  /**
   * Delete subscriber.
   *
   * @param string $list_id
   *   Apsis mailing list id.
   * @param string $email
   *   Email address.
   *
   * @throws ApsisException
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
   * @param array $demographic_data
   *   Demographic data.
   *
   * @throws ApsisException
   */
  public function addSubscriber($list_id, $email, $name, array $demographic_data = []) {
    // Request options.
    $method = 'post';
    $path = '/v1/subscribers/mailinglist/' . $list_id . '/create?updateIfExists=true';
    $args = [
      'json' => [
        'Email' => $email,
        'Name' => $name,
        'DemDataFields' => $demographic_data,
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

  /**
   * Get subscriber data.
   *
   * @param string $email
   *   An email address.
   *
   * @throws ApsisException
   */
  public function getSubscriberInfoByEmail($email) {
    // Get subscriber id.
    $id = $this->getSubscriberIdByEmail($email);

    // Request options.
    $method = 'get';
    $path = '/v1/subscribers/id/' . $id;

    // Do request.
    $contents = $this->request($method, $path);

    return $contents ? $contents->Result : NULL;
  }

  /**
   * Get a list of allowed demographic data.
   *
   * @return array
   *   Allowed demographic data.
   *
   * @throws ApsisException
   */
  public function getAllowedDemographicData() {
    // Get all lists.
    $all_demographics = $this->getDemographicData();
    // Get config.
    $allowed_demographics = \Drupal::state()->get('apsis_mail.demographic_data', []);

    // Get allowed list settings.
    $allowed_demographic_data = [];
    foreach ($allowed_demographics as $key => $demographic) {
      if ($demographic['available']) {
        // Set value as key for alternatives.
        $alternatives = [];
        foreach ($all_demographics[$key]['alternatives'] as $alternative) {
          $alternatives[$alternative] = $alternative;
        }
        $allowed_demographic_data[$key] = [
          'index' => $all_demographics[$key]['index'],
          'alternatives' => $alternatives,
          'required' => boolval($demographic['required']),
        ];
      }
    }
    return $allowed_demographic_data;
  }

  /**
   * Returns demographic data fields.
   *
   * @return array
   *   The demographics from the APSIS account.
   *
   * @throws ApsisException
   */
  public function getDemographicData() {
    // Request options.
    $method = 'get';
    $path = '/accounts/v2/demographics';
    $args = [
      'headers' => [
        'Content-length' => 0,
      ],
    ];
    // Get request content.
    $contents = $this->cachableRequest($method, $path, $args);
    // Populate array for demographics.
    $demographics = [];
    if (!empty($contents)) {
      foreach ($contents->Result->Demographics as $result) {
        $alternatives = [];
        foreach ($result->Alternatives as $alternative) {
          $alternatives[$alternative] = $alternative;
        }
        $demographics[$result->Key] = [
          'index' => $result->Index,
          'alternatives' => $alternatives,
        ];
      }
    }
    return $demographics;
  }

  /**
   * Formats form element based on options.
   *
   * @param array $alternatives
   *   Defined values from api.
   * @param string $label
   *   Demographic label.
   * @param bool $required
   *   Form element required.
   * @param bool $checkbox
   *   If it is a checkbox or not.
   * @param bool $return_value
   *   If it has a return value.
   *
   * @return array
   *   A drupal form element.
   */
  public function demographicFormElement(array $alternatives, $label, $required, $checkbox = FALSE, $return_value = FALSE) {
    $element = [];

    if (empty($alternatives)) {
      // If there's no alternatives in Apsis, render as a textfield.
      $element = [
        '#title' => $label,
        '#type' => 'textfield',
        '#required' => $required,
      ];
    }
    else {
      // If there's more multiple alternatives, render as a select or checkbox.
      $element = [
        '#title' => $label,
        '#type' => ($checkbox) ? 'checkbox' : 'select',
        '#options' => $alternatives,
        '#required' => $required,
        '#return_value' => ($checkbox) ? $return_value : '',
        '#validated' => TRUE,
      ];
    }

    return $element;
  }

}
