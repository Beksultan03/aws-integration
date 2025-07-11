<?php

namespace app\Console\Commands\Amazon\Save;

use app\Console\Commands\Amazon\AmazonLoginTrait;
use app\Console\Commands\Amazon\Load\AmazonAllListingDataReportCommand;
use app\Console\Commands\BaseCommand;
use App\Models\Asin\Asin;
use App\Models\Marketplace\Marketplace;
use App\Models\Sku\Sku;
use App\Models\Sku\SkuAsin;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\Exception\ReaderNotOpenedException;
use Throwable;

ini_set('memory_limit', '-1');

class UpdateSkusAndAsins extends BaseCommand
{
    use AmazonLoginTrait;
    protected $signature = 'amazon:update:skus-and-asins';
    protected $description = 'Update SKUs and ASINs';
    protected string $marketPlaceId;
    protected int $batchSize = 1000;
    protected Client $client;

    /**
     * @throws ReaderNotOpenedException
     * @throws IOException
     */
    public function executeCommand(): array
    {
        $this->init();
        $amazonReports = (new AmazonAllListingDataReportCommand())->getMarketplaceFileNames();
        foreach ($amazonReports as $marketPlace => $reportFile) {
            $this->info("Saving for marketplace [$marketPlace] started...");
            /** @var Marketplace $marketPlaceId */
            $this->marketPlaceId = Marketplace::query()->where('name', $marketPlace)->first()->id;
            $amazonReportProducts = $this->parseAmazonReport($reportFile, $marketPlace);
            $this->saveSkuDictionary($amazonReportProducts);
            $this->saveAsinsDictionary($amazonReportProducts);
        }
        $this->saveAttributesAndRelations();

        return [];
    }

    /**
     * @param string $fileName
     * @return array
     * @throws Exception
     */
    protected function parseAmazonReport(string $fileName, string $marketplaceName): array
    {
        if (!Storage::exists($fileName)) {
            throw new Exception("No such file: $fileName");
        }

        $products = [];
        $asinsToSkus = [];
        $columns = [3 => 'sku', 16 => 'asin', 5 => 'quantity', 28 => 'status'];
        $columnKeys = array_keys($columns);
        $notUniqueAsins = [];
        $rows = explode("\n", Storage::get($fileName));
        // To check order of properties
        $headers = str_getcsv(array_shift($rows));
        $headers = explode("\t", $headers[0]);
        foreach ($rows as $rowIdx => $row) {
            $productData = explode("\t", $row);
            $product = [];
            foreach ($columns as $columnIndex => $column) {
                if (!in_array($columnIndex, $columnKeys)) continue;
                if (array_key_exists($columnIndex, $productData) && isset($productData[$columnIndex])) {
                    $value = $productData[$columnIndex];
                    if($column == 'status') {
                        $value = match ($value) {
                            'Active' => 1,
                            'Incomplete' => 2,
                            default => 0,
                        };
                    }

                    if($column == 'quantity') {
                        $value = (int) $value;
                    }

                    $product[$column] = $value;
                }
            }

            if (($product['sku'] ?? false)) {
                // $product['sku_alias'] = $product['sku'];
                if(str_contains($product['sku'], "-$marketplaceName")) {
                    $product['sku'] = explode("-$marketplaceName", $product['sku'])[0];
                }
            }

            if (
                ($product['sku'] ?? false) && ($product['asin'] ?? false)
                && array_key_exists('quantity', $product) && array_key_exists('status', $product)
            ) {
                if ($asinsToSkus[$product['asin']] ?? false) {
                    $notUniqueAsins[$product['asin']] = $product['sku'];
                }
                $products[$product['sku']] = $product;
                $asinsToSkus[$product['asin']] = $product['sku'];
            }
        }

        if(count($notUniqueAsins) > 0) {
            $this->info("[$this->signature][parseAmazonReport] $fileName has " . count($notUniqueAsins) . " not unique ASINS");
        }
        $this->info("[$this->signature][parseAmazonReport] $fileName has " . count($products) . " valid products");


        return $products;
    }

    /**
     * @param array $productsRelations
     * @return void
     */
    protected function saveSkuDictionary(array &$productsRelations): void
    {
        $data = [];
        foreach ($productsRelations as $productRelation) {
            if ($productRelation['status'] != 1) {
                continue;
            }

            $data[] = ['value' => $productRelation['sku']];
            if (count($data) >= $this->batchSize) {
                Sku::upsert($data, ['value']);
                $data = [];
                $this->info("SKUs $this->batchSize updated/inserted");
            }
        }

        if (!empty($data)) {
            Sku::upsert($data, ['value']);
            $this->info("SKUs " . count($data) . " updated/inserted");
        }
    }

