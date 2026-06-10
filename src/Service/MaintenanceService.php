<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Tryb przerwy technicznej (maintenance) sterowany plikiem-flagą JSON.
 *
 * Zasada nadrzędna: FAIL-OPEN. Każdy błąd/brak pliku/niepoprawny JSON → tryb NIEAKTYWNY,
 * tak aby błąd w tym mechanizmie NIGDY nie zablokował działającego systemu.
 *
 * Plik (CONFIG/maintenance.json):
 * {
 *   "enabled": true,
 *   "message": "Trwają prace techniczne...",
 *   "from": "2026-06-12T22:00:00",   // start blokady (opcjonalnie; brak = od razu)
 *   "to":   "2026-06-12T23:00:00",   // koniec blokady (opcjonalnie; po nim auto-wyłączenie)
 *   "notice_from": "2026-06-12T18:00:00", // od kiedy pokazywać baner zapowiedzi (opcjonalnie)
 *   "allow_cron": true               // czy crony z kluczem mają działać w trakcie
 * }
 */
class MaintenanceService
{
    private string $flagPath;

    public function __construct(?string $flagPath = null)
    {
        $this->flagPath = $flagPath ?? (CONFIG . 'maintenance.json');
    }

    public function flagPath(): string
    {
        return $this->flagPath;
    }

    /** Odczyt stanu; [] przy jakimkolwiek problemie (fail-open). */
    public function state(): array
    {
        try {
            if (!is_file($this->flagPath)) {
                return [];
            }
            $raw = @file_get_contents($this->flagPath);
            if ($raw === false || trim($raw) === '') {
                return [];
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Czy tryb przerwy jest AKTYWNY (blokuje ruch) w danym momencie. */
    public function isActive(?\DateTimeInterface $now = null): bool
    {
        try {
            $s = $this->state();
            if (empty($s['enabled'])) {
                return false;
            }
            $now  = $now ?? new \DateTimeImmutable();
            $from = !empty($s['from']) ? new \DateTimeImmutable((string)$s['from']) : null;
            $to   = !empty($s['to'])   ? new \DateTimeImmutable((string)$s['to'])   : null;

            // Zaplanowane okno: przed startem → jeszcze nie blokuje (tylko baner),
            // po końcu → auto-wyłączenie.
            if ($from !== null && $now < $from) {
                return false;
            }
            if ($to !== null && $now > $to) {
                return false;
            }

            return true;
        } catch (\Throwable) {
            return false; // fail-open
        }
    }

    /** Czy pokazywać baner zapowiedzi (przed startem okna prac). */
    public function isNoticeWindow(?\DateTimeInterface $now = null): bool
    {
        try {
            $s = $this->state();
            if (empty($s['enabled'])) {
                return false;
            }
            $now        = $now ?? new \DateTimeImmutable();
            $from       = !empty($s['from'])        ? new \DateTimeImmutable((string)$s['from'])        : null;
            $noticeFrom = !empty($s['notice_from']) ? new \DateTimeImmutable((string)$s['notice_from']) : null;

            if ($from === null) {
                return false;          // brak zaplanowanego startu → brak zapowiedzi
            }
            if ($now >= $from) {
                return false;          // już w trakcie prac (nie zapowiedź)
            }
            if ($noticeFrom !== null && $now < $noticeFrom) {
                return false;          // jeszcze za wcześnie na baner
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function message(): string
    {
        $m = trim((string)($this->state()['message'] ?? ''));

        return $m !== '' ? $m : 'Trwają prace techniczne. Prosimy spróbować ponownie za chwilę.';
    }

    /** ['from'=>?string, 'to'=>?string] — surowe wartości z flagi. */
    public function window(): array
    {
        $s = $this->state();

        return ['from' => $s['from'] ?? null, 'to' => $s['to'] ?? null];
    }

    public function allowCron(): bool
    {
        return (bool)($this->state()['allow_cron'] ?? true);
    }

    /** Zapis stanu do pliku-flagi. Zwraca true/false. */
    public function save(array $state): bool
    {
        try {
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return false;
            }

            return @file_put_contents($this->flagPath, $json) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Samodzielny (bez warstwy widoków) HTML strony 503 — renderuje się nawet
     * gdy widoki/zasoby mają problem.
     */
    public function renderPage(): string
    {
        $msg = htmlspecialchars($this->message(), ENT_QUOTES, 'UTF-8');
        $win = $this->window();
        $fmt = function (?string $iso): string {
            if (!$iso) {
                return '';
            }
            try {
                return (new \DateTimeImmutable($iso))->format('d.m.Y H:i');
            } catch (\Throwable) {
                return '';
            }
        };
        $from = $fmt($win['from'] ?? null);
        $to   = $fmt($win['to'] ?? null);
        $window = '';
        if ($from !== '' || $to !== '') {
            $window = '<p class="win">Przewidywane okno prac: '
                . htmlspecialchars(trim($from . ($to !== '' ? ' – ' . $to : '')), ENT_QUOTES, 'UTF-8')
                . '</p>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Przerwa techniczna</title>
<style>
  * { box-sizing: border-box; }
  body { margin:0; font-family: Arial, Helvetica, sans-serif; background:#eef2f7; color:#1f2937; }
  .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
  .card { background:#fff; max-width:520px; width:100%; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,.08); padding:40px 36px; text-align:center; }
  .ico { font-size:54px; line-height:1; margin-bottom:14px; }
  h1 { font-size:22px; margin:0 0 10px; color:#111827; }
  p { font-size:15px; line-height:1.6; color:#4b5563; margin:0 0 8px; }
  .win { margin-top:14px; font-size:13px; color:#6b7280; }
  .bar { height:6px; background:#6fc14b; border-radius:0 0 14px 14px; margin:32px -36px -40px; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="ico">🛠️</div>
      <h1>Przerwa techniczna</h1>
      <p>{$msg}</p>
      {$window}
      <div class="bar"></div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
