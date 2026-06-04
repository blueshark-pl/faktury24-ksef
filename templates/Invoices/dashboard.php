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
    <?= $this->Html->link(
      '<i class="ri-arrow-left-line align-middle me-1"></i> Wróć do listy',
      ['action' => 'index'],
      ['class' => 'btn btn-outline-primary btn-wave me-0', 'escape' => false]
    ) ?>
  </div>
</div>

<!-- Filter Daty -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Od</label>
        <input type="date" name="dateFrom" class="form-control" value="<?= $dateFrom->format('Y-m-d') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Do</label>
        <input type="date" name="dateTo" class="form-control" value="<?= $dateTo->format('Y-m-d') ?>">
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary">
          <i class="ri-search-line me-1"></i>Filtruj
        </button>
      </div>
    </form>
  </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Przychód razem</div>
      <div class="kpi-value"><?= $this->Number->format($totalRevenue, ['places' => 0]) ?></div>
      <small class="text-muted"><?= (int)$invoiceCount ?> faktury</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Średnia faktura</div>
      <div class="kpi-value"><?= $this->Number->format($avgInvoiceValue, ['places' => 0]) ?></div>
      <small class="text-muted">Wartość brutto</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Opłacone</div>
      <div class="kpi-value text-success"><?= $paymentPercent ?>%</div>
      <small class="text-muted">Z przychodu razem</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi-card">
      <div class="kpi-label">Do zapłaty</div>
      <div class="kpi-value text-warning"><?= $this->Number->format($totalRevenue - (($totalRevenue * $paymentPercent) / 100), ['places' => 0]) ?></div>
      <small class="text-muted">Oczekujące płatności</small>
    </div>
  </div>
</div>

<!-- Wykresy -->
<div class="row">
  <!-- Revenue Trend -->
  <div class="col-lg-8">
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
  <div class="col-lg-4">
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
});
</script>
