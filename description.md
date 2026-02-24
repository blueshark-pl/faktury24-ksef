# Opis zmian od 13.02.2026

Zakres: wszystkie zmiany w repozytorium od 2026-02-13 do 2026-02-24 (na podstawie `git log --since="2026-02-13 00:00" --reverse`).

## 1) Podsumowanie tematyczne

### A. Fundament i dokumentacja
- Start repo (`first commit`) i dodanie instrukcji Copilot.
- Regularne aktualizacje dziennika prac i notatek w README.

### B. UI, auth i i18n (13.02)
- Wersjonowanie aplikacji i wyświetlanie wersji w stopkach.
- Integracja statusów KSeF (Latarnia, komunikaty MF, modal, bannery, poprawki otwierania modali).
- Uspójnienia Regulaminu/Polityki + dodanie DPA jako załącznika (osobny modal).
- Lokalizacja CakeDC Users (PL), poprawki cache tłumaczeń, spolszczenie auth templates.
- 2FA opt-in per user + linki do aplikacji 2FA.
- Rejestracja/login/onboarding: naprawy uprawnień, redirectów i synchronizacji `username` z `email`.

### C. Faktury i KSeF workflow (20.02–24.02)
- Rozbudowa edycji faktur: akcje per typ, użycie templatek `add_*`, prefill kontrahenta i pozycji.
- Tryb KSeF per firma, bannery statusu i ograniczenia akcji zależnie od trybu.
- Workflow draftów: lista roboczych, planowanie i wysyłka „teraz”, blokada edycji wysłanych.
- UPO logging i scheduler wysyłek roboczych.
- Dodanie podglądu/szablonów faktur.
- Poprawki numeracji:
  - wymuszenie `number` dla draftów,
  - ostrzeżenie + normalizacja daty draftu przed wysyłką do KSeF,
  - poprawne mapowanie i zachowanie serii numeracji przy edycji.

### D. Rejestracja i dane firmy (22.02–23.02)
- Prefill danych firmy po NIP (GUS + MF), lokal nr, konta bankowe, walidacja VAT/NIP.
- Walidacje i UX formularza rejestracji.
- Epizod z automatycznym tworzeniem firmy podczas rejestracji (kilka commitów) został finalnie cofnięty revertami.
- Wdrożono podejście „uzupełnienie firmy przy pierwszym logowaniu z `additional_data.onboarding_prefill`”.

### E. Serie numeracji firmy
- Dodanie zarządzania seriami w `companies/edit`.
- Kopiowanie serii systemowych, ochrona przed usunięciem, tryb read-only dla skopiowanych.
- Diagnostyka/logowanie i poprawki renderowania.
- Domyślność serii per typ dokumentu.

## 2) Pełna lista commitów (chronologicznie)

