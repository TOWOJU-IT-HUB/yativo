{{-- resources/views/export-tables.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Export Tables</title>
</head>
<body>
    <h1>Select Tables to Export</h1>
    <form method="POST" action="{{ route('tables.export') }}">
        @csrf
        @foreach($tableNames as $table)
            <div>
                <input type="checkbox" name="tables[]" value="{{ $table }}"> {{ $table }}
            </div>
        @endforeach
        <button type="submit">Export Selected</button>
    </form>
</body>
</html>
