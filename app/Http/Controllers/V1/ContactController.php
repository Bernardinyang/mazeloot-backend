<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyAdminsContactSubmission;
use App\Models\ContactSubmission;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * Submit contact form. Stores submission and notifies all admins (in-app + email).
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'company' => 'nullable|string|max:200',
            'email' => 'required|email',
            'country' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:5000',
        ]);

        $submission = ContactSubmission::create($validated);
        NotifyAdminsContactSubmission::dispatchSync($validated, $submission->uuid);

        return ApiResponse::success([
            'message' => 'Thank you for your message. We\'ll get back to you soon.',
        ], 201);
    }
}
