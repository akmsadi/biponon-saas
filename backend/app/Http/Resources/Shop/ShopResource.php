<?php

namespace App\Http\Resources\Shop;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An organization's shop front: its name, contact details, currency and whether it takes orders.
 *
 * @mixin Organization
 */
class ShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'open' => $this->shopIsOpen(),
            'currency' => $this->setting('currency'),
            'contact' => [
                'email' => $this->setting('contact_email'),
                'phone' => $this->setting('phone'),
                'address' => $this->setting('address'),
            ],
        ];
    }
}
