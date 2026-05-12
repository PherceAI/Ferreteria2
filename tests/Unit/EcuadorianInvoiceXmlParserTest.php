<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Purchasing\Exceptions\UnsupportedPurchaseDocumentException;
use App\Domain\Purchasing\Services\EcuadorianInvoiceXmlParser;
use RuntimeException;
use Tests\TestCase;

final class EcuadorianInvoiceXmlParserTest extends TestCase
{
    public function test_it_parses_valid_invoice_xml(): void
    {
        $invoice = $this->parser()->parse($this->invoiceXml(), 'gmail-123', 'proveedor@example.com');

        $this->assertSame('1790012345001', $invoice->supplierRuc);
        $this->assertSame('Proveedor Ferretero SA', $invoice->supplierName);
        $this->assertSame('001-002-000000123', $invoice->invoiceNumber);
        $this->assertSame('1105202601179001234500110010020000001231234567811', $invoice->accessKey);
        $this->assertSame('2026-05-11', $invoice->emissionDate);
        $this->assertSame(42.50, $invoice->total);
        $this->assertSame('gmail-123', $invoice->gmailMessageId);
        $this->assertSame('proveedor@example.com', $invoice->fromEmail);
        $this->assertCount(2, $invoice->items);
        $this->assertSame('MT-001', $invoice->items[0]->code);
        $this->assertSame('Martillo', $invoice->items[0]->description);
        $this->assertSame(2.0, $invoice->items[0]->quantity);
        $this->assertSame(10.5, $invoice->items[0]->unitPrice);
        $this->assertSame(21.0, $invoice->items[0]->subtotal);
        $this->assertSame('CL-010', $invoice->items[1]->code);
        $this->assertSame(21.5, $invoice->items[1]->subtotal);
    }

    public function test_credit_note_metadata_is_detected_but_not_parsed_as_physical_reception(): void
    {
        $parser = $this->parser();
        $metadata = $parser->metadata($this->creditNoteXml());

        $this->assertSame('notaCredito', $metadata->documentType);
        $this->assertSame('1790012345001', $metadata->supplierRuc);
        $this->assertSame('Proveedor Ferretero SA', $metadata->supplierName);
        $this->assertTrue($metadata->isRelevantPurchaseDocument());

        $this->expectException(UnsupportedPurchaseDocumentException::class);

        $parser->parse($this->creditNoteXml(), 'gmail-124', 'proveedor@example.com');
    }

    public function test_invalid_xml_fails_in_a_controlled_way(): void
    {
        $this->expectException(RuntimeException::class);

        $this->parser()->parse('<factura>', 'gmail-125', 'proveedor@example.com');
    }

    public function test_signed_sri_wrapper_invoice_is_parsed(): void
    {
        $wrapped = '<autorizacion><comprobante><![CDATA['.$this->invoiceXml().']]></comprobante></autorizacion>';

        $invoice = $this->parser()->parse($wrapped, 'gmail-126', 'proveedor@example.com');

        $this->assertSame('001-002-000000123', $invoice->invoiceNumber);
        $this->assertSame('2026-05-11', $invoice->emissionDate);
    }

    private function parser(): EcuadorianInvoiceXmlParser
    {
        return new EcuadorianInvoiceXmlParser;
    }

    private function invoiceXml(): string
    {
        return <<<'XML'
<factura>
    <infoTributaria>
        <ruc>1790012345001</ruc>
        <razonSocial>Proveedor Ferretero SA</razonSocial>
        <claveAcceso>1105202601179001234500110010020000001231234567811</claveAcceso>
        <estab>001</estab>
        <ptoEmi>002</ptoEmi>
        <secuencial>000000123</secuencial>
    </infoTributaria>
    <infoFactura>
        <fechaEmision>11/05/2026</fechaEmision>
        <importeTotal>42.50</importeTotal>
    </infoFactura>
    <detalles>
        <detalle>
            <codigoPrincipal>MT-001</codigoPrincipal>
            <descripcion>Martillo</descripcion>
            <cantidad>2</cantidad>
            <precioUnitario>10.50</precioUnitario>
            <subtotal>21.00</subtotal>
        </detalle>
        <detalle>
            <codigoAuxiliar>CL-010</codigoAuxiliar>
            <descripcion>Clavos 2 pulgadas</descripcion>
            <cantidad>1</cantidad>
            <precioUnitario>21.50</precioUnitario>
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
        <ruc>1790012345001</ruc>
        <razonSocial>Proveedor Ferretero SA</razonSocial>
        <claveAcceso>1105202604179001234500110010020000001241234567816</claveAcceso>
    </infoTributaria>
</notaCredito>
XML;
    }
}
