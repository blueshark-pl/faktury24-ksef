<?php
/**
 * KSeF filters element
 * Expects:
 * - string $currentAction
 * - string $peerAction
 * - string $storageKey
 */
$req   = $this->getRequest();
$env   = (string)$req->getQuery('env', 'test');
$from  = (string)$req->getQuery('from', '');
$to    = (string)$req->getQuery('to', '');
$q     = (string)$req->getQuery('q', '');
$ksef  = (string)$req->getQuery('ksef', '');
$inv   = (string)$req->getQuery('inv', '');
$seller= (string)$req->getQuery('seller_nip', '');
$buyer = (string)$req->getQuery('buyer_nip', '');
$debug = $req->getQuery('debug') !== null;
?>

<div class="card my-3">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-2">
        <label class="form-label">Od</label>
        <input type="date" name="from" value="<?= h($from) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <label class="form-label">Do</label>
        <input type="date" name="to" value="<?= h($to) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <label class="form-label">Szukaj (nr/nip/nazwa)</label>
        <input type="search" name="q" value="<?= h($q) ?>" class="form-control" placeholder="np. 12/11/2025" />
      </div>
      <div class="col-md-2">
        <label class="form-label">Numer KSeF</label>
        <input type="text" name="ksef" value="<?= h($ksef) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <label class="form-label">Numer faktury</label>
        <input type="text" name="inv" value="<?= h($inv) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <label class="form-label">NIP sprzedawcy</label>
        <input type="text" name="seller_nip" value="<?= h($seller) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <label class="form-label">NIP nabywcy</label>
        <input type="text" name="buyer_nip" value="<?= h($buyer) ?>" class="form-control" />
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit">Filtruj</button>
      </div>
      <div class="col-md-2">
        <a class="btn btn-outline-secondary w-100" href="<?= $this->Url->build(['action' => $currentAction]) ?>">Wyczyść</a>
      </div>
      <div class="col-md-2 text-end ms-auto">
        <a class="btn btn-outline-info" href="<?= $this->Url->build(['action' => $peerAction, '?' => $req->getQueryParams()]) ?>">Zobacz <?= $peerAction === 'issued' ? 'wystawione' : 'otrzymane' ?></a>
      </div>
    </form>
  </div>
</div>

<script>
// Presety dat
document.querySelectorAll('.date-preset').forEach(btn => {
  btn.addEventListener('click', () => {
    const preset = btn.getAttribute('data-preset');
    const from = document.querySelector('input[name="from"]');
    const to = document.querySelector('input[name="to"]');
    const d = new Date();
    const pad = (n) => (n<10?'0':'')+n;
    const format = (dt) => dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate());
    if (preset === 'today') {
      const s = format(d);
      from.value = s; to.value = s;
    } else if (preset === 'yesterday') {
      d.setDate(d.getDate()-1);
      const s = format(d);
      from.value = s; to.value = s;
    } else if (preset === 'last7') {
      const toD = new Date(d);
      const fromD = new Date(d); fromD.setDate(fromD.getDate()-6);
      from.value = format(fromD); to.value = format(toD);
    } else if (preset === 'month') {
      const toD = new Date(d.getFullYear(), d.getMonth()+1, 0);
      const fromD = new Date(d.getFullYear(), d.getMonth(), 1);
      from.value = format(fromD); to.value = format(toD);
    }
  });
});

// Pamiętanie filtrów w localStorage
(function(){
  const form = document.querySelector('form');
  if (!form) return;
  const key = <?= json_encode($storageKey ?? 'ksef_filters') ?>;
  try {
    const saved = JSON.parse(localStorage.getItem(key) || 'null');
    if (saved) {
      for (const [name, val] of Object.entries(saved)) {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) {
          if (el.type === 'checkbox') { el.checked = !!val; }
          else { el.value = val; }
        }
      }
    }
  } catch(e) {}
  const save = () => {
    const data = {};
    form.querySelectorAll('input,select').forEach(el => {
      if (!el.name) return;
      data[el.name] = (el.type === 'checkbox') ? el.checked : el.value;
    });
    localStorage.setItem(key, JSON.stringify(data));
  };
  form.addEventListener('change', save);
})();
</script>
