<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Internal Server Error.
 *
 * Used if there was an issue with the API server. You should contact APSIS
 * support with the request details to get this resolved.
 */
class InternalServerErrorException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -1, $code = 500, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
