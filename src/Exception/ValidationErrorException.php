<?php

namespace Drupal\apsis_mail\Exception;

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
  public static function getState()
  {
    return -2;
  }

  /**
   * {@inheritdoc}
   */
  public static function getHttpStatus()
  {
    return 400;
  }

}
