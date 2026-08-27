<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ApplicationBackupService
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function create(
        ?string $rootPath = null,
        ?bool $includeMedia = null,
        bool $prune = true,
    ): array {
        $connectionName = (string) config('database.default');
        $connection = (array) config("database.connections.{$connectionName}", []);
        $driver = (string) ($connection['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Az automatikus mentés jelenleg MySQL/MariaDB kapcsolathoz támogatott.');
        }

        $database = trim((string) ($connection['database'] ?? ''));
        $username = (string) ($connection['username'] ?? '');
        if ($database === '' || $username === '') {
            throw new RuntimeException('Hiányos adatbázis-konfiguráció: DB_DATABASE és DB_USERNAME szükséges.');
        }

        $root = $this->normalizeRoot($rootPath ?: (string) config('backup.path'));
        $this->ensurePrivateDirectory($root);

        $timestamp = Carbon::now()->format('Ymd-His');
        $finalDirectory = $root.DIRECTORY_SEPARATOR."backup-{$timestamp}";
        $temporaryDirectory = $root.DIRECTORY_SEPARATOR.'.tmp-'.Str::uuid();
        $this->ensurePrivateDirectory($temporaryDirectory);

        $includeMedia ??= (bool) config('backup.include_media', true);
        $startedAt = Carbon::now();
        $mysqlConfigFile = null;

        try {
            $mysqlConfigFile = $this->createMysqlOptionFile($connection);
            $sqlPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'database.sql';
            $compressedSqlPath = $sqlPath.'.gz';
            $this->dumpDatabase($connection, $database, $mysqlConfigFile, $sqlPath);
            $this->compressDatabase($sqlPath);

            $media = $includeMedia
                ? $this->copyMedia($temporaryDirectory.DIRECTORY_SEPARATOR.'media')
                : ['included' => false, 'files' => 0, 'bytes' => 0, 'paths' => []];

            $databaseBytes = is_file($compressedSqlPath) ? (int) filesize($compressedSqlPath) : 0;
            $manifest = [
                'format_version' => 1,
                'created_at' => Carbon::now()->toIso8601String(),
                'app_env' => (string) config('app.env'),
                'database' => [
                    'connection' => $connectionName,
                    'driver' => $driver,
                    'name' => $database,
                    'file' => 'database.sql.gz',
                    'bytes' => $databaseBytes,
                    'sha256' => hash_file('sha256', $compressedSqlPath),
                ],
                'media' => $media,
            ];

            $manifestPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'manifest.json';
            $this->files->put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
            );

            $this->verify($temporaryDirectory);

            if (file_exists($finalDirectory)) {
                throw new RuntimeException("A mentési célkönyvtár már létezik: {$finalDirectory}");
            }
            if (! @rename($temporaryDirectory, $finalDirectory)) {
                throw new RuntimeException('A kész mentés atomikus átnevezése sikertelen.');
            }
            @chmod($finalDirectory, 0700);

            $deleted = $prune ? $this->prune($root) : [];

            return [
                'path' => $finalDirectory,
                'database_bytes' => $databaseBytes,
                'media_files' => (int) ($media['files'] ?? 0),
                'media_bytes' => (int) ($media['bytes'] ?? 0),
                'deleted' => $deleted,
                'duration_seconds' => max(0, Carbon::now()->diffInMilliseconds($startedAt) / 1000),
            ];
        } catch (Throwable $exception) {
            if (is_dir($temporaryDirectory)) {
                $this->files->deleteDirectory($temporaryDirectory);
            }
            throw $exception;
        } finally {
            if ($mysqlConfigFile && is_file($mysqlConfigFile)) {
                @unlink($mysqlConfigFile);
            }
        }
    }

    public function verify(?string $backupDirectory = null): array
    {
        $directory = $backupDirectory ?: $this->latestBackupPath();
        if (! $directory || ! is_dir($directory)) {
            throw new RuntimeException('Nem található ellenőrizhető mentés.');
        }

        $manifestPath = $directory.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('A mentés manifest.json fájlja hiányzik.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $databaseFile = (string) ($manifest['database']['file'] ?? '');
        $expectedHash = (string) ($manifest['database']['sha256'] ?? '');
        $databasePath = $directory.DIRECTORY_SEPARATOR.$databaseFile;

        if ($databaseFile === '' || ! is_file($databasePath) || filesize($databasePath) === 0) {
            throw new RuntimeException('Az adatbázis-mentés hiányzik vagy üres.');
        }
        if ($expectedHash === '' || ! hash_equals($expectedHash, hash_file('sha256', $databasePath))) {
            throw new RuntimeException('Az adatbázis-mentés SHA-256 ellenőrzése sikertelen.');
        }

        $gzip = (string) config('backup.gzip_binary', 'gzip');
        $process = new Process([$gzip, '-t', $databasePath]);
        $process->setTimeout((int) config('backup.timeout_seconds', 300));
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('A tömörített SQL mentés sérült: '.trim($process->getErrorOutput()));
        }

        $mediaIncluded = (bool) ($manifest['media']['included'] ?? false);
        if ($mediaIncluded && ! is_dir($directory.DIRECTORY_SEPARATOR.'media')) {
            throw new RuntimeException('A manifest médiafájlokat jelez, de a media könyvtár hiányzik.');
        }

        return [
            'path' => $directory,
            'created_at' => $manifest['created_at'] ?? null,
            'database_bytes' => (int) filesize($databasePath),
            'media_files' => (int) ($manifest['media']['files'] ?? 0),
            'media_bytes' => (int) ($manifest['media']['bytes'] ?? 0),
        ];
    }

    public function latestBackupPath(?string $rootPath = null): ?string
    {
        $root = $this->normalizeRoot($rootPath ?: (string) config('backup.path'));
        if (! is_dir($root)) {
            return null;
        }

        $directories = collect($this->files->directories($root))
            ->filter(fn (string $path) => str_starts_with(basename($path), 'backup-'))
            ->sortByDesc(fn (string $path) => basename($path))
            ->values();

        return $directories->first();
    }

    public function prune(?string $rootPath = null): array
    {
        $root = $this->normalizeRoot($rootPath ?: (string) config('backup.path'));
        if (! is_dir($root)) {
            return [];
        }

        $retentionDays = max(1, (int) config('backup.retention_days', 14));
        $cutoff = Carbon::now()->subDays($retentionDays);
        $deleted = [];

        foreach ($this->files->directories($root) as $directory) {
            $name = basename($directory);
            if (! preg_match('/^backup-(\d{8})-(\d{6})$/', $name, $matches)) {
                continue;
            }

            $createdAt = Carbon::createFromFormat('Ymd-His', $matches[1].'-'.$matches[2]);
            if ($createdAt->lt($cutoff)) {
                $this->files->deleteDirectory($directory);
                $deleted[] = $directory;
            }
        }

        return $deleted;
    }

    private function dumpDatabase(array $connection, string $database, string $mysqlConfigFile, string $sqlPath): void
    {
        $binary = (string) config('backup.mysqldump_binary', 'mysqldump');
        $arguments = [
            $binary,
            "--defaults-extra-file={$mysqlConfigFile}",
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            $database,
        ];

        $process = new Process($arguments);
        $process->setTimeout((int) config('backup.timeout_seconds', 300));
        $handle = fopen($sqlPath, 'wb');
        if (! $handle) {
            throw new RuntimeException('Nem hozható létre az ideiglenes SQL fájl.');
        }

        $stderr = '';
        try {
            $process->run(function (string $type, string $buffer) use ($handle, &$stderr): void {
                if ($type === Process::OUT) {
                    fwrite($handle, $buffer);
                } else {
                    $stderr .= $buffer;
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful() || ! is_file($sqlPath) || filesize($sqlPath) === 0) {
            @unlink($sqlPath);
            throw new RuntimeException('A mysqldump sikertelen: '.trim($stderr ?: $process->getErrorOutput()));
        }
    }

    private function compressDatabase(string $sqlPath): void
    {
        $gzip = (string) config('backup.gzip_binary', 'gzip');
        $process = new Process([$gzip, '-9', '-f', $sqlPath]);
        $process->setTimeout((int) config('backup.timeout_seconds', 300));
        $process->run();

        $compressed = $sqlPath.'.gz';
        if (! $process->isSuccessful() || ! is_file($compressed) || filesize($compressed) === 0) {
            throw new RuntimeException('Az SQL mentés tömörítése sikertelen: '.trim($process->getErrorOutput()));
        }
    }

    private function createMysqlOptionFile(array $connection): string
    {
        $directory = storage_path('framework/cache');
        $this->files->ensureDirectoryExists($directory, 0700, true);
        $path = tempnam($directory, 'backup-mysql-');
        if ($path === false) {
            throw new RuntimeException('Nem hozható létre ideiglenes MySQL konfiguráció.');
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (int) ($connection['port'] ?? 3306);
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $content = "[client]\n"
            .'host='.$this->optionValue($host)."\n"
            .'port='.$port."\n"
            .'user='.$this->optionValue($username)."\n"
            .'password='.$this->optionValue($password)."\n"
            ."protocol=tcp\n";

        file_put_contents($path, $content, LOCK_EX);
        @chmod($path, 0600);

        return $path;
    }

    private function copyMedia(string $targetRoot): array
    {
        $files = 0;
        $bytes = 0;
        $copiedPaths = [];

        foreach ((array) config('backup.media_paths', []) as $name => $source) {
            $source = (string) $source;
            if (! is_dir($source)) {
                continue;
            }

            $destination = $targetRoot.DIRECTORY_SEPARATOR.$name;
            $this->files->ensureDirectoryExists(dirname($destination), 0700, true);
            if (! $this->files->copyDirectory($source, $destination)) {
                throw new RuntimeException("A médiafájlok másolása sikertelen: {$source}");
            }
            $copiedPaths[] = (string) $name;

            foreach ($this->files->allFiles($destination) as $file) {
                if ($file->isFile()) {
                    $files++;
                    $bytes += (int) $file->getSize();
                }
            }
        }

        return [
            'included' => true,
            'files' => $files,
            'bytes' => $bytes,
            'paths' => $copiedPaths,
        ];
    }

    private function ensurePrivateDirectory(string $path): void
    {
        $this->files->ensureDirectoryExists($path, 0700, true);
        @chmod($path, 0700);
        if (! is_dir($path) || ! is_writable($path)) {
            throw new RuntimeException("A backup könyvtár nem írható: {$path}");
        }
    }

    private function normalizeRoot(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            throw new RuntimeException('A BACKUP_PATH nem lehet üres.');
        }

        return rtrim($path, DIRECTORY_SEPARATOR.'/\\');
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
