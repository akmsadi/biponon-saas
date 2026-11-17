<?php

namespace App\Actions\Shop;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Turns a validated web shop order into a draft sales order that ships from the shop's default warehouse,
 * priced at the variants' current prices. The shopper becomes a customer, or is matched to an existing one
 * by phone number.
 */
class PlaceShopOrder
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, address: string, note?: ?string, lines: array<int, array{variant_id: int, quantity: int}>}  $order
     */
    public function handle(Organization $shop, array $order): SalesOrder
    {
        return DB::transaction(function () use ($shop, $order): SalesOrder {
            $sequence = $shop->nextSalesOrderSequence();
            $prices = $shop->productVariants()
                ->whereKey(array_column($order['lines'], 'variant_id'))
                ->pluck('price', 'id');

            $salesOrder = $shop->salesOrders()->create([
                'sequence' => $sequence,
                'number' => SalesOrder::numberFor($sequence),
                'customer_id' => $this->customerFor($shop, $order)->id,
                'warehouse_id' => $shop->defaultWarehouseId(),
                'status' => 'draft',
                'notes' => $this->notes($order),
                'created_by' => null,
            ]);

            foreach ($order['lines'] as $line) {
                $salesOrder->lines()->create([
                    'product_variant_id' => $line['variant_id'],
                    'quantity_ordered' => $line['quantity'],
                    'unit_price' => $prices[$line['variant_id']],
                ]);
            }

            return $salesOrder;
        });
    }

    /**
     * The existing customer with the same phone number, or a new one. Numbers match on their digits, and on
     * the last 10 digits when both are that long, so "01712 345678" matches "+880 1712-345678".
     *
     * @param  array{name: string, phone: string, email?: ?string, address: string}  $order
     */
    private function customerFor(Organization $shop, array $order): Customer
    {
        $digits = $this->digits($order['phone']);

        $existing = $shop->customers()
            ->whereNotNull('phone')
            ->get(['id', 'phone'])
            ->first(fn (Customer $customer) => $this->samePhone($digits, $this->digits($customer->phone)));

        if ($existing !== null) {
            return $existing;
        }

        return $shop->customers()->create([
            'name' => $this->uniqueName($shop, $order['name'], $order['phone']),
            'email' => $order['email'] ?? null,
            'phone' => $order['phone'],
            'address' => $order['address'],
            'notes' => __('Created by a web shop order.'),
            'is_active' => true,
        ]);
    }

    private function samePhone(string $a, string $b): bool
    {
        if (strlen($a) >= 10 && strlen($b) >= 10) {
            return substr($a, -10) === substr($b, -10);
        }

        return $a === $b;
    }

    private function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }

    /**
     * Customer names are unique per organization, so a clash gets the phone number added: "Rahim (01712…)".
     */
    private function uniqueName(Organization $shop, string $name, string $phone): string
    {
        $candidate = $name;

        for ($attempt = 1; $shop->customers()->where('name', $candidate)->exists(); $attempt++) {
            $candidate = $attempt === 1 ? "{$name} ({$phone})" : "{$name} ({$phone}) {$attempt}";
        }

        return $candidate;
    }

    /**
     * @param  array{address: string, note?: ?string}  $order
     */
    private function notes(array $order): string
    {
        $notes = __('Web shop order (cash on delivery).')."\n".__('Deliver to: :address', ['address' => $order['address']]);

        if (filled($order['note'] ?? null)) {
            $notes .= "\n".__('Shopper\'s note: :note', ['note' => $order['note']]);
        }

        return $notes;
    }
}
