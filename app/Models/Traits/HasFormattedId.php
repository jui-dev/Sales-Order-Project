<?php

namespace App\Models\Traits;

/**
 * Adds a "code" attribute (and magic accessor "formatted_id") that combines
 * a model-specific prefix plus the numeric primary key, zero-padded to 4+ digits.
 *
 * Example:  Product with id 15  => PRD-0015
 */
trait HasFormattedId
{
    /**
     * Add the "code" attribute to the model's appended attributes list (once).
     */
    protected static function bootHasFormattedId(): void
    {
        // After a model is retrieved from the database we make sure the
        // instance still has the attribute appended (edge-cases when the
        // model was cached without constructing through the usual path).
        static::retrieved(function ($model) {
            if (! in_array('code', $model->appends, true)) {
                $model->appends[] = 'code';
            }
        });
    }

    /**
     * Get a human-readable identifier such as "PRD-0001".
     * You can override the static $idPrefix property on the model
     * or implement getIdPrefix() to customise the prefix.
     */
    public function getCodeAttribute(): string
    {
        $prefix = $this->getIdPrefix();
        $pad    = max(4, strlen((string) $this->id));
        return sprintf('%s-%s', $prefix, str_pad((string) $this->id, $pad, '0', STR_PAD_LEFT));
    }

    /**
     * Alias for Blade convenience ($model->formatted_id).
     */
    public function getFormattedIdAttribute(): string
    {
        return $this->getCodeAttribute();
    }

    /**
     * String casting returns the readable identifier instead of the raw id.
     */
    public function __toString(): string
    {
        return $this->getCodeAttribute();
    }

    /**
     * This initializer runs whenever a new model instance is constructed
     * (either via new Model or when hydrated from the database). Ensures the
     * "code" attribute is always in the appended list.
     */
    public function initializeHasFormattedId(): void
    {
        if (! in_array('code', $this->appends, true)) {
            $this->appends[] = 'code';
        }
    }

    protected function getIdPrefix(): string
    {
        // Allow models to define `protected static $idPrefix = 'XYZ';`
        if (isset(static::$idPrefix) && static::$idPrefix) {
            return static::$idPrefix;
        }

        // Fallback: first three letters of class name upper-cased (e.g., Product → PRO)
        return strtoupper(substr(class_basename(static::class), 0, 3));
    }
} 