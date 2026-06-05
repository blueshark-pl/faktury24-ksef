<?php
/**
 * @var \App\View\AppView $this
 * @var array $monthlyRevenue
 * @var array $paymentStatus
 * @var array $currencyData
 * @var array $topContractors
 * @var float $totalRevenue
 * @var int $invoiceCount
 * @var float $avgInvoiceValue
 * @var float $paymentPercent
 * @var \DateTime $dateFrom
 * @var \DateTime $dateTo
 */
$this->assign('title', 'Dashboard Faktur');
?>

<style>
  .dashboard-grid {
    touch-action: none;
  }

  .dashboard-item {
    cursor: move;
    transition: box-shadow 0.2s ease;
  }

  .dashboard-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .dashboard-item.sortable-ghost {
    opacity: 0.5;
    background: #f0f0f0 !important;
  }

  .dashboard-item.sortable-drag {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
    z-index: 1000;
  }

  .kpi-card {
    padding: 20px;
    border-radius: 6px;
    background: #f8f9fc;
    border: 1px solid #e3e6f0;
  }
  .kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2e59d9;
    margin: 10px 0;
  }
  .kpi-label {
    font-size: 0.85rem;
    text-transform: uppercase;
    color: #858796;
    letter-spacing: 0.5px;
  }
  .chart-container {
    position: relative;
    height: 400px;
    margin-bottom: 30px;
  }
  .chart-container-sm {
    position: relative;
    height: 300px;
  }
</style>

<!-- Page Header -->
<div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title fw-medium fs-18 mb-2">Dashboard Faktur</h1>
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= $this->Url->build('/') ?>">Start</a></li>
      <li class="breadcrumb-item"><a href="<?= $this->Url->build(['action' => 'index']) ?>">Faktury</a></li>
      <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
    </ol>
  </div>
  <div class="btn-list">
    <button type="button" id="resetLayoutBtn" class="btn btn-outline-secondary btn-wave me-2" title="Przywróć domyślny układ">
      <i class="ri-refresh-line align-middle me-1"></i>Reset
    </button>
    <?= $this->Html->link(
      '<i class="ri-arrow-left-line align-middle me-1"></i> Wróć do listy',
      ['action' => 'index'],
      ['class' => 'btn btn-outline-primary btn-wave me-0', 'escape' => false]
    ) ?>
  </div>
</div>

