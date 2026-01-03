<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = auth()->user();
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('memora_presets', 'name')->where('user_uuid', $user?->uuid),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:50', 'in:Wedding,Portrait,Event,Commercial,Other'],
            'isSelected' => ['nullable', 'boolean'],
            'collectionTags' => ['nullable', 'string'],
            'photoSets' => ['nullable', 'array'],
            'photoSets.*' => ['string', 'max:255'],
            'defaultWatermarkId' => ['nullable', 'uuid', 'exists:memora_watermarks,uuid'],
            'emailRegistration' => ['nullable', 'boolean'],
            'galleryAssist' => ['nullable', 'boolean'],
            'slideshow' => ['nullable', 'boolean'],
            'slideshowSpeed' => ['nullable', 'string', 'in:slow,regular,fast'],
            'slideshowAutoLoop' => ['nullable', 'boolean'],
            'socialSharing' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:10'],

            // Design fields (excluding cover style/focal point)
            'design.fontFamily' => ['nullable', 'string', 'max:100'],
            'design.fontStyle' => ['nullable', 'string', 'max:50'],
            'design.colorPalette' => ['nullable', 'string', 'max:50'],
            'design.gridStyle' => ['nullable', 'string', 'max:50'],
            'design.gridColumns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'design.thumbnailOrientation' => ['nullable', 'string', 'max:50'],
            'design.gridSpacing' => ['nullable', 'integer', 'min:0', 'max:100'],
            'design.tabStyle' => ['nullable', 'string', 'max:50'],
            'design.joyCover.title' => ['nullable', 'string', 'max:255'],
            'design.joyCover.avatar' => ['nullable', 'string', 'max:255'],
            'design.joyCover.showDate' => ['nullable', 'boolean'],
            'design.joyCover.showName' => ['nullable', 'boolean'],
            'design.joyCover.buttonText' => ['nullable', 'string', 'max:255'],
            'design.joyCover.showButton' => ['nullable', 'boolean'],
            'design.joyCover.backgroundPattern' => ['nullable', 'string', 'max:255'],

            // Privacy fields
            'privacy.collectionPassword' => ['nullable', 'boolean'],
            'privacy.showOnHomepage' => ['nullable', 'boolean'],
            'privacy.clientExclusiveAccess' => ['nullable', 'boolean'],
            'privacy.allowClientsMarkPrivate' => ['nullable', 'boolean'],
            'privacy.clientOnlySets' => ['nullable', 'array'],
            'privacy.clientOnlySets.*' => ['string'],

            // Download fields
            'download.photoDownload' => ['nullable', 'boolean'],
            'download.highResolution.enabled' => ['nullable', 'boolean'],
            'download.highResolution.size' => ['nullable', 'string', 'max:50'],
            'download.webSize.enabled' => ['nullable', 'boolean'],
            'download.webSize.size' => ['nullable', 'string', 'max:50'],
            'download.videoDownload' => ['nullable', 'boolean'],
            'download.downloadPin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'download.downloadPinEnabled' => ['nullable', 'boolean'],
            'download.limitDownloads' => ['nullable', 'boolean'],
            'download.downloadLimit' => ['nullable', 'integer', 'min:1'],
            'download.restrictToContacts' => ['nullable', 'boolean'],
            'download.downloadableSets' => ['nullable', 'array'],
            'download.downloadableSets.*' => ['string'],

            // Favorite fields
            'favorite.enabled' => ['nullable', 'boolean'],
            'favorite.photos' => ['nullable', 'boolean'],
            'favorite.notes' => ['nullable', 'boolean'],
        ];
    }
}