    /**
     * @param array $productsRelations
     * @return void
     */
    protected function saveAsinsDictionary(array &$productsRelations): void
    {
        $data = [];
        foreach ($productsRelations as $productRelation) {
            if ($productRelation['status'] != 1) {
                continue;
            }

            if (($productRelation['sku'] ?? false) && ($productRelation['asin'] ?? false)) {
                $data[] = $productRelation;
            }

            if (count($data) >= $this->batchSize) {
                $this->bulkProcessing($data);
                $data = [];
            }
        }

        if (!empty($data)) {
            $this->bulkProcessing($data);
        }
    }

    /**
     * @param array $data
     * @return void
     */
    protected function bulkProcessing(array $data): void {
        $this->saveAsins($data);
        $this->saveSkuToAsinDictionary($data);
    }

    /**
     * @param array $data
     * @return void
     */
    protected function saveAsins(array $data): void
    {
        $asins = [];
        foreach ($data as $item) {
            $asins[] = ['value' => $item['asin']];
        }

        Asin::upsert($asins, ['value']);
        $this->info("ASINs " . count($asins) . " updated/inserted");
    }

    /**
     * @param array $data
     * @return void
     */
    protected function saveSkuToAsinDictionary(array $data): void
    {
        if (empty($data)) return;

        $skuValues = array_column($data, 'sku');
        $asinValues = array_column($data, 'asin');
        $skus = Sku::whereIn('value', $skuValues)->pluck('id', 'value')->toArray();
        $asins = Asin::whereIn('value', $asinValues)->pluck('id', 'value')->toArray();
        // ToDo: take status mapping from DB
        $skuAsinPairs = [];
        foreach ($data as $item) {
            if (($skus[$item['sku']] ?? false) && ($asins[$item['asin']] ?? false)) {
                $skuAsinPairs[] = [
                    'sku_id' => $skus[$item['sku']],
                    'asin_id' => $asins[$item['asin']],
                    'marketplace' => $this->marketPlaceId,
                    'status' => $item['status'],
                    'quantity' => $item['quantity'],
                ];
            } else {
                $message = 'Data corupted.';
            }
        }

        if (!empty($skuAsinPairs)) {
            SkuAsin::upsert(
                $skuAsinPairs,
                ['sku_id', 'asin_id', 'marketplace'],
                ['status', 'quantity']
            );
            $this->info("SKUs to ASINs " . count($skuAsinPairs) . " updated/inserted");
        }
    }

    /**
     * @return void
     * @throws GuzzleException
     */
    protected function saveAttributesAndRelations(): void
    {

        // Debug start
//        $parentChildren = Storage::get('tempParentChildrenFromAmazon.json');
//        $parentChildren = json_decode($parentChildren, true);
//        $this->saveAsinsRelations($parentChildren, 1);
//        return;
        // Debug end


        $amazonIdLimit = 10;
        $handleJustActive = true;
        $activeStatus = 1;
        foreach ($this->accounts as $name => $account) {
            $query = SkuAsin::query()
                ->select([
                    'sku_asin.id',
                    'sku_asin.marketplace as marketplace_name',
                    'sku.value as sku_value',
                    'asin.value as asin_value',
                ])
                ->leftJoin('sku', 'sku_asin.sku_id', '=', 'sku.id')
                ->leftJoin('asin', 'sku_asin.asin_id', '=', 'asin.id')
                ->leftJoin('marketplace', 'sku_asin.marketplace', '=', 'marketplace.id');

            if ($handleJustActive) {
                $query->where('sku_asin.status', $activeStatus);
            }

            $marketPlaceId = Marketplace::query()->where('name', $name)->first()->id;
            $query->where('marketplace', $marketPlaceId);
//            $query->whereIn('sku_asin.status', [1, 2]); // Just active

            $this->logIn($account, $name);
            $items = [];
            // Start pagination, using `paginate` for page-based navigation
            $page = 1; // Start from the first page
            do {
                // Fetch the paginated records with the `paginate` method
                $records = $query->paginate($amazonIdLimit, ['*'], 'page', $page);
                // Process the current page of records
                $response = $this->getDataWithRetries($records, $account);
                if($response['items'] ?? false) {
                    foreach ($response['items'] as $responseItem) {
                        if (($responseItem['asin'] ?? false) && !($items[$responseItem['asin']] ?? false)) {
                            $items[$responseItem['asin']] = $responseItem;
                        } else {
                            $this->info('Duplicate items found.');
                        }
                    }
                }

                // Check if there are more pages (based on the pagination result)
                $hasMorePages = $records->hasMorePages();
                $page++; // Increment the page number for the next iteration
                if (count($items) > 1000) {
                    $parentChildren = $this->buildParentChildTreeFromAmazonData($items);
                    $this->saveAsinsRelations($parentChildren, $marketPlaceId);
                    $items = [];
                }


            } while ($hasMorePages && $page <= 50); // Limit the number of pages if necessary

            if (count($items) > 0) {
                $parentChildren = $this->buildParentChildTreeFromAmazonData($items);
                $this->saveAsinsRelations($parentChildren, $marketPlaceId);
            }
        }
    }


