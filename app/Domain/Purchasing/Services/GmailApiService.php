<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GmailApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('gmail_inbox.api_base');
    }

    /**
     * Retorna lista de IDs de mensajes no leídos con adjunto XML.
     *
     * @return array<string>
     */
    public function fetchUnreadInvoiceMessageIds(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/messages", [
                'q' => config('gmail_inbox.poll_query'),
                'maxResults' => 50,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gmail messages list failed: '.$response->body());
        }

        $data = $response->json();

        if (empty($data['messages'])) {
            return [];
        }

        return array_column($data['messages'], 'id');
    }

    /**
     * Retorna el mensaje completo con sus partes (payload).
     *
     * @return array<string, mixed>
     */
    public function getMessage(string $accessToken, string $messageId): array
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/messages/{$messageId}", [
                'format' => 'full',
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Gmail get message {$messageId} failed: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Extrae y decodifica el primer adjunto .xml del mensaje.
     */
    public function extractXmlAttachment(string $accessToken, array $message): ?string
    {
        $parts = $message['payload']['parts'] ?? [];

        foreach ($parts as $part) {
            if (! $this->isXmlAttachment($part)) {
                continue;
            }

            $attachmentId = $part['body']['attachmentId'] ?? null;
            $data = $part['body']['data'] ?? null;

            if ($data !== null) {
                return $this->decodeBase64Url($data);
            }

            if ($attachmentId !== null) {
                return $this->fetchAttachmentData($accessToken, $message['id'], $attachmentId);
            }
        }

        return null;
    }

    /**
     * Extrae el remitente del mensaje.
     */
    public function getFromEmail(array $message): string
    {
        $headers = $message['payload']['headers'] ?? [];

        foreach ($headers as $header) {
            if (strtolower($header['name']) === 'from') {
                return $header['value'];
            }
        }

        return '';
    }

    /**
     * Marca el mensaje como leído eliminando el label UNREAD.
     */
    public function markAsRead(string $accessToken, string $messageId): void
    {
        Http::withToken($accessToken)
            ->post("{$this->baseUrl}/messages/{$messageId}/modify", [
                'removeLabelIds' => ['UNREAD'],
            ]);
    }

    private function isXmlAttachment(array $part): bool
    {
        $mimeType = strtolower($part['mimeType'] ?? '');
        $filename = strtolower($part['filename'] ?? '');

        return str_ends_with($filename, '.xml') ||
            in_array($mimeType, ['application/xml', 'text/xml', 'application/octet-stream'], true) && str_ends_with($filename, '.xml');
    }

    private function fetchAttachmentData(string $accessToken, string $messageId, string $attachmentId): string
    {
        $response = Http::withToken($accessToken)
            ->get("{$this->baseUrl}/messages/{$messageId}/attachments/{$attachmentId}");

        if ($response->failed()) {
            throw new RuntimeException("Gmail get attachment failed: ".$response->body());
        }

        return $this->decodeBase64Url($response->json('data'));
    }

    private function decodeBase64Url(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
