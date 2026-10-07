<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | PaperTrail</title>
    <style>
        body {
            color: #0b2341;
            font-family: Arial, sans-serif;
            margin: 28px;
        }

        header {
            border-bottom: 2px solid #0b2341;
            margin-bottom: 20px;
            padding-bottom: 12px;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }

        p {
            margin: 0;
        }

        table {
            border-collapse: collapse;
            font-size: 11px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 7px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            color: #0b2341;
        }

        .print-meta {
            color: #4b5563;
            font-size: 12px;
        }

        @media print {
            body {
                margin: 14mm;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>{{ $title }}</h1>
        <p>{{ $lguName }} | PaperTrail</p>
        <p class="print-meta">Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </header>

    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @if ($type === 'system-summary')
                        <td>{{ $row['metric'] }}</td>
                        <td>{{ $row['value'] }}</td>
                    @elseif ($type === 'users')
                        <td>{{ $row->user_id }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->office }}</td>
                        <td>{{ $row->role }}</td>
                        <td>{{ ucfirst($row->status) }}</td>
                        <td>{{ $row->created_at?->format('M d, Y') }}</td>
                        <td>{{ $row->updated_at?->format('M d, Y') }}</td>
                    @elseif ($type === 'offices')
                        <td>{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->type }}</td>
                        <td>{{ ucfirst($row->status) }}</td>
                        <td>{{ $row->users_count }}</td>
                        <td>{{ $row->created_at?->format('M d, Y') }}</td>
                    @elseif ($type === 'roles')
                        <td>{{ $row->code }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ ucfirst($row->status) }}</td>
                        <td>{{ $row->is_system ? 'System' : 'Custom' }}</td>
                        <td>{{ $row->users_count }}</td>
                        <td>{{ $row->permissions_count }}</td>
                        <td>{{ $row->created_at?->format('M d, Y') }}</td>
                    @else
                        <td>{{ $row->created_at?->format('M d, Y h:i A') }}</td>
                        <td>{{ $row->user_identifier ?? 'System' }}</td>
                        <td>{{ $row->user_name ?? 'System' }}</td>
                        <td>{{ $row->user_role ?? 'N/A' }}</td>
                        <td>{{ $row->user_office ?? 'N/A' }}</td>
                        <td>{{ $row->module }}</td>
                        <td>{{ $row->action }}</td>
                        <td>{{ ucfirst($row->severity) }}</td>
                        <td>{{ $row->ip_address }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No report records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