    /**
     * Main logic for making requests with retries
     *
     * @param mixed $records
     * @param array $account
     * @param int $maxRetries
     * @param int $waitTime
     * @return array|null
     * @throws GuzzleException
     */
    public function getDataWithRetries(
        mixed $records,
        array $account,
        int $maxRetries = 10,
        int $waitTime = 1
    ): ?array
    {
        $attempt = 0;
        $asins = [];
        foreach ($records as $record) {
            $asins[] = $record->asin_value;
        }

        if (empty($asins) || !is_array($asins)) {
            return [];
        }

        while ($attempt < $maxRetries) {
            // Relogin
            $this->logIn($account, $account['name']);
            try {
                $asins = implode(',', $asins);
                dump($asins);
                $response = $this->client->get("$this->baseUrl/catalog/2022-04-01/items" , [
                    'headers' => $this->getHeaders(),
                    'query' => [
                        'identifiersType' => 'ASIN',
                        'identifiers' => $asins,
                        'marketplaceIds' => $account['marketplace_id'],
                        'includedData' => 'summaries,relationships'
                        // ToDo: add attributes
                        /*'includedData' => 'summaries,attributes,relationships'*/
                    ],
                ]);
                if ($response->getStatusCode() == 200) {
                    $body = $response->getBody();
                    return json_decode($body, true);
                } else {
                    $this->error("Error {$response->getStatusCode()}: {$response->getBody()}");
                }
            } catch (Throwable $e) {
                $this->info("Request error: " . $e->getMessage());
                sleep($waitTime);
            }

            $attempt++;
            $this->info("Attempt {$attempt} failed, retrying in {$waitTime} second(s)...");
        }

        $this->error("Reached the maximum number of attempts. Failed to retrieve data.");

        return null;
    }

    /**
     * Build a parent-child tree from Amazon data (variations and parents).
     *
     * @param array $amazonItems - List of items retrieved from Amazon API
     * @return array - Parent-child tree structure
     */
    protected function buildParentChildTreeFromAmazonData(array $amazonItems): array
    {
        $parentsWithChildren = [];
        foreach ($amazonItems as $item) {
            $asin = $item['asin'];
            if ($item['relationships'] ?? false) {
                foreach ($item['relationships'] as $child) {
                    if ($child['relationships'] ?? false) {
                        foreach ($child['relationships'] as $relation) {
                            if($relation['parentAsins'] ?? false) {
                                foreach ($relation['parentAsins'] as $parentAsin) {
                                    if (!($parentsWithChildren[$parentAsin] ?? false)) {
                                        $parentsWithChildren[$parentAsin] = ['asin' => $parentAsin, 'children' => []];
                                    }
                                    if ($parentsWithChildren[$parentAsin]['validatedParent'] ?? false) {
                                        $this->info("[ASIN: $asin] Ignore parent [$parentAsin] parent is validated.");
                                        break;
                                    }
                                    $parentsWithChildren[$parentAsin]['children'][$asin] = ['asin' => $asin, 'attributes' => []];
                                }
                            }
                            if($relation['childAsins'] ?? false) {
                                $parentsWithChildren[$asin] = ['asin' => $asin, 'children' => [], 'validatedParent' => true];
                                if ($relation['childAsins'] ?? false) {
                                    $this->info("[ASIN: $asin] Validated parent.");
                                }
                                foreach ($relation['childAsins'] as $childAsin) {

                                    if (in_array($childAsin, ['B0C2115FWD', 'B0C211LD9H', 'B0C1ZYVKSR', 'B0C1ZY2469', 'B0C1ZXFRGN', 'B0C2126M1F', 'B0C1ZZ1GF9', 'B0C1ZZZYSH', 'B0C1ZXN81G', 'B0C1ZYTQYK', 'B0C1ZYHSM7', 'B0C1ZZY65M', 'B0C1ZYY94M', 'B0C1ZZ44DD', 'B0C211D28M'])) {
                                        $qq = '';
                                    }
                                    $parentsWithChildren[$asin]['children'][$childAsin] = ['asin' => $childAsin, 'attributes' => []];
                                }
                            }
                        }
                    } else {
                        $parentsWithChildren[$asin] = ['asin' => $asin, 'children' => [], 'validatedParent' => true];
                    }
                }
            }
        }

        return $parentsWithChildren;
    }

