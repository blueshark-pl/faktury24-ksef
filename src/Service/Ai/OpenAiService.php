<?php
declare(strict_types=1);

namespace App\Service\Ai;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use RuntimeException;

/**
 * Wrapper na OpenAI Chat Completions API (gpt-4o-mini domyślnie).
 *
 * Klucz w app_local.php['OpenAI']['apiKey'].
 * Wszystkie wywołania mają tryb 'json' z structured output (response_format).
 */
class OpenAiService
{
    private const CHAT_URL = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;
    private string $model;
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $key = (string)Configure::read('OpenAI.apiKey');
        if ($key === '') {
            throw new RuntimeException('Klucz OpenAI API nie jest skonfigurowany.');
        }
        $this->apiKey = $key;
        $this->model = (string)(Configure::read('OpenAI.model') ?: 'gpt-4o-mini');
        $this->client = $client ?? new Client(['timeout' => 60]);
    }

    /**
     * Wykonuje zapytanie do OpenAI z structured JSON output.
     *
     * @param string $system  System prompt (instrukcja, format wyjścia)
     * @param string $user    User prompt (dane do przetworzenia)
     * @param int    $maxTokens Limit tokenów odpowiedzi
     * @return array Zdekodowany JSON z modelu
     * @throws RuntimeException
     */
    public function chatJson(string $system, string $user, int $maxTokens = 1500): array
    {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => $maxTokens,
            'temperature' => 0.2,
        ];
        try {
            $resp = $this->client->post(self::CHAT_URL, json_encode($body), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
            ]);
            if (!$resp->isOk()) {
                $err = $resp->getStringBody();
                Log::warning('OpenAI HTTP ' . $resp->getStatusCode() . ': ' . $err);
                throw new RuntimeException('OpenAI API zwróciło błąd ' . $resp->getStatusCode());
            }
            $data = $resp->getJson();
            $content = $data['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                throw new RuntimeException('Brak odpowiedzi z OpenAI.');
            }
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                throw new RuntimeException('OpenAI zwróciło nieprawidłowy JSON.');
            }
            return $parsed;
        } catch (\Throwable $e) {
            Log::error('OpenAI error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Wykonuje zapytanie z plain-text output (do generacji dokumentów).
     */
    public function chatText(string $system, string $user, int $maxTokens = 2000): string
    {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => 0.4,
        ];
        try {
            $resp = $this->client->post(self::CHAT_URL, json_encode($body), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
            ]);
            if (!$resp->isOk()) {
                throw new RuntimeException('OpenAI HTTP ' . $resp->getStatusCode());
            }
            $data = $resp->getJson();
            return (string)($data['choices'][0]['message']['content'] ?? '');
        } catch (\Throwable $e) {
            Log::error('OpenAI text error: ' . $e->getMessage());
            throw $e;
        }
    }

    // ── Specjalizowane wywołania dla planera ──────────────────────────

    /**
     * Parser tekstu kontrahenta → struktura trasy.
     * Wejście: "Załadunek 15.05 Kraków ul. Wielicka 22, dostawa 16.05 Berlin Tempelhof, 24t, ADR kl.3"
     */
    public function parseRouteText(string $text): array
    {
        $system = <<<SYS
Jesteś asystentem AI dla firmy transportowej. Analizujesz tekst po polsku
i wyciągasz informacje o trasie. Zwracasz WYŁĄCZNIE poprawny JSON o strukturze:
{
  "points": [
    {"address": "Pełny adres po polsku z miastem i krajem (np. 'Kraków, ul. Wielicka 22, Polska')", "date": "YYYY-MM-DDTHH:MM lub pusty string"}
  ],
  "cargo_weight_kg": liczba lub null,
  "cargo_description": "krótki opis ładunku lub pusty string",
  "adr_class": "1"-"9" lub null,
  "vehicle_type_hint": "truck/tractor/van/trailer/cysterna lub pusty string",
  "special_notes": "uwagi specjalne lub pusty string"
}
Co najmniej 2 punkty (origin + dest). Jeśli daty/godziny nie podano — pusty string.
SYS;
        return $this->chatJson($system, $text, 1200);
    }

    /**
     * Wizard ładunku: opis → ADR, sugerowany pojazd, ostrzeżenia.
     */
    public function analyzeCargo(string $description): array
    {
        $system = <<<SYS
Jesteś ekspertem ADR i transportu. Analizujesz opis ładunku po polsku i zwracasz JSON:
{
  "adr_class": "1"-"9" lub null,
  "adr_class_name": "nazwa klasy ADR po polsku lub null",
  "recommended_vehicle": "truck/tractor/van/trailer/cysterna",
  "tonnage_estimate_kg": liczba lub null,
  "special_requirements": [tablica wymagań specjalnych],
  "warnings": [tablica ostrzeżeń bezpieczeństwa],
  "summary": "krótkie podsumowanie 1 zdanie"
}
Bądź konkretny i praktyczny.
SYS;
        return $this->chatJson($system, $description, 1000);
    }

    /**
     * Pricing advisor: kontekst trasy → sugerowana cena z uzasadnieniem.
     *
     * @param array $context  ['distance_km' => ..., 'duration_min' => ..., 'tolls_pln' => ..., 'fuel_pln' => ..., 'driver_pln' => ..., 'vehicle' => 'name', 'countries' => 'PL,DE', 'date' => '2026-05-14', 'rate_per_km' => 4.5]
     */
    public function priceAdvisor(array $context): array
    {
        $ctx = json_encode($context, JSON_UNESCAPED_UNICODE);
        $system = <<<SYS
Jesteś analitykiem cen frachtu w Polsce/EU. Otrzymujesz dane trasy i sugerujesz
optymalną cenę frachtu w PLN. Uwzględnij sezon, dzień tygodnia, opłaty, marżę
typową dla branży (10-25%), ADR/tunelowanie, weekendy.

Zwracasz JSON:
{
  "suggested_price_pln": liczba (cena całkowita w PLN),
  "price_per_km_pln": liczba (stawka),
  "margin_percent": liczba (marża nad bezpośrednie koszty),
  "reasoning": "uzasadnienie 2-3 zdania po polsku",
  "factors": [tablica 3-5 czynników wpływających, np. ["Wysokie opłaty DE Maut", "Weekend +10%", "ADR kl.3 +5%"]],
  "competitiveness": "low/fair/high (vs rynek)"
}
SYS;
        return $this->chatJson($system, $ctx, 800);
    }

    /**
     * Route optimizer: analizuje N alternatyw i rekomenduje najlepszą.
     *
     * @param array $alternatives Array of { idx, distance_km, duration_min, tolls_pln, fuel_pln, driver_pln, countries }
     * @param array $criteria     ['priority' => 'time'|'cost'|'comfort'|'risk']
     */
    public function routeOptimizer(array $alternatives, array $criteria = []): array
    {
        $ctx = json_encode(['alternatives' => $alternatives, 'criteria' => $criteria], JSON_UNESCAPED_UNICODE);
        $system = <<<SYS
Jesteś dyspozytorem flot z 20 letnim doświadczeniem. Otrzymujesz N alternatyw trasy
z metrykami (czas, koszt, kraje). Oceniasz każdą po 4 kryteriach (1-10):
- speed: szybkość
- cost: ekonomia
- comfort: komfort jazdy (autostrady, dobre drogi)
- risk: brak ryzyka (mało przejść granicznych, dobre kraje)

Zwracasz JSON:
{
  "recommended_idx": <0-based index>,
  "scores": [
    {"idx": 0, "speed": 8, "cost": 6, "comfort": 9, "risk": 7, "overall": 7.5, "note": "krótkie 1-zd uzasadnienie"},
    ...
  ],
  "reasoning": "Dlaczego polecam idx=N — 2-3 zdania po polsku."
}
SYS;
        return $this->chatJson($system, $ctx, 1000);
    }

    /**
     * AI email reply: na wklejony email klienta sugeruje odpowiedź z kalkulacją.
     */
    public function emailReply(string $clientEmail, array $routeCtx = []): array
    {
        $ctx = json_encode(['email' => $clientEmail, 'route' => $routeCtx], JSON_UNESCAPED_UNICODE);
        $system = <<<SYS
Jesteś dyspozytorem firmy transportowej. Otrzymujesz email od klienta (możliwe
zapytanie o ofertę, status zlecenia, fakturę). Zwracasz JSON:
{
  "intent": "quote_request"|"status_check"|"complaint"|"other",
  "subject": "PL temat odpowiedzi",
  "body_pl": "PL pełna odpowiedź (form. grzecznościowa, ze stawką/ETA jeśli intent=quote_request)",
  "body_en": "EN tłumaczenie tej samej odpowiedzi",
  "extracted_route": {"from": "...", "to": "...", "weight_t": null|liczba, "date": "..."},
  "next_action": "<co dyspozytor powinien teraz zrobić, krótko>"
}
Jeśli intent=quote_request i mamy kontekst route z ceną/dystansem — wpleć w body.
Zachowuj profesjonalny ale przyjazny ton.
SYS;
        return $this->chatJson($system, $ctx, 1500);
    }

    /**
     * Driver brief: trasa → dokument-instrukcja dla kierowcy w wybranym języku.
     */
    public function driverBrief(array $routeContext, string $language = 'pl'): string
    {
        $langMap = [
            'pl' => 'polskim',
            'en' => 'angielskim',
            'de' => 'niemieckim',
            'ua' => 'ukraińskim',
            'ru' => 'rosyjskim',
        ];
        $langName = $langMap[$language] ?? 'polskim';
        $ctx = json_encode($routeContext, JSON_UNESCAPED_UNICODE);
        $system = <<<SYS
Jesteś dyspozytorem floty. Generujesz CZYTELNY brief dla kierowcy w języku $langName.
Format: zwykły tekst (nie markdown), krótkie sekcje, każda zwięzła. Uwzględnij:
- Trasa: punkty A → B → ...
- Daty/godziny załadunku i rozładunku jeśli podane
- Pojazd
- Etapy z dystansem i czasem
- Wymagane przerwy 45 min co 4.5h jazdy (AETR)
- Przejścia graniczne i szacowany czas oczekiwania jeśli są
- Opłaty drogowe (suma + kraje)
- Ostrzeżenia ADR jeśli są
- Kontakt awaryjny: dyspozytor +48 600 100 200
- Życzenia szczęśliwej drogi w odpowiednim języku

Maksymalnie 30 linii, używaj emoji oszczędnie dla wizualnej hierarchii (📍🚛⏰⛽🛂).
SYS;
        return $this->chatText($system, $ctx, 1500);
    }
}
