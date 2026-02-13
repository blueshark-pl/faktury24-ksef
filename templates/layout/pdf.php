<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktura PDF</title        .totals-table .total-final {
            background-color: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .vat-summary {
            margin-bottom: 20px;
        }
        
        .vat-summary h4 {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #2c3e50;
        }
        
        .vat-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        
        .vat-summary-table th,
        .vat-summary-table td {
            padding: 5px 8px;
            border: 1px solid #bdc3c7;
        }
        
        .vat-summary-table th {
            background-color: #34495e;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        
        .vat-summary-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .payment-info {style>
        @page {
            margin: 20mm;
            size: A4;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .invoice-container {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 0;
        }
        
        .header {
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            margin: 0;
            text-align: center;
            font-weight: bold;
        }
        
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        
        .seller, .buyer {
            display: table-cell;
            vertical-align: top;
            width: 48%;
        }
        
        .buyer {
            padding-left: 4%;
        }
        
        .company-box {
            border: 2px solid #34495e;
            padding: 15px;
            background-color: #f8f9fa;
            min-height: 150px;
        }
        
        .company-title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            text-transform: uppercase;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 5px;
        }
        
        .company-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        
        .company-details {
            font-size: 11px;
            line-height: 1.5;
        }
        
        .invoice-details {
            margin: 30px 0;
            display: table;
            width: 100%;
        }
        
        .invoice-number {
            display: table-cell;
            width: 50%;
        }
        
        .invoice-dates {
            display: table-cell;
            width: 50%;
            text-align: right;
        }
        
        .detail-row {
            margin-bottom: 5px;
        }
        
        .label {
            font-weight: bold;
            color: #34495e;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            border: 2px solid #34495e;
        }
        
        .items-table th {
            background-color: #34495e;
            color: white;
            padding: 12px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
        }
        
        .items-table td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid #bdc3c7;
            vertical-align: middle;
        }
        
        .items-table td.text-left {
            text-align: left;
        }
        
        .items-table td.text-right {
            text-align: right;
        }
        
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .totals {
            width: 100%;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #34495e;
        }
        
        .totals-table th,
        .totals-table td {
            padding: 10px 15px;
            text-align: right;
            border-bottom: 1px solid #bdc3c7;
        }
        
        .totals-table th {
            background-color: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
            width: 70%;
        }
        
        .totals-table .total-final {
            background-color: #34495e;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .payment-info {
            margin-top: 30px;
            border: 1px solid #bdc3c7;
            padding: 15px;
            background-color: #f8f9fa;
        }
        
        .payment-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #bdc3c7;
            padding-top: 15px;
        }
        
        .signature-area {
            margin-top: 50px;
            display: table;
            width: 100%;
        }
        
        .signature-box {
            display: table-cell;
            width: 45%;
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        
        .signature-right {
            text-align: right;
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(231, 76, 60, 0.1);
            z-index: -1;
            font-weight: bold;
        }
        
        /* Print-specific styles */
        @media print {
            body { margin: 0; }
            .invoice-container { box-shadow: none; }
            .page-break { page-break-before: always; }
        }
        
        .currency {
            font-weight: bold;
        }
        
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <?= $this->fetch('content') ?>
</body>
</html>