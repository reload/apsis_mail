<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Bad request exception.
 *
 * Used if the request is badly formatted.
 */
class BadRequestException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -7, $code = 503, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
