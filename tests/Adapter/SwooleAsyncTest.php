<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\Async\AsyncRequest;
use BEAR\Async\Fake\FakeInvoker;
use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Async\Fake\FakeSwooleThrowingInvoker;
use BEAR\Async\PendingRequests;
use BEAR\Async\RequestTask;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Swoole\Coroutine;
use Throwable;

/**
 * @psalm-suppress UnusedClass
 */
#[RequiresPhpExtension('swoole')]
final class SwooleAsyncTest extends TestCase
{
    private SwooleAsync $swooleAsync;
    private SyncAsync $syncAsync;

    protected function setUp(): void
    {
        $this->swooleAsync = new SwooleAsync();
        $this->syncAsync = new SyncAsync();
    }

    public function testInvokeWithEmptyTasks(): void
    {
        $captured = null;
        Coroutine\run(function () use (&$captured): void {
            try {
                ($this->swooleAsync)([]);
            } catch (Throwable $e) {
                $captured = $e;
            }
        });

        $this->assertNull($captured);
    }

    public function testExecuteWithEmptyRequestsReturnsEmptyArray(): void
    {
        $results = null;
        Coroutine\run(function () use (&$results): void {
            $results = $this->swooleAsync->execute([]);
        });

        $this->assertSame([], $results);
    }

    public function testInvokeRunsAllTasksAndRethrowsOriginalExceptionAfterCompletion(): void
    {
        $ro1 = new FakeResourceObject('app://self/one');
        $ro1->body = ['name' => 'one'];
        $task1 = new RequestTask('hash-1', new Request(new FakeInvoker($ro1), $ro1, Method::GET, []));

        $ro2 = new FakeResourceObject('app://self/two');
        $failure = new RuntimeException('embed failed');
        $throwingInvoker = new FakeSwooleThrowingInvoker($failure);
        $task2 = new RequestTask('hash-2', new Request($throwingInvoker, $ro2, Method::GET, []));

        $ro3 = new FakeResourceObject('app://self/three');
        $ro3->body = ['name' => 'three'];
        $task3 = new RequestTask('hash-3', new Request(new FakeInvoker($ro3), $ro3, Method::GET, []));

        $caught = null;
        Coroutine\run(function () use ($task1, $task2, $task3, &$caught): void {
            try {
                ($this->swooleAsync)([
                    'hash-1' => $task1,
                    'hash-2' => $task2,
                    'hash-3' => $task3,
                ]);
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        // Sibling tasks completed and had their results set despite task2 failing.
        $this->assertSame(['name' => 'one'], $task1->getResult());
        $this->assertSame(['name' => 'three'], $task3->getResult());
        $this->assertNull($task2->getResult());

        // The original throwable propagated out of __invoke, unchanged.
        $this->assertSame($failure, $caught);
        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('embed failed', $caught->getMessage());
    }

    public function testExecuteRunsAllRequestsAndRethrowsOriginalExceptionWithoutKillingProcess(): void
    {
        $roOk = new FakeResourceObject('app://self/ok');
        $roOk->body = ['name' => 'ok'];
        $okRequest = new Request(new FakeInvoker($roOk), $roOk, Method::GET, []);

        $roFail = new FakeResourceObject('app://self/fail');
        $failure = new RuntimeException('request failed');
        $throwingInvoker = new FakeSwooleThrowingInvoker($failure);
        $failRequest = new Request($throwingInvoker, $roFail, Method::GET, []);

        $pendingRequests = new PendingRequests($this->swooleAsync);
        $okAsyncRequest = new AsyncRequest($okRequest, $pendingRequests);
        $failAsyncRequest = new AsyncRequest($failRequest, $pendingRequests);

        $caught = null;
        Coroutine\run(function () use ($okAsyncRequest, $failAsyncRequest, &$caught): void {
            try {
                $this->swooleAsync->execute([
                    'app://self/ok' => $okAsyncRequest,
                    'app://self/fail' => $failAsyncRequest,
                ]);
            } catch (Throwable $e) {
                $caught = $e;
            }
        });

        // The original throwable propagated out of execute(), unchanged.
        $this->assertSame($failure, $caught);
        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('request failed', $caught->getMessage());

        // Reaching this assertion proves the failing coroutine did not take
        // down the process (see Finding F1: previously $wg->wait() never
        // returned and the process exited 255).
        $this->assertTrue(true);
    }

    public function testInvokeProducesSameResultAsSyncAsync(): void
    {
        $syncRo = new FakeResourceObject('app://self/user');
        $syncRo->body = ['name' => 'Sync'];
        $syncTask = new RequestTask('hash-user', new Request(new FakeInvoker($syncRo), $syncRo, Method::GET, []));
        ($this->syncAsync)(['hash-user' => $syncTask]);

        $swooleRo = new FakeResourceObject('app://self/user');
        $swooleRo->body = ['name' => 'Sync'];
        $swooleTask = new RequestTask('hash-user', new Request(new FakeInvoker($swooleRo), $swooleRo, Method::GET, []));
        Coroutine\run(function () use ($swooleTask): void {
            ($this->swooleAsync)(['hash-user' => $swooleTask]);
        });

        $this->assertSame($syncTask->getResult(), $swooleTask->getResult());
    }

    public function testExecuteProducesSameResultsAndOrderingAsSyncAsync(): void
    {
        $syncRoA = new FakeResourceObject('app://self/a');
        $syncRoA->body = ['name' => 'a'];
        $syncRoB = new FakeResourceObject('app://self/b');
        $syncRoB->body = ['name' => 'b'];

        $syncPending = new PendingRequests($this->syncAsync);
        $syncRequestA = new AsyncRequest(new Request(new FakeInvoker($syncRoA), $syncRoA, Method::GET, []), $syncPending);
        $syncRequestB = new AsyncRequest(new Request(new FakeInvoker($syncRoB), $syncRoB, Method::GET, []), $syncPending);

        $syncResults = $this->syncAsync->execute([
            'app://self/a' => $syncRequestA,
            'app://self/b' => $syncRequestB,
        ]);

        $swooleRoA = new FakeResourceObject('app://self/a');
        $swooleRoA->body = ['name' => 'a'];
        $swooleRoB = new FakeResourceObject('app://self/b');
        $swooleRoB->body = ['name' => 'b'];

        $swoolePending = new PendingRequests($this->swooleAsync);
        $swooleRequestA = new AsyncRequest(new Request(new FakeInvoker($swooleRoA), $swooleRoA, Method::GET, []), $swoolePending);
        $swooleRequestB = new AsyncRequest(new Request(new FakeInvoker($swooleRoB), $swooleRoB, Method::GET, []), $swoolePending);

        $swooleResults = null;
        Coroutine\run(function () use ($swooleRequestA, $swooleRequestB, $swoolePending, &$swooleResults): void {
            $swooleResults = $this->swooleAsync->execute([
                'app://self/a' => $swooleRequestA,
                'app://self/b' => $swooleRequestB,
            ]);
            unset($swoolePending);
        });

        $this->assertSame($syncResults, $swooleResults);
        $this->assertSame(['app://self/a', 'app://self/b'], array_keys((array) $swooleResults));
    }
}
