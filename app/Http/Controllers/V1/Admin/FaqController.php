<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\StoreFaqRequest;
use App\Http\Requests\V1\Admin\UpdateFaqRequest;
use App\Models\Faq;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * List all FAQs (admin).
     */
    public function index(): JsonResponse
    {
        $faqs = Faq::query()->orderBy('sort_order')->orderBy('created_at')->get();

        $data = $faqs->map(fn (Faq $faq) => [
            'uuid' => $faq->uuid,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'sort_order' => $faq->sort_order,
            'is_published' => $faq->is_published,
            'created_at' => $faq->created_at?->toIso8601String(),
            'updated_at' => $faq->updated_at?->toIso8601String(),
        ]);

        return ApiResponse::success(['data' => $data]);
    }

    /**
     * Store a new FAQ.
     */
    public function store(StoreFaqRequest $request): JsonResponse
    {
        $faq = Faq::create($request->validated());

        return ApiResponse::successCreated([
            'data' => [
                'uuid' => $faq->uuid,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_published' => $faq->is_published,
                'created_at' => $faq->created_at?->toIso8601String(),
                'updated_at' => $faq->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Show a single FAQ.
     */
    public function show(string $uuid): JsonResponse
    {
        $faq = Faq::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success([
            'data' => [
                'uuid' => $faq->uuid,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_published' => $faq->is_published,
                'created_at' => $faq->created_at?->toIso8601String(),
                'updated_at' => $faq->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update an FAQ.
     */
    public function update(UpdateFaqRequest $request, string $uuid): JsonResponse
    {
        $faq = Faq::where('uuid', $uuid)->firstOrFail();
        $faq->update($request->validated());

        return ApiResponse::successOk([
            'data' => [
                'uuid' => $faq->uuid,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_published' => $faq->is_published,
                'created_at' => $faq->created_at?->toIso8601String(),
                'updated_at' => $faq->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete an FAQ.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $faq = Faq::where('uuid', $uuid)->firstOrFail();
        $faq->delete();

        return ApiResponse::successOk(['message' => 'FAQ deleted']);
    }
}
