<?php
namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Requests\VerifyDocumentRequest;
use App\Models\Document;
use App\Models\LoanApplication;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    protected DocumentService $service;
    public function __construct(
        DocumentService $service
    ) {
        $this->service = $service;
    }

    public function upload(UploadDocumentRequest $request, LoanApplication $application): JsonResponse
    {
        if ($request->user()->id !== $application->borrower_id) {
            return ApiResponse::forbidden(
                'You do not have permission to upload documents for this loan application.',
                'UPLOAD_DOCUMENT_ERROR',
            );
        }

        try {
            $document = $this->service->upload($application, $request->file('file'), $request->document_type);

            return ApiResponse::success($document, 'UPLOAD_DOCUMENT_SUCCESS', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPLOAD_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function delete(Request $request, LoanApplication $application, Document $document): JsonResponse
    {
        if ($request->user()->id !== $application->borrower_id) {
            return ApiResponse::forbidden(
                'You do not have permission to delete this document.',
                'DELETE_DOCUMENT_ERROR',
            );
        }

        try {
            $this->service->delete($document);
            return ApiResponse::success(null, 'DELETE_DOCUMENT_SUCCESS', Response::HTTP_OK);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DELETE_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function verify(VerifyDocumentRequest $request, Document $document): JsonResponse
    {
        try {
            $this->service->verify($document, $request->user(), $request->validated(['notes']));

            return ApiResponse::success(
                null,
                'VERIFY_DOCUMENT_SUCCESS',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {

            return ApiResponse::error(
                $e->getMessage(),
                'VERIFY_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

}