<?php
declare(strict_types=1);

namespace App\Service\Weather;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use RuntimeException;

/**
 * OpenWeatherMap API wrapper.
 *
 * Free tier (One Call API 3.0 wymaga subskrypcji, ale current + forecast 5-day są w free).
 * Tutaj używamy `forecast` (5 dni × 3h) — to free i wystarcza dla planera.
 */
class OpenWeatherService
{
    private const FORECAST_URL = 'https://api.openweathermap.org/data/2.5/forecast';
    private const CURRENT_URL  = 'https://api.openweathermap.org/data/2.5/weather';

    private string $apiKey;
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $key = (string)Configure::read('OpenWeather.apiKey');
        if ($key === '') {
            throw new RuntimeException('Klucz OpenWeather API nie jest skonfigurowany.');
        }
        $this->apiKey = $key;
        $this->client = $client ?? new Client(['timeout' => 15]);
    }

    /**
     * Pobiera prognozę pogody na konkretną godzinę dla punktu (lat/lng).
     *
     * @param float       $lat
     * @param float       $lng
     * @param string|null $iso  ISO datetime — jeśli null, pobiera current
     * @return array{temp:float,feels:float,desc:string,icon:string,wind:float,humidity:int,clouds:int,rain:float,snow:float,dt:string,warning:?string}|null
     */
    public function forecastAt(float $lat, float $lng, ?string $iso = null): ?array
    {
        try {
            // Jeśli ISO jest > 5 dni w przyszłość lub w przeszłości → użyj current
            $target = $iso ? strtotime($iso) : 0;
            $now = time();
            $useCurrent = !$target || $target < $now || ($target - $now) > 5 * 86400;

            if ($useCurrent) {
                $resp = $this->client->get(self::CURRENT_URL, [
                    'lat' => $lat, 'lon' => $lng, 'units' => 'metric', 'lang' => 'pl',
                    'appid' => $this->apiKey,
                ]);
                if (!$resp->isOk()) {
                    Log::warning('OpenWeather current HTTP ' . $resp->getStatusCode() . ': ' . $resp->getStringBody());
                    return null;
                }
                $data = $resp->getJson();
                return $this->normalize($data);
            }

            // Forecast (5d × 3h) — znajdź najbliższy timestamp
            $resp = $this->client->get(self::FORECAST_URL, [
                'lat' => $lat, 'lon' => $lng, 'units' => 'metric', 'lang' => 'pl',
                'appid' => $this->apiKey,
            ]);
            if (!$resp->isOk()) {
                Log::warning('OpenWeather forecast HTTP ' . $resp->getStatusCode() . ': ' . $resp->getStringBody());
                return null;
            }
            $data = $resp->getJson();
            $list = $data['list'] ?? [];
            if (empty($list)) return null;

            $best = $list[0];
            $bestDiff = PHP_INT_MAX;
            foreach ($list as $entry) {
                $d = abs(((int)$entry['dt']) - $target);
                if ($d < $bestDiff) { $bestDiff = $d; $best = $entry; }
            }
            return $this->normalize($best);
        } catch (\Throwable $e) {
            Log::error('OpenWeather error: ' . $e->getMessage());
            return null;
        }
    }

    private function normalize(array $entry): array
    {
        $main = $entry['main'] ?? [];
        $weather = ($entry['weather'][0] ?? []);
        $wind = $entry['wind']['speed'] ?? 0;
        $rain = $entry['rain']['3h'] ?? ($entry['rain']['1h'] ?? 0);
        $snow = $entry['snow']['3h'] ?? ($entry['snow']['1h'] ?? 0);
        $temp = (float)($main['temp'] ?? 0);

        // Ostrzeżenia praktyczne dla kierowcy
        $warn = null;
        if ($snow > 0.5)    $warn = 'Opady śniegu — uważaj na oblodzenie i widoczność.';
        elseif ($rain > 3)  $warn = 'Intensywny deszcz — ograniczona widoczność, dłuższy hamulec.';
        elseif ($temp <= 0) $warn = 'Temperatura poniżej zera — możliwe oblodzenie nawierzchni.';
        elseif ($wind > 15) $warn = 'Silny wiatr (' . round($wind) . ' m/s) — niebezpieczny dla wysokich pojazdów.';

        return [
            'temp'     => round($temp, 1),
            'feels'    => round((float)($main['feels_like'] ?? $temp), 1),
            'desc'     => (string)($weather['description'] ?? ''),
            'icon'     => (string)($weather['icon'] ?? ''),
            'wind'     => round((float)$wind, 1),
            'humidity' => (int)($main['humidity'] ?? 0),
            'clouds'   => (int)($entry['clouds']['all'] ?? 0),
            'rain'     => round((float)$rain, 1),
            'snow'     => round((float)$snow, 1),
            'dt'       => date('c', (int)($entry['dt'] ?? time())),
            'warning'  => $warn,
        ];
    }
}
