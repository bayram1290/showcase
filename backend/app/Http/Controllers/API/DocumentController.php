<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use App\Helpers\ApiResponse;
use App\Models\Document;
use App\Models\LoanApplication;
use App\DataTransferObjects\DocumentData;
use App\Http\Resources\DocumentResource;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Requests\VerifyDocumentRequest;
use App\Contracts\Services\DocumentServiceInterface;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    protected DocumentServiceInterface $service;

    public function __construct(
        DocumentServiceInterface $service
    ) {
        $this->service = $service;
    }

    /**
     * Show a list of the loan documents for a given loan application (intended for borrower).
     *
     * @param Request $request The request object.
     * @param LoanApplication $application The loan application object.
     * @return JsonResponse The JSON response containing the loan documents.
     */
    public function index(Request $request, LoanApplication $application): JsonResponse
    {
        try {
            $this->authorize('viewLoanDocuments', $application);

            $filters = $request->only(['document_type', 'is_verified']);
            $documents = $this->service->listDocuments($application, $filters);

            return ApiResponse::success(
                DocumentResource::collection($documents),
                'LIST_DOCUMENTS_SUCCESS',
                Response ::HTTP_OK
            );

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'LIST_DOCUMENTS_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Upload a document for a given loan application (intended for borrower).
     *
     * @param UploadDocumentRequest $request The request object.
     * @param LoanApplication $application The loan application object.
     * @return JsonResponse The JSON response indicating the success or failure of the upload.
     */
    public function upload(UploadDocumentRequest $request, LoanApplication $application): JsonResponse
    {
        try {
            $this->authorize('uploadDocument', $application);

            $dto = DocumentData::fromRequest($request, $application);
            $document = $this->service->upload($dto->application, $dto->file, $dto->documentType);

            return ApiResponse::success(
                new DocumentResource($document),
                'UPLOAD_DOCUMENT_SUCCESS',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPLOAD_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Generate a temporary download URL for a given document (intended for borrower).
     *
     * @param Request $request The request object.
     * @param LoanApplication $application The loan application object.
     * @param Document $document The document object.
     * @return JsonResponse The JSON response containing the download URL.
     */
    public function download(Request $request, LoanApplication $application, Document $document): JsonResponse
    {
        try {
            $this->authorize('downloadDocument', $document);

            $signed_url = $this->service->generateTemporaryUrl($document);

            return ApiResponse::success(
                ['download_url' => $signed_url],
                'DOWNLOAD_DOCUMENT_SUCCESS',
                Response::HTTP_OK
            );

        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DOWNLOAD_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Delete a document for a given loan application (intended for borrower).
     *
     * @param Request $request The request object.
     * @param LoanApplication $application The loan application object.
     * @param Document $document The document object.
     * @return JsonResponse The JSON response indicating the success or failure of the deletion.
     */
    public function destroy(Request $request, LoanApplication $application, Document $document): JsonResponse
    {
        try {
            Document::clearScanStatusEnumCache();
            $this->authorize('deleteDocument', $document);

            $this->service->delete($document);

            return ApiResponse::success(
                null,
                'DELETE_DOCUMENT_SUCCESS',
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DELETE_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Verify a document for a given loan application (intended for staff).
     *
     * @param VerifyDocumentRequest $request The request object.
     * @param Document $document The document object.
     * @return JsonResponse The JSON response indicating the success or failure of the verification.
     */
    public function verify(VerifyDocumentRequest $request, Document $document): JsonResponse
    {
        try {
            $this->authorize('verifyDocument', $document);

            $user = $request->user() ?? request()->user();
            $this->service->verify($document, $user, $request->validated()['verification_notes']);

            return ApiResponse::success(
                null,
                'VERIFY_DOCUMENT_SUCCESS',
                Response::HTTP_OK
            );
        } catch(\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'VERIFY_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Download a file for a given document (public route).
     *
     * @param Request $request The request object.
     * @param Document $document The document object.
     * @return StreamedResponse|JsonResponse The streamed response or JSON response containing the file download.
     * @throws \Exception If an error occurs during the file download.
     */
    public function downloadFile(Request $request, Document $document): StreamedResponse | JsonResponse
    {
        try {
            if (!$request->hasValidSignature()) {
                return ApiResponse::error(
                    'Invalid or expired download link.',
                    'INVALID_SIGNATURE',
                    Response::HTTP_UNAUTHORIZED
                );
            }

            $file_path = $document->file_path . '/' . $document->file_name;
            if (!Storage::disk('public')->exists($file_path)) {
                return ApiResponse::error(
                    'File not found.',
                    'FILE_NOT_FOUND',
                    Response::HTTP_NOT_FOUND
                );
            }

            return Storage::disk('public')->download($file_path, $document->original_file_name);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DOWNLOAD_DOCUMENT_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}