<?php

namespace App\Traits;

use App\Exceptions\DataNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

trait HasErrorHandling
{
    /**
     * A sort field and direction the query builder will actually accept.
     *
     * Every listing offers a getSortOptions() whitelist, and only
     * PurchaseOrderService ever consulted it. Everywhere else the request went
     * straight into orderBy(): an unknown column raises a QueryException and a
     * direction other than asc/desc an InvalidArgumentException, both of which
     * getPaginatedOrEmpty() catches into an empty paginator - so ?sort=nonsense
     * answered "no records found" rather than showing the records.
     *
     * @param  array<string,string>  $allowed  getSortOptions(), keyed by column
     * @return array{0:string,1:string}
     */
    protected function sortFrom(array $filters, array $allowed, string $default = 'id', string $defaultDirection = 'desc'): array
    {
        $field = $filters['sort'] ?? $default;

        if (! array_key_exists($field, $allowed)) {
            $field = $default;
        }

        $direction = strtolower((string) ($filters['direction'] ?? $defaultDirection));

        return [$field, in_array($direction, ['asc', 'desc'], true) ? $direction : $defaultDirection];
    }

    /**
     * Find a model by ID or throw a descriptive exception
     */
    protected function findOrFail(string $modelClass, int $id, string $resourceName = null): Model
    {
        $resourceName = $resourceName ?? class_basename($modelClass);
        
        $model = $modelClass::find($id);
        
        if (!$model) {
            $this->logMissingData($resourceName, $id);
            throw new DataNotFoundException($resourceName, $id);
        }
        
        return $model;
    }

    /**
     * Get a collection or return empty collection with logging
     */
    protected function getCollectionOrEmpty(string $modelClass, string $resourceName = null): Collection
    {
        $resourceName = $resourceName ?? class_basename($modelClass);
        
        try {
            $collection = $modelClass::all();
            
            if ($collection->isEmpty()) {
                $this->logEmptyCollection($resourceName);
            }
            
            return $collection;
        } catch (\Exception $e) {
            $this->logError($resourceName, $e->getMessage());
            return collect();
        }
    }

    /**
     * Get a filtered collection with pagination or return empty
     */
    protected function getFilteredCollectionOrEmpty(callable $queryCallback, string $resourceName, array $filters = []): Collection
    {
        try {
            $collection = $queryCallback();
            
            if ($collection->isEmpty()) {
                $this->logEmptyFilteredCollection($resourceName, $filters);
            }
            
            return $collection;
        } catch (\Exception $e) {
            $this->logError($resourceName, $e->getMessage(), $filters);
            return collect();
        }
    }

    /**
     * Get paginated results or return empty paginator with proper structure
     */
    protected function getPaginatedOrEmpty(callable $queryCallback, string $resourceName, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        try {
            $paginator = $queryCallback();
            
            if ($paginator->isEmpty()) {
                $this->logEmptyPaginatedCollection($resourceName, $filters);
            }
            
            return $paginator;
        } catch (\Exception $e) {
            $this->logError($resourceName, $e->getMessage(), $filters);
            // Return empty paginator with proper structure
            return $this->createEmptyPaginator($resourceName, $perPage);
        }
    }

    /**
     * Create an empty paginator with proper structure for the given model
     */
    protected function createEmptyPaginator(string $modelClass, int $perPage = 20): LengthAwarePaginator
    {
        $model = new $modelClass;
        $query = $model->newQuery();
        
        return $query->paginate($perPage);
    }

    /**
     * Standardized empty data response for API endpoints
     */
    protected function getEmptyDataResponse(string $resourceName, string $message = null): array
    {
        return [
            'status' => 'empty',
            'message' => $message ?? "No {$resourceName} found",
            'data' => [],
            'total' => 0,
            'per_page' => 0,
            'current_page' => 1,
            'last_page' => 1,
        ];
    }

    /**
     * Standardized success response for API endpoints
     */
    protected function getSuccessResponse($data, string $message = null): array
    {
        $response = [
            'status' => 'success',
            'message' => $message ?? 'Data retrieved successfully',
            'data' => $data,
        ];

        // Add pagination info if it's a paginator
        if ($data instanceof LengthAwarePaginator) {
            $response['total'] = $data->total();
            $response['per_page'] = $data->perPage();
            $response['current_page'] = $data->currentPage();
            $response['last_page'] = $data->lastPage();
        }

        return $response;
    }

    /**
     * Standardized error response for API endpoints
     */
    protected function getErrorResponse(string $message, int $statusCode = 500, array $context = []): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'error_code' => $statusCode,
            'context' => $context,
        ];
    }

    /**
     * Log missing data scenarios for debugging
     */
    protected function logMissingData(string $resource, $identifier = null): void
    {
        Log::info("Data not found", [
            'resource' => $resource,
            'identifier' => $identifier,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Log empty collection scenarios
     */
    protected function logEmptyCollection(string $resource): void
    {
        Log::info("Empty collection", [
            'resource' => $resource,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Log empty filtered collection scenarios
     */
    protected function logEmptyFilteredCollection(string $resource, array $filters = []): void
    {
        Log::info("Empty filtered collection", [
            'resource' => $resource,
            'filters' => $filters,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Log empty paginated collection scenarios
     */
    protected function logEmptyPaginatedCollection(string $resource, array $filters = []): void
    {
        Log::info("Empty paginated collection", [
            'resource' => $resource,
            'filters' => $filters,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Log general errors
     */
    protected function logError(string $resource, string $error, array $context = []): void
    {
        Log::error("Service error", [
            'resource' => $resource,
            'error' => $error,
            'context' => $context,
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    /**
     * Handle service operations with proper error handling
     */
    protected function handleServiceOperation(callable $operation, string $resourceName, $identifier = null)
    {
        try {
            return $operation();
        } catch (DataNotFoundException $e) {
            // Re-throw DataNotFoundException as-is
            throw $e;
        } catch (\InvalidArgumentException $e) {
            // Validation failures describe what the caller did wrong, so the
            // message is worth showing. Masking these as "try again later"
            // hides the actual reason the operation was refused.
            throw $e;
        } catch (\Exception $e) {
            // Log the actual error details for debugging
            Log::error("Service operation failed", [
                'resource' => $resourceName,
                'identifier' => $identifier,
                'actual_error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->logError($resourceName, $e->getMessage(), ['identifier' => $identifier]);
            throw new \Exception("Unable to process {$resourceName}. Please try again later.");
        }
    }

    /**
     * Handle API service operations with standardized responses
     */
    protected function handleApiServiceOperation(callable $operation, string $resourceName, string $successMessage = null)
    {
        try {
            $data = $operation();
            
            if (empty($data) || ($data instanceof Collection && $data->isEmpty()) || ($data instanceof LengthAwarePaginator && $data->isEmpty())) {
                return $this->getEmptyDataResponse($resourceName);
            }
            
            return $this->getSuccessResponse($data, $successMessage);
        } catch (DataNotFoundException $e) {
            return $this->getErrorResponse($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            $this->logError($resourceName, $e->getMessage());
            return $this->getErrorResponse("Unable to process {$resourceName}. Please try again later.");
        }
    }
} 