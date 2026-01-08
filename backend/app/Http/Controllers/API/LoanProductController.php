<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoanProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = LoanProduct::active()->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator =  Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:' . $request->min_amount,
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:fixed,variable',
            'min_tenure' => 'required|integer|min:1',
            'max_tenure' => 'required|integer|min:' . $request->min_tenure,
            'type' => 'required|in:personal,mortgage,auto,business,education',
            'processing_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'late_fee' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = LoanProduct::create($request->all());

        return  response()->json([
            'success'=> true,
            'message' => 'Loan product created successfully',
            'data' => $product
        ], 201);
    }

    public function update(Request $request, int $id) {

        $product = LoanProduct::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'sometimes|required|numeric|min:' . ($request->min_amount ?? $product->min_amount),
            'interest_rate' => 'sometimes|required|numeric|min:0|max:100',
            'interest_type' => 'sometimes|required|in:fixed,variable',
            'min_tenure' => 'sometimes|required|integer|min:1',
            'max_tenure' => 'sometimes|required|integer|min:' . ($request->min_tenure ?? $product->min_tenure),
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->all());

        return response()->json([
            'success'=> true,
            'message'=> 'Loan product updated successfully'
        ]);

    }

    public function destroy(int $id): JsonResponse
    {

        $product = LoanProduct::findOrFail($id);

        if ($product->loanApplication()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Loan product cannot be deleted because it has loan applications'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'success'=> true,
            'message'=> 'Loan product deleted successfully'
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $product = LoanProduct::findOrFail($id);

        return response()->json([
            'success'=> true,
            'data' => $product
        ]);
    }
}
