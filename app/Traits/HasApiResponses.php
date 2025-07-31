<?php

namespace App\Traits;

use App\Exceptions\DataNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

trait HasApiResponses
{
    /**
     * Standardized success response for API endpoints
     */
    protected function successResponse($data, string $message = null, int $statusCode = 200): JsonResponse
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

        return response()->json($response, $statusCode);
    }

    /**
     * Standardized empty data response for API endpoints
     */
    protected function emptyResponse(string $resourceName, string $message = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'status' => 'empty',
            'message' => $message ?? "No {$resourceName} found",
            'data' => [],
            'total' => 0,
            'per_page' => 0,
            'current_page' => 1,
            'last_page' => 1,
        ], $statusCode);
    }

    /**
     * Standardized error response for API endpoints
     */
    protected function errorResponse(string $message, int $statusCode = 500, array $context = []): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
            'error_code' => $statusCode,
        ];

        if (!empty($context)) {
            $response['context'] = $context;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle service operations with standardized API responses
     */
    protected function handleApiOperation(callable $operation, string $resourceName, string $successMessage = null)
    {
        try {
            $data = $operation();
            
            if (empty($data) || ($data instanceof Collection && $data->isEmpty()) || ($data instanceof LengthAwarePaginator && $data->isEmpty())) {
                return $this->emptyResponse($resourceName);
            }
            
            return $this->successResponse($data, $successMessage);
        } catch (DataNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            \Log::error("API Error in {$resourceName}: " . $e->getMessage(), [
                'resource' => $resourceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
            
            return $this->errorResponse("Unable to process {$resourceName}. Please try again later.");
        }
    }

    /**
     * Handle paginated API operations
     */
    protected function handlePaginatedApiOperation(callable $operation, string $resourceName, string $successMessage = null)
    {
        try {
            $data = $operation();
            
            if ($data instanceof LengthAwarePaginator && $data->isEmpty()) {
                return $this->emptyResponse($resourceName);
            }
            
            return $this->successResponse($data, $successMessage);
        } catch (DataNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            \Log::error("API Error in {$resourceName}: " . $e->getMessage(), [
                'resource' => $resourceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
            
            return $this->errorResponse("Unable to process {$resourceName}. Please try again later.");
        }
    }

    /**
     * Handle single item API operations
     */
    protected function handleSingleItemApiOperation(callable $operation, string $resourceName, string $successMessage = null)
    {
        try {
            $data = $operation();
            
            if (empty($data)) {
                return $this->emptyResponse($resourceName);
            }
            
            return $this->successResponse($data, $successMessage);
        } catch (DataNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            \Log::error("API Error in {$resourceName}: " . $e->getMessage(), [
                'resource' => $resourceName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
            
            return $this->errorResponse("Unable to process {$resourceName}. Please try again later.");
        }
    }

    /**
     * Validate request and return error response if validation fails
     */
    protected function validateRequestOrFail(Request $request, array $rules, array $messages = []): ?JsonResponse
    {
        $validator = \Validator::make($request->all(), $rules, $messages);
        
        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, [
                'errors' => $validator->errors()->toArray()
            ]);
        }
        
        return null;
    }
} 