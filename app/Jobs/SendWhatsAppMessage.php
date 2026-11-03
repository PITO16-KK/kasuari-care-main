<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public string $to, public string $message)
    {
    }

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(HttpFactory $http): void
    {
        $url = config('services.whatsapp.url');
        $token = config('services.whatsapp.token');

        if (! $url || ! $token) {
            throw new RuntimeException('WhatsApp gateway URL/token belum dikonfigurasi.');
        }

        $http->timeout(20)->withToken($token)->post($url, [
            'to' => $this->to,
            'message' => $this->message,
        ])->throw();
    }
}