<?php

namespace App\Services\Package;

use App\Services\CgNetApiClient;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

class CgNetProductCatalog
{
    public function __construct(private CgNetApiClient $api) {}

    /**
     * @return list<array{
     *     productID: string,
     *     productName: string,
     *     productPrice: float,
     *     monthCycle: int,
     *     monthCycleUnit: '月'|'天',
     *     productLine: string,
     *     productSpeed: string,
     *     installationFee: float|null
     * }>
     */
    public function products(): array
    {
        return $this->fetchProducts('CGNETProductInfoListAPI');
    }

    /**
     * @return list<array{
     *     productID: string,
     *     productName: string,
     *     productPrice: float,
     *     monthCycle: int,
     *     monthCycleUnit: '月'|'天',
     *     productLine: string,
     *     productSpeed: string,
     *     installationFee: float|null
     * }>
     */
    public function productsForServiceType(string $serviceType): array
    {
        return $this->fetchProducts($this->commandForServiceType($serviceType));
    }

    public function commandForServiceType(string $serviceType): string
    {
        return match ($serviceType) {
            'FTTH' => 'FTTHProductInfoListAPI',
            'CG' => 'CGRouterProductInfoListAPI',
            default => throw new \InvalidArgumentException('Unsupported installation service type.'),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForServiceType(string $serviceType, string $productId): ?array
    {
        foreach ($this->productsForServiceType($serviceType) as $product) {
            if ($product['productID'] === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $productId): ?array
    {
        foreach ($this->products() as $product) {
            if ($product['productID'] === $productId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *     productID: string,
     *     productName: string,
     *     productPrice: float,
     *     monthCycle: int,
     *     monthCycleUnit: '月'|'天',
     *     productLine: string,
     *     productSpeed: string,
     *     installationFee: float|null
     * }>
     */
    private function fetchProducts(string $command): array
    {
        try {
            $response = $this->api->get($command);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Unable to retrieve the CG-Net product catalog.', previous: $exception);
        }

        $payload = $response->json();

        if (
            ! $response->successful()
            || ! is_array($payload)
            || ($payload['ErrCode'] ?? null) !== 0
            || ! is_string($payload['Message'] ?? null)
            || ! is_array($payload['result'] ?? null)
            || ! array_is_list($payload['result'])
        ) {
            throw new RuntimeException('CG-Net returned an invalid product catalog response.');
        }

        $products = [];

        foreach ($payload['result'] as $product) {
            if (! $this->validProduct($product)) {
                throw new RuntimeException('CG-Net returned an invalid product record.');
            }

            $products[] = [
                'productID' => (string) $product['productID'],
                'productName' => $product['productName'],
                'productPrice' => (float) $product['productPrice'],
                'monthCycle' => (int) $product['monthCycle'],
                'monthCycleUnit' => $product['monthCycleUnit'],
                'productLine' => $product['productLine'],
                'productSpeed' => $product['productSpeed'],
                'installationFee' => is_numeric($product['installationFee'] ?? null)
                    ? (float) $product['installationFee']
                    : null,
            ];
        }

        return $products;
    }

    private function validProduct(mixed $product): bool
    {
        return is_array($product)
            && (is_string($product['productID'] ?? null) || is_int($product['productID'] ?? null))
            && (string) $product['productID'] !== ''
            && is_string($product['productName'] ?? null)
            && trim($product['productName']) !== ''
            && is_numeric($product['productPrice'] ?? null)
            && (float) $product['productPrice'] > 0
            && is_numeric($product['monthCycle'] ?? null)
            && (int) $product['monthCycle'] > 0
            && in_array($product['monthCycleUnit'] ?? null, ['月', '天'], true)
            && is_string($product['productLine'] ?? null)
            && is_string($product['productSpeed'] ?? null)
            && (
                ! array_key_exists('installationFee', $product)
                || $product['installationFee'] === null
                || is_numeric($product['installationFee'])
            );
    }
}
