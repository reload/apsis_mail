<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Unauthorized exception.
 *
 * Represents lack of access rights to perform the API request, for example
 * using an invalid API key.
 */
class UnauthorizedException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -4, $code = 403, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
