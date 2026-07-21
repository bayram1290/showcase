<?php

namespace App\Exceptions;

use Exception;

class ReceivablesException extends Exception
{
    private const QUERY_FAILED = 'QUERY_FAILED';
    private const LATE_FEE_WAIVER_FAILED = 'LATE_FEE_WAIVER_FAILED';
    private const REMINDER_SEND_FAILED = 'REMINDER_SEND_FAILED';
    private const DEFAULTATION_FAILED = 'DEFAULTATION_FAILED';
    private const RESTORATION_FAILED = 'RESTORATION_FAILED';
    private const NEGOTIATION_FAILED = 'NEGOTIATION_FAILED';

    private string $errorType;
    private array $context;

    /**
     * Create a new LoanOperationException instance
     *
     * @param string $message The error message
     * @param string $errorType The type of error that occurred
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return void
     */
    public function __construct(
        string $message,
        string $errorType = self::QUERY_FAILED,
        array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorType = $errorType;
        $this->context = $context;
    }

    /**
     * Create a new LoanOperationException instance for a query failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function queryFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::QUERY_FAILED, $context, $previous);
    }

     /**
     * Create a new LoanOperationException instance for a late fee waiver failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function lateFeeWaiverFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::LATE_FEE_WAIVER_FAILED, $context, $previous);
    }

    /**
     * Create a new LoanOperationException instance for a reminder send failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function reminderSendFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::REMINDER_SEND_FAILED, $context, $previous);
    }

    /**
     * Create a new LoanOperationException instance for a defaultation failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function defaultationFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::DEFAULTATION_FAILED, $context, $previous);
    }

    /**
     * Create a new LoanOperationException instance for a restoration failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function restorationFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::RESTORATION_FAILED, $context, $previous);
    }

    /**
     * Create a new LoanOperationException instance for a negotiation failure
     *
     * @param string $message The error message
     * @param array $context Additional context information for the error
     * @param ?Exception $previous The previous exception that caused this exception
     * @return self The new LoanOperationException instance
     */
    public static function negotiationFailed(string $message, array $context = [], ?Exception $previous = null): self
    {
        return new self($message, self::NEGOTIATION_FAILED, $context, $previous);
    }

    /**
     * Get the type of error that occurred
     *
     * @return string The type of error
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Get additional context information for the error
     *
     * @return array The context information
     */
    public function getContext(): array
    {
        return $this->context;
    }
}