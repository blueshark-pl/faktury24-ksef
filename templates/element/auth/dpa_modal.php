<?php
/**
 * Element: DPA (modal)
 * Plik: templates/element/auth/dpa_modal.php
 */

$dpaText = <<<'TXT'
ZAŁĄCZNIK NR 1
UMOWA POWIERZENIA PRZETWARZANIA DANYCH OSOBOWYCH (DPA)

§ 1 Strony

Administratorem danych jest Użytkownik Serwisu.
Procesorem jest:
Biuro Rachunkowe „PARTNER” s.c.
ul. Ciołka 10, 01-402 Warszawa
NIP: 527-251-12-37
kontakt@faktury24.com

§ 2 Zakres powierzenia

1. Administrator powierza Procesorowi dane osobowe wprowadzane do Serwisu.
2. Przetwarzanie odbywa się wyłącznie w celu świadczenia usług Serwisu.

§ 3 Kategorie danych

1. Dane identyfikacyjne (np. imię, nazwisko, nazwa, NIP).
2. Dane adresowe.
3. Dane kontaktowe.
4. Dane finansowe i dokumentowe.

§ 4 Obowiązki Procesora

1. Przetwarzanie wyłącznie na polecenie Administratora.
2. Zachowanie poufności.
3. Stosowanie środków bezpieczeństwa zgodnych z art. 32 RODO.
4. Informowanie o naruszeniach danych bez zbędnej zwłoki.

§ 5 Podpowierzenie

1. Administrator wyraża zgodę na korzystanie z podwykonawców IT i hostingowych.
2. Procesor zapewnia odpowiedni poziom ochrony danych.

§ 6 KSeF

1. Serwis generuje XML na podstawie danych Administratora.
2. Nie przechowuje tokenów ani certyfikatów.
3. Nie cache’uje UPO.

§ 7 Zakończenie

1. Po zakończeniu umowy dane zostają usunięte lub udostępnione do pobrania.
2. DPA stanowi integralną część Regulaminu.
TXT;

$raw = (string)$dpaText;
$chunks = preg_split('/^\s*§\s*(\d+)\s+([^\r\n]+)\s*$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

$preamble = trim((string)($chunks[0] ?? ''));
$sections = [];

for ($i = 1; $i < count($chunks); $i += 3) {
  $num = trim((string)($chunks[$i] ?? ''));
  $title = trim((string)($chunks[$i + 1] ?? ''));
  $content = trim((string)($chunks[$i + 2] ?? ''));
  if ($num === '' || $title === '' || $content === '') continue;

  $sections[] = [
    'id' => 'dpa-par-' . $num,
    'toc' => '§ ' . $num . ' — ' . $title,
    'heading' => '§ ' . $num . ' ' . $title,
    'content' => $content,
  ];
}
?>

<!-- UI identyczne jak w regulaminie (sticky + smooth scroll) -->
