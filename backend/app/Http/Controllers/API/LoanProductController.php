<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanProductRequest;
use App\Http\Requests\UpdateLoanProductRequest;
use App\Models\LoanProduct;
use App\Services\LoanProductService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LoanProductController extends Controller
{
    protected LoanProductService $service;

    /**
     * Constructs a new instance of the LoanProductController.
     *
     * @param LoanProductService $service The service responsible for performing operations on loan products.
     */
    public function __construct(
        LoanProductService $service
    ) {
        $this->service = $service;
    }

    /**
     * List of loan products filtered by the request parameters, paginated by default.
     *
     * Parameters accepted are:
     *   type: The type of the loan product (personal, mortgage, etc.)
     *   is_active: Whether the loan product is active or not
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->service->list($request, request()->user());

        return ApiResponse::success($products, 'LIST_LOAN_PRODUCTS_SUCCESS', Response::HTTP_OK);
    }

    /**
     * Retrieves a loan product by product ID.
     *
     * @param LoanProduct $product The loan product to retrieve.
     *
     * @return JsonResponse The loan product.
     *
     * @throws \Exception
     */
    public function show(LoanProduct $product): JsonResponse
    {
        try {
            $product = $this->service->show($product, request()->user());

            return ApiResponse::success($product, 'SHOW_LOAN_PRODUCT_SUCCESS', Response::HTTP_OK);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'SHOW_LOAN_PRODUCT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * "Moderator" route only
     * Creates a new loan product.
     *
     * @param StoreLoanProductRequest $request
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function store(StoreLoanProductRequest $request): JsonResponse
    {
        try {
            $product = $this->service->store($request->validated());

            return ApiResponse::success($product, 'CREATE_LOAN_PRODUCT_SUCCESS', Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'CREATE_LOAN_PRODUCT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * "Moderator" route only
     * Updates a loan product.
     *
     * @param UpdateLoanProductRequest $request The request containing the updated loan product data.
     * @param LoanProduct $loanProduct The loan product to update.
     *
     * @return JsonResponse The updated loan product.
     *
     * @throws \Exception
     */
    public function update(UpdateLoanProductRequest $request, LoanProduct $loanProduct): JsonResponse
    {
        try {
            $product = $this->service->update($loanProduct, $request->validated());

            return ApiResponse::success($product, 'UPDATE_LOAN_PRODUCT_SUCCESS', Response::HTTP_OK);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPDATE_LOAN_PRODUCT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * "Moderator" route only
     * Toggles the active status of a loan product.
     *
     * @param LoanProduct $loanProduct The loan product to toggle active status.
     *
     * @return JsonResponse The response containing a success message or an error message.
     *
     * @throws \Exception
     */
    public function updateStatus(LoanProduct $loanProduct): JsonResponse
    {
        try {
            $this->service->toggleActive($loanProduct);

            return ApiResponse::success(null, 'UPDATE_LOAN_PRODUCT_STATUS_SUCCESS', Response::HTTP_OK);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPDATE_LOAN_PRODUCT_STATUS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * "Moderator" route only
     * Deletes a loan product.
     *
     * @param LoanProduct $loanProduct The loan product to delete.
     *
     * @return JsonResponse The response containing a success message or an error message.
     *
     * @throws \Exception
     */
    public function destroy(LoanProduct $loanProduct): JsonResponse
    {
        try {
            $this->service->destroy($loanProduct);

            return ApiResponse::success(null, 'DELETE_LOAN_PRODUCT_SUCCESS', Response::HTTP_OK);

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DELETE_LOAN_PRODUCT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}