<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Validation error.
 *
 * Used if one or more parameters in the request are invalid.
 */
class ValidationErrorException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -2, $code = 400, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
