<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:50', 'in:Wedding,Portrait,Event,Commercial,Other'],
            'isSelected' => ['sometimes', 'nullable', 'boolean'],
            'collectionTags' => ['sometimes', 'nullable', 'string'],
            'photoSets' => ['sometimes', 'nullable', 'array'],
            'photoSets.*' => ['string', 'max:255'],
            'defaultWatermarkId' => ['sometimes', 'nullable', 'uuid', 'exists:memora_watermarks,uuid'],
            'emailRegistration' => ['sometimes', 'nullable', 'boolean'],
            'galleryAssist' => ['sometimes', 'nullable', 'boolean'],
            'slideshow' => ['sometimes', 'nullable', 'boolean'],
            'slideshowSpeed' => ['sometimes', 'nullable', 'string', 'in:slow,regular,fast'],
            'slideshowAutoLoop' => ['sometimes', 'nullable', 'boolean'],
            'socialSharing' => ['sometimes', 'nullable', 'boolean'],
            'language' => ['sometimes', 'nullable', 'string', 'max:10'],

            // Design fields (excluding cover style/focal point)
            'design.fontFamily' => ['sometimes', 'nullable', 'string', 'max:100'],
            'design.fontStyle' => ['sometimes', 'nullable', 'string', 'max:50'],
            'design.colorPalette' => ['sometimes', 'nullable', 'string', 'max:50'],
            'design.gridStyle' => ['sometimes', 'nullable', 'string', 'max:50'],
            'design.gridColumns' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'design.thumbnailOrientation' => ['sometimes', 'nullable', 'string', 'max:50'],
            'design.gridSpacing' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'design.tabStyle' => ['sometimes', 'nullable', 'string', 'max:50'],
            'design.joyCover.title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'design.joyCover.avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'design.joyCover.showDate' => ['sometimes', 'nullable', 'boolean'],
            'design.joyCover.showName' => ['sometimes', 'nullable', 'boolean'],
            'design.joyCover.buttonText' => ['sometimes', 'nullable', 'string', 'max:255'],
            'design.joyCover.showButton' => ['sometimes', 'nullable', 'boolean'],
            'design.joyCover.backgroundPattern' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Privacy fields
            'privacy.collectionPassword' => ['sometimes', 'nullable', 'boolean'],
            'privacy.showOnHomepage' => ['sometimes', 'nullable', 'boolean'],
            'privacy.clientExclusiveAccess' => ['sometimes', 'nullable', 'boolean'],
            'privacy.allowClientsMarkPrivate' => ['sometimes', 'nullable', 'boolean'],
            'privacy.clientOnlySets' => ['sometimes', 'nullable', 'array'],
            'privacy.clientOnlySets.*' => ['string'],

            // Download fields
            'download.photoDownload' => ['sometimes', 'nullable', 'boolean'],
            'download.highResolution.enabled' => ['sometimes', 'nullable', 'boolean'],
            'download.highResolution.size' => ['sometimes', 'nullable', 'string', 'max:50'],
            'download.webSize.enabled' => ['sometimes', 'nullable', 'boolean'],
            'download.webSize.size' => ['sometimes', 'nullable', 'string', 'max:50'],
            'download.videoDownload' => ['sometimes', 'nullable', 'boolean'],
            'download.downloadPin' => ['sometimes', 'nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'download.downloadPinEnabled' => ['sometimes', 'nullable', 'boolean'],
            'download.limitDownloads' => ['sometimes', 'nullable', 'boolean'],
            'download.downloadLimit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'download.restrictToContacts' => ['sometimes', 'nullable', 'boolean'],
            'download.downloadableSets' => ['sometimes', 'nullable', 'array'],
            'download.downloadableSets.*' => ['string'],

            // Favorite fields
            'favorite.enabled' => ['sometimes', 'nullable', 'boolean'],
            'favorite.photos' => ['sometimes', 'nullable', 'boolean'],
            'favorite.notes' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}

