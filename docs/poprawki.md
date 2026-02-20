# Poprawki / TODO (KSeF + UX faktur)

Data: 2026-02-20

## Co już zostało wdrożone

- [x] Usunięto akcję „Zapisz i wyślij do KSeF” z formularzy innych niż podstawowa faktura VAT.
- [x] Dodano to samo podejście także w widoku faktury „zwykłej” (przycisk zapisu bez natychmiastowej wysyłki do KSeF).
- [x] Naprawiono problem gubienia pozycji przy zapisie (np. 2. pozycja w walutowej):
  - synchronizacja nazwy pozycji przed submit,
  - brak „cichego” pomijania pozycji z danymi bez nazwy (jawny błąd pozycji).
- [x] Przy błędzie zapisu zachowane i ponownie wypełniane są dane:
  - kontrahenta,
  - pozycji faktury.

## Odpowiedź do uwag biznesowych

1. **Jednostka miary** – rekomendacja: **lista podpowiedzi + własna wartość** (bez sztywnej blokady).
2. **Stawki VAT** – rekomendacja: **wyłącznie wybór z listy** (spójność danych i KSeF).
3. **GTU** – rekomendacja: **jak dotychczas** (kontrolowany słownik, opcjonalne użycie wg procesu).
4. **PKWiU** – rekomendacja: **pole opcjonalne, dostępne** (bez wymuszania w standardowym flow).
5. **Nazwa skrócona** – można **usunąć z UI** (brak wartości biznesowej w obecnym procesie).

## Kierunek UX (ocena)

Kierunek jest dobry i spójny z praktyką:
- dokument roboczy edytowalny,
- finalizacja i blokada dopiero przy wysyłce do KSeF,
- jasna, stała informacja o trybie KSeF i uprawnieniach.

To zmniejszy liczbę błędów operacyjnych i pytań użytkowników.

## TODO – etapowanie wdrożenia

### Etap 1 (najwyższy priorytet)

- [x] **Ustawienie firmy**: `Wysyłam dokumenty do KSeF: TAK/NIE` (domyślnie TAK).
- [x] **Stały pasek statusu u góry** (globalnie):
  - `Tryb KSeF: WŁ / WYŁ`,
  - `Uprawnienia KSeF: OK / brak / wymagane`.
- [x] **Warunkowe etykiety przycisków**:
  - KSeF WŁ: `Zapisz i wyślij do KSeF` + `Zapisz jako robocza`,
  - KSeF WYŁ: `Zapisz i wystaw` / `Zapisz i wyślij do kontrahenta` (bez KSeF).
- [ ] **Usunięcie zbędnych elementów modal/JS KSeF** tam, gdzie nie ma już akcji „save_and_send_ksef”.

### Etap 2 (robocze faktury)

- [x] Nowy status dokumentu: `draft` (robocza, bez numeru faktury i bez numeru KSeF).
- [x] Pole: `planned_ksef_send_at` (data planowanej wysyłki).
- [x] Lista roboczych:
  - kolumny: status, data planowanej wysyłki, kontrahent, kwota,
  - szybkie akcje: `Edytuj`, `Usuń`, `Wyślij teraz`, `Zaplanuj`.
- [x] Banner po logowaniu: `Masz X roboczych faktur niewysłanych do KSeF` + link.

### Etap 3 (finalizacja i wysyłka)

- [x] Akcja `Zatwierdź i wyślij do KSeF` z potwierdzeniem blokady edycji.
- [x] Przy wysyłce:
  - nadanie lokalnego numeru faktury,
  - przejście do statusu `sending` / `sent` / `error`,
  - blokada edycji treści (zmiany wyłącznie korektą).
- [ ] Odbiór i zapis numeru KSeF + UPO.

### Etap 4 (walidacje i automaty)

- [ ] Walidacja przed wysyłką do KSeF:
  - `P_1 (data faktury) >= dzisiaj - 1 dzień`,
  - w przeciwnym razie blokada + komunikat.
- [ ] Scheduler: wysyłka zaplanowanych roboczych w zadanym dniu.
- [ ] Log zdarzeń wysyłki (audit trail + diagnostyka).

## Ryzyka / czasochłonność

- **Średnie**: statusy i robocze faktury (zmiana modelu cyklu życia dokumentu).
- **Średnie/Wyższe**: scheduler + niezawodna kolejka wysyłki + retry.
- **Niskie/Średnie**: pasek statusu KSeF i przełącznik trybu firmy.

## Proponowana kolejność realizacji

1. Etap 1 (ustawienia + widoczność + przyciski)  
2. Etap 2 (draft + lista roboczych + banner)  
3. Etap 3 (finalizacja, blokada, numeracja przy wysyłce)  
4. Etap 4 (walidacja daty + harmonogram + retry)

---

Jeśli priorytetem jest szybki efekt dla klientów, najlepiej zacząć od **Etapu 1 + minimalnego Etapu 2** (draft + lista + „wyślij teraz”).
