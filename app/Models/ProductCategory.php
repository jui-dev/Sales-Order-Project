<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    /**
     * Get the parent category (for subcategories)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    /**
     * Get the subcategories (for main categories)
     */
    public function subcategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id');
    }

    /**
     * Get all products in this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get all products in this category and its subcategories
     */
    public function productsWithSubcategories(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id')
            ->orWhereIn('category_id', $this->subcategories->pluck('id'));
    }

    /**
     * Check if this category is a main category (no parent)
     */
    public function isMainCategory(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this category is a subcategory
     */
    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Get the full category path (e.g., "Electronics > Smartphones")
     */
    public function getFullPathAttribute(): string
    {
        if ($this->isMainCategory()) {
            return $this->name;
        }

        return $this->parent->full_path . ' > ' . $this->name;
    }

    /**
     * Get all main categories (categories without parent)
     */
    public static function getMainCategories()
    {
        return static::whereNull('parent_id')->with('subcategories')->get();
    }

    /**
     * Get all subcategories for a given main category
     */
    public static function getSubcategories(int $parentId)
    {
        return static::where('parent_id', $parentId)->get();
    }

    /**
     * Check if a category can be set as parent (prevents circular references)
     */
    public function canBeParent(ProductCategory $potentialParent): bool
    {
        // A category cannot be its own parent
        if ($this->id === $potentialParent->id) {
            return false;
        }

        // Check if the potential parent is a descendant of this category
        $current = $potentialParent;
        while ($current->parent_id !== null) {
            if ($current->parent_id === $this->id) {
                return false; // This would create a circular reference
            }
            $current = $current->parent;
        }

        return true;
    }
}
