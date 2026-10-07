<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Trail Print | PaperTrail</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #0b2341;
            margin: 24px;
            background: #fff;
        }

        .no-print {
            margin-bottom: 16px;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        p {
            margin: 4px 0 16px;
            color: #53647c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        th,
        td {
            border: 1px solid #cfd8e3;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eef4fb;
            color: #0b2341;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print Audit Logs</button>

    <h1>PaperTrail Audit Trail</h1>
    <p>Generated {{ $generatedAt->format('M d, Y h:i A') }}. Showing up to 500 filtered records.</p>

    <table>
        <thead>
            <tr>
                <th>Date / Time</th>
                <th>User</th>
                <th>Role</th>
                <th>Office</th>
                <th>Action</th>
                <th>Module</th>
                <th>Document</th>
                <th>Status</th>
                <th>Severity</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->user_identifier ?? 'Guest' }} {{ $log->user_name ? '- ' . $log->user_name : '' }}</td>
                    <td>{{ $log->role_name ?? $log->user_role ?? 'N/A' }}</td>
                    <td>{{ $log->office_name ?? $log->user_office ?? 'N/A' }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->module }}</td>
                    <td>
                        {{ $log->target_label ?? $log->tracking_number ?? 'N/A' }}
                        @if ($log->document_reference_number)
                            <br>Tracking Number {{ $log->document_reference_number }}
                        @endif
                    </td>
                    <td>{{ ucfirst($log->status ?? 'success') }}</td>
                    <td>{{ ucfirst($log->severity) }}</td>
                    <td>{{ $log->ip_address ?? 'N/A' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No audit logs found for the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
