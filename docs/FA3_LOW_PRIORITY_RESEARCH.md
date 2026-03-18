# FA(3) KSeF — LOW Priority Items Research Report
**Date:** 2026-03-18  
**Source:** `src/faktura.xsd` (FA(3) schema, 3950 lines)  
**Controller:** `src/Controller/InvoicesController.php` (5809 lines)

---

## Table of Contents
1. [NoweSrodkiTransportu](#1-nowesrodkitransportu)
2. [WarunkiTransakcji](#2-warunkitransakcji)
3. [Zamówienie](#3-zamówienie)
4. [Obciazenia / Odliczenia](#4-obciazenia--odliczenia)
5. [Skonto](#5-skonto)
6. [KursWaluty per-wiersz](#6-kurswaluty-per-wiersz)
7. [RachunekBankowyFaktora](#7-rachunekbankowyfaktora)
8. [Adres korespondencyjny](#8-adres-korespondencyjny-adreskoresp)
9. [StatusInfoPodatnika](#9-statusinfopodatnika)
10. [PodmiotUpowazniony](#10-podmiotupowazniony)
11. [Summary — New Tables & Columns](#11-summary--new-tables--columns)

---

## 1. NoweSrodkiTransportu

### XSD Location
- **Parent:** `Fa > Adnotacje > NoweSrodkiTransportu` (XSD line ~2703)
- **Required:** YES (element is mandatory inside `Adnotacje`, but uses `xsd:choice` — either data OR `P_22N=1`)

### XSD Structure
```
NoweSrodkiTransportu (complexType, choice)
├── [Option A: is WDT]
│   ├── P_22           : TWybor1          (REQUIRED) — "1" = is WDT of new transport means
│   ├── P_42_5         : TWybor1_2        (REQUIRED) — art. 42 ust. 5 obligation (1=yes, 2=no)
│   └── NowySrodekTransportu (maxOccurs=10000, complexType)
│       ├── P_22A      : TDataT           (REQUIRED) — date of first use
│       ├── P_NrWierszaNST : TNaturalny   (REQUIRED) — invoice line number
│       ├── P_22BMK    : TZnakowy         (opt) — brand/marka
│       ├── P_22BMD    : TZnakowy         (opt) — model
│       ├── P_22BK     : TZnakowy         (opt) — color
│       ├── P_22BNR    : TZnakowy         (opt) — registration number
│       ├── P_22BRP    : TZnakowy         (opt) — year of production
│       └── [CHOICE — vehicle type]:
│           ├── [Land vehicle]:
│           │   ├── P_22B   : TZnakowy    (REQUIRED) — mileage
│           │   ├── [choice opt: P_22B1 (VIN) | P_22B2 (body nr) | P_22B3 (chassis nr) | P_22B4 (frame nr)]
│           │   └── P_22BT  : TZnakowy    (opt) — vehicle type
│           ├── [Watercraft]:
│           │   ├── P_22C   : TZnakowy    (REQUIRED) — hours of operation
│           │   └── P_22C1  : TZnakowy    (opt) — hull number
│           └── [Aircraft]:
│               ├── P_22D   : TZnakowy    (REQUIRED) — hours of operation
│               └── P_22D1  : TZnakowy    (opt) — serial number
│
├── [Option B: NOT WDT]
│   └── P_22N          : TWybor1          — "1" = no WDT of new transport
```

### Existing Code Status
**✅ FULLY IMPLEMENTED** — `buildNoweSrodkiTransportuXml()` method at line ~5384.
- Reads `$inv->is_new_transport_wdt` (bool) and `$inv->new_transport_rows` (array)
- Handles all P_22* fields, P_22B–D variants, P_22BT, VIN/body/chassis/frame choice
- Outputs `P_22N=1` when not WDT

### DB Status
**⚠️ NO DB COLUMNS** — the builder reads virtual/transient properties. Needs:
- `invoices.is_new_transport_wdt` (bool)
- `invoices.p_42_5` (tinyint)
- NEW TABLE `invoice_new_transport_rows` for `NowySrodekTransportu` items

### Recommended DB Columns

**Table: `invoices`**
| Column | Type | Comment |
|---|---|---|
| `is_new_transport_wdt` | `TINYINT(1) DEFAULT 0` | WDT nowych środków transportu |
| `p_42_5` | `TINYINT(1) NULL` | Art. 42 ust. 5 (1/2) |

**NEW Table: `invoice_new_transports`**
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | UUID |
| `invoice_id` | `CHAR(36) FK` | → invoices |
| `p_22a` | `DATE NOT NULL` | Data dopuszczenia |
| `line_number` | `INT NOT NULL` | Nr wiersza faktury (P_NrWierszaNST) |
| `brand` | `VARCHAR(256) NULL` | Marka (P_22BMK) |
| `model` | `VARCHAR(256) NULL` | Model (P_22BMD) |
| `color` | `VARCHAR(256) NULL` | Kolor (P_22BK) |
| `registration_nr` | `VARCHAR(256) NULL` | Nr rejestracyjny (P_22BNR) |
| `production_year` | `VARCHAR(256) NULL` | Rok produkcji (P_22BRP) |
| `vehicle_type` | `ENUM('land','water','air')` | Typ środka transportu |
| `mileage_or_hours` | `VARCHAR(256) NULL` | Przebieg/godziny (P_22B/C/D) |
| `vin` | `VARCHAR(256) NULL` | VIN (P_22B1) |
| `body_nr` | `VARCHAR(256) NULL` | Nr nadwozia (P_22B2) |
| `chassis_nr` | `VARCHAR(256) NULL` | Nr podwozia (P_22B3) |
| `frame_nr` | `VARCHAR(256) NULL` | Nr ramy (P_22B4) |
| `vehicle_type_desc` | `VARCHAR(256) NULL` | Typ (P_22BT) |
| `hull_nr` | `VARCHAR(256) NULL` | Nr kadłuba (P_22C1) |
| `serial_nr` | `VARCHAR(256) NULL` | Nr fabryczny (P_22D1) |

---

## 2. WarunkiTransakcji

### XSD Location
- **Parent:** `Fa > WarunkiTransakcji` (XSD line ~3441)
- **Required:** NO (`minOccurs="0"`)

### XSD Structure
```
WarunkiTransakcji (complexType, optional)
├── Umowy (0..100, complexType)
│   ├── DataUmowy     : TDataU     (opt) — contract date
│   └── NrUmowy       : TZnakowy   (opt) — contract number
├── Zamowienia (0..100, complexType)
│   ├── DataZamowienia: TDataU     (opt) — order date
│   └── NrZamowienia  : TZnakowy   (opt) — order number
├── NrPartiiTowaru    : TZnakowy   (0..1000) — batch numbers
├── WarunkiDostawy    : TZnakowy   (opt) — Incoterms delivery terms
├── [sequence opt]:
│   ├── KursUmowny    : TIlosci    (REQUIRED in seq) — contractual exchange rate
│   └── WalutaUmowna  : TKodWaluty (REQUIRED in seq) — contractual currency code
├── Transport (0..20, complexType)
│   ├── [choice]:
│   │   ├── RodzajTransportu : TRodzajTransportu (enum 1-8)
│   │   └── [TransportInny=1 + OpisInnegoTransportu : TZnakowy50]
│   ├── Przewoznik (opt)
│   │   ├── DaneIdentyfikacyjne : TPodmiot2
│   │   └── AdresPrzewoznika    : TAdres
│   ├── NrZleceniaTransportu : TZnakowy (opt)
│   ├── [choice: OpisLadunku(TLadunek enum 1-20) | LadunekInny=1+OpisInnegoLadunku]
│   │   └── JednostkaOpakowania : TZnakowy (opt)
│   ├── DataGodzRozpTransportu : TDataCzas (opt)
│   ├── DataGodzZakTransportu  : TDataCzas (opt)
│   ├── WysylkaZ    : TAdres (opt) — origin address
│   ├── WysylkaPrzez: TAdres (0..20) — intermediate addresses
│   └── WysylkaDo   : TAdres (opt) — destination address
└── PodmiotPosredniczacy : TWybor1 (opt) — "1" = intermediary entity
```

**Referenced enums:**
- `TRodzajTransportu`: 1=morski, 2=kolejowy, 3=drogowy, 4=lotniczy, 5=pocztowy, 7=instalacje, 8=śródlądowy
- `TLadunek`: 1=Bańka, 2=Beczka, 3=Butla, 4=Karton, 5=Kanister, 6=Klatka, 7=Kontener, 8=Kosz, 9=Łubianka, 10=Opak.zbiorcze, 11=Paczka, 12=Pakiet, 13=Paleta, 14=Pojemnik, 15=Poj.masowe stałe, 16=Poj.masowe płynne, 17=Pudełko, 18=Puszka, 19=Skrzynia, 20=Worek

### Existing Code Status
**❌ NOT IMPLEMENTED** — no builder code found.

### DB Status
**❌ NO DB COLUMNS** — needs new tables for this complex structure.

### Recommended DB Columns

**Table: `invoices`**
| Column | Type | Comment |
|---|---|---|
| `delivery_terms` | `VARCHAR(256) NULL` | Incoterms (WarunkiDostawy) |
| `contractual_rate` | `DECIMAL(22,6) NULL` | KursUmowny |
| `contractual_currency` | `CHAR(3) NULL` | WalutaUmowna (ISO-4217) |
| `is_intermediary` | `TINYINT(1) NULL` | PodmiotPośredniczący (1=tak) |

**NEW Table: `invoice_contracts`**
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `contract_date` | `DATE NULL` | DataUmowy |
| `contract_number` | `VARCHAR(256) NULL` | NrUmowy |

**NEW Table: `invoice_orders`**
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `order_date` | `DATE NULL` | DataZamowienia |
| `order_number` | `VARCHAR(256) NULL` | NrZamowienia |

**NEW Table: `invoice_batch_numbers`**
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `batch_number` | `VARCHAR(256)` | NrPartiiTowaru |

**NEW Table: `invoice_transports`** (complex — may also need sub-tables for addresses)
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `transport_type` | `TINYINT NULL` | RodzajTransportu (1-8) |
| `transport_other` | `VARCHAR(50) NULL` | OpisInnegoTransportu |
| `carrier_name` | `VARCHAR(256) NULL` | Przewoźnik |
| `carrier_nip` | `VARCHAR(20) NULL` | NIP przewoźnika |
| `carrier_address` | `TEXT NULL` | JSON: KodKraju+AdresL1+AdresL2 |
| `transport_order_nr` | `VARCHAR(256) NULL` | NrZleceniaTransportu |
| `cargo_type` | `TINYINT NULL` | OpisLadunku (1-20) |
| `cargo_other` | `VARCHAR(50) NULL` | OpisInnegoLadunku |
| `packing_unit` | `VARCHAR(256) NULL` | JednostkaOpakowania |
| `start_datetime` | `DATETIME NULL` | DataGodzRozpTransportu |
| `end_datetime` | `DATETIME NULL` | DataGodzZakTransportu |
| `origin_address` | `TEXT NULL` | JSON WysylkaZ |
| `destination_address` | `TEXT NULL` | JSON WysylkaDo |
| `intermediate_addresses` | `TEXT NULL` | JSON array WysylkaPrzez |

---

## 3. Zamówienie

### XSD Location
- **Parent:** `Fa > Zamowienie` (XSD line ~3610)
- **Required:** NO (`minOccurs="0"`) — used only for advance invoices (ZAL)

### XSD Structure
```
Zamowienie (complexType, optional)
├── WartoscZamowienia : TKwotowy (REQUIRED) — total order value incl. tax
└── ZamowienieWiersz (1..10000, complexType)
    ├── NrWierszaZam   : TNaturalny   (REQUIRED) — line number
    ├── UU_IDZ         : TZnakowy50   (opt) — universal unique line ID
    ├── P_7Z           : TZnakowy512  (opt) — product/service name
    ├── IndeksZ        : TZnakowy50   (opt) — internal code
    ├── GTINZ          : TZnakowy20   (opt) — GTIN
    ├── PKWiUZ         : TZnakowy50   (opt) — PKWiU code
    ├── CNZ            : TZnakowy50   (opt) — CN code
    ├── PKOBZ          : TZnakowy50   (opt) — PKOB code
    ├── P_8AZ          : TZnakowy     (opt) — unit of measure
    ├── P_8BZ          : TIlosci      (opt) — quantity
    ├── P_9AZ          : TKwotowy2    (opt) — net unit price
    ├── P_11NettoZ     : TKwotowy     (opt) — net value
    ├── P_11VatZ       : TKwotowy     (opt) — VAT amount
    ├── P_12Z          : TStawkaPodatku (opt) — VAT rate
    ├── P_12Z_XII      : TProcentowy  (opt) — VAT rate for chapter XII
    ├── P_12Z_Zal_15   : TWybor1      (opt) — attachment 15 flag
    ├── GTUZ           : TGTU         (opt) — GTU code
    ├── ProceduraZ     : TOznaczenieProceduryZ (opt) — procedure marking
    ├── KwotaAkcyzyZ   : TKwotowy     (opt) — excise amount
    └── StanPrzedZ     : TWybor1      (opt) — "before correction" flag
```

### Existing Code Status
**❌ NOT IMPLEMENTED** — no builder code found.

### DB Status
**❌ NO DB COLUMNS**

### Recommended DB Columns

**Table: `invoices`**
| Column | Type | Comment |
|---|---|---|
| `order_total_value` | `DECIMAL(18,2) NULL` | WartoscZamowienia |

**NEW Table: `invoice_order_lines`** (for ZamowienieWiersz)
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `line_number` | `INT NOT NULL` | NrWierszaZam |
| `uu_id` | `VARCHAR(50) NULL` | UU_IDZ |
| `name` | `VARCHAR(512) NULL` | P_7Z |
| `internal_code` | `VARCHAR(50) NULL` | IndeksZ |
| `gtin` | `VARCHAR(20) NULL` | GTINZ |
| `pkwiu` | `VARCHAR(50) NULL` | PKWiUZ |
| `cn_code` | `VARCHAR(50) NULL` | CNZ |
| `pkob` | `VARCHAR(50) NULL` | PKOBZ |
| `unit` | `VARCHAR(256) NULL` | P_8AZ |
| `quantity` | `DECIMAL(22,6) NULL` | P_8BZ |
| `unit_price_net` | `DECIMAL(22,8) NULL` | P_9AZ |
| `net_value` | `DECIMAL(18,2) NULL` | P_11NettoZ |
| `vat_amount` | `DECIMAL(18,2) NULL` | P_11VatZ |
| `vat_rate` | `DECIMAL(9,6) NULL` | P_12Z |
| `vat_rate_xii` | `DECIMAL(9,6) NULL` | P_12Z_XII |
| `is_attachment15` | `TINYINT(1) NULL` | P_12Z_Zal_15 |
| `gtu_code` | `VARCHAR(16) NULL` | GTUZ |
| `procedure_marking` | `VARCHAR(50) NULL` | ProceduraZ |
| `excise_amount` | `DECIMAL(18,2) NULL` | KwotaAkcyzyZ |
| `is_before_correction` | `TINYINT(1) NULL` | StanPrzedZ |

---

## 4. Obciazenia / Odliczenia

### XSD Location
- **Parent:** `Fa > Rozliczenie` (XSD line ~3218)
- **Required:** NO — `Rozliczenie` itself is `minOccurs="0"`

### XSD Structure
```
Rozliczenie (complexType, optional)
├── Obciazenia (0..100, complexType)
│   ├── Kwota   : TKwotowy  (REQUIRED) — charge amount added to P_15
│   └── Powod   : TZnakowy  (REQUIRED) — reason for charge
├── SumaObciazen : TKwotowy  (opt) — sum of charges
├── Odliczenia (0..100, complexType)
│   ├── Kwota   : TKwotowy  (REQUIRED) — deduction amount subtracted from P_15
│   └── Powod   : TZnakowy  (REQUIRED) — reason for deduction
├── SumaOdliczen : TKwotowy  (opt) — sum of deductions
└── [choice opt]:
    ├── DoZaplaty     : TKwotowy — amount to pay = P_15 + charges - deductions
    └── DoRozliczenia : TKwotowy — overpayment amount for settlement/refund
```

### Existing Code Status
**❌ NOT IMPLEMENTED** — no builder code found.

### DB Status
**❌ NO DB COLUMNS**

### Recommended DB Columns

**NEW Table: `invoice_charges`** (covers both Obciazenia and Odliczenia)
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `type` | `ENUM('charge','deduction')` | Obciazenie vs Odliczenie |
| `amount` | `DECIMAL(18,2) NOT NULL` | Kwota |
| `reason` | `VARCHAR(256) NOT NULL` | Powod |

**Table: `invoices`**
| Column | Type | Comment |
|---|---|---|
| `settlement_amount` | `DECIMAL(18,2) NULL` | DoZaplaty or DoRozliczenia |
| `settlement_type` | `ENUM('pay','refund') NULL` | Which settlement variant |

---

## 5. Skonto

### XSD Location
- **Parent:** `Fa > Platnosc > Skonto` (XSD line ~3398)
- **Required:** NO (`minOccurs="0"`)

### XSD Structure
```
Skonto (complexType, optional)
├── WarunkiSkonta   : TZnakowy (REQUIRED, max 256 chars) — conditions for discount
└── WysokoscSkonta  : TZnakowy (REQUIRED, max 256 chars) — discount amount/rate
```

### Existing Code Status
**❌ NOT IMPLEMENTED** — `buildPaymentXml()` does not output Skonto.

### DB Status
**❌ NO DB COLUMNS**

### Recommended DB Columns

**Table: `invoices`**
| Column | Type | Comment |
|---|---|---|
| `skonto_conditions` | `VARCHAR(256) NULL` | WarunkiSkonta |
| `skonto_amount` | `VARCHAR(256) NULL` | WysokoscSkonta (text, not decimal — XSD is TZnakowy) |

---

## 6. KursWaluty per-wiersz

### XSD Location
- **Parent:** `Fa > FaWiersz > KursWaluty` (XSD line ~3199)
- **Required:** NO (`minOccurs="0"`)
- **Note:** This is DIFFERENT from `KursWalutyZ` (header-level for advance invoices) and `KursWalutyZK`/`KursWalutyZW` (correction variants)

### XSD Structure
```
FaWiersz > KursWaluty : TIlosci (optional)
  — "Kurs waluty stosowany do wyliczenia kwoty podatku w przypadkach,
     o których mowa w dziale VI ustawy"
  — Type: TIlosci = DECIMAL(22,6)
```

**Context — all KursWaluty variants in the schema:**
| Element | Parent | Purpose |
|---|---|---|
| `KursWalutyZ` | `Fa` (line 2636) | Rate for advance invoice (art.106b ust.1 pkt 4) |
| `KursWalutyZK` | `Fa` (line 3001) | Rate before correction (advance correction) |
| `KursWalutyZW` | `ZaliczkaCzesciowa` (line 3024) | Rate per partial advance payment |
| **`KursWaluty`** | **`FaWiersz`** (line 3199) | **Rate per invoice line** |

### Existing Code Status
**❌ NOT IMPLEMENTED** in `buildSingleLineXml()` — the builder does NOT emit `<KursWaluty>`.  
(Note: `KursWalutyZ` at header level IS implemented at line ~4903, reading `$inv->currency_exchange`.)

### DB Status
**❌ NO DB COLUMN** on `invoice_contents`

### Recommended DB Columns

**Table: `invoice_contents`**
| Column | Type | Comment |
|---|---|---|
| `currency_rate` | `DECIMAL(22,6) NULL` | KursWaluty per line (FaWiersz) |

---

## 7. RachunekBankowyFaktora

### XSD Location
- **Parent:** `Fa > Platnosc > RachunekBankowyFaktora` (XSD line ~3393)
- **Required:** NO (`minOccurs="0"`, `maxOccurs="20"`)
- **Type:** `TRachunekBankowy` (same type as main `RachunekBankowy`)

### Referenced Type: `TRachunekBankowy` (XSD line 1507)
```
TRachunekBankowy (complexType)
├── NrRB              : TNrRB              (REQUIRED) — full account number
├── SWIFT             : SWIFT_Type         (opt) — SWIFT code
├── RachunekWlasnyBanku : TRachunekWlasnyBanku (opt, enum 1-3) — bank's own account type
├── NazwaBanku        : TZnakowy           (opt) — bank name
└── OpisRachunku      : TZnakowy           (opt) — account description
```

`TRachunekWlasnyBanku` enum: 1=rachunek wierzytelności, 2=rachunek pobrań, 3=rachunek własny banku

### Existing Code Status
**❌ NOT IMPLEMENTED** — `buildPaymentXml()` only emits the main `RachunekBankowy` (seller's bank account). No `RachunekBankowyFaktora` output.

### DB Status
**❌ NO DB COLUMNS** — factoring bank accounts not stored.

### Recommended DB Columns

**NEW Table: `invoice_factor_bank_accounts`**
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK` | |
| `account_number` | `VARCHAR(64) NOT NULL` | NrRB |
| `swift` | `VARCHAR(16) NULL` | SWIFT |
| `own_account_type` | `TINYINT NULL` | RachunekWlasnyBanku (1-3) |
| `bank_name` | `VARCHAR(256) NULL` | NazwaBanku |
| `account_desc` | `VARCHAR(256) NULL` | OpisRachunku |

---

## 8. Adres korespondencyjny (AdresKoresp)

### XSD Location
Appears in FOUR places:
1. **Podmiot1 (Seller)** — line ~2157 — type: `extension of TAdres` (has complex extension)
2. **Podmiot2 (Buyer)** — line ~2215 — type: `TAdres` (direct)
3. **Podmiot3 (Third party)** — line ~2330 — type: `TAdres` (direct)
4. **PodmiotUpowazniony** — line ~2407 — type: `TAdres` (direct)

- **Required:** NO (`minOccurs="0"` in all cases)

### Referenced Type: `TAdres` (XSD line 1115)
```
TAdres (complexType)
├── KodKraju : TKodKraju  (REQUIRED) — ISO country code
├── AdresL1  : TZnakowy512 (REQUIRED) — address line 1
├── AdresL2  : TZnakowy512 (opt) — address line 2
└── GLN      : TGLN        (opt) — Global Location Number
```

**Note:** For `Podmiot1`, the seller's `AdresKoresp` uses `xsd:extension base="tns:TAdres"` — same fields but allows schema extension.

### Existing Code Status
**❌ NOT IMPLEMENTED** — `buildSellerXml()` and `buildBuyerXml()` do NOT output `AdresKoresp`.

### DB Status
**❌ NO DB COLUMNS** — no correspondence address fields in `invoice_company_details` or `invoice_contractors`.

### Recommended DB Columns

**Table: `invoice_company_details`** (seller correspondence address)
| Column | Type | Comment |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | AdresKoresp/KodKraju |
| `corresp_address_l1` | `VARCHAR(512) NULL` | AdresKoresp/AdresL1 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | AdresKoresp/AdresL2 |
| `corresp_gln` | `VARCHAR(16) NULL` | AdresKoresp/GLN |

**Table: `invoice_contractors`** (buyer correspondence address)
| Column | Type | Comment |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | AdresKoresp/KodKraju |
| `corresp_address_l1` | `VARCHAR(512) NULL` | AdresKoresp/AdresL1 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | AdresKoresp/AdresL2 |
| `corresp_gln` | `VARCHAR(16) NULL` | AdresKoresp/GLN |

**Table: `invoice_recipients`** (Podmiot3 correspondence address)
| Column | Type | Comment |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | AdresKoresp/KodKraju |
| `corresp_address_l1` | `VARCHAR(512) NULL` | AdresKoresp/AdresL1 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | AdresKoresp/AdresL2 |
| `corresp_gln` | `VARCHAR(16) NULL` | AdresKoresp/GLN |

---

## 9. StatusInfoPodatnika

### XSD Location
- **Parent:** `Podmiot1 > StatusInfoPodatnika` (XSD line ~2186)
- **Required:** NO (`minOccurs="0"`)
- **Type:** `TStatusInfoPodatnika` — simple type (enum)

### XSD Structure
```
StatusInfoPodatnika : TStatusInfoPodatnika (optional)
  — simpleType, xsd:integer, enum:
    1 = Podatnik w stanie likwidacji
    2 = Podatnik w trakcie postępowania restrukturyzacyjnego
    3 = Podatnik w stanie upadłości
    4 = Przedsiębiorstwo w spadku
```

### Existing Code Status
**❌ NOT IMPLEMENTED** — `buildSellerXml()` does NOT output `StatusInfoPodatnika`.

### DB Status
**❌ NO DB COLUMNS**

### Recommended DB Columns

**Table: `invoices`** (or `invoice_company_details`)
| Column | Type | Comment |
|---|---|---|
| `seller_status_info` | `TINYINT NULL` | StatusInfoPodatnika (1-4) |

---

## 10. PodmiotUpowazniony

### XSD Location
- **Parent:** `Faktura > PodmiotUpowazniony` (XSD line ~2386)
- **Required:** NO (`minOccurs="0"`)

### XSD Structure
```
PodmiotUpowazniony (complexType, optional)
├── NrEORI                : TZnakowy    (opt) — EORI number
├── DaneIdentyfikacyjne   : TPodmiot1   (REQUIRED)
│   ├── NIP   : TNrNIP       (REQUIRED)
│   └── Nazwa : TZnakowy512  (REQUIRED)
├── Adres                 : TAdres      (REQUIRED)
│   ├── KodKraju : TKodKraju    (REQUIRED)
│   ├── AdresL1  : TZnakowy512  (REQUIRED)
│   ├── AdresL2  : TZnakowy512  (opt)
│   └── GLN      : TGLN         (opt)
├── AdresKoresp           : TAdres      (opt) — correspondence address
├── DaneKontaktowe (0..3, complexType)
│   ├── EmailPU   : TAdresEmail     (opt)
│   └── TelefonPU : TNumerTelefonu  (opt)
└── RolaPU : TRolaPodmiotuUpowaznionego (REQUIRED, enum)
    — 1 = Organ egzekucyjny (art. 106c pkt 1)
    — 2 = Komornik sądowy (art. 106c pkt 2)
    — 3 = Przedstawiciel podatkowy (art. 18a-18d)
```

### Existing Code Status
**❌ NOT IMPLEMENTED** — no builder code for PodmiotUpowazniony.

### DB Status
**❌ NO DB COLUMNS**

### Recommended DB Columns

**NEW Table: `invoice_authorized_entities`** (PodmiotUpowazniony)
| Column | Type | Comment |
|---|---|---|
| `id` | `CHAR(36) PK` | |
| `invoice_id` | `CHAR(36) FK UNIQUE` | max 1 per invoice |
| `eori` | `VARCHAR(256) NULL` | NrEORI |
| `nip` | `VARCHAR(20) NOT NULL` | NIP |
| `name` | `VARCHAR(512) NOT NULL` | Nazwa |
| `country_code` | `CHAR(2) NOT NULL` | KodKraju |
| `address_l1` | `VARCHAR(512) NOT NULL` | AdresL1 |
| `address_l2` | `VARCHAR(512) NULL` | AdresL2 |
| `gln` | `VARCHAR(16) NULL` | GLN |
| `corresp_country_code` | `CHAR(2) NULL` | AdresKoresp KodKraju |
| `corresp_address_l1` | `VARCHAR(512) NULL` | AdresKoresp AdresL1 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | AdresKoresp AdresL2 |
| `corresp_gln` | `VARCHAR(16) NULL` | AdresKoresp GLN |
| `email` | `VARCHAR(128) NULL` | EmailPU |
| `phone` | `VARCHAR(40) NULL` | TelefonPU |
| `role` | `TINYINT NOT NULL` | RolaPU (1-3) |

---

## 11. Summary — New Tables & Columns

### Implementation Status Overview

| # | Item | Builder Code | DB Columns | Priority |
|---|---|---|---|---|
| 1 | NoweSrodkiTransportu | ✅ EXISTS | ❌ Missing | Need new table |
| 2 | WarunkiTransakcji | ❌ Missing | ❌ Missing | Need 4+ tables |
| 3 | Zamówienie (ZAL) | ❌ Missing | ❌ Missing | Need new table |
| 4 | Obciazenia/Odliczenia | ❌ Missing | ❌ Missing | Need new table |
| 5 | Skonto | ❌ Missing | ❌ Missing | 2 cols on invoices |
| 6 | KursWaluty per-wiersz | ❌ Missing | ❌ Missing | 1 col on invoice_contents |
| 7 | RachunekBankowyFaktora | ❌ Missing | ❌ Missing | Need new table |
| 8 | AdresKoresp | ❌ Missing | ❌ Missing | 12 cols across 3 tables |
| 9 | StatusInfoPodatnika | ❌ Missing | ❌ Missing | 1 col on invoices |
| 10 | PodmiotUpowazniony | ❌ Missing | ❌ Missing | Need new table |

### All New Tables Required

| Table | For Item | Rows per Invoice |
|---|---|---|
| `invoice_new_transports` | #1 NoweSrodkiTransportu | 0..10000 |
| `invoice_contracts` | #2 WarunkiTransakcji/Umowy | 0..100 |
| `invoice_orders` | #2 WarunkiTransakcji/Zamowienia | 0..100 |
| `invoice_batch_numbers` | #2 WarunkiTransakcji/NrPartiiTowaru | 0..1000 |
| `invoice_transports` | #2 WarunkiTransakcji/Transport | 0..20 |
| `invoice_order_lines` | #3 Zamówienie/ZamowienieWiersz | 0..10000 |
| `invoice_charges` | #4 Obciazenia+Odliczenia | 0..200 |
| `invoice_factor_bank_accounts` | #7 RachunekBankowyFaktora | 0..20 |
| `invoice_authorized_entities` | #10 PodmiotUpowazniony | 0..1 |

### All New Columns on Existing Tables

**`invoices`** (7 new columns):
| Column | Type | From Item |
|---|---|---|
| `is_new_transport_wdt` | `TINYINT(1) DEFAULT 0` | #1 |
| `p_42_5` | `TINYINT(1) NULL` | #1 |
| `delivery_terms` | `VARCHAR(256) NULL` | #2 |
| `contractual_rate` | `DECIMAL(22,6) NULL` | #2 |
| `contractual_currency` | `CHAR(3) NULL` | #2 |
| `is_intermediary` | `TINYINT(1) NULL` | #2 |
| `order_total_value` | `DECIMAL(18,2) NULL` | #3 |
| `settlement_amount` | `DECIMAL(18,2) NULL` | #4 |
| `settlement_type` | `ENUM('pay','refund') NULL` | #4 |
| `skonto_conditions` | `VARCHAR(256) NULL` | #5 |
| `skonto_amount` | `VARCHAR(256) NULL` | #5 |
| `seller_status_info` | `TINYINT NULL` | #9 |

**`invoice_contents`** (1 new column):
| Column | Type | From Item |
|---|---|---|
| `currency_rate` | `DECIMAL(22,6) NULL` | #6 |

**`invoice_company_details`** (4 new columns):
| Column | Type | From Item |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | #8 |
| `corresp_address_l1` | `VARCHAR(512) NULL` | #8 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | #8 |
| `corresp_gln` | `VARCHAR(16) NULL` | #8 |

**`invoice_contractors`** (4 new columns):
| Column | Type | From Item |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | #8 |
| `corresp_address_l1` | `VARCHAR(512) NULL` | #8 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | #8 |
| `corresp_gln` | `VARCHAR(16) NULL` | #8 |

**`invoice_recipients`** (4 new columns):
| Column | Type | From Item |
|---|---|---|
| `corresp_country_code` | `CHAR(2) NULL` | #8 |
| `corresp_address_l1` | `VARCHAR(512) NULL` | #8 |
| `corresp_address_l2` | `VARCHAR(512) NULL` | #8 |
| `corresp_gln` | `VARCHAR(16) NULL` | #8 |

### Grand Total
- **9 new tables**
- **~25 new columns** across 4 existing tables
- **9 builder methods** to implement (only NoweSrodkiTransportu has builder code)
