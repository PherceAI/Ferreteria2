<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Jobs;

use App\Domain\Purchasing\Exceptions\UnsupportedPurchaseDocumentException;
use App\Domain\Purchasing\Services\EcuadorianInvoiceXmlParser;
use App\Domain\Purchasing\Services\GmailApiService;
use App\Domain\Purchasing\Services\GmailOAuthService;
use App\Domain\Purchasing\Services\PurchaseInvoiceService;
use App\Domain\Purchasing\Services\SupplierDocumentFilterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("fetch-invoice-emails:{$this->branchId}"))->expireAfter(900),
        ];
    }

    public function handle(
        GmailOAuthService $oauthService,
        GmailApiService $gmailService,
        EcuadorianInvoiceXmlParser $parser,
        PurchaseInvoiceService $invoiceService,
        SupplierDocumentFilterService $supplierFilter,
    ): void {
        $tokens = collect($oauthService->activeTokens());

        if ($tokens->isEmpty()) {
            Log::warning('[FetchInvoiceEmails] Gmail no autorizado: no hay correos activos.');

            return;
        }

        foreach ($tokens as $token) {
            try {
                $accessToken = $oauthService->getValidAccessToken($token);
            } catch (RuntimeException $e) {
                Log::warning("[FetchInvoiceEmails] Gmail {$token->email} no autorizado: ".$e->getMessage());

                continue;
            }

            $messageIds = $gmailService->fetchUnreadPurchaseDocumentMessageIds($accessToken);

            if (empty($messageIds)) {
                continue;
            }

            Log::info("[FetchInvoiceEmails] {$this->branchId} {$token->email}: ".count($messageIds).' mensajes encontrados.');

            foreach ($messageIds as $messageId) {
                $this->processMessage($accessToken, $messageId, $gmailService, $parser, $invoiceService, $supplierFilter);
            }
        }
    }

    private function processMessage(
        string $accessToken,
        string $messageId,
        GmailApiService $gmailService,
        EcuadorianInvoiceXmlParser $parser,
        PurchaseInvoiceService $invoiceService,
        SupplierDocumentFilterService $supplierFilter,
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
            $metadata = $parser->metadata($xmlContent);

            if (! $supplierFilter->isAllowed($metadata, $this->branchId)) {
                Log::info("[FetchInvoiceEmails] Mensaje {$messageId} omitido: proveedor no reconocido en inventario o documento no relevante.", [
                    'document_type' => $metadata->documentType,
                    'supplier_ruc' => $metadata->supplierRuc,
                    'supplier_name' => $metadata->supplierName,
                    'from' => $fromEmail,
                ]);
                $gmailService->markAsRead($accessToken, $messageId);

                return;
            }

            if ($metadata->documentType === 'notaCredito') {
                Log::info('[FetchInvoiceEmails] Nota de credito relevante omitida para recepcion fisica.', [
                    'supplier_ruc' => $metadata->supplierRuc,
                    'supplier_name' => $metadata->supplierName,
                    'access_key' => $metadata->accessKey,
                    'from' => $fromEmail,
                ]);
                $gmailService->markAsRead($accessToken, $messageId);

                return;
            }

            $invoiceData = $parser->parse($xmlContent, $messageId, $fromEmail);

            $created = $invoiceService->createFromGmailMessage($invoiceData, $this->branchId);

            if ($created === null) {
                Log::debug("[FetchInvoiceEmails] Mensaje {$messageId} ya procesado, saltando.");
            } else {
                Log::info("[FetchInvoiceEmails] Factura creada: {$created->invoice_number} (ID: {$created->id})");
            }

            $gmailService->markAsRead($accessToken, $messageId);
        } catch (UnsupportedPurchaseDocumentException $e) {
            Log::info("[FetchInvoiceEmails] Mensaje {$messageId} omitido: ".$e->getMessage());
            $gmailService->markAsRead($accessToken, $messageId);
        } catch (Throwable $e) {
            Log::error("[FetchInvoiceEmails] Error procesando mensaje {$messageId}: ".$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
