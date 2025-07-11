<?php

namespace app\Console\Commands\Amazon\Load;

use app\Console\Commands\Amazon\BaseAmazonCommand;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Storage;

/**
 * Amazon SP API report base command
 * SP API - Selling Partner API
 *
 * @property string $baseUrl
 * @property Client $client
 * @property string $accessToken
 * @property string $reportType
 * @property array $accounts
 */

abstract class AmazonReportCommand extends BaseAmazonCommand
{
    protected string $reportType;
    protected string $accessToken;
    public static string $type;

    protected string $action = "reports/2021-06-30/reports";

    // ToDo: check if possible to get just necessary columns

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function handle()
    {
        parent::handle();
        $this->reportType = $this->getReportType();
        foreach ($this->accounts as $marketplaceId => $account) {
            if(!$this->logIn($account, $marketplaceId)) continue;
            $fileName = $this->getFileName($marketplaceId, 'txt');
            // Try to get info from the storage
            // Avoid big amount of report requests
            // Once a day per account
            $reportId = $this->getFileContent($fileName, 84600);
            if(is_null($reportId)) {
                $reportId = $this->fetchReportId($account, $marketplaceId, $fileName);
            }

            if (!($reportId ?? false) || !$this->checkReportStatus($marketplaceId, $fileName)) {
                $this->showErrorMessage("No report ID or report for the marketplace [$marketplaceId]");
            } else {
                $this->deleteReportFromRemote($reportId);
            }
        }

        return 0;
    }

    protected function getFileName(string $marketplaceId, string $extension = 'csv'): string
    {
        $type = static::$type;
        return "amazon/report_{$marketplaceId}_$type.$extension";
    }

    public function getMarketplaceFileNames(string $extension = 'csv'): array
    {
        if (!($this->accounts ?? false)) {
            $this->init();
        }

        $fileNames = [];
        foreach ($this->accounts as $marketplaceId => $account) {

            $fileNames[$marketplaceId] = $this->getFileName($marketplaceId, $extension);
        }

        return $fileNames;
    }

    protected function getFileContent(string $fileName, int $ttl): ?string
    {
        if (
            Storage::exists($fileName)
            && (time() - Storage::lastModified($fileName)) < $ttl
            && $token = Storage::get($fileName)
        ) {
            return $token;
        }

        return null;
    }

    protected function checkReportStatus(string $marketplaceId, string $fileName): bool
    {
        $reportId = $this->getFileContent($fileName, 84600);
        if (!$reportId) {
            $this->error("No report ID found for $marketplaceId");
            return false;
        }

        try {
            $attempts = 600;
            do {
                $response = $this->client->get($this->baseUrl . "/" . $this->action . "/$reportId", [
                    'headers' => $this->getHeaders()
                ]);
                $data = json_decode($response->getBody(), true);
                if ($data === null) {
                    $this->showErrorMessage("Failed to decode JSON response for marketplace $marketplaceId while checking report status");
                    return false;
                }

                if($data['processingStatus'] === 'DONE') {
                    $this->info("Report is ready for $marketplaceId, downloading...");
                    $downloadUrl = $data['reportDocumentId'];
                    $this->downloadReport($marketplaceId, $downloadUrl);
                    return true;
                }

                $this->info("Waiting {$this->sleepTime}s for report generating $marketplaceId: {$data['processingStatus']}");
                sleep($this->sleepTime);
                $attempts--;
            } while ($attempts > 0);
            $this->info("Report status for $marketplaceId: {$data['processingStatus']}");
        } catch (RequestException $e) {
            $this->showErrorMessage("Amazon SP API Fetch Error for marketplace $marketplaceId", $e);
        } catch (GuzzleException) {
        }

        return false;
    }

    protected function downloadReport($marketplaceId, $documentId): void
    {
        $filePath = 'amazon/report_' . strtolower($this->reportType) . "_$marketplaceId.csv";
        $report = $this->getFileContent($filePath, 84600);
        if ($report ?? false) {
            $this->info("Report exists: $filePath");
            return;
        }

        try {
            $response = $this->client->get($this->baseUrl . "/reports/2021-06-30/documents/$documentId", [
                'headers' => $this->getHeaders()
            ]);
            $data = json_decode($response->getBody(), true);
            if ($data === null) {
                $this->showErrorMessage("Failed to decode JSON response for marketplace $marketplaceId");
                return;
            }

            $fileContents = file_get_contents($data['url']);
            $filePath = $this->getFileName($marketplaceId);
            $gzFilePath = "$filePath.gz";
            Storage::put($gzFilePath, $fileContents);
            $gzFile = gzopen(Storage::path($gzFilePath), 'rb');
            Storage::put($filePath, $gzFile);
            Storage::delete($gzFilePath);
            $this->info("Report downloaded to: $filePath");
        } catch (RequestException $e) {
            $this->showErrorMessage("Amazon SP API Download Error for marketplace $marketplaceId", $e);
        }
    }

    protected function deleteReportFromRemote(string $reportId): void
    {
        try {
            // ToDo: check it
            /**
                curl --request DELETE \
                 --url https://sellingpartnerapi-na.amazon.com/reports/2021-06-30/reports/reportId \
                 --header 'accept: application/json'
             */
            $link = "$this->baseUrl/$this->action/$reportId";
            $this->client->delete( $link, [
                'headers' => $this->getHeaders(),
            ]);
            $this->info("Report deleted: $reportId");
        } catch (RequestException) {

        }
    }

    /**
     * @param array $account
     * @param string $marketplaceId
     * @param string $fileName
     * @return string|null
     * @throws GuzzleException
     */
    protected function fetchReportId(
        array $account,
        string $marketplaceId,
        string $fileName
    ): ?string
    {
        $retryCount = 0;
        $maxRetries = 5;
        $reportId = null;
        do {
            try {
                $response = $this->client->post($this->baseUrl . "/$this->action", [
                    'headers' => $this->getHeaders(),
                    'json' => [
                        'reportType' => $this->reportType,
                        'marketplaceIds' => [$account["marketplace_id"]],
                        'reportOptions' => [
                            "classification" => "VARIATION_PARENT",
                        ]
                    ]
                ]);
                $data = json_decode($response->getBody(), true);
                if ($data === null) {
                    $this->showErrorMessage("Failed to decode JSON response for marketplace $marketplaceId");
                    return null;
                }

                $reportId = $data['reportId'] ?? null;
                if ($reportId) {
                    Storage::put($fileName, $reportId);
                    $this->info("Report requested successfully for $marketplaceId. Report ID: $reportId");
                    break;
                }
            } catch (RequestException $e) {
                $this->showErrorMessage("Failed to request report for $marketplaceId. Attempt: " . ($retryCount + 1), $e);
                $retryCount++;
                sleep(min(10, 2 * $retryCount));
            } catch (Exception $e) {
                $this->showErrorMessage("General error for $marketplaceId", $e);
                $retryCount++;
                sleep(min(10, 2 * $retryCount));
            }
        } while ($retryCount < $maxRetries);

        if ($retryCount === $maxRetries) {
            $this->showErrorMessage("Failed to request report for $marketplaceId after $maxRetries attempts.");
        }

        return $reportId;
    }

}
