<?php

namespace Drupal\contentpool_client\Exception;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A custom exception for all replication related errors.
 */
class ReplicationException extends \Exception {

  use MessengerTrait;

  /**
   * The message with replacement patterns.
   *
   * @var string
   */
  protected $messageTemplate;

  /**
   * The message replacement variables.
   *
   * @var array
   */
  protected $messageVariables = [];

  /**
   * Gets the message template.
   */
  public function getMessageTemplate() {
    return $this->messageTemplate;
  }

  /**
   * Gets the message replacement variables.
   *
   * @return string[]
   *   Keyed by replacement string.
   */
  public function getMessageVariables() {
    return $this->messageVariables;
  }

  /**
   * ReplicationException constructor.
   *
   * @param string $message
   *   The message, with message replacement variables.
   * @param array $variables
   *   The message replacement variables.
   * @param int $code
   *   See parent.
   * @param \Throwable|null $previous
   *   See parent.
   */
  public function __construct(string $message = "", array $variables = [], int $code = 0, \Throwable $previous = NULL) {
    $this->messageTemplate = $message;
    $this->messageVariables = $variables;
    $message = (new FormattableMarkup($message, $variables))->__toString();
    parent::__construct($message, $code, $previous);
  }

  /**
   * Prints the exception as error message.
   */
  public function printError() {
    // While it's not best practice, we deliberately do pass a variable to
    // the translation system here as it always us to benefit from proper error
    // handling using exceptions.
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    $this->messenger()->addError(new TranslatableMarkup($this->messageTemplate, $this->messageVariables));
  }

}
