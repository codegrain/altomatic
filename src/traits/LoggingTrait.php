<?php
namespace altomatic\traits;

use Craft;

trait LoggingTrait
{
    private static ?string $logFile = null;

    private static function writeLog(string $level, string $message, ?string $category = null): void
    {
        try {
            if (!self::$logFile) {
                $date = date('Y-m-d');
                self::$logFile = Craft::$app->path->getStoragePath() . "/logs/altomatic-{$date}.log";
            }

            $timestamp = date('Y-m-d H:i:s');
            $cat = $category ? "[{$category}] " : '';
            $logLine = "[{$timestamp}] [altomatic.{$level}] {$cat}{$message}" . PHP_EOL;
            
            // Force create the file immediately 
            $result = file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
            
            // Debug to system log if our file write failed
            if ($result === false) {
                error_log("ALTOMATIC: Failed to write to " . self::$logFile);
            }
        } catch (\Throwable $e) {
            // Fallback to Craft logging if our custom logging fails
            Craft::error("Altomatic logging failed: {$e->getMessage()} - File: " . (self::$logFile ?? 'null'), __METHOD__);
            error_log("ALTOMATIC ERROR: " . $e->getMessage() . " - File: " . (self::$logFile ?? 'null'));
        }
    }

    protected function logWarning(string $message, ?string $category = null): void
    {
        self::writeLog('WARNING', $message, $category);
    }

    protected function logError(string $message, ?string $category = null): void
    {
        self::writeLog('ERROR', $message, $category);
    }

    protected function logInfo(string $message, ?string $category = null): void
    {
        self::writeLog('INFO', $message, $category);
    }

    protected function logDebug(string $message, ?string $category = null): void
    {
        self::writeLog('DEBUG', $message, $category);
    }
}