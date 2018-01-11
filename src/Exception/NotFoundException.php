<?php

namespace Drupal\apsis_mail\Exception;


use Throwable;

/**
 * Not found exception.
 *
 * Used if the URL is incorrect and no API method is found to deal with the request.
 */
class NotFoundException extends ApsisException
{

  /**
   * {@inheritdoc}
   */
  public function __construct($message, $state = -3, $code = 404, Throwable $previous = null)
  {
    parent::__construct($message, $state, $code, $previous);
  }

}
