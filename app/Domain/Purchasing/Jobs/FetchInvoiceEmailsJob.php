<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Jobs;

use App\Domain\Purchasing\Services\EcuadorianInvoiceXmlParser;
use App\Domain\Purchasing\Services\GmailApiService;
use App\Domain\Purchasing\Services\GmailOAuthService;
use App\Domain\Purchasing\Services\PurchaseInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class FetchInvoiceEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly int $branchId,
    ) {}

    public function handle(
        GmailOAuthService $oauthService,
        GmailApiService $gmailService,
        EcuadorianInvoiceXmlParser $parser,
        PurchaseInvoiceService $invoiceService,
    ): void {
        try {
            $accessToken = $oauthService->getValidAccessToken();
        } catch (RuntimeException $e) {
            Log::warning('[FetchInvoiceEmails] Gmail no autorizado: '.$e->getMessage());

            return;
        }

        $messageIds = $gmailService->fetchUnreadInvoiceMessageIds($accessToken);

        if (empty($messageIds)) {
            return;
        }

        Log::info("[FetchInvoiceEmails] {$this->branchId}: ".count($messageIds).' mensajes encontrados.');

        foreach ($messageIds as $messageId) {
            $this->processMessage($accessToken, $messageId, $gmailService, $parser, $invoiceService);
        }
    }

    private function processMessage(
        string $accessToken,
        string $messageId,
        GmailApiService $gmailService,
        EcuadorianInvoiceXmlParser $parser,
        PurchaseInvoiceService $invoiceService,
    ): void {
        try {
            $message = $gmailService->getMessage($accessToken, $messageId);
            $xmlContent = $gmailService->extractXmlAttachment($accessToken, $message);

            if ($xmlContent === null) {
                Log::debug("[FetchInvoiceEmails] Mensaje {$messageId} sin adjunto XML válido, saltando.");
                $gmailService->markAsRead($accessToken, $messageId);

                return;
            }

            $fromEmail = $gmailService->getFromEmail($message);
            $invoiceData = $parser->parse($xmlContent, $messageId, $fromEmail);

            $created = $invoiceService->createFromGmailMessage($invoiceData, $this->branchId);

            if ($created === null) {
                Log::debug("[FetchInvoiceEmails] Mensaje {$messageId} ya procesado, saltando.");
            } else {
                Log::info("[FetchInvoiceEmails] Factura creada: {$created->invoice_number} (ID: {$created->id})");
            }

            $gmailService->markAsRead($accessToken, $messageId);
        } catch (Throwable $e) {
            Log::error("[FetchInvoiceEmails] Error procesando mensaje {$messageId}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
