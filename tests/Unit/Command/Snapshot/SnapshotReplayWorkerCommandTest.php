<?php
declare(strict_types=1);

/**
 * This source file is available under the terms of the
 * Pimcore Open Core License (POCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (https://www.pimcore.com)
 *  @license    Pimcore Open Core License (POCL)
 */

namespace Pimcore\Bundle\GenericDataIndexBundle\Tests\Unit\Command\Snapshot;

use Codeception\Test\Unit;
use Composer\Autoload\ClassLoader;
use Pimcore\Bundle\GenericDataIndexBundle\Command\Snapshot\SnapshotReplayWorkerCommand;
use Pimcore\Bundle\GenericDataIndexBundle\Exception\Snapshot\SnapshotImportException;
use Pimcore\Bundle\GenericDataIndexBundle\Service\SearchIndex\Snapshot\BulkSenderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class SnapshotReplayWorkerCommandTest extends Unit
{
    /** @var string[] */
    private array $paths = [];

    protected function _after(): void
    {
        foreach ($this->paths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->paths = [];
    }

    public function testSendsEveryChunkFromStdinDeletesItAndAcknowledges(): void
    {
        $a = $this->chunk("a\n");
        $b = $this->chunk("b\n");
        $sent = [];
        $sender = $this->makeEmpty(BulkSenderInterface::class, [
            'send' => static function (string $body, string $index) use (&$sent): void {
                $sent[] = [$index, $body];
            },
        ]);
        $tester = $this->tester($sender, "$a\n\n$b\n");

        $this->assertSame(Command::SUCCESS, $tester->execute(['index' => 'data-object_ptcar']));
        $this->assertSame([['data-object_ptcar', "a\n"], ['data-object_ptcar', "b\n"]], $sent);
        $this->assertSame("OK\t$a\nOK\t$b\n", $tester->getDisplay(true));
        $this->assertFileDoesNotExist($a);
        $this->assertFileDoesNotExist($b);
    }

    public function testReportsAFailureOnOneLineAndStops(): void
    {
        $a = $this->chunk('a');
        $b = $this->chunk('b');
        $sender = $this->makeEmpty(BulkSenderInterface::class, [
            'send' => static function (): never {
                throw new SnapshotImportException("mapper_parsing_exception\nfailed to parse");
            },
        ]);
        $tester = $this->tester($sender, "$a\n$b\n");

        $this->assertSame(Command::FAILURE, $tester->execute(['index' => 'asset']));
        $this->assertSame("ERR\t$a\tmapper_parsing_exception failed to parse\n", $tester->getDisplay(true));
        $this->assertFileDoesNotExist($a, 'the failed chunk is removed');
        $this->assertFileExists($b, 'later chunks are left to the dispatcher');
    }

    /**
     * Symfony evaluates every property default when it rebuilds the container cache, also in web
     * requests where the CLI-only STDIN constant does not exist.
     */
    public function testHasNoPropertyDefaultThatOnlyExistsInTheCliSapi(): void
    {
        $defaults = (new \ReflectionClass(SnapshotReplayWorkerCommand::class))->getDefaultProperties();

        $this->assertArrayHasKey('inputStream', $defaults);
        foreach ($defaults as $name => $default) {
            $this->assertFalse(is_resource($default), sprintf('$%s defaults to a resource', $name));
        }
    }

    public function testReadsTheProcessStdinWhenNoStreamIsInjected(): void
    {
        $a = $this->chunk('a');
        $autoload = dirname((string) (new \ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
        $script = sprintf(
            'require %s;'
            . ' $sender = new class implements %s { public function send(string $b, string $i): void {} };'
            . ' $tester = new %s(new %s($sender));'
            . ' $code = $tester->execute(["index" => "asset"]);'
            . ' echo $tester->getDisplay(true); exit($code);',
            var_export($autoload, true),
            '\\' . BulkSenderInterface::class,
            '\\' . CommandTester::class,
            '\\' . SnapshotReplayWorkerCommand::class,
        );
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script], null, null, "$a\n");
        $process->run();

        $this->assertSame(Command::SUCCESS, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame("OK\t$a\n", $process->getOutput());
        $this->assertFileDoesNotExist($a);
    }

    private function tester(BulkSenderInterface $sender, string $stdin): CommandTester
    {
        $command = new SnapshotReplayWorkerCommand($sender);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $stdin);
        rewind($stream);
        $command->setInputStream($stream);

        return new CommandTester($command);
    }

    private function chunk(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gdi-chunk-');
        file_put_contents($path, $content);
        $this->paths[] = $path;

        return $path;
    }
}
