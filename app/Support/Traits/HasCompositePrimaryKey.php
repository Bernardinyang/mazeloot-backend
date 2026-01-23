<?php

namespace App\Support\Traits;

trait HasCompositePrimaryKey
{
    /**
     * The primary key for the model.
     * Use a single string key to avoid Laravel's composite key issues.
     */
    protected $primaryKey = 'id';

    /**
     * Get the composite key fields as an array.
     * Must be implemented by the model.
     *
     * @return array<string>
     */
    abstract protected function getCompositeKeyFields(): array;

    /**
     * Get a virtual ID by combining composite key fields.
     */
    public function getIdAttribute()
    {
        $fields = $this->getCompositeKeyFields();
        $values = [];

        foreach ($fields as $field) {
            if (! isset($this->attributes[$field])) {
                return null;
            }
            $values[] = $this->attributes[$field];
        }

        return implode(':', $values);
    }

    /**
     * Override to prevent array offset errors when accessing original key.
     */
    protected function getKeyForSaveQuery()
    {
        return $this->getKey();
    }

    /**
     * Override to set keys for save query using composite key fields.
     */
    protected function setKeysForSaveQuery($query)
    {
        $fields = $this->getCompositeKeyFields();

        foreach ($fields as $field) {
            $query->where($field, '=', $this->attributes[$field] ?? null);
        }

        return $query;
    }

    /**
     * Override fresh() to use composite keys instead of 'id' column.
     */
    public function fresh($with = [])
    {
        if (! $this->exists) {
            return null;
        }

        $query = static::query();
        $fields = $this->getCompositeKeyFields();

        foreach ($fields as $field) {
            $query->where($field, '=', $this->attributes[$field] ?? null);
        }

        return $query->with($with)->first();
    }

    /**
     * Override refresh() to use composite keys instead of 'id' column.
     */
    public function refresh()
    {
        if (! $this->exists) {
            return $this;
        }

        $query = static::query();
        $fields = $this->getCompositeKeyFields();

        foreach ($fields as $field) {
            $query->where($field, '=', $this->attributes[$field] ?? null);
        }

        $fresh = $query->first();

        if ($fresh) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Override getKey to return virtual ID.
     */
    public function getKey()
    {
        return $this->getIdAttribute();
    }

    /**
     * Override getKeyName to ensure it always returns a string.
     */
    public function getKeyName()
    {
        $keyName = $this->primaryKey;
        if (is_array($keyName)) {
            return 'id';
        }

        return is_string($keyName) ? $keyName : 'id';
    }

    /**
     * Override to prevent Laravel from trying to access attributes with invalid keys.
     */
    protected function getAttributeFromArray($key)
    {
        if (! is_string($key) && ! is_int($key)) {
            return null;
        }

        if ($key === 'id') {
            return $this->getIdAttribute();
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Override hasAttribute to prevent array_key_exists errors.
     */
    public function hasAttribute($key)
    {
        if (! is_string($key) && ! is_int($key)) {
            return false;
        }

        if ($key === 'id') {
            $fields = $this->getCompositeKeyFields();
            foreach ($fields as $field) {
                if (! isset($this->attributes[$field])) {
                    return false;
                }
            }

            return true;
        }

        try {
            return $this->getAttributeFromArray($key) !== null ||
                   array_key_exists($key, $this->casts) ||
                   $this->hasGetMutator($key) ||
                   $this->hasAttributeMutator($key) ||
                   $this->isClassCastable($key);
        } catch (\TypeError $e) {
            return false;
        }
    }
}