- `76c28ba` | 2026-02-13 | first commit
- `acb0ce5` | 2026-02-13 | docs: add Copilot instructions
- `61c8ef3` | 2026-02-13 | feat: add app version and show in footer
- `d17f114` | 2026-02-13 | docs: add work log entry
- `4427890` | 2026-02-13 | docs: clarify repo scope in multi-root
- `cf38fb9` | 2026-02-13 | feat: show Latarnia KSeF status
- `f5d3ceb` | 2026-02-13 | docs: update worklog
- `aff11d0` | 2026-02-13 | feat: show MF messages modal and login banner
- `38a9aeb` | 2026-02-13 | docs: update worklog
- `e653c40` | 2026-02-13 | fix: render MF messages modal reliably
- `fdb964d` | 2026-02-13 | docs: update worklog
- `2188faa` | 2026-02-13 | fix: allow MF modal to open
- `f935b13` | 2026-02-13 | docs: update worklog
- `c6dcd75` | 2026-02-13 | privacy policy
- `3e7992c` | 2026-02-13 | fix: render KSeF MF messages modal reliably
- `0bb0b75` | 2026-02-13 | docs: update worklog
- `0432b06` | 2026-02-13 | term fix
- `3444460` | 2026-02-13 | fix: align MF modal close button
- `bb26ced` | 2026-02-13 | docs: update worklog
- `9cf6911` | 2026-02-13 | fix regulamin
- `93ea2d7` | 2026-02-13 | feat: add DPA modal as regulamin attachment
- `ffb76b5` | 2026-02-13 | docs: log DPA modal attachment
- `9ec90ef` | 2026-02-13 | refactor: match DPA modal styling to regulamin
- `23a787b` | 2026-02-13 | docs: log DPA modal styling alignment
- `a3b6c26` | 2026-02-13 | fix: localize CakeDC Users UI
- `70e04fb` | 2026-02-13 | docs: log CakeDC Users localization
- `d3d85c2` | 2026-02-13 | feat: add 2FA app links on verify
- `03ecde0` | 2026-02-13 | docs: log 2FA verify links
- `4758dbe` | 2026-02-13 | fix: avoid Permission denied for i18n cache
- `84cad3f` | 2026-02-13 | docs: update work log
- `b75164d` | 2026-02-13 | feat: opt-in 2FA per user
- `ec2527a` | 2026-02-13 | fix: sync username with email on register
- `1c64dce` | 2026-02-13 | docs: update work log
- `3a1ee5a` | 2026-02-13 | fix: allow onboarding for user role
- `cf7fae7` | 2026-02-13 | docs: update work log
- `5c8ccee` | 2026-02-13 | fix: configure password rehash
- `1d0cb95` | 2026-02-13 | docs: update work log
- `0acd398` | 2026-02-13 | fix: enable user onboarding and access
- `e7926f7` | 2026-02-13 | docs: add work log entry
- `74a7dc9` | 2026-02-13 | fix: force pl locale in bootstrap
- `bfdbdae` | 2026-02-13 | docs: log locale fix
- `d05b271` | 2026-02-13 | fix: spolszcz auth templates
- `c3dfd32` | 2026-02-13 | fix: allow invoice add actions for user
- `163b05f` | 2026-02-13 | docs: cleanup readme notes
- `56811fd` | 2026-02-13 | fix
- `7b38eff` | 2026-02-13 | fix: unify user settings navigation
- `a574712` | 2026-02-13 | fix: simplify profile settings UI
- `a969f89` | 2026-02-13 | fix: allow user manage products and contractors
- `a0018ad` | 2026-02-13 | feat: show KSeF cert context in footer
- `df64ff5` | 2026-02-13 | fix: allow user view KSeF lists
- `d4f6552` | 2026-02-13 | fix: load Authentication component during onboarding
- `61c9a39` | 2026-02-13 | fix: prevent re-onboarding when company exists
- `11453ec` | 2026-02-13 | feat: show KSeF offline message in footer
- `949b493` | 2026-02-13 | feat: hint missing KSeF permissions in footer
- `7b00045` | 2026-02-13 | feat: check KSeF permissions in status action
- `aba28e9` | 2026-02-13 | Revert "feat: check KSeF permissions in status action"
- `2adb3e3` | 2026-02-13 | feat: status KSeF wg InvoiceWrite i master cert
- `d01a2d4` | 2026-02-13 | feat: AJAX status InvoiceWrite + cache
- `2b09812` | 2026-02-13 | fix: force KSeF statusAjax refresh
- `19a51f8` | 2026-02-13 | feat: PHP cache for KSeF statusAjax
- `60c9878` | 2026-02-13 | feat: message when InvoiceWrite active
- `e17fdf0` | 2026-02-13 | feat: footer message when InvoiceWrite OK
- `ffc0cdc` | 2026-02-13 | fix: hide KSeF cert info in footer
- `fe8e090` | 2026-02-13 | chore: bump build version
- `218f8ec` | 2026-02-20 | feat: invoice edit action on list
- `a877079` | 2026-02-20 | feat: type-specific invoice edit actions
- `e8397fa` | 2026-02-20 | feat: edit uses add_* templates by type
- `95213f8` | 2026-02-20 | fix: edycja zaliczek i korekt w add_*
- `743f646` | 2026-02-20 | fix: prefill contractor and items on invoice edit
- `a42f4ae` | 2026-02-20 | fix: KSeF send makeClientBuilder arg mismatch
- `b17d693` | 2026-02-20 | fix: only save (no KSeF send) for non-standard invoices
- `9df7f09` | 2026-02-20 | fix: preserve invoice items and contractor on save errors
- `87e1306` | 2026-02-20 | docs: add KSeF UX roadmap and todo plan
- `bf1c758` | 2026-02-20 | feat: add company KSeF mode toggle and top status banner
- `062d9d6` | 2026-02-20 | chore: update migration lock after ksef mode migration
- `da6d4d1` | 2026-02-20 | feat: conditional invoice actions by company KSeF mode
- `da6c33f` | 2026-02-20 | feat: add draft invoice workflow and drafts list
- `259c2b9` | 2026-02-20 | feat: finalize draft send flow and lock sent edits
- `b260b9c` | 2026-02-20 | feat: add UPO logging and planned draft scheduler
- `c9e1633` | 2026-02-20 | docs: close final KSeF modal/js todo
- `0745890` | 2026-02-21 | docs: add onboarding and registration follow-up todo
- `7264959` | 2026-02-21 | docs: remove selected onboarding todo items
- `7435fe1` | 2026-02-21 | feat: add invoice template preview picker
- `1c3ac6d` | 2026-02-21 | feat: improve onboarding with NIP-first and required email
- `2c8c2d1` | 2026-02-22 | feat: add NIP GUS prefill in registration
- `eb2abf0` | 2026-02-22 | fix: make register GUS fetch work with onboarding fields
- `871a825` | 2026-02-22 | fix: restore register GUS button click handler
- `f157b44` | 2026-02-22 | fix: allow public access to contractors gusLookup
- `d628003` | 2026-02-22 | fix: use form csrf token for register GUS request
- `3a9dfda` | 2026-02-22 | refactor: widen register card and trim onboarding hint text
- `40cc0d1` | 2026-02-22 | refactor: widen register auth card container
- `b57508b` | 2026-02-22 | fix: make auth column width configurable for register
- `e93184b` | 2026-02-22 | feat: add local number to register onboarding prefill
- `c66e60f` | 2026-02-22 | fix: split GUS apartment number from street
- `8514c93` | 2026-02-22 | feat: verify VAT status via MF white list on GUS lookup
- `d19a979` | 2026-02-22 | feat: auto-attach MF bank accounts in onboarding
- `937af24` | 2026-02-22 | fix: remove heredoc interpolation warning in register
- `32ca6c7` | 2026-02-22 | feat: warn when NIP already exists in companies
- `fbcb05c` | 2026-02-22 | fix: correct SQL operator in nipExists check
- `c92a659` | 2026-02-22 | feat: require NIP and company data on registration
- `ada3d18` | 2026-02-22 | fix: remove onboarding helper texts from register
- `7a3a975` | 2026-02-22 | fix: remove invoice template from company edit
- `196c5e4` | 2026-02-23 | feat: check VAT white list when adding contractor
- `25c3dbb` | 2026-02-23 | feat: streamline contractor add form and email settings
- `21095ac` | 2026-02-23 | fix: allow user access to draft invoices and init series
- `f5aef34` | 2026-02-23 | feat: add invoice series management tab in company edit
- `e089d4d` | 2026-02-23 | fix: protect copied system invoice series from deletion
- `6cb7350` | 2026-02-23 | fix: ensure system series copy maps fields and appears in company edit
- `d61faa1` | 2026-02-23 | fix: add detailed logs for invoice series copy and save
- `8463e2d` | 2026-02-23 | fix: log every invoice series save attempt
- `b8681d0` | 2026-02-23 | fix: write invoice series diagnostics to dedicated file
- `a498305` | 2026-02-23 | feat: show copied system series info in company edit
- `8bf4ee4` | 2026-02-23 | fix: render copied series as read-only in company edit
- `1bea0f3` | 2026-02-23 | fix: render company invoice series from list result
- `62a78c3` | 2026-02-23 | fix: handle default invoice series per document type
- `1dcee87` | 2026-02-23 | fix: unlock onboarding bank accounts field on register
- `8a7a161` | 2026-02-23 | feat: auto-create company and assign user on register
- `30bc677` | 2026-02-23 | fix: detect user create correctly in afterSave hook
- `87143a7` | 2026-02-23 | fix: create company in users beforeSave during registration
- `f818b3f` | 2026-02-23 | fix: ensure company assignment for new users on first request
- `1850d6f` | 2026-02-23 | fix: add deep diagnostics for register company assignment
- `cf1b0cf` | 2026-02-23 | fix: load users config before CakeDC bootstrap
- `177c254` | 2026-02-23 | fix: remove duplicate email validator in users table
- `3bbc7c0` | 2026-02-23 | fix: add app user entity extending cakedc user
- `0ec1bde` | 2026-02-23 | refactor: move company assignment to first login fallback
- `edf24e7` | 2026-02-23 | Revert "refactor: move company assignment to first login fallback"
- `e330deb` | 2026-02-23 | Revert "fix: add app user entity extending cakedc user"
- `0cfc8db` | 2026-02-23 | Revert "fix: remove duplicate email validator in users table"
- `763e86b` | 2026-02-23 | Revert "fix: load users config before CakeDC bootstrap"
- `c937f63` | 2026-02-23 | Revert "fix: add deep diagnostics for register company assignment"
- `12ce8ae` | 2026-02-23 | Revert "fix: ensure company assignment for new users on first request"
- `7541985` | 2026-02-23 | Revert "fix: create company in users beforeSave during registration"
- `34f5112` | 2026-02-23 | Revert "fix: detect user create correctly in afterSave hook"
- `662d176` | 2026-02-23 | Revert "feat: auto-create company and assign user on register"
- `dec6e73` | 2026-02-23 | feat: assign company from prefill on first login
- `f79cb21` | 2026-02-23 | fix: ensure draft invoice number is always assigned
- `c878a3f` | 2026-02-23 | feat: warn and normalize draft invoice date before KSeF send
- `055b0dc` | 2026-02-23 | fix: preserve and map invoice series correctly on edit
- `09a9ffb` | 2026-02-24 | docs: add invoices module verification TODOs

---

Jeśli chcesz, mogę od razu przygotować też wersję skróconą „biznesową” (1–2 strony) bez hashy commitów, tylko funkcjonalności i wpływ na użytkownika.
