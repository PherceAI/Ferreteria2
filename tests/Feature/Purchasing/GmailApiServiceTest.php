<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Purchasing\Jobs\FetchInvoiceEmailsJob;
use App\Domain\Purchasing\Services\GmailApiService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GmailApiServiceTest extends TestCase
{
    public function test_mark_as_read_surfaces_gmail_failures(): void
    {
        config(['gmail_inbox.api_base' => 'https://gmail.googleapis.com/gmail/v1/users/me']);

        Http::fake([
            'https://gmail.googleapis.com/*' => Http::response('temporary failure', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gmail mark as read message-1 failed');

        app(GmailApiService::class)->markAsRead('token', 'message-1');
    }

    public function test_invalid_inline_attachment_data_is_reported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid base64');

        app(GmailApiService::class)->extractXmlAttachment('token', [
            'id' => 'message-1',
            'payload' => [
                'parts' => [[
                    'mimeType' => 'application/xml',
                    'filename' => 'factura.xml',
                    'body' => ['data' => '%%%'],
                ]],
            ],
        ]);
    }

    public function test_invoice_fetch_job_does_not_overlap_per_branch(): void
    {
        $middleware = (new FetchInvoiceEmailsJob(20))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }
}
