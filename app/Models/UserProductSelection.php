<?php

namespace App\Models;

use App\Support\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProductSelection extends Model
{
    use HasCompositePrimaryKey;

    /**
     * Indicates if the model uses auto-incrementing IDs.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_product_selections';

    /**
     * Get the composite key fields.
     *
     * @return array<string>
     */
    protected function getCompositeKeyFields(): array
    {
        return ['user_uuid', 'product_uuid'];
    }

    protected $fillable = [
        'user_uuid',
        'product_uuid',
        'selected_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
    ];

    /**
     * Get the user that owns the product selection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the product that was selected.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    /**
     * Scope a query to filter by product.
     */
    public function scopeForProduct($query, string $productUuid)
    {
        return $query->where('product_uuid', $productUuid);
    }

    /**
     * Scope a query to only include selected products.
     */
    public function scopeSelected($query)
    {
        return $query->whereNotNull('selected_at');
    }
}
