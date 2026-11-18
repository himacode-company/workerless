<?php

declare(strict_types=1);

namespace HimaCode\Workerless\Tests\Unit;

use HimaCode\Workerless\Support\EnvWriter;
use PHPUnit\Framework\TestCase;

class EnvWriterTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'workerless-env-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach ((array) scandir($this->dir) as $name) {
            $file = $this->dir . DIRECTORY_SEPARATOR . $name;

            if (is_string($name) && is_file($file)) {
                unlink($file);
            }
        }

        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_it_adds_the_keys_and_the_queue_hint(): void
    {
        $env = $this->write("APP_NAME=Demo\nQUEUE_CONNECTION=database\n");

        (new EnvWriter($this->dir))->addMissingKeys();

        $content = (string) file_get_contents($env);

        $this->assertStringContainsString("WORKERLESS_USER_IDS=\n", $content);
        $this->assertStringContainsString(EnvWriter::QUEUE_HINT . "\nQUEUE_CONNECTION=database", $content);
    }

    public function test_it_never_changes_existing_values(): void
    {
        $env = $this->write("QUEUE_CONNECTION=database\nWORKERLESS_USER_IDS=1,7\n");

        (new EnvWriter($this->dir))->addMissingKeys();

        $content = (string) file_get_contents($env);

        $this->assertStringContainsString('QUEUE_CONNECTION=database', $content);
        $this->assertStringContainsString('WORKERLESS_USER_IDS=1,7', $content);
        $this->assertSame(1, substr_count($content, 'WORKERLESS_USER_IDS='));
    }

    public function test_running_it_twice_adds_nothing_more(): void
    {
        $env = $this->write("QUEUE_CONNECTION=database\n");

        (new EnvWriter($this->dir))->addMissingKeys();
        $once = (string) file_get_contents($env);

        (new EnvWriter($this->dir))->addMissingKeys();
        $twice = (string) file_get_contents($env);

        $this->assertSame($once, $twice);
    }

    public function test_no_hint_when_the_queue_is_already_workerless(): void
    {
        $env = $this->write("QUEUE_CONNECTION=workerless\n");

        (new EnvWriter($this->dir))->addMissingKeys();

        $this->assertStringNotContainsString(EnvWriter::QUEUE_HINT, (string) file_get_contents($env));
    }

    public function test_windows_line_endings_are_kept(): void
    {
        $env = $this->write("APP_NAME=Demo\r\nQUEUE_CONNECTION=database\r\n");

        (new EnvWriter($this->dir))->addMissingKeys();

        $content = (string) file_get_contents($env);

        $this->assertStringContainsString("WORKERLESS_USER_IDS=\r\n", $content);
        $this->assertSame(0, preg_match("/(?<!\r)\n/", $content), 'A bare \n was written into a \r\n file.');
    }

    public function test_a_missing_env_file_is_left_alone(): void
    {
        (new EnvWriter($this->dir))->addMissingKeys();

        $this->assertFileDoesNotExist($this->dir . DIRECTORY_SEPARATOR . '.env');
    }

    protected function write(string $content): string
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($path, $content);

        return $path;
    }
}
