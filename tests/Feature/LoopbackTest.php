<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Feature;

use HimaCode\Workerless\Http\TickController;
use HimaCode\Workerless\Loopback\Signer;
use HimaCode\Workerless\Loopback\Trigger;
use HimaCode\Workerless\Tests\TestCase;

class LoopbackTest extends TestCase
{
    public function test_a_valid_signature_is_accepted_only_once(): void
    {
        $signer = $this->app->make(Signer::class);
        $h = $this->headers($signer->headers());

        $this->assertTrue($signer->verify($h[Signer::HEADER_TIME], $h[Signer::HEADER_NONCE], $h[Signer::HEADER_SIGNATURE]));
        $this->assertFalse($signer->verify($h[Signer::HEADER_TIME], $h[Signer::HEADER_NONCE], $h[Signer::HEADER_SIGNATURE]));
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $signer = $this->app->make(Signer::class);
        $h = $this->headers($signer->headers());

        $tampered = substr($h[Signer::HEADER_SIGNATURE], 0, -1) . ($h[Signer::HEADER_SIGNATURE][-1] === 'a' ? 'b' : 'a');

        $this->assertFalse($signer->verify($h[Signer::HEADER_TIME], $h[Signer::HEADER_NONCE], $tampered));
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        $signer = $this->app->make(Signer::class);
        $time = (string) (time() - 3600);
        $nonce = str_repeat('ab', 16);

        // A correct signature, but for a time one hour ago.
        $signature = (function () use ($time, $nonce): string {
            return $this->sign($this->key(), $time, $nonce);
        })->call($signer);

        $this->assertFalse($signer->verify($time, $nonce, $signature));
    }

    public function test_nothing_is_signed_without_app_key(): void
    {
        config()->set('app.key', '');

        $this->assertNull($this->app->make(Signer::class)->headers());
    }

    public function test_the_self_call_endpoint_answers_404_without_a_signature(): void
    {
        config()->set('workerless.loopback.enabled', true);

        $this->post('/' . TickController::PATH)->assertNotFound();
    }

    public function test_the_self_call_endpoint_accepts_a_signed_request_once(): void
    {
        config()->set('workerless.loopback.enabled', true);

        $headers = $this->headers($this->app->make(Signer::class)->headers());
        $headers[Trigger::HEADER_PROBE] = '0123456789abcdef'; // probe: answers without running jobs

        $this->post('/' . TickController::PATH, [], $headers)->assertNoContent();
        $this->post('/' . TickController::PATH, [], $headers)->assertNotFound();
    }

    public function test_the_self_call_endpoint_is_closed_when_self_calls_are_off(): void
    {
        config()->set('workerless.loopback.enabled', true);
        $headers = $this->headers($this->app->make(Signer::class)->headers());

        config()->set('workerless.loopback.enabled', false);

        $this->post('/' . TickController::PATH, [], $headers)->assertNotFound();
    }

    /**
     * "Name: value" lines into ['Name' => 'value'].
     *
     * @param  array<int, string>|null  $lines
     * @return array<string, string>
     */
    protected function headers(?array $lines): array
    {
        $this->assertNotNull($lines);

        $out = [];

        foreach ($lines as $line) {
            [$name, $value] = explode(': ', $line, 2);
            $out[$name] = $value;
        }

        return $out;
    }
}
