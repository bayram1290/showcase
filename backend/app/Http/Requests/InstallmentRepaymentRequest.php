<?php

namespace App\Http\Requests;

use App\Models\Borrower;
use App\Models\InstallmentRepaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;

class InstallmentRepaymentRequest extends FormRequest
{
    private const INSTALLMENT_PAID_STATUS = 'paid';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {

        $rules = [
            'amount' => 'required|numeric|min:.01',
            'repayment_method_id' => 'required|exists:installment_repayment_methods,id',
            'remarks' => 'nullable|string|max:500',
        ];

        return $rules;
    }

    /**
     * Configure the validator instance with validation rules and checks for specific conditions
     * related to installment repayment.
     *
     * @param Validator $validator The validator instance.
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {

            $installment = $this->route('installment');
            $amount = $this->input('amount');

            $max_payable = $installment->due_amount - $installment->paid_amount + $installment->late_feed;

            if ($installment->status === self::INSTALLMENT_PAID_STATUS) {
                $validator->errors()->add('status', 'Installment has already paid off.');
            }

            if ($amount > $max_payable) {
                $validator->errors()->add('amount', "Amount cannot be greater than the due amount plus late fee (max: {$max_payable}).");
            }

            if ($amount <= 0) {
                $validator->errors()->add('amount', "Amount must be greater than 0.");
            }

            if (!$validator->errors()->has('repayment_method_id')) {
                if ($this->user() instanceOf Borrower) {
                    $allowed_payment_methods = InstallmentRepaymentMethod::getBorrowerApplicableMethodIDs();

                    if (!in_array($this->input('repayment_method_id'), $allowed_payment_methods)) {
                        $validator->errors()->add('repayment_method', "You cannot make repayment via \"{$this->input('repayment_method')}\".");
                    }
                }
            }
        });
    }

    #[Override]
    public function prepareForValidation(): void
    {
        if ($this->has('repayment_method')) {
            $method_id = InstallmentRepaymentMethod::getMethodIDForCode($this->input('repayment_method'));
            if (!is_null($method_id)) $this->merge(['repayment_method_id' => $method_id]);
        }
    }
}