<!-- Filtry -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <!-- Date Range -->
      <div class="col-md-3">
        <label class="form-label">Od</label>
        <input type="date" name="dateFrom" class="form-control" value="<?= $dateFrom->format('Y-m-d') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Do</label>
        <input type="date" name="dateTo" class="form-control" value="<?= $dateTo->format('Y-m-d') ?>">
      </div>

      <!-- Currency Selector -->
      <div class="col-md-3">
        <label class="form-label">Waluta</label>
        <select name="currency" class="form-select">
          <option value="">Wszystkie</option>
          <?php foreach ($currencies as $curr): ?>
            <option value="<?= h($curr) ?>" <?= $this->request->getQuery('currency') === $curr ? 'selected' : '' ?>>
              <?= h($curr) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Netto/Brutto Toggle -->
      <div class="col-md-3">
        <label class="form-label">Typ kwoty</label>
        <div class="btn-group w-100" role="group">
          <input type="radio" class="btn-check" name="amount_type" id="amount_brutto" value="brutto"
            <?= ($this->request->getQuery('amount_type') === 'netto') ? '' : 'checked' ?>>
          <label class="btn btn-outline-primary" for="amount_brutto">Brutto</label>

          <input type="radio" class="btn-check" name="amount_type" id="amount_netto" value="netto"
            <?= ($this->request->getQuery('amount_type') === 'netto') ? 'checked' : '' ?>>
          <label class="btn btn-outline-primary" for="amount_netto">Netto</label>
        </div>
      </div>

      <!-- Submit -->
      <div class="col-12">
        <button type="submit" class="btn btn-primary">
          <i class="ri-search-line me-1"></i>Filtruj
        </button>
      </div>
    </form>
  </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4" id="dashboard-grid">
  <div class="col-md-3 dashboard-item" id="item-revenue-total">
    <div class="kpi-card">
      <div class="kpi-label">Przychód razem</div>
      <div class="kpi-value"><?= $this->Number->format($totalRevenue, ['places' => 0]) ?></div>
      <small class="text-muted"><?= (int)$invoiceCount ?> faktury</small>
    </div>
  </div>
  <div class="col-md-3 dashboard-item" id="item-avg-invoice">
    <div class="kpi-card">
      <div class="kpi-label">Średnia faktura</div>
      <div class="kpi-value"><?= $this->Number->format($avgInvoiceValue, ['places' => 0]) ?></div>
      <small class="text-muted">Wartość brutto</small>
    </div>
  </div>
  <div class="col-md-3 dashboard-item" id="item-paid-percent">
    <div class="kpi-card">
      <div class="kpi-label">Opłacone</div>
      <div class="kpi-value text-success"><?= $paymentPercent ?>%</div>
      <small class="text-muted">Z przychodu razem</small>
    </div>
  </div>
  <div class="col-md-3 dashboard-item" id="item-pending-amount">
    <div class="kpi-card">
      <div class="kpi-label">Do zapłaty</div>
      <div class="kpi-value text-warning"><?= $this->Number->format($totalRevenue - (($totalRevenue * $paymentPercent) / 100), ['places' => 0]) ?></div>
      <small class="text-muted">Oczekujące płatności</small>
    </div>
  </div>
</div>

<!-- KPI Cards per Currency -->
<?php if (!empty($currencyMetrics)): ?>
<div class="row mt-4">
  <?php foreach ($currencyMetrics as $cm): ?>
  <div class="col-lg-6 col-xl-4">
    <div class="card border-left-4" style="border-left: 4px solid #4f46e5;">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div class="kpi-label">Waluta <?= h($cm['currency']) ?></div>
            <div class="kpi-value" style="font-size: 1.8rem;"><?= $this->Number->format($cm['total'], ['places' => 0]) ?></div>
          </div>
          <span class="badge bg-light text-dark" style="font-size: 0.9rem;"><?= (int)$cm['count'] ?> faktury</span>
        </div>

        <div class="row text-center small mb-2">
          <div class="col-6">
            <div class="text-muted">Średnia</div>
            <div class="fw-semibold text-primary"><?= $this->Number->format($cm['avg'], ['places' => 0]) ?></div>
          </div>
          <div class="col-6">
            <div class="text-muted">Opłacone</div>
            <div class="fw-semibold text-success"><?= $cm['paid_percent'] ?>%</div>
          </div>
        </div>

        <div class="progress mt-2" style="height: 6px;">
          <div class="progress-bar" role="progressbar" style="width: <?= min($cm['paid_percent'], 100) ?>%; background-color: #10b981;" aria-valuenow="<?= $cm['paid_percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="d-flex justify-content-between mt-3 small">
          <span class="text-success">✓ <?= $this->Number->format($cm['paid'], ['places' => 0]) ?></span>
          <span class="text-warning">⏳ <?= $this->Number->format($cm['pending'], ['places' => 0]) ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Wykresy -->
