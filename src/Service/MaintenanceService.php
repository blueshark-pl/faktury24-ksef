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

    /** Komunikat na stronie 503 (wyświetlany W TRAKCIE przerwy). */
    public function message(): string
    {
        $m = trim((string)($this->state()['message'] ?? ''));

        return $m !== '' ? $m : 'Trwają prace techniczne. Prosimy spróbować ponownie za chwilę.';
    }

    /** Komunikat banera ZAPOWIEDZI (przed przerwą). Może być pusty → baner pokaże sam tytuł + okno. */
    public function noticeMessage(): string
    {
        return trim((string)($this->state()['notice_message'] ?? ''));
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
            $window = '<div class="win"><span class="dot"></span> '
                . htmlspecialchars(trim($from . ($to !== '' ? ' – ' . $to : '')), ENT_QUOTES, 'UTF-8')
                . '</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Przerwa techniczna — Faktury24</title>
<style>
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; height:100%; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    background: linear-gradient(135deg, #eef2f7 0%, #e3ebf6 100%);
    color:#1f2937; min-height:100vh;
    display:flex; align-items:center; justify-content:center; padding:24px;
  }
  .card {
    background:#fff; max-width:540px; width:100%;
    border-radius:18px; box-shadow:0 20px 60px rgba(31,41,55,.12);
    overflow:hidden; text-align:center; animation:rise .5s ease both;
  }
  @keyframes rise { from { opacity:0; transform:translateY(14px);} to { opacity:1; transform:none;} }
  .top { padding:44px 40px 8px; }
  .ico {
    width:88px; height:88px; margin:0 auto 20px; border-radius:50%;
    background:#f0f9eb; display:flex; align-items:center; justify-content:center;
    font-size:42px; line-height:1; box-shadow:0 0 0 8px #f7fbf3;
  }
  h1 { font-size:24px; margin:0 0 12px; color:#111827; font-weight:700; }
  .msg { font-size:16px; line-height:1.65; color:#4b5563; margin:0 auto; max-width:420px; }
  .win {
    display:inline-flex; align-items:center; gap:8px; margin-top:22px;
    background:#f3f4f6; color:#374151; font-size:14px;
    padding:9px 16px; border-radius:999px; font-weight:600;
  }
  .win .dot { width:8px; height:8px; border-radius:50%; background:#6fc14b; box-shadow:0 0 0 4px rgba(111,193,75,.2); }
  .foot {
    margin-top:32px; padding:16px 40px; border-top:1px solid #f0f2f5;
    font-size:13px; color:#9ca3af;
  }
  .foot a { color:#6b7280; text-decoration:none; border-bottom:1px dashed #cbd5e1; }
  .foot a:hover { color:#374151; }
  .bar { height:6px; background:linear-gradient(90deg,#6fc14b,#8fd56f); }
</style>
</head>
<body>
  <div class="card">
    <div class="top">
      <div class="ico">🛠️</div>
      <h1>Przerwa techniczna</h1>
      <p class="msg">{$msg}</p>
      {$window}
    </div>
    <div class="foot">
      Dziękujemy za cierpliwość. &middot; <a href="/login">Panel administratora</a>
    </div>
    <div class="bar"></div>
  </div>
</body>
</html>
HTML;
    }
}
