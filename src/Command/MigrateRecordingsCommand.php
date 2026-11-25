<?php

declare(strict_types=1);

namespace Hschottm\AurigaBundle\Command;

use Doctrine\DBAL\Connection;
use Hschottm\AurigaBundle\RecordingMigration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'auriga:migrate-recordings', description: 'Übernimmt alte Aufnahme-Zuordnungen in die Contao-Dateiauswahl (standardmäßig nur Prüflauf).')]
final class MigrateRecordingsCommand extends Command
{
    public function __construct(private Connection $connection, private string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Geprüfte Änderungen mit Sicherung in die Datenbank schreiben.')
            ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Absoluter Pfad für einen neuen JSON-Bericht.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $db = $this->connection;
        $stamp = date('Ymd-His').'-'.bin2hex(random_bytes(4));
        $directory = $this->projectDir.'/var/auriga-recording-migration';
        $reportFile = $input->getOption('report') ?: $directory.'/'.$stamp.'-report.json';
        if (!str_starts_with($reportFile, '/')) throw new \InvalidArgumentException('--report benötigt einen absoluten Pfad.');
        $planner = new RecordingMigration($db->fetchAllAssociative("SELECT uuid, path FROM tl_files WHERE type = 'file' AND LOWER(path) LIKE '%.mp3'"));
        $legacy = [];
        foreach ($db->fetchAllAssociative('SELECT pid, filename FROM tl_broadcast_file ORDER BY pid, sorting, id') as $row) {
            $legacy[$row['pid']][] = $row['filename'];
        }
        $changes = [];
        $report = ['mode' => $apply ? 'apply' : 'dry-run', 'created' => date(DATE_ATOM), 'backup' => null, 'counts' => ['change' => 0, 'unchanged' => 0, 'skipped' => 0], 'broadcasts' => []];
        foreach ($db->fetchAllAssociative('SELECT b.id, b.files, a.numbering FROM tl_broadcast b LEFT JOIN tl_adventure a ON a.id = b.pid ORDER BY b.id') as $row) {
            $plan = $planner->plan($row['files'], $legacy[$row['id']] ?? [], $row['numbering']);
            ++$report['counts'][$plan['status']];
            if ($plan['status'] === 'unchanged') continue;
            $report['broadcasts'][] = ['id' => $row['id'], 'adventure' => $row['numbering'], 'status' => $plan['status'], 'paths' => $plan['paths'] ?? [], 'issues' => $plan['issues']];
            if ($plan['status'] === 'change') {
                $changes[] = ['id' => $row['id'], 'before' => $row['files'] === null ? null : base64_encode($row['files']), 'after' => base64_encode($plan['value'])];
            }
        }
        // Reserve the report before any database writes; never overwrite previous reports.
        self::writeNew($reportFile, $report);
        if ($apply && $changes) {
            $backupFile = $directory.'/'.$stamp.'-backup.json';
            self::writeNew($backupFile, ['created' => date(DATE_ATOM), 'table' => 'tl_broadcast', 'column' => 'files', 'encoding' => 'base64', 'rows' => $changes]);
            $report['backup'] = $backupFile;
            $db->beginTransaction();
            try {
                foreach ($changes as $change) {
                    $current = $db->fetchAssociative('SELECT files FROM tl_broadcast WHERE id = ? FOR UPDATE', [$change['id']]);
                    $before = $change['before'] === null ? null : base64_decode($change['before'], true);
                    if ($current === false || $current['files'] !== $before) {
                        throw new \RuntimeException('Sendung '.$change['id'].' wurde inzwischen geändert. Alle Änderungen zurückgerollt; bitte erneut prüfen.');
                    }
                    $db->update('tl_broadcast', ['files' => base64_decode($change['after'], true)], ['id' => $change['id']]);
                }
                $db->commit();
                $report['applied'] = count($changes);
            } catch (\Throwable $e) {
                $db->rollBack();
                $report['error'] = $e->getMessage();
                self::updateReport($reportFile, $report);
                throw $e;
            }
        }
        self::updateReport($reportFile, $report);
        $io->table(['Modus', 'Änderungen', 'Unverändert', 'Übersprungen'], [[$report['mode'], $report['counts']['change'], $report['counts']['unchanged'], $report['counts']['skipped']]]);
        $io->writeln('Bericht: '.$reportFile);
        if ($report['backup']) $io->writeln('Sicherung: '.$report['backup']);
        if ($report['counts']['skipped']) $io->warning('Nicht eindeutig zuordenbare Sendungen bleiben vollständig unverändert. Details stehen im JSON-Bericht.');
        if (!$apply) $io->note('Prüflauf: keine Datenbankänderungen. Mit --apply werden die eindeutigen Zuordnungen übernommen.');
        else $io->success('Übernahme abgeschlossen. tl_broadcast_file und die MP3-Dateien wurden nicht verändert.');
        return Command::SUCCESS;
    }

    private static function writeNew(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Verzeichnis nicht anlegbar: '.$directory);
        $handle = fopen($path, 'x');
        if ($handle === false) throw new \RuntimeException('Datei existiert bereits oder ist nicht schreibbar: '.$path);
        try {
            if (!chmod($path, 0600)) throw new \RuntimeException('Sicherungsrechte nicht setzbar.');
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n";
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new \RuntimeException('Datei konnte nicht vollständig geschrieben werden: '.$path);
        } finally {
            fclose($handle);
        }
    }

    private static function updateReport(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n";
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) throw new \RuntimeException('Bericht konnte nicht geschrieben werden: '.$path);
    }
}
