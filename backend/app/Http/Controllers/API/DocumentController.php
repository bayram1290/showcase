<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{

    public function __construct()
    {}

    public function upload(Request $request, $application_id): JsonResponse
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
            ], 422);
        }

        $application = LoanApplication::where('user_id', $request->user()->id)
                        ->findOrFail($application_id);

        $file = $request->file('file');
        $filename = time() . '_' .  $file->getClientOriginalName();
        $path = "documents/applications/{$application_id}/{$filename}";

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
        ], 201);
    }

    public function getDocuments(Request $request, $application_id): JsonResponse
    {

        $application = LoanApplication::where('user_id', $request->user()->id)
                        ->findOrFail($application_id);

        $documents = Document::where('loan_application_id', $application->id)->get();

        return response()->json([
            'success'=> true,
            'data' => $documents
        ],200);
    }

    public function verifyDocument(Request $request, $document_id): JsonResponse {

        $user = $request->user();

        if (!$user->isLoanOfficer() && !$user->isAdmin()) {
            return response()->json([
                'success'=> false,
                'message'=> 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'is_verified' => 'required|boolean',
            'verification_notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'=> false,
                'errors'=> $validator->errors()
            ], 422);
        }

        $document = Document::findOrFail($document_id);

        $document->update([
            'is_verified' => $request->is_verified,
            'verification_notes' => $request->verification_notes,
            'verified_by' => $user->id,
            'verified_at' => now()
        ]);

        AuditLog::create([
            'action' => 'document_verified',
            'user_id' => $user->id,
            'loan_application_id' => $document->loan_application_id,
            'new_data' => $document->only(['is_verified', 'verification_notes'])
        ]);

        return response()->json([
            'success'=> true,
            'message'=> 'Document verified successfully',
            'data' => $document
        ], 200);
    }

}
