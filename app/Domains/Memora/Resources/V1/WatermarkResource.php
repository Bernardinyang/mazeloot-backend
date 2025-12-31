<?php

namespace App\Domains\Memora\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class WatermarkResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type?->value ?? $this->type,
            'imageUrl' => $this->image_url,
            'text' => $this->text,
            'fontFamily' => $this->font_family,
            'fontStyle' => $this->font_style?->value ?? $this->font_style,
            'fontColor' => $this->font_color,
            'backgroundColor' => $this->background_color,
            'lineHeight' => $this->line_height,
            'letterSpacing' => $this->letter_spacing,
            'padding' => $this->padding,
            'textTransform' => $this->text_transform?->value ?? $this->text_transform,
            'borderRadius' => $this->border_radius,
            'borderWidth' => $this->border_width,
            'borderColor' => $this->border_color,
            'borderStyle' => $this->border_style?->value ?? $this->border_style,
            'scale' => $this->scale,
            'opacity' => $this->opacity,
            'position' => $this->position?->value ?? $this->position,
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
