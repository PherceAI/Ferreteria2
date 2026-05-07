<?php

declare(strict_types=1);

namespace Tests\Integration\Purchasing;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\DTOs\PurchaseDocumentMetadata;
use App\Domain\Purchasing\Services\EcuadorianInvoiceXmlParser;
use App\Domain\Purchasing\Services\GmailApiService;
use App\Domain\Purchasing\Services\SupplierDocumentFilterService;
use App\Models\Branch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PurchaseDocumentFilteringTest extends TestCase
{
    use DatabaseTransactions;

    public function test_supplier_filter_allows_only_known_inventory_suppliers_with_relevant_xml_documents(): void
    {
        $branch = Branch::factory()->create();

        InventoryProduct::create([
            'branch_id' => $branch->id,
            'code' => 'P-001',
            'name' => 'Producto ferretero',
            'current_stock' => 1,
            'supplier_code' => '0999999999001',
            'supplier_name' => 'Proveedor Test S.A.',
        ]);

        $filter = app(SupplierDocumentFilterService::class);

        $this->assertTrue($filter->isAllowed(new PurchaseDocumentMetadata(
            documentType: 'factura',
            supplierRuc: '0999999999001',
            supplierName: 'Proveedor Distinto',
        ), $branch->id));

        $this->assertTrue($filter->isAllowed(new PurchaseDocumentMetadata(
            documentType: 'notaCredito',
            supplierRuc: '0000000000000',
            supplierName: 'Proveedor Test SA',
        ), $branch->id));

        $this->assertFalse($filter->isAllowed(new PurchaseDocumentMetadata(
            documentType: 'factura',
            supplierRuc: '0111111111001',
            supplierName: 'Proveedor Desconocido',
        ), $branch->id));

        $this->assertFalse($filter->isAllowed(new PurchaseDocumentMetadata(
            documentType: 'retencion',
            supplierRuc: '0999999999001',
            supplierName: 'Proveedor Test S.A.',
        ), $branch->id));
    }

    public function test_parser_reads_metadata_for_invoice_and_credit_note_xml(): void
    {
        $parser = app(EcuadorianInvoiceXmlParser::class);

        $invoice = $parser->metadata($this->invoiceXml());
        $creditNote = $parser->metadata($this->creditNoteXml());

        $this->assertSame('factura', $invoice->documentType);
        $this->assertSame('0999999999001', $invoice->supplierRuc);
        $this->assertSame('Proveedor Test S.A.', $invoice->supplierName);

        $this->assertSame('notaCredito', $creditNote->documentType);
        $this->assertSame('0999999999001', $creditNote->supplierRuc);
        $this->assertSame('Proveedor Test S.A.', $creditNote->supplierName);
    }

    public function test_gmail_xml_extraction_finds_nested_xml_attachments_only(): void
    {
        $service = app(GmailApiService::class);
        $xml = '<factura><infoTributaria /></factura>';

        $message = [
            'id' => 'gmail-message-id',
            'payload' => [
                'mimeType' => 'multipart/mixed',
                'parts' => [[
                    'mimeType' => 'multipart/alternative',
                    'parts' => [[
                        'filename' => 'factura.xml',
                        'mimeType' => 'application/xml',
                        'body' => [
                            'data' => rtrim(strtr(base64_encode($xml), '+/', '-_'), '='),
                        ],
                    ]],
                ]],
            ],
        ];

        $this->assertSame($xml, $service->extractXmlAttachment('unused-token', $message));
    }

    private function invoiceXml(): string
    {
        return <<<'XML'
<factura>
    <infoTributaria>
        <ruc>0999999999001</ruc>
        <razonSocial>Proveedor Test S.A.</razonSocial>
        <claveAcceso>123</claveAcceso>
    </infoTributaria>
    <infoFactura>
        <fechaEmision>01/05/2026</fechaEmision>
        <importeTotal>10.00</importeTotal>
    </infoFactura>
    <detalles>
        <detalle>
            <codigoPrincipal>P-001</codigoPrincipal>
            <descripcion>Producto</descripcion>
            <cantidad>1</cantidad>
            <precioUnitario>10</precioUnitario>
            <subtotal>10</subtotal>
        </detalle>
    </detalles>
</factura>
XML;
    }

    private function creditNoteXml(): string
    {
        return <<<'XML'
<notaCredito>
    <infoTributaria>
        <ruc>0999999999001</ruc>
        <razonSocial>Proveedor Test S.A.</razonSocial>
        <claveAcceso>456</claveAcceso>
    </infoTributaria>
</notaCredito>
XML;
    }
}
