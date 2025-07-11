<?php

namespace App\BlueOcean\Helper;

use App\BlueOcean\Exceptions\ApiException;
use App\BlueOcean\Exceptions\BlueOceanException;
use CurlHandle;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CurlHelper
{
    protected string $url;
    protected const string API_URL = 'https://customer.blueoceanglobe.com/api/';
    protected const int ERROR_RESPONSE_STATUS = 2;
    public const int NUMBER_OF_RETRIES = 3;
    public const int BETWEEN_REQUESTS_WAITING_TIME = 1000;
    public const int FIRST_TRY_WAITING_TIME = 10;
    public const int PAGE_SIZE = 500;
    protected CurlHandle $curl;

    private bool $firstTry = true;

    public function __construct(
        protected string $action,
        protected array  $data
    )
    {
        $this->url = self::API_URL . $action;
        $this->curl = curl_init($this->url);
        if ($this->data['page'] ?? false) {
            $this->data['page'] = (string)$this->data['page'];
        }
        $this->setOptions();
    }

    /**
     * @throws ApiException
     */
    protected function checkConnection(?array $currentResponse): void
    {
        if (isset($currentResponse['result']) && (int)$currentResponse['result'] === self::ERROR_RESPONSE_STATUS) {
            throw new ApiException("Couldn't connect to API: " . ($currentResponse['data'] ?? ''));
        }

        if (!empty($currentResponse['data']['order_array'])) {
            $orders = [];
            foreach ($currentResponse['data']['order_array'] as $order) {
                if (!isset($order['error'])) {
                    continue;
                }

                if ($order['error'] !== 'success') {
                    $orders[] = [
                        'order_id' => $order['order_id'],
                        'message' => $order['error'],
                    ];
                }
            }

            if (!empty($orders)) {
                throw BlueOceanException::create(
                    'Orders are processed: ' . implode(', ', array_column($orders, 'order_id')) . ', but we have problems with some.',
                    $orders
                );
            }
        }
    }

    protected function getHeaders(): array
    {
        return ["Content-Type: application/json"];
    }

    protected function getRequestBody(): array
    {
        return [
            'proc' => $this->action,
            'key' => env('BLUE_OCEAN_KEY'),
            'data' => $this->data,
        ];
    }

    protected function setOptions(): void
    {
        $options = [
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            // CURLOPT_PROXY => '127.0.0.1:8090',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_POSTFIELDS => json_encode($this->getRequestBody()),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];

        if (config('app.debug')) {
            $options[CURLOPT_SSL_VERIFYHOST] = false;
            $options[CURLOPT_SSL_VERIFYPEER] = false;
        }

        curl_setopt_array($this->curl, $options);
    }

    /**
     * Request attempt
     *
     * @return mixed
     */
    protected function request(): mixed
    {
        try {
            $curlResponse = curl_exec($this->curl);
            if ($curlResponse === false) {
                throw new ApiException("cURL error: " . curl_error($this->curl));
            }

            $curlResponse = urldecode($curlResponse);
            $response = json_decode($curlResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException("JSON decode error: " . json_last_error_msg());
            }

            $this->checkConnection($response);
        } catch (BlueOceanException|ApiException $e) {
            $response['customMessage'] =
                $e->getMessage()
                . "<br>"
                . ($e instanceof BlueOceanException ? $e->getIncorrectOrdersMessage() : '');
        } finally {
            curl_close($this->curl);
        }

        return $response;
    }

    /**
     * @throws Throwable
     */
    public function execute(): ?array
    {
        return retry(
            self::NUMBER_OF_RETRIES,
            function () {
                try {
                    return $this->request();
                } catch (Exception $e) {
                    Log::error($e->getMessage(), $response ?? []);
                    throw $e;
                }
            },
            self::BETWEEN_REQUESTS_WAITING_TIME,
            function () {
                if ($this->firstTry) {
                    sleep(self::FIRST_TRY_WAITING_TIME);
                    $this->firstTry = false;
                }

                return true;
            }
        );
    }

    /**
     * @throws Throwable
     */
    public static function exec(string $action, array $data): array
    {
        return (new self($action, $data))->execute();
    }

    /**
     * @throws Throwable
     */
    public static function execWithPaging (
        string $action,
        array $conditions = [],
        ?int $pageSize = null): array
    {
        $pageNumber = 0;
        $pageQuantity = -1;
        $data = [];
        $requestCondition = [
            ...$conditions,
//            'page' => &$pageNumber,
            'page_size' => (string) ($pageSize ?? self::PAGE_SIZE),
        ];
        do{
            try {
                $response = self::exec($action, $requestCondition + ['page' => (string) $pageNumber]);
                $requestData = $response['data'] ?? null;
                if($pageQuantity < 0) $pageQuantity = $response['page_count'];
                if (is_null($requestData)) {
                    $pageNumber = -1;
                }
                $data = array_merge($data, $requestData);
                $pageNumber++;
                if (env('APP_DEBUG')) {
                    dump("Page number $pageNumber of $pageQuantity");
                }
                sleep(1);
            } catch (Throwable $e) {
                dump($e->getMessage());
                dump('Sleep: 1s');
                sleep(1);
            }

        } while ($pageNumber < $pageQuantity);

        return $data;
    }

}
