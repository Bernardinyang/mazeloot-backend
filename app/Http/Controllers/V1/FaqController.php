<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * List published FAQs (public).
     */
    public function index(): JsonResponse
    {
        $faqs = Faq::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get(['uuid', 'question', 'answer', 'sort_order']);

        $data = $faqs->map(fn (Faq $faq) => [
            'question' => $faq->question,
            'answer' => $faq->answer,
        ]);

        return ApiResponse::success(['data' => $data]);
    }
}
