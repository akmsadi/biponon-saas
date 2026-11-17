<?php

namespace App\Http\Requests\Shop;

use App\Models\Organization;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a web shop order: the shopper's contact and delivery details and up to 20 lines, each an
 * orderable variant of this shop with no more units than are in stock. `website` is a honeypot that
 * people never see, so any value means a bot.
 */
class PlaceShopOrderRequest extends FormRequest
{
    public const MAX_LINES = 20;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+()\-\s]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'website' => ['prohibited'],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.variant_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Organization $shop */
                $shop = $this->route('shop');

                if (! $shop->shopIsOpen()) {
                    $validator->errors()->add('shop', __('This shop is not taking orders right now.'));

                    return;
                }

                if (strlen((string) preg_replace('/\D+/', '', $this->input('phone'))) < 6) {
                    $validator->errors()->add('phone', __('Enter a phone number we can call.'));
                }

                $lines = $this->input('lines');
                $variants = $shop->productVariants()
                    ->whereKey(array_column($lines, 'variant_id'))
                    ->orderableInShop()
                    ->whereHas('product', fn (Builder $products) => $products->where('is_active', true)->where('show_in_shop', true))
                    ->withShopStock()
                    ->get()
                    ->keyBy('id');

                foreach ($lines as $index => $line) {
                    /** @var ProductVariant|null $variant */
                    $variant = $variants->get($line['variant_id']);

                    if ($variant === null) {
                        $validator->errors()->add("lines.{$index}.variant_id", __('This item is no longer available.'));
                    } elseif ($line['quantity'] > (int) $variant->shop_stock) {
                        $validator->errors()->add("lines.{$index}.quantity", __('Not enough in stock.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number using digits, spaces, + ( ) or -.'),
            'website.prohibited' => __('Your order could not be placed.'),
        ];
    }
}