    /**
     * Virtual parent / lost ASIN
     *
     * @param array $asin
     * @param int $marketplaceId
     * @param array $parentChildren
     * @param bool $isParent
     * @return void
     */
    protected function checkAndGetAsinData(
        array $asin,
        int $marketplaceId,
        array &$parentChildren,
        bool $isParent = false): ?array
    {
        // ToDo: temporary solution. We should think how to make it faster.
        try {
            if (!($asin['id'] ?? false)) {
                $newAsin = Asin::query()
                    ->where('value', $asin['asin'])
                    ->with('skuAsins')
                    ->firstOrNew();

                if (!($newAsin->value ?? false)) {
                    $newAsin->value = $asin['asin'];
                    $newAsin->save();
                }

                if ($isParent) {
                    $asin['parent_asin'] = null;
                    $newAsin->save();
                }

                if (!($newAsin->skuAsin ?? false) || count($newAsin->skuAsin) <= 0) {
                    $newSkuAsin = new SkuAsin();
                    $newSkuAsin->asin_id = $newAsin->id;
                    $newSkuAsin->marketplace = $marketplaceId;
                    $newSkuAsin->status = 1;
                    $newSkuAsin->parent_asin = $asin['parent_asin'] ?? null;
                    $newSkuAsin->quantity = 0;
                    $newSkuAsin->save();
                    $asin['id'] = $newAsin->id;
                    $asin['parent_asin'] = $newSkuAsin->parent_asin;

                }

                if(!($asin['asin'] ?? false)) {
                    $asin['asin'] = $newAsin->asin;
                }
                $asin['asin_id'] = $newAsin->id;
            }

            /*if (in_array($asin['asin'], ['B0C212Q7KD',
                'B0C2115FWD', 'B0C211LD9H', 'B0C1ZYVKSR', 'B0C1ZY2469', 'B0C1ZXFRGN', 'B0C2126M1F', 'B0C1ZZ1GF9', 'B0C1ZZZYSH', 'B0C1ZXN81G', 'B0C1ZYTQYK', 'B0C1ZYHSM7', 'B0C1ZZY65M', 'B0C1ZYY94M', 'B0C1ZZ44DD', 'B0C211D28M'])) {
                $qq = '';
            }*/

            $product = [
                'id' => $asin['id'],
                'asin_id' => $asin['asin_id'],
                'parent_asin' => $asin['parent_asin'],
                'marketplace' => $marketplaceId,
                'status' => 1,  // ToDo: get status id from DB
                'quantity' => 0,
            ];

            $parentChildren[] = $product;

            return $product;
        } catch (Throwable $throwable) {
            // To do log errors
            $this->info($throwable->getMessage());
        }

        return null;
    }

