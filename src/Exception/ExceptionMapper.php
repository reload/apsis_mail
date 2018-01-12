<?php

namespace Drupal\apsis_mail\Exception;


use GuzzleHttp\Exception\RequestException;

/**
 * Mapper for transforming generic HTTP exceptions to specific ApsisExceptions.
 *
 * Officially APSIS uses a number of different types of errors. This set is
 * larger than the number of used HTTP status codes. To distinguish between
 * these APSIS also returns a custom error code referred to as state. Even
 * between these there are different types of errors which can only be
 * distinguished by inspecting the custom error message.
 *
 * This mapper is responsible for managing this process of determining the right
 * custom exception type based on the generic HTTP exception.
 *
 * @see http://se.apidoc.anpdm.com/Help/GettingStarted/Getting%20started
 */
class ExceptionMapper
{

  /**
   * The exception classes which can be mapped to.
   *
   * @var \ReflectionClass[]
   */
  protected $exceptionClasses = [];

  /**
   * ExceptionMapper constructor.
   */
  public function __construct() {
  }

  /**
   * Register an exception which can be mapped to from this mapper.
   *
   * Exceptions must be a subclass of the ApsisException.
   *
   * @param string $className
   *   The fully qualified exception class name.
   */
  public function registerException($className) {
    $class = new \ReflectionClass($className);
    if (!$class->isSubclassOf(ApsisException::class)) {
      throw new \InvalidArgumentException(
        sprintf('%s is not a valid %s', $class->getName(), ApsisException::class)
      );
    }
    $this->exceptionClasses[] = $class;
  }

  /**
   * Extract data from an Apsis request exception.
   *
   * @param RequestException $exception
   *  Exception thrown as a result of calling the APSIS API.
   *
   * @return array
   *   An array containing two entries:
   *   - APSIS custom exception code
   *   - Exception message
   */
  protected static function getExceptionData(RequestException $exception) {
    $response = $exception->getResponse();
    if ($response) {
      $body = $response->getBody();
      $exceptionData = json_decode($body->getContents());
      $body->rewind();
      return [$exceptionData->Code, $exceptionData->Message];
    } else {
      return [NULL, NULL];
    }
  }

  /**
   * Get ApsisException classes which use the same HTTP and APSIS error codes as the thrown exception.
   *
   * @param RequestException $httpException
   *   The exception to map.
   *
   * @return array|\ReflectionClass[]
   *   Matching exception classes based on HTTP and APSIS error codes.
   */
  protected function codeStateMatchStrategy(RequestException $httpException) {
    /* @var \ReflectionClass[] $matchingExceptionClasses */
    return array_filter($this->exceptionClasses, function(\ReflectionClass $class) use ($httpException) {
      list($code, $message) = self::getExceptionData($httpException);
      return $class->getMethod('getHttpStatus')->invoke(NULL) == $httpException->getCode() &&
        $class->getMethod('getState')->invoke(NULL) == $code;
    });
  }

  /**
   * Get ApsisException classes where the associared pattern matches message from the thrown exception.
   *
   * @param RequestException $httpException
   *   The exception to map.
   *
   * @return array|\ReflectionClass[]
   *   Matching exception classes based on message patterns.
   */
  protected function messageMatchStrategy(RequestException $httpException) {
    /* @var \ReflectionClass[] $matchingExceptionClasses */
    return array_filter($this->exceptionClasses, function(\ReflectionClass $class) use ($httpException) {
      list($code, $message) = self::getExceptionData($httpException);
      $matchPhrase = $class->getMethod('getMatchPhrase')->invoke(NULL);
      return ($matchPhrase) ? preg_match($matchPhrase, $message) : FALSE;
    });
  }

  /**
   * Map a generic exception thrown as a result of calling the APSIS API to a custom APSIS exception.
   *
   * This should be a subclass of ApsisException which has been registered in this mapper. Alternately it can be or a
   * generic ApsisException instance if it cannot be mapped to something more specific.
   *
   * @param RequestException $httpException
   *   The generic exception to map.
   *
   * @return ApsisException
   *   The corresponding custom APSIS exception instance.
   */
  public function map(RequestException $httpException) {
    // Assemble a prioritized list of mapping strategies. Each may return an array of matching exception classes.
    $strategies = [
      function ($exception) {
        return $this->messageMatchStrategy($exception);
      },
      function ($exception) {
        return $this->codeStateMatchStrategy($exception);
      },
      function ($exception) {
        return [new \ReflectionClass(ApsisException::class)];
      }
    ];

    // Execute all strategies. The first one will be the target of the mapping.
    $matchingExceptionClasses = [];
    foreach ($strategies as $strategy) {
      $matchingExceptionClasses = array_merge($matchingExceptionClasses, $strategy($httpException));
    }
    $exceptionClass = array_shift($matchingExceptionClasses);

    // Finally create the mapped exception instance.
    list($code, $message) = self::getExceptionData($httpException);
    return $exceptionClass->newInstance($message, $httpException->getCode(), $httpException);
  }

  /**
   * Static factory method.
   *
   * @param string[] $classNames
   *   Fully qualified classnames to exception classes which can be mapped to.
   *
   * @return ExceptionMapper
   *   An ExceptionMapper instance with the classnames registered properly.
   */
  public static function factory(array $classNames) {
    $mapper = new ExceptionMapper();
    foreach ($classNames as $className) {
      $mapper->registerException($className);
    }
    return $mapper;
  }

}
