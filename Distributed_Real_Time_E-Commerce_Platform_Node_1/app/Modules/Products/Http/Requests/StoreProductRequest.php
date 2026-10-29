<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sent as multipart/form-data so images can be uploaded with the product.
 */
class StoreProductRequest extends FormRequest
{
    /**
     * Checked before validation, so a customer gets 403 rather than validation hints.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            // `image` + `mimes` check the real file content, not just the extension.
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
