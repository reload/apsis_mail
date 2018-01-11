<?php

namespace Drupal\apsis_mail\Plugin\QueueWorker;
use Drupal\apsis_mail\Apsis;
use Drupal\apsis_mail\Exception\ApiDisabledException;
use Drupal\apsis_mail\Exception\ApsisException;
use Drupal\apsis_mail\Exception\BusyException;
use Drupal\apsis_mail\Exception\OptOutSubscriberException;
use Drupal\apsis_mail\Exception\UnauthorizedException;
use Drupal\apsis_mail\Exception\ValidationErrorException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
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
  use LoggerAwareTrait;

  /**
   * @var \Drupal\apsis_mail\Apsis
   */
  protected $apsis;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerInterface $logger,
    Apsis $apsis
  )
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->apsis = $apsis;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $loggerFactory = $container->get('logger.factory');

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $loggerFactory->get('apsis_mail'),
      $container->get('apsis')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data)
  {
    list($email, $list_id, $name, $demographic_data) = $data;
    try {
      $this->apsis->addSubscriber($email, $list_id, $name, $demographic_data);
    } catch (OptOutSubscriberException $e) {
      // User on opt-out list. Log for good measure and move on.
      $this->logger->notice($e->getMessage());
    } catch (ValidationErrorException $e) {
      // Invalid data. Nothing really we can do here. Log and more on.
      $this->logger->error(sprintf('Unable to subscribe email %s to list %s: %s', $email, $list_id, $e->getMessage()));
    } catch (ApiDisabledException $e) {
      throw new SuspendQueueException($e);
    } catch (BusyException $e) {
      throw new SuspendQueueException($e);
    } catch (UnauthorizedException $e) {
      throw new SuspendQueueException($e);
    }
  }
}
