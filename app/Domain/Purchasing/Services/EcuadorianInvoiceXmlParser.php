<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\DTOs\InvoiceLineItemData;
use App\Domain\Purchasing\DTOs\PurchaseDocumentMetadata;
use App\Domain\Purchasing\DTOs\PurchaseInvoiceData;
use App\Domain\Purchasing\Exceptions\UnsupportedPurchaseDocumentException;
use RuntimeException;
use SimpleXMLElement;

final class EcuadorianInvoiceXmlParser
{
    /**
     * Parsea el XML de comprobante electrónico SRI Ecuador (formato factura).
     * Soporta tanto el XML plano como el XML firmado (envuelto en <comprobante>).
     */
    public function parse(string $xmlContent, string $gmailMessageId, string $fromEmail): PurchaseInvoiceData
    {
        $xml = $this->loadDocumentXml($xmlContent);

        // Ahora $xml debe ser <factura>
        if (strtolower($xml->getName()) !== 'factura') {
            throw new UnsupportedPurchaseDocumentException("Tipo de comprobante no soportado para recepcion fisica: {$xml->getName()}. Solo se crean recepciones para facturas.");
        }

        $tributaria = $xml->infoTributaria;
        $infoFactura = $xml->infoFactura;

        $ruc = (string) $tributaria->ruc;
        $razonSocial = (string) $tributaria->razonSocial;
        $claveAcceso = (string) $tributaria->claveAcceso;

        $estab = (string) $tributaria->estab;
        $ptoEmi = (string) $tributaria->ptoEmi;
        $secuencial = (string) $tributaria->secuencial;
        $invoiceNumber = "{$estab}-{$ptoEmi}-{$secuencial}";

        $emissionDate = $this->parseDate((string) $infoFactura->fechaEmision);
        $total = (float) $infoFactura->importeTotal;

        $items = $this->parseItems($xml->detalles);

        return new PurchaseInvoiceData(
            supplierRuc: $ruc,
            supplierName: $razonSocial,
            invoiceNumber: $invoiceNumber,
            accessKey: $claveAcceso,
            emissionDate: $emissionDate,
            total: $total,
            items: $items,
            gmailMessageId: $gmailMessageId,
            fromEmail: $fromEmail,
        );
    }

    public function metadata(string $xmlContent): PurchaseDocumentMetadata
    {
        $xml = $this->loadDocumentXml($xmlContent);
        $tributaria = $xml->infoTributaria;

        return new PurchaseDocumentMetadata(
            documentType: $xml->getName(),
            supplierRuc: (string) ($tributaria->ruc ?? ''),
            supplierName: (string) ($tributaria->razonSocial ?? ''),
            accessKey: (string) ($tributaria->claveAcceso ?? '') ?: null,
        );
    }

    private function loadDocumentXml(string $xmlContent): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string(trim($xmlContent));

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new RuntimeException('XML inválido: '.($errors[0]->message ?? 'parse error'));
        }

        // El SRI firma el XML y lo envuelve en <autorizacion><comprobante>...</comprobante></autorizacion>
        // Si el root es autorizacion, extraemos el comprobante interno
        if (strtolower($xml->getName()) === 'autorizacion') {
            $inner = (string) ($xml->comprobante ?? '');
            if ($inner === '') {
                $matches = $xml->xpath('//*[local-name()="comprobante"]');
                $inner = isset($matches[0]) ? (string) $matches[0] : '';
            }

            $xml = simplexml_load_string(html_entity_decode($inner));

            if ($xml === false) {
                throw new RuntimeException('No se pudo parsear el comprobante dentro de la autorización SRI.');
            }
        }

        return $xml;
    }

    /**
     * @return array<InvoiceLineItemData>
     */
    private function parseItems(SimpleXMLElement $detalles): array
    {
        $items = [];

        foreach ($detalles->detalle as $detalle) {
            $items[] = new InvoiceLineItemData(
                code: (string) ($detalle->codigoPrincipal ?? $detalle->codigoAuxiliar ?? ''),
                description: (string) $detalle->descripcion,
                quantity: (float) $detalle->cantidad,
                unitPrice: (float) $detalle->precioUnitario,
                subtotal: (float) ($detalle->subtotal ?? ((float) $detalle->cantidad * (float) $detalle->precioUnitario)),
            );
        }

        return $items;
    }

    /** Convierte dd/mm/yyyy a yyyy-mm-dd */
    private function parseDate(string $date): string
    {
        if (str_contains($date, '/')) {
            [$d, $m, $y] = explode('/', $date);

            return "{$y}-{$m}-{$d}";
        }

        return $date;
    }
}
