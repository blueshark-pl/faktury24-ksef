<?php
/**
 * @var \App\View\AppView $this
 * @var string $trackId
 * @var array $waypoints
 * @var float|null $distance_km
 * @var int|null $duration_min
 * @var float|null $tolls_total
 * @var string|null $tolls_currency
 * @var string $hereApiKey
 */
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= __('Śledź trasę') ?> — Booklio TMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">
<style>
body { background: linear-gradient(135deg, #f9fafb, #eff6ff); font-family: -apple-system, "Segoe UI", Roboto, sans-serif; min-height: 100vh; }
.hero { background: linear-gradient(135deg, #2563eb, #6366f1); color: white; padding: 22px; border-radius: 16px; margin-bottom: 16px; box-shadow: 0 12px 32px rgba(37,99,235,.25); }
.hero h1 { font-size: 1.5rem; margin: 0 0 4px; font-weight: 700; }
.hero .meta { opacity: .9; font-size: .9rem; }
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 16px; }
.stats .pill { background: rgba(255,255,255,.16); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.25); border-radius: 12px; padding: 10px 14px; }
.stats .pill .label { font-size: .65rem; opacity: .85; text-transform: uppercase; letter-spacing: .5px; }
.stats .pill .value { font-size: 1.2rem; font-weight: 700; margin-top: 2px; }
.stats .pill .unit { font-size: .7rem; opacity: .8; margin-left: 3px; }
#map { height: 380px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.waypoint-card { background: white; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
.wp-marker { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; flex-shrink: 0; }
.wp-marker.origin { background: #16a34a; }
.wp-marker.via { background: #f59e0b; }
.wp-marker.dest { background: #dc2626; }
.wp-info { flex: 1; min-width: 0; }
.wp-info .addr { font-weight: 600; font-size: .9rem; }
.wp-info .date { color: #6b7280; font-size: .78rem; }
.footer-note { text-align: center; color: #6b7280; font-size: .75rem; margin-top: 24px; padding: 12px; }
</style>
</head>
<body>
<div class="container py-3" style="max-width: 700px">
    <div class="hero">
        <h1>🚛 <?= __('Śledź swoją trasę') ?></h1>
        <div class="meta"><?= __('Live tracking · Booklio TMS') ?></div>
        <div class="stats">
            <?php if ($distance_km): ?>
            <div class="pill">
                <div class="label"><i class="ri-roadster-line"></i> <?= __('Dystans') ?></div>
                <div class="value"><?= number_format($distance_km, 1, ',', ' ') ?><span class="unit">km</span></div>
            </div>
            <?php endif; ?>
            <?php if ($duration_min): ?>
            <div class="pill">
                <div class="label"><i class="ri-time-line"></i> <?= __('Czas') ?></div>
                <?php $h = intdiv($duration_min, 60); $m = $duration_min % 60; ?>
                <div class="value"><?= $h ?>h <?= $m ?>min</div>
            </div>
            <?php endif; ?>
            <?php if ($tolls_total): ?>
            <div class="pill">
                <div class="label"><i class="ri-money-euro-circle-line"></i> <?= __('Opłaty') ?></div>
                <div class="value"><?= number_format($tolls_total, 2, ',', ' ') ?><span class="unit"><?= h($tolls_currency ?? 'EUR') ?></span></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="map" class="mb-3"></div>

    <h5 class="mb-2 px-2"><i class="ri-route-fill text-primary me-1"></i><?= __('Punkty trasy') ?></h5>
    <?php $count = count($waypoints); foreach ($waypoints as $i => $wp): ?>
        <?php
        $letter = chr(65 + $i);
        $cls = ($i === 0) ? 'origin' : (($i === $count - 1) ? 'dest' : 'via');
        $typeLabel = ($i === 0) ? __('Załadunek') : (($i === $count - 1) ? __('Dostawa') : __('Postój'));
        ?>
        <div class="waypoint-card">
            <div class="wp-marker <?= $cls ?>"><?= $letter ?></div>
            <div class="wp-info">
                <div class="addr"><?= h($wp['address'] ?? $wp['label'] ?? '—') ?></div>
                <div class="date">
                    <i class="ri-map-pin-line"></i> <?= $typeLabel ?>
                    <?php if (!empty($wp['date'])): ?> · <i class="ri-calendar-event-line"></i> <?= h(str_replace('T', ' ', $wp['date'])) ?><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="footer-note">
        <i class="ri-shield-check-line"></i> <?= __('Trasa udostępniona przez Booklio TMS') ?><br>
        <span style="opacity:.7">ID: <code><?= h(substr($trackId, 0, 8)) ?>…</code></span>
    </div>
</div>

<script src="https://js.api.here.com/v3/3.1/mapsjs-core.js" type="text/javascript" charset="utf-8"></script>
<script src="https://js.api.here.com/v3/3.1/mapsjs-service.js" type="text/javascript" charset="utf-8"></script>
<script src="https://js.api.here.com/v3/3.1/mapsjs-ui.js" type="text/javascript" charset="utf-8"></script>
<script src="https://js.api.here.com/v3/3.1/mapsjs-mapevents.js" type="text/javascript" charset="utf-8"></script>
<link rel="stylesheet" href="https://js.api.here.com/v3/3.1/mapsjs-ui.css" type="text/css">

<script>
var hereKey = <?= json_encode($hereApiKey) ?>;
var waypoints = <?= json_encode($waypoints, JSON_UNESCAPED_UNICODE) ?>;

var platform = new H.service.Platform({ apikey: hereKey });
var layers = platform.createDefaultLayers();
var map = new H.Map(document.getElementById('map'), layers.vector.normal.map, {
    center: { lat: 52, lng: 19 }, zoom: 6, pixelRatio: window.devicePixelRatio || 1
});
new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
H.ui.UI.createDefault(map, layers);

// Markery
var pinsGroup = new H.map.Group();
var pts = waypoints.filter(function (w) { return w.lat && w.lng; });
pts.forEach(function (wp, idx) {
    var color = idx === 0 ? '#16a34a' : (idx === pts.length - 1 ? '#dc2626' : '#f59e0b');
    var letter = String.fromCharCode(65 + idx);
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="58" viewBox="0 0 48 58">'
            + '<defs><filter id="shadow"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-opacity=".25"/></filter></defs>'
            + '<g filter="url(#shadow)">'
            + '<path d="M24 0C11 0 0 11 0 24c0 16 24 34 24 34s24-18 24-34C48 11 37 0 24 0z" fill="' + color + '" stroke="white" stroke-width="2"/>'
            + '<circle cx="24" cy="24" r="11" fill="white"/>'
            + '<text x="24" y="29" text-anchor="middle" font-family="sans-serif" font-size="14" font-weight="bold" fill="' + color + '">' + letter + '</text>'
            + '</g></svg>';
    var icon = new H.map.Icon('data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), { anchor: { x: 24, y: 58 } });
    pinsGroup.addObject(new H.map.Marker({ lat: wp.lat, lng: wp.lng }, { icon: icon }));
});
map.addObject(pinsGroup);

// Polyline od HERE Routing (na bazie waypoints)
if (pts.length >= 2) {
    var router = platform.getRoutingService(null, 8);
    var params = {
        transportMode: 'truck',
        origin: pts[0].lat + ',' + pts[0].lng,
        destination: pts[pts.length - 1].lat + ',' + pts[pts.length - 1].lng,
        return: 'polyline'
    };
    pts.slice(1, -1).forEach(function (p, i) { params['via' + i] = new H.service.Url.MultiValueQueryParameter([p.lat + ',' + p.lng]); });
    router.calculateRoute(params, function (result) {
        if (!result.routes || !result.routes.length) return;
        var routeGroup = new H.map.Group();
        result.routes[0].sections.forEach(function (sect) {
            var line = H.geo.LineString.fromFlexiblePolyline(sect.polyline);
            routeGroup.addObject(new H.map.Polyline(line, { style: { strokeColor: 'rgba(37,99,235,.95)', lineWidth: 9 } }));
        });
        map.addObject(routeGroup);
        var bbox = routeGroup.getBoundingBox().mergeRect(pinsGroup.getBoundingBox());
        map.getViewModel().setLookAtData({ bounds: bbox }, true);
    }, function () {
        // Fallback: zoom do pinów
        var bbox = pinsGroup.getBoundingBox();
        if (bbox) map.getViewModel().setLookAtData({ bounds: bbox }, true);
    });
} else {
    var bbox = pinsGroup.getBoundingBox();
    if (bbox) map.getViewModel().setLookAtData({ bounds: bbox }, true);
}
</script>
</body>
</html>
