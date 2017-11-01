<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MagentoCloud\Util;

use Magento\MagentoCloud\Shell\ShellInterface;
use Psr\Log\LoggerInterface;

/**
 * Utility class for static content compression.
 */
class StaticContentCompressor
{
    /**
     * Target directory to be compressed relative to the Magento application folder.
     */
    const TARGET_DIR = 'pub/static';

    /**
     * Default gzip compression level if not otherwise specified.
     *
     * Compression level 4 takes about as long as compression level 1.
     * It's just as fast because the reduction in I/O from the smaller
     * compressed file speeds up compression about as fast as the increased
     * CPU usage slows it down.
     * Compression level 4 is the default instead of compression level 1 as a
     * result.
     */
    const DEFAULT_COMPRESSION_LEVEL = 4;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ShellInterface
     */
    private $shell;

    /**
     * @var string The outer wrapper command that limits execution time and prevents hanging during deployment.
     */
    private static $timeoutCommand = '/usr/bin/timeout -k 30 600 /bin/bash -c ';

    /**
     * @param LoggerInterface $logger
     * @param ShellInterface  $shell
     */
    public function __construct(
        LoggerInterface $logger,
        ShellInterface $shell
    ) {
        $this->logger = $logger;
        $this->shell = $shell;
    }

    /**
     * Compress select files in the static content directory.
     *
     * @param int $compressionLevel
     *
     * @return bool
     */
    public function process(int $compressionLevel = self::DEFAULT_COMPRESSION_LEVEL, string $verbose = ''): bool
    {
        $compressionCommand = $this->getCompressionCommand($compressionLevel);

        $startTime = microtime(true);
        $this->shell->execute($compressionCommand);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        if ($verbose) {
            $this->logger->info(
                "Static content compression took $duration seconds.",
                [
                    'commandRun' => $compressionCommand
                ]
            );
        }

        return true;
    }

    /**
     * Return the inner find/xargs/gzip command that compresses the content.
     *
     * @return string
     */
    private function innerCompressionCommand(): string
    {
        return "find " . escapeshellarg(static::TARGET_DIR) . " -type f -size +300c"
            . " '(' -name '*.js' -or -name '*.css' -or -name '*.svg'"
            . " -or -name '*.html' -or -name '*.htm' ')'"
            . " | xargs -n100 -P16 gzip --keep";
    }

    /**
     * Get the string containing the full shell command for compression.
     *
     * @param int  $compressionLevel
     * @param bool $verbose
     *
     * @return string
     */
    private function getCompressionCommand(
        int $compressionLevel = self::DEFAULT_COMPRESSION_LEVEL
    ): string {
        $compressionLevel = (int)$compressionLevel;
        $compressionLevel = $compressionLevel > 0 && $compressionLevel <= 9
            ? $compressionLevel
            : static::DEFAULT_COMPRESSION_LEVEL;

        $compressionCommand = $this->innerCompressionCommand();
        $compressionCommand .= " -$compressionLevel";
        $compressionCommand = static::$timeoutCommand . '"' . $compressionCommand . '"';

        return $compressionCommand;
    }
}