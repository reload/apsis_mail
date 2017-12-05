<?php

namespace Drupal\apsis_mail\Plugin\QueueWorker;
use Drupal\apsis_mail\Apsis;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add subscribers to Apsis.
 *
 * @QueueWorker(
 *   id = "apsis_mail_add_subscriber",
 *   title = @Translation("Add subscribers to Apsis"),
 *   cron = {"time" = 60}
 * )
 */
class AddSubscriber extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\apsis_mail\Apsis
   */
  protected $apsis;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Apsis $apsis
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->apsis = $apsis;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('apsis')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data)
  {
    list($email, $list_id, $name, $demographic_data) = $data;
    $result = $this->apsis->addSubscriber($email, $list_id, $name, $demographic_data);
    // If the request fails then requeue for later processing. This could for example be due to a request timeout. We
    // currently have no way to distinguish between different types of error so we always retry.
    if ($result === FALSE) {
      throw new RequeueException(
        sprintf(
          'Unable to add subscriber (%s, %s) to list %s with data %s',
          $email,
          $name,
          $list_id,
          var_export($demographic_data, true)
        )
      );
    }
  }
}
