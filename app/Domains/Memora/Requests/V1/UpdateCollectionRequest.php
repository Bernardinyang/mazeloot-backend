<?php

namespace App\Domains\Memora\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active,archived'],
            'presetId' => ['nullable', 'uuid', 'exists:memora_presets,uuid'],
            'watermarkId' => ['nullable', 'uuid', 'exists:memora_watermarks,uuid'],
            'settings' => ['nullable', 'array'],
            'eventDate' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'thumbnail' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'coverDesign' => ['nullable', 'array'],
            'coverDesign.coverFocalPoint' => ['nullable', 'array'],
            'coverDesign.coverFocalPoint.x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'coverDesign.coverFocalPoint.y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'typographyDesign' => ['nullable', 'array'],
            'typographyDesign.fontFamily' => ['nullable', 'string', 'max:100'],
            'typographyDesign.fontStyle' => ['nullable', 'string', 'max:50'],
            'colorDesign' => ['nullable', 'array'],
            'colorDesign.colorPalette' => ['nullable', 'string', 'max:50'],
            'gridDesign' => ['nullable', 'array'],
            'gridDesign.gridStyle' => ['nullable', 'string', 'max:50'],
            'gridDesign.gridColumns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'gridDesign.thumbnailOrientation' => ['nullable', 'string', 'max:50'],
            'gridDesign.gridSpacing' => ['nullable'],
            'gridDesign.tabStyle' => ['nullable', 'string', 'max:50'],
            'mediaSets' => ['nullable', 'array'],
            'mediaSets.*.id' => ['nullable', 'string'],
            'mediaSets.*.name' => ['required', 'string', 'max:255'],
            'mediaSets.*.description' => ['nullable', 'string', 'max:1000'],
            'mediaSets.*.order' => ['nullable', 'integer', 'min:0'],
            // General settings
            'url' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'emailRegistration' => ['nullable', 'boolean'],
            'galleryAssist' => ['nullable', 'boolean'],
            'slideshow' => ['nullable', 'boolean'],
            'slideshowSpeed' => ['nullable', 'string', 'in:slow,regular,fast'],
            'slideshowAutoLoop' => ['nullable', 'boolean'],
            'socialSharing' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:10'],
            'autoExpiryDate' => ['nullable', 'date'],
            'expiryDate' => ['nullable', 'date'],
            'expiryDays' => ['nullable', 'integer', 'min:1', 'max:365'],
            // Privacy settings
            'password' => ['nullable', 'string', 'max:255'],
            'showOnHomepage' => ['nullable', 'boolean'],
            'clientExclusiveAccess' => ['nullable', 'boolean'],
            'clientPrivatePassword' => ['nullable', 'string', 'max:255'],
            'allowClientsMarkPrivate' => ['nullable', 'boolean'],
            'clientOnlySets' => ['nullable', 'array'],
            'clientOnlySets.*' => ['string'],
            // Download settings
            'photoDownload' => ['nullable', 'boolean'],
            'highResolutionEnabled' => ['nullable', 'boolean'],
            'webSizeEnabled' => ['nullable', 'boolean'],
            'webSize' => ['nullable', 'string', 'max:50'],
            'downloadPin' => ['nullable', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'downloadPinEnabled' => ['nullable', 'boolean'],
            'limitDownloads' => ['nullable', 'boolean'],
            'downloadLimit' => ['nullable', 'integer', 'min:1'],
            'restrictToContacts' => ['nullable', 'boolean'],
            'allowedDownloadEmails' => ['nullable', 'array'],
            'allowedDownloadEmails.*' => ['email', 'max:255'],
            'downloadableSets' => ['nullable', 'array'],
            'downloadableSets.*' => ['string'],
            // Favorite settings
            'favoritePhotos' => ['nullable', 'boolean'],
            'favoriteNotes' => ['nullable', 'boolean'],
            'downloadEnabled' => ['nullable', 'boolean'],
            'favoriteEnabled' => ['nullable', 'boolean'],
        ];
    }
}
