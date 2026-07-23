<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\LoanProduct;
use App\Models\User;
use App\Models\Borrower;
use Exception;

class LoanProductService
{

    const SHOW_BORROWER_DETAILS = [
        'name',
        'description',
        'min_amount',
        'max_amount',
        'interest_rate',
        'min_tenure',
        'max_tenure',
        'type',
        'eligibility_criteria',
        'required_documents',
    ];

    const SHOW_MODERATOR_DETAILS = [
        'id',
        'name',
        'min_amount',
        'max_amount',
        'interest_type',
        'description',
        'interest_rate',
        'min_tenure',
        'max_tenure',
        'type',
        'eligibility_criteria',
        'required_documents',
        'processing_fee_percentage',
        'late_fee',
        'is_active'
    ];

    /**
     * Lists loan products based on the request parameters.
     *
     * - If the user is a borrower, it will only show the fields specified in SHOW_BORROWER_DETAILS.
     * - If the user is not a borrower, it will only show the fields specified in SHOW_MODERATOR_DETAILS.
     *
     * @param Request $request
     * @param User|Borrower $user
     * @return Collection
     */
    public function list(Request $request, User|Borrower $user): Collection
    {
        $query = LoanProduct::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($user instanceof Borrower) {
            $products =$query->active()
                ->orderBy('name')
                ->paginate($request->input('per_page', config('helper.default_pagination_length')));

            return $products->getCollection()->transform(function ($product) {
                return $product->only(self::SHOW_BORROWER_DETAILS);
            });
        }

        return $query
            ->orderBy('name')
            ->paginate($request->input('per_page', config('helper.default_pagination_length')))
            ->getCollection()->transform(function ($product) {
                return $product->only(self::SHOW_MODERATOR_DETAILS);
            });
    }

    /**
     * Shows a loan product.
     *
     * - If the user is a borrower, it will only show the fields specified in SHOW_BORROWER_DETAILS.
     * - If the user is not a borrower, it will only show the fields specified in SHOW_MODERATOR_DETAILS.
     *
     * @param LoanProduct $product
     * @param User|Borrower $user
     *
     * @return Collection
     *
     * @throws \Exception
     */
    public function show(LoanProduct $product, User|Borrower $user): Collection
    {
        if ($user instanceof Borrower) {
            if (!$product->active()) {
                throw new Exception('There is not any loan available.');
            }

            return collect($product->only(self::SHOW_BORROWER_DETAILS));
        }

        return collect($product->only(self::SHOW_MODERATOR_DETAILS));
    }

    public function store(array $data): LoanProduct
    {
        try {
            return DB::transaction(function () use ($data) {
                return LoanProduct::create($data);
            });
        } catch (Exception $e) {
            Log::error('Loan product creation failed: ' . $e->getMessage());
            throw new Exception('Could not create loan product. Please try again.');
        }
    }

    /**
     * Updates a loan product.
     *
     * @param LoanProduct $product The loan product to be updated.
     * @param array $data The data to be updated.
     *
     * @return LoanProduct The updated loan product.
     *
     * @throws \Exception If the loan product could not be updated.
     */
    public function update(LoanProduct $product, array $data): LoanProduct
    {
        try {
            $product = DB::transaction(function () use ($product, $data) {
                $product->update($data);
                return $product->refresh();
            });
            return $product;
        } catch (Exception $e) {
            Log::error('Loan product update failed: ' . $e->getMessage());
            throw new Exception('Could not update loan product. Please try again.');
        }
    }

    /**
     * Deletes a loan product if it has NO associated loan applications.
     *
     * Throws an exception if the loan product has associated loan applications.
     *
     * @param LoanProduct $product The loan product to be deleted.
     *
     * @throws \Exception If the loan product could not be deleted.
     */
    public function destroy(LoanProduct $product): void
    {
        if ($product->loanApplications()->exists()) {
            throw new Exception('Cannot delete loan product that has associated loan applications.');
        }

        if (!is_null($product->deleted_at)) {
            throw new Exception('Loan product has already been deleted.');
        }

        try {
            DB::transaction(function () use ($product) {
                $product->delete();
            });
        } catch (Exception $e) {
            Log::error('Loan product deletion failed: ' . $e->getMessage());
            throw new Exception('Could not delete loan product. Please try again or contact support.');
        }
    }

    /**
     * Toggles the active status of a loan product.
     *
     * @param LoanProduct $product The loan product to toggle active status.
     *
     * @return LoanProduct The loan product with the updated active status.
     *
     * @throws \Exception If the loan product's active status could not be updated.
     */
    public function toggleActive(LoanProduct $product): void
    {
        try {
            DB::transaction(function () use ($product) {
                $product->update(['is_active' => !$product->is_active]);
            });

        } catch (Exception $e) {
            Log::error('Loan product status update failed: ' . $e->getMessage());
            throw new Exception('Could not update the status of loan product. Please try again.');
        }
    }
}