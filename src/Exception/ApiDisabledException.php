<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * API Disabled exception.
 *
 * Used if the API service is temporarily offline for maintenance and not
 * available at the moment.
 */
class ApiDisabledException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -6, $code = 503, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
