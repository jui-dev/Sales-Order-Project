<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DataNotFoundException extends Exception
{
    protected $resource;
    protected $identifier;
    protected $httpCode;

    public function __construct(string $resource, $identifier = null, string $message = null, int $httpCode = 404)
    {
        $this->resource = $resource;
        $this->identifier = $identifier;
        $this->httpCode = $httpCode;

        $defaultMessage = $identifier 
            ? "No {$resource} found with ID {$identifier}"
            : "No {$resource} records found";

        parent::__construct($message ?? $defaultMessage, $httpCode);
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $this->getMessage(),
                'resource' => $this->resource,
                'identifier' => $this->identifier,
            ], $this->httpCode);
        }

        abort($this->httpCode, $this->getMessage());
    }
} 