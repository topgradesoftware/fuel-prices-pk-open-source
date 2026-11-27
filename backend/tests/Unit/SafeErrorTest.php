<?php

namespace Tests\Unit;

use App\Support\SafeError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SafeErrorTest extends TestCase
{
    public function test_public_message_never_echoes_exception_text(): void
    {
        $e = new RuntimeException('SQLSTATE[HY000]: Access denied for user');

        $this->assertSame('Server error.', SafeError::publicMessage($e, 500));
        $this->assertSame('Request failed.', SafeError::publicMessage($e, 400));
        $this->assertSame('Not found.', SafeError::publicMessage(new NotFoundHttpException('Model not found'), 404));
        $this->assertStringNotContainsString('SQLSTATE', SafeError::publicMessage($e, 500));
        $this->assertStringNotContainsString('Access denied', SafeError::publicMessage($e, 500));
    }

    public function test_stored_message_is_generic(): void
    {
        $this->assertSame(
            'Operation failed. See server logs for details.',
            SafeError::storedMessage(),
        );
        $this->assertSame(
            'Sync failed. See server logs for details.',
            SafeError::storedMessage('Sync failed. See server logs for details.'),
        );
    }
}