    /**
     * @param array $data
     * @param int $marketplaceId
     * @return void
     */
    protected function saveAsinsRelations(array $data, int $marketplaceId): void
    {
        if (empty($data)) return;
        /**
         * // Debug start
        Storage::put("tempParentChildrenFromAmazon.json", json_encode($data, 256));
        // Debug end
        */
        $data = $this->fillData($data, $marketplaceId);
        $parentChildren = [];
        foreach ($data as $parentId => $parent) {
            $parentElement = null;
            if ($parentId != 'deprecated') {
                $parentElement = $this->checkAndGetAsinData($parent, $marketplaceId, $parentChildren, true);
            }

            foreach ($parent['children'] as $child) {
                if ($parentElement) {
                    $child['parent_asin'] = $parentElement['asin_id'];
                }
                $this->checkAndGetAsinData($child, $marketplaceId, $parentChildren, false);
            }
        }

        $chunks = array_chunk($parentChildren, 1000);
        foreach ($chunks as $chunk) {
            SkuAsin::upsert($chunk, ['id', 'asin_id', 'marketplace'], ['parent_asin']);
        }

//        SkuAsin::upsert($parentChildren, ['id', 'asin_id', 'marketplace'], ['parent_asin']);
//        $query = SkuAsin::query();
//        $query->chunk(1000, function ($parentChildren) {
//            $dataChunk = $parentChildren->toArray();
//            $dataChunk = collect($dataChunk)->map(function ($item) {
//                $item['created_at'] = Carbon::parse($item['created_at'])->format('Y-m-d H:i:s');
//                $item['updated_at'] = Carbon::parse($item['updated_at'])->format('Y-m-d H:i:s');
//                return $item;
//            })->toArray();
//            if (!empty($dataChunk)) {
//                SkuAsin::upsert($dataChunk, ['id', 'asin_id', 'marketplace'], ['parent_asin']);
//            }
//        });
    }

    /**
     * @param array $data
     * @return array
     */
    protected function childAsins(array $data): array
    {
        $childrenAsins = [];
        foreach ($data as $parent) {
            if ($parent['children'] ?? false) {
               foreach ($parent['children'] as $child) {
                   $childrenAsins[] = $child['asin'];
               }
            }
        }

        return $childrenAsins;
    }

    /**
     * @param array $data
     * @param $marketplaceId
     * @return void
     */
    protected function fillData(array $data, $marketplaceId): array
    {
        $parentAsins = array_keys($data);
        $childrenAsins = $this->childAsins($data);
        $allAsins = array_merge($parentAsins, $childrenAsins);
        $allAsins = array_unique($allAsins);
        $fields = ['sku_asin.id', 'asin.id as asin_id', 'asin.value', 'sku_asin.parent_asin', 'sku_asin.quantity'];
        $parentAsinsWithId = Asin::byAsins($allAsins, $marketplaceId, $fields)
            ->paginate(1000)
            ->pluck('id', 'value')->toArray();
        $chunkSize = 1000;
        $allAsinsChunks = array_chunk($allAsins, $chunkSize);
        $result = [];
        foreach ($allAsinsChunks as $chunk) {
            $chunkResults = Asin::byAsins($chunk, $marketplaceId, $fields)
                ->orWhere(function ($query) use ($marketplaceId, $parentAsinsWithId) {
                    $query->whereIn('sku_asin.parent_asin', $parentAsinsWithId)
                        ->where('sku_asin.marketplace', $marketplaceId);
                })
                ->get()
                ->toArray();
            $result = array_merge($result, $chunkResults);
        }

        $allAsins = $result;
        $asins = [];
        foreach ($allAsins as $asin) {
            if ($asins[$asin['value']] ?? false) {
                if ($asin['quantity'] <= 0) {
                    $this->info("Duplicate ASIN {$asin['value']} qty:{$asin['quantity']}");
                    continue;
                }
            }

            $asins[$asin['value']] = [
                'id' => $asin['id'],
                'asin_id' => $asin['asin_id'],
                'parent_asin_id' => $asin['parent_asin'],
            ];
        }

        foreach ($data as &$parentAsin) {
            if ($asins[$parentAsin['asin']] ?? false) {
                $parentAsin['id'] = $asins[$parentAsin['asin']]['id'];
                $parentAsin['asin_id'] = $asins[$parentAsin['asin']]['asin_id'];
                $parentAsin['parent_asin'] = null;
                if ($parentAsin['children'] ?? false) {
                    foreach ($parentAsin['children'] as &$child) {
                        if($asins[$child['asin']] ?? false ){
                            $child['id'] = $asins[$child['asin']]['id'];
                            $child['asin_id'] = $asins[$child['asin']]['asin_id'];
                            $child['parent_asin'] = $parentAsin['asin_id'];
                            unset($asins[$child['asin']]);
                        }
                    }
                }

                unset($asins[$parentAsin['asin']]);
            }
        }

        // Stayed ASINs are ready to vanish parents to NULL
        foreach ($asins as $asinValue => $asin) {
            $data['deprecated']['children'][] = [
                'id' => $asin['id'],
                'asin' => $asinValue,
                'asin_id' => $asin['asin_id'],
                'parent_asin' => null
            ];
        }

        return $data;
    }

}
