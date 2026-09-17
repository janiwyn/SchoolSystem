<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Payment Receipt - cPanel Web Hosting LLC</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f7f9fa;
            color: #333;
            margin: 0;
            padding: 40px 20px;
        }
        .invoice-card {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 40px;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 24px;
            margin-bottom: 30px;
        }
        .company-logo {
            font-size: 26px;
            font-weight: 800;
            color: #ff6c2c;
            letter-spacing: -0.5px;
        }
        .company-logo span {
            color: #2c3e50;
            font-weight: 400;
            font-size: 18px;
        }
        .company-address {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            margin-top: 8px;
        }
        .invoice-details {
            text-align: right;
        }
        .invoice-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a252f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .invoice-number {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 4px;
        }
        .paid-badge {
            display: inline-block;
            margin-top: 10px;
            background-color: #27ae60;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .billing-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 13px;
            color: #555;
            line-height: 1.6;
        }
        .billing-info div {
            width: 48%;
        }
        .section-heading {
            font-size: 12px;
            font-weight: 700;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 14px;
        }
        th {
            background-color: #f8f9fa;
            color: #4a5568;
            font-weight: 600;
            text-align: left;
            padding: 12px 14px;
            border-bottom: 2px solid #edf2f7;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 14px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }
        .amount-col {
            text-align: right;
        }
        .summary-table {
            width: 50%;
            margin-left: auto;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .summary-table td {
            padding: 8px 14px;
            border: none;
        }
        .total-row td {
            font-size: 16px;
            font-weight: 700;
            color: #27ae60;
            border-top: 2px solid #edf2f7;
            padding-top: 12px;
        }
        .receipt-footer {
            border-top: 1px solid #edf2f7;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #a0aec0;
            line-height: 1.5;
        }
        .print-btn {
            display: block;
            width: 160px;
            margin: 25px auto 0;
            padding: 10px 18px;
            background-color: #2c3e50;
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
        }
        .print-btn:hover {
            background-color: #1a252f;
        }
        @media print {
            body { background: white; padding: 0; }
            .invoice-card { box-shadow: none; border: none; padding: 20px; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <div class="invoice-card">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="company-logo">cPanel <span>Hosting Services</span></div>
                <div class="company-address">
                    cPanel Cloud Infrastructure LLC<br>
                    2550 North Loop West, Suite 400<br>
                    Houston, TX 77092, United States<br>
                    EIN / Tax ID: US-9482019-TX<br>
                    Email: billing-support@cpanel-cloud-hosting.com
                </div>
            </div>
            <div class="invoice-details">
                <div class="invoice-title">Official Receipt</div>
                <div class="invoice-number">Receipt #: <strong>CP-2026-94821</strong></div>
                <div class="invoice-number">Date Paid: <strong>September 16, 2026</strong></div>
                <div class="paid-badge">PAID IN FULL</div>
            </div>
        </div>

        <!-- Billing Info -->
        <div class="billing-info">
            <div>
                <div class="section-heading">Billed To:</div>
                <strong>Bornwell Academy</strong><br>
                Server Administrator / IT Operations<br>
                Account ID: ACC-SERVER-84092<br>
                Host Server: cpanel-us-central-04.node-srv.net
            </div>
            <div>
                <div class="section-heading">Payment Summary:</div>
                <strong>Payment Status:</strong> Cleared (Completed)<br>
                <strong>Payment Method:</strong> Electronic Wire Transfer / Card<br>
                <strong>Transaction Ref:</strong> TXN-948019482-CP<br>
                <strong>Balance Remaining:</strong> $0.00 USD
            </div>
        </div>

        <!-- Line Items Table -->
        <table>
            <thead>
                <tr>
                    <th>Description / Service Period</th>
                    <th class="amount-col">Amount (USD)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>June Server Hosting &amp; Cloud Maintenance Fee</td>
                    <td class="amount-col">$10.00</td>
                </tr>
                <tr>
                    <td>July Server Hosting &amp; Cloud Maintenance Fee</td>
                    <td class="amount-col">$10.00</td>
                </tr>
                <tr>
                    <td>August Server Hosting &amp; Cloud Maintenance Fee</td>
                    <td class="amount-col">$10.00</td>
                </tr>
                <tr>
                    <td>September Server Hosting &amp; Cloud Maintenance Fee</td>
                    <td class="amount-col">$10.00</td>
                </tr>
                <tr>
                    <td>Regulatory Taxes &amp; Service Processing Fees</td>
                    <td class="amount-col">$8.00</td>
                </tr>
            </tbody>
        </table>

        <!-- Summary Totals -->
        <table class="summary-table">
            <tr>
                <td>Subtotal:</td>
                <td class="amount-col">$40.00 USD</td>
            </tr>
            <tr>
                <td>Taxes &amp; Service Fees:</td>
                <td class="amount-col">$8.00 USD</td>
            </tr>
            <tr class="total-row">
                <td>Total Amount Paid:</td>
                <td class="amount-col">$48.00 USD</td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="receipt-footer">
            Thank you for your business. This official receipt confirms that all outstanding server fees totaling <strong>$48.00 USD</strong> have been received and verified. Your server services remain fully active.<br><br>
            cPanel Cloud Infrastructure LLC &copy; 2026. All rights reserved.
        </div>

        <button onclick="window.print()" class="print-btn">Print Receipt</button>
    </div>

</body>
</html>
