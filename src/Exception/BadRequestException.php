<?php

namespace Drupal\apsis_mail\Exception;

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
  public static function getState()
  {
    return -7;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 503;
  }

}
