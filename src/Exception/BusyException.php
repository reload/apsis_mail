<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Busy exception
 *
 * Used if the API server is currently not available.
 */
class BusyException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -5, $code = 503, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
