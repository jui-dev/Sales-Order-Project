<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use App\Exceptions\DataNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class GlobalErrorHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);
            
            // Handle empty data responses for API requests
            if ($request->expectsJson() && $response instanceof JsonResponse) {
                // Skip standardization for specific endpoints that have their own response format
                if ($request->is('products/ajax/subcategories')) {
                    return $response;
                }
                
                $data = $response->getData(true);

                // Standardize *empty collection listings* only. A response that carries no
                // `data` key at all is not an empty collection - it is some other payload
                // (e.g. {"success": true, "redirect_url": ...}) and must pass through
                // untouched. Mutations never return a listing, so restrict this to GET.
                $isEmptyListing = empty($data)
                    || (is_array($data) && array_key_exists('data', $data) && empty($data['data']));

                if ($request->isMethod('GET') && $response->getStatusCode() === 200 && $isEmptyListing) {
                    $resourceName = $this->extractResourceNameFromRequest($request);
                    $standardizedResponse = [
                        'status' => 'empty',
                        'message' => "No {$resourceName} found",
                        'data' => [],
                        'total' => 0,
                        'per_page' => 0,
                        'current_page' => 1,
                        'last_page' => 1,
                    ];
                    
                    return response()->json($standardizedResponse, 200);
                }
            }
            
            return $response;
            
        } catch (DataNotFoundException $e) {
            return $this->handleDataNotFoundException($e, $request);
        } catch (NotFoundHttpException $e) {
            return $this->handleNotFoundException($e, $request);
        } catch (MethodNotAllowedHttpException $e) {
            return $this->handleMethodNotAllowedException($e, $request);
        } catch (\Exception $e) {
            return $this->handleGenericException($e, $request);
        }
    }

    /**
     * Handle DataNotFoundException
     */
    protected function handleDataNotFoundException(DataNotFoundException $e, Request $request)
    {
        $this->logException($e, 'Data not found');
        
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'error_code' => $e->getHttpCode(),
                'resource' => $e->getResource(),
                'identifier' => $e->getIdentifier(),
            ], $e->getHttpCode());
        }
        
        abort($e->getHttpCode(), $e->getMessage());
    }

    /**
     * Handle NotFoundHttpException
     */
    protected function handleNotFoundException(NotFoundHttpException $e, Request $request)
    {
        $this->logException($e, 'Route not found');
        
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'The requested resource was not found',
                'error_code' => 404,
                'path' => $request->path(),
            ], 404);
        }
        
        abort(404, 'The requested resource was not found');
    }

    /**
     * Handle MethodNotAllowedHttpException
     */
    protected function handleMethodNotAllowedException(MethodNotAllowedHttpException $e, Request $request)
    {
        $this->logException($e, 'Method not allowed');
        
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => 'The HTTP method is not allowed for this resource',
                'error_code' => 405,
                'method' => $request->method(),
                'allowed_methods' => $e->getHeaders()['Allow'] ?? [],
            ], 405);
        }
        
        abort(405, 'The HTTP method is not allowed for this resource');
    }

    /**
     * Handle generic exceptions
     */
    protected function handleGenericException(\Exception $e, Request $request)
    {
        $this->logException($e, 'Unhandled exception');
        
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
                'error_code' => 500,
                'debug_info' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ] : null,
            ], 500);
        }
        
        abort(500, 'An unexpected error occurred');
    }

    /**
     * Log exception details
     */
    protected function logException(\Exception $e, string $context): void
    {
        Log::error($context, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->id() ?? 1, // Default to user ID 1 if not authenticated
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Extract resource name from request path
     */
    protected function extractResourceNameFromRequest(Request $request): string
    {
        $path = $request->path();
        $segments = explode('/', $path);
        
        // Remove empty segments and get the last meaningful segment
        $segments = array_filter($segments);
        $resourceName = end($segments);
        
        // Convert to singular form and capitalize
        $resourceName = rtrim($resourceName, 's'); // Simple singularization
        $resourceName = ucfirst($resourceName);
        
        return $resourceName ?: 'data';
    }
} 