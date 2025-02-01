<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Send a success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function successResponse($data = null, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Send an error response
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorResponse($message = 'Error', $statusCode = 400, $errors = null, $overError = false)
    {
        try {
            $errors = convertErrorArrayToString($errors);
        } catch (\Throwable $th) {
        }

        return response()->json([
            'status' => false,
            'data' => null,
            'message' => $message,
            'errors' => $errors,
            'is_order_limit_over' => $overError
        ], $statusCode);
    }

    /**
     * Send a validation error response
     *
     * @param mixed $errors
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function validationErrorResponse($errors, $message = 'Validation Failed', $statusCode = 422)
    {
        return $this->errorResponse($message, $statusCode, $errors);
    }

    /**
     * Handle exceptions and send a standardized error response
     *
     * @param \Throwable $exception
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function exceptionResponse(\Throwable $exception, $statusCode = 500)
    {
        return response()->json([
            'status' => false,
            'message' => $exception->getMessage(),
            'trace' => config('app.debug') ? $exception->getTrace() : null,
        ], $statusCode);
    }
}