<div class="row" id="dashboard-grid">
  <!-- Revenue Trend -->
  <div class="col-lg-8 dashboard-item" id="item-revenue-chart">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Przychód - trend (brutto)</h6>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Status -->
  <div class="col-lg-4 dashboard-item" id="item-payment-status">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Status płatności</h6>
      </div>
      <div class="card-body">
        <div class="chart-container-sm">
          <canvas id="paymentChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <!-- Revenue by Currency -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Przychód po walutach</h6>
      </div>
      <div class="card-body">
        <div class="chart-container-sm">
          <canvas id="currencyChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Contractors -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Top 10 Kontrahentów</h6>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="contractorsChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <!-- Invoice Types -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Typ faktury</h6>
      </div>
      <div class="card-body">
        <div class="chart-container-sm">
          <canvas id="invoiceTypesChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Methods -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Forma płatności</h6>
      </div>
      <div class="card-body">
        <div class="chart-container-sm">
          <canvas id="paymentMethodsChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Days Overdue -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h6 class="card-title">Rozkład przeterminowania</h6>
      </div>
      <div class="card-body">
        <div class="chart-container-sm">
          <canvas id="overdueChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <!-- Biggest Invoice -->
  <div class="col-lg-6 col-xl-3">
    <div class="card border-left-4" style="border-left: 4px solid #e74c3c;">
      <div class="card-body">
        <div class="kpi-label">Największa faktura</div>
        <?php if ($biggestInvoice): ?>
          <div class="kpi-value text-danger" style="font-size: 1.5rem;"><?= $this->Number->format($biggestInvoice->total, ['places' => 0]) ?> <?= h($biggestInvoice->currency) ?></div>
          <small class="text-muted"><?= h($biggestInvoice->fullnumber) ?></small>
          <br/>
          <small class="text-muted"><?= $biggestInvoice->date->format('d.m.Y') ?></small>
          <?php if ($biggestInvoice->invoice_contractors): ?>
            <br/><small class="text-primary fw-semibold"><?= h($biggestInvoice->invoice_contractors->name) ?></small>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-muted">Brak danych</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Avg Payment Time -->
  <div class="col-lg-6 col-xl-3">
    <div class="card border-left-4" style="border-left: 4px solid #f6c23e;">
      <div class="card-body">
        <div class="kpi-label">Średni czas płatności</div>
        <div class="kpi-value text-warning"><?= $avgPaymentDays ?></div>
        <small class="text-muted">dni</small>
      </div>
    </div>
  </div>

  <!-- YoY Growth -->
  <div class="col-lg-6 col-xl-3">
    <div class="card border-left-4" style="border-left: 4px solid #1cc88a;">
      <div class="card-body">
        <div class="kpi-label">Wzrost rok do roku</div>
        <div class="kpi-value <?= $yoyGrowth >= 0 ? 'text-success' : 'text-danger' ?>"><?= $yoyGrowth >= 0 ? '+' : '' ?><?= $yoyGrowth ?>%</div>
        <small class="text-muted"><?= (int)$currentYear ?> vs <?= (int)$currentYear - 1 ?></small>
      </div>
    </div>
  </div>

  <!-- Contractor Avg Value -->
  <div class="col-lg-6 col-xl-3">
    <div class="card border-left-4" style="border-left: 4px solid #4f46e5;">
      <div class="card-body">
        <div class="kpi-label">Śr. wartość per kontrahent</div>
        <?php
          $overallAvg = 0;
          if (!empty($avgContractorValue)) {
              $overallAvg = array_sum(array_column($avgContractorValue, 'avg')) / count($avgContractorValue);
          }
        ?>
        <div class="kpi-value text-primary"><?= $this->Number->format($overallAvg, ['places' => 0]) ?></div>
        <small class="text-muted"><?= count($avgContractorValue) ?> kontrahentów</small>
      </div>
    </div>
  </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // ===== 1. REVENUE TREND =====
  const monthlyData = <?= json_encode($monthlyRevenue) ?>;
  const months = Object.keys(monthlyData);
  const revenues = Object.values(monthlyData);

  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: months.map(m => {
        const [year, month] = m.split('-');
        return new Date(year, month - 1).toLocaleDateString('pl-PL', { month: 'short', year: 'numeric' });
      }),
      datasets: [{
        label: 'Przychód (brutto)',
        data: revenues,
        borderColor: '#2e59d9',
        backgroundColor: 'rgba(46, 89, 217, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#2e59d9',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(value);
            }
          }
        }
      }
    }
  });

  // ===== 2. PAYMENT STATUS =====
  const paymentData = <?= json_encode($paymentStatus) ?>;
  const paymentLabels = {
    'paid': 'Opłacone',
    'unpaid': 'Nieopłacone',
    'partial': 'Częściowa',
    'overdue': 'Po terminie'
  };
  const paymentColors = {
    'paid': '#1cc88a',
    'unpaid': '#858796',
    'partial': '#f6c23e',
    'overdue': '#e74c3c'
  };

  new Chart(document.getElementById('paymentChart'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(paymentData).map(k => paymentLabels[k] + ' (' + paymentData[k].count + ')'),
      datasets: [{
        data: Object.values(paymentData).map(d => d.total),
        backgroundColor: Object.keys(paymentData).map(k => paymentColors[k]),
        borderColor: '#f8f9fc',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // ===== 3. REVENUE BY CURRENCY =====
  const currencyData = <?= json_encode($currencyData) ?>;
  const currencies = Object.keys(currencyData);
  const grossByC = currencies.map(c => currencyData[c].brutto);
  const netByC = currencies.map(c => currencyData[c].netto);

  new Chart(document.getElementById('currencyChart'), {
    type: 'bar',
    data: {
      labels: currencies,
      datasets: [
        {
          label: 'Netto',
          data: netByC,
          backgroundColor: 'rgba(46, 89, 217, 0.5)',
        },
        {
          label: 'Brutto',
          data: grossByC,
          backgroundColor: 'rgba(46, 89, 217, 1)',
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(value);
            }
          }
        }
      }
    }
  });

  // ===== 4. TOP CONTRACTORS =====
  const contractorsData = <?= json_encode($topContractors) ?>;
  const contractorNames = Object.keys(contractorsData);
  const contractorAmounts = Object.values(contractorsData);

  new Chart(document.getElementById('contractorsChart'), {
    type: 'bar',
    data: {
      labels: contractorNames,
      datasets: [{
        label: 'Kwota brutto',
        data: contractorAmounts,
        backgroundColor: 'rgba(46, 89, 217, 0.8)',
        borderColor: 'rgba(46, 89, 217, 1)',
        borderWidth: 1
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(value);
            }
          }
        }
      }
    }
  });

  // ===== 5. INVOICE TYPES =====
  const invoiceTypesData = <?= json_encode($invoiceTypes) ?>;
  const typeLabels = {
    'vat': 'Faktura VAT',
    'proforma': 'Proforma',
    'advance': 'Zaliczka',
    'currency': 'Walutowa',
    'margin': 'Marża',
    'novat': 'Rachunek'
  };

  new Chart(document.getElementById('invoiceTypesChart'), {
    type: 'pie',
    data: {
      labels: Object.keys(invoiceTypesData).map(k => typeLabels[k] || k),
      datasets: [{
        data: Object.values(invoiceTypesData).map(d => d.total),
        backgroundColor: ['#4f46e5', '#e74c3c', '#f6c23e', '#1cc88a', '#3498db', '#9b59b6'],
        borderColor: '#f8f9fc',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // ===== 6. PAYMENT METHODS =====
  const paymentMethodsData = <?= json_encode($paymentMethods) ?>;
  const methodLabels = {
    'transfer': 'Przelew',
    'cash': 'Gotówka',
    'card': 'Karta',
    'other': 'Inne'
  };

  new Chart(document.getElementById('paymentMethodsChart'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(paymentMethodsData).map(k => methodLabels[k] || k),
      datasets: [{
        data: Object.values(paymentMethodsData).map(d => d.count),
        backgroundColor: ['#2e59d9', '#10b981', '#ffc107', '#6c757d'],
        borderColor: '#f8f9fc',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // ===== 7. DAYS OVERDUE =====
  const overdueData = <?= json_encode($overdueDistribution) ?>;

  new Chart(document.getElementById('overdueChart'), {
    type: 'bar',
    data: {
      labels: Object.keys(overdueData),
      datasets: [{
        label: 'Faktury',
        data: Object.values(overdueData),
        backgroundColor: ['#ffc107', '#fd7e14', '#e74c3c', '#721c24'],
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
});
</script>

<!-- SortableJS Library -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize SortableJS for all dashboard grids
  const grids = document.querySelectorAll('#dashboard-grid');
  const STORAGE_KEY = 'dashboard_layout_' + window.location.pathname;

  grids.forEach(grid => {
    Sortable.create(grid, {
      handle: '.dashboard-item',
      animation: 150,
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      onEnd: function(evt) {
        saveDashboardLayout();
      }
    });
  });

  // Save layout to localStorage
  function saveDashboardLayout() {
    const layouts = {};
    grids.forEach((grid, index) => {
      const items = Array.from(grid.querySelectorAll('.dashboard-item'));
      layouts['grid_' + index] = items.map(item => item.id);
    });
    localStorage.setItem(STORAGE_KEY, JSON.stringify(layouts));
  }

  // Restore layout from localStorage
  function restoreDashboardLayout() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return;

    try {
      const layouts = JSON.parse(saved);
      grids.forEach((grid, index) => {
        const targetLayout = layouts['grid_' + index];
        if (!targetLayout || !Array.isArray(targetLayout)) return;

        const items = Array.from(grid.querySelectorAll('.dashboard-item'));
        const itemMap = {};
        items.forEach(item => {
          itemMap[item.id] = item;
        });

        // Sort items according to saved layout
        const sortedItems = targetLayout
          .filter(id => itemMap[id])
          .map(id => itemMap[id]);

        // Add any missing items at the end
        items.forEach(item => {
          if (!sortedItems.includes(item)) {
            sortedItems.push(item);
          }
        });

        // Reorder DOM
        sortedItems.forEach(item => {
          grid.appendChild(item);
        });
      });
    } catch (e) {
      console.warn('Failed to restore dashboard layout:', e);
    }
  }

  // Reset layout button
  document.getElementById('resetLayoutBtn')?.addEventListener('click', function() {
    if (confirm('Przywrócić domyślny układ kafelków?')) {
      localStorage.removeItem(STORAGE_KEY);
      location.reload();
    }
  });

  // Load saved layout on startup
  restoreDashboardLayout();

  // ===== AMOUNT TYPE TOGGLE (Netto/Brutto) =====
  const amountTypeRadios = document.querySelectorAll('input[name="amount_type"]');
  const dualData = <?= $dualAmountData ?>;

  amountTypeRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const amountType = this.value; // 'brutto' or 'netto'
      updateDashboardValues(amountType);
    });
  });

  function updateDashboardValues(amountType) {
    const data = dualData[amountType];
    if (!data) return;

    // Update KPI cards
    document.getElementById('item-revenue-total').querySelector('.kpi-value').textContent =
      new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(data.totalRevenue);

    document.getElementById('item-avg-invoice').querySelector('.kpi-value').textContent =
      new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(data.avgInvoiceValue);

    document.getElementById('item-paid-percent').querySelector('.kpi-value').textContent =
      data.paymentPercent + '%';

    const pendingAmount = data.totalRevenue - (data.totalRevenue * data.paymentPercent / 100);
    document.getElementById('item-pending-amount').querySelector('.kpi-value').textContent =
      new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 0 }).format(pendingAmount);

    // Update revenue trend chart
    updateRevenueChart(data.monthlyRevenue);
  }

  function updateRevenueChart(monthlyData) {
    // Find and update the revenue trend chart
    const canvas = document.getElementById('revenueChart');
    if (canvas && Chart.instances) {
      const chart = Chart.instances.find(c => c.canvas === canvas);
      if (chart) {
        chart.data.datasets[0].data = Object.values(monthlyData);
        chart.update();
      }
    }
  }
});
</script>