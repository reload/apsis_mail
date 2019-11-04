<?php

namespace Drupal\Tests\apsis_mail\Unit;

use Drupal\apsis_mail\Exception\ApsisException;
use Drupal\apsis_mail\Exception\ExceptionMapper;
use Drupal\apsis_mail\Exception\InternalServerErrorException;
use Drupal\apsis_mail\Exception\OptOutSubscriberException;
use Drupal\apsis_mail\Exception\ValidationErrorException;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Test that the ExceptionMapper will map HTTP exceptions to APSIS exceptions.
 */
class ExceptionMapperTest extends UnitTestCase {

  /**
   * Test that an unrecognized exception is mapped to the generic exception.
   */
  public function testGenericMapping() {
    $httpException = $this->generateException();

    $exceptionMapper = new ExceptionMapper();
    $exceptionMapper->registerException(InternalServerErrorException::class);
    $apsisException = $exceptionMapper->map($httpException);
    $this->assertEquals(ApsisException::class, get_class($apsisException));
  }

  /**
   * Test that exceptions are mapped according to state and status.
   */
  public function testStateStatusMapping() {
    $httpException = $this->generateException(
      InternalServerErrorException::getHttpStatus(),
      InternalServerErrorException::getState()
    );

    $exceptionMapper = new ExceptionMapper();
    // Internal server error and opt out subscriber use the same status and
    // error code. We add both to ensure that the correct one is mapped here.
    $exceptionMapper->registerException(InternalServerErrorException::class);
    $exceptionMapper->registerException(OptOutSubscriberException::class);
    $apsisException = $exceptionMapper->map($httpException);
    $this->assertEquals(InternalServerErrorException::class, get_class($apsisException));
  }

  /**
   * Test that exceptions are mapped according to state and status.
   */
  public function testMatchPhraseMapping() {
    $httpException = $this->generateException(
      OptOutSubscriberException::getHttpStatus(),
      OptOutSubscriberException::getState(),
      'Subscriber with e-mail foo@bar.com exists on the Opt-out List.'
    );

    $exceptionMapper = new ExceptionMapper();
    // Internal server error and opt out subscriber use the same status and
    // error code. We add both to ensure that the correct one is mapped here.
    $exceptionMapper->registerException(ValidationErrorException::class);
    $exceptionMapper->registerException(OptOutSubscriberException::class);
    $apsisException = $exceptionMapper->map($httpException);
    $this->assertEquals(OptOutSubscriberException::class, get_class($apsisException));
  }

  /**
   * Generate an HTTP exception for mapping.
   *
   * @param int $httpStatus
   *   The HTTP status code use.
   * @param int $apsisCode
   *   The APSIS error code to contain within the exception.
   * @param int $apsisMessage
   *   The APSIS error message to contain within the exception.
   *
   * @return \GuzzleHttp\Exception\RequestException
   *   The resulting exception.
   */
  public function generateException($httpStatus = 0, $apsisCode = 0, $apsisMessage = 'Some error occcured') {
    $httpException = new RequestException(
      'Exception message',
      new Request('GET', 'http://foo.bar'),
      new Response(
        $httpStatus,
        [],
        json_encode([
          'Code' => $apsisCode,
          'Message' => $apsisMessage,
        ])
      )
    );
    return $httpException;
  }

}
