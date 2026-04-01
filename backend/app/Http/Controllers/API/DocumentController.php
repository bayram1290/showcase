<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Mail\GenericEmail;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\TryCatch;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{

    public function __construct()
    {}

    /**
     * Upload a document for a given loan application.
     *
     * @param Request $request
     * @param int $application_id
     * @return JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function uploadApplicationDocument(Request $request, $uuid): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:identity,income,address,employment,bank_statement,collateral',
            'document_name' => 'sometimes|string',
            'file' => 'required|file|max:5120|mimetypes:image/jpeg,image/jpg,image/png,application/pdf'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application_id = LoanApplication::where('application_uuid', $uuid)->first()?->id;

        if (!$application_id) {
            return response()->json([
                'success'=> false,
                'message' => 'Loan application not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $file = $request->file('file');
        $filename = time() . '_' .  $file->getClientOriginalName();
        $path = "documents/applications/{$uuid}/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $document = Document::create([
            'loan_application_id' => $application_id,
            'document_type' => $request->document_type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => ($file->getSize() / 1024) // File size in KB
        ]);

        AuditLog::create([
            'action' => 'document_uploaded',
            'user_id' => $request->user()->id,
            'loan_application_id' => $application_id,
            'new_data' => $document->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document
        ], Response::HTTP_CREATED);
    }

    /**
     * Get all documents for a given loan application
     *
     * @param Request $request
     * @param int $application_id
     * @return JsonResponse
     */
    public function getDocuments(Request $request, $application_id): JsonResponse
    {

        $application = LoanApplication::where('user_id', $request->user()->id)
                        ->findOrFail($application_id);

        $documents = Document::where('loan_application_id', $application->id)->get();

        return response()->json([
            'success'=> true,
            'data' => $documents
        ], Response::HTTP_OK);
    }

    /**
     * Verify a document for a given loan application.
     *
     * @param Request $request
     * @param int $document_id
     * @return JsonResponse
     */
    public function verifyDocument(Request $request, $document_id): JsonResponse
    {

        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'is_verified' => 'required|boolean',
            'verification_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $document = Document::with(['loanApplication', 'loanApplication.borrower'])
                    ->findOrFail($document_id);

        if (!$user->isLoanOfficer()) {
            if ($document->loanApplication->assigned_officer_id != $user->id) {
                return response()->json([
                    'success'=> false,
                    'message'=> 'Unauthorized: You can only verify documents for applications assigned to you.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if ($document->is_verified && $request->is_verified) {
            return response()->json([
                'success'=> false,
                'message'=> 'Document already verified'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $application = $document->loanApplication;
        if (!in_array($application->status, ['submitted', 'under_review'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot verify document for application with status: ' . $application->status
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $old_data = $document->only(['is_verified', 'verification_notes', 'verified_by', 'verified_at']);
        $old_data['borrower_id'] = $application->borrower_id;
        $update_data = [
            'is_verified' => $request->is_verified,
            'verification_notes' => $request->verification_notes,
            'verified_by' => $user->id,
            'verified_at' => Carbon::now(),
            'borrower_id' => $application->borrower_id
        ];

        $document->update($update_data);

        AuditLog::create([
            'action' => $request->is_verified ? 'document_verified' : 'document_rejected',
            'old_data' => $old_data,
            'new_data' => $document->only(['is_verified', 'verification_notes']),
            'user_id' => $user->id,
            'loan_application_id' => $document->loan_application_id,
        ]);

        $this->checkAndUpdateApplicationDocumentStatus($application);
        $this->sendDocumentVerificationNotification($document, $application, $user, $request->is_verified);

        return response()->json([
            'success'=> true,
            'message'=> $request->is_verified ? 'Document verified successfully' : 'Document rejected',
            'data' => $document->fresh(),
            'application_status' => $application->fresh()->status,
            'all_documents_verified' => $this->areAllDocumentsVerified($application)
        ], Response::HTTP_OK);
    }

    /**
     * Checks if all documents for a given loan application have been verified.
     * If all documents have been verified and the application status is 'submitted',
     * the application status is updated to 'under_review' and an audit log is created.
     *
     * @param LoanApplication $application The loan application to check
     */
    private function checkAndUpdateApplicationDocumentStatus(LoanApplication $application): void {
        $total_documents = $application->documents()->count();
        $verified_documents = $application->documents()->where('is_verified', true)->count();

        if ($total_documents > 0 && $total_documents == $verified_documents) {
            if ($application->status === 'submitted') {
                $application->update([
                    'status' => 'under_review',
                    'reviewed_at' => Carbon::now()
                ]);

                AuditLog::create([
                    'action' => 'application_moved_to_review',
                    'loan_application_id' => $application->id,
                    'user_id' => auth()->user()->id,
                    'new_data' => [
                        'status' => 'under_review',
                        'reason' => 'All documents verified',
                        'reviewed_at' => Carbon::now(),
                        'borrower_id' => $application->borrower_id,
                    ],
                    'notes' => 'Application moved to under_review after all documents were verified'
                ]);
            }

        }
    }

    /**
     * Checks if all documents for a given loan application have been verified.
     *
     * @param LoanApplication $application
     * @return bool
     */
    private function areAllDocumentsVerified(LoanApplication $application): bool {
        $total_documents = $application->documents()->count();
        if ($total_documents === 0) return false;

        $verified_documents = $application->documents()->where('is_verified', true)->count();
        return $total_documents === $verified_documents;
    }

    /**
     * Sends a notification to the borrower when a document has been verified or rejected.
     *
     * @param Document $document The document that has been verified or rejected
     * @param LoanApplication $application The loan application that the document belongs to
     * @param User $user The user who verified or rejected the document
     * @param bool $is_verified Whether the document has been verified or rejected
     */
    private function sendDocumentVerificationNotification(Document $document, LoanApplication $application, $user, bool $is_verified): void
    {
        $borrower = $application->borrower;
        $subject = ($is_verified ? 'Document Verified:' : 'Document Rejected') . ': ' . $document->document_type;
        $content = [
            'message' => ($is_verified ? 'Your document has been verified' : 'Your document has been rejected'),
            'document_details' => [
                'Document type' => $document->document_type,
                'Document name' => $document->document_name,
                'Status' => $is_verified ? 'Verified' : 'Rejected',
                'Verified by' => ($user->full_name . ' ' . $user->last_name),
                'Verified at' => now()->format('d-m-Y H:i:s')
            ],
            'application_details' => [
                'Application Reference' => $application->application_ref,
                'Status' => $application->status
            ]
        ];

        if (!$is_verified && $document->verification_notes) {
            $content['rejection_reason'] = 'Your document has the following notes: ' . $document->verification_notes;
            $content['next_steps'] = 'Please correct the issues and resubmit the document';
        }

        try {
            \Mail::to($borrower->email)
                ->send(new GenericEmail(
                    $subject,
                    $content,
                    [
                        'document_id' => $document->id,
                        'application_id' => $application->id,
                        'verification_status' => $is_verified ? 'verified' : 'rejected'
                    ]
                ));
        } catch (\Exception $e) {
            \Log::error('Failed to send document verification notification', [
                'borrower_id' => $borrower->id,
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
        }
    }

}
