<!DOCTYPE html>
<html lang="en">
<head>
    @include('favicon')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Barangay ABYIP</title>
    <style>
        body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 40px 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        select, input[type="number"], input[type="file"] { width: 100%; margin-bottom: 16px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; }
        button { background: #0450a8; color: #fff; border: 0; border-radius: 10px; padding: 12px 18px; font-weight: 600; cursor: pointer; }
        .status { background: #ecfdf5; color: #047857; padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; }
        .error { color: #b91c1c; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Upload Barangay ABYIP PDF</h1>

        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.abyip.upload.store') }}" enctype="multipart/form-data">
            @csrf

            <label for="barangay_id">Barangay</label>
            <select name="barangay_id" id="barangay_id" required>
                <option value="">Select barangay</option>
                @foreach ($barangays as $barangay)
                    <option value="{{ $barangay->id }}" @selected(old('barangay_id') == $barangay->id)>{{ $barangay->name }}</option>
                @endforeach
            </select>

            <label for="year">Calendar Year</label>
            <input type="number" name="year" id="year" value="{{ old('year', now()->year) }}" min="2000" max="2100" required>

            <label for="pdf">ABYIP PDF</label>
            <input type="file" name="pdf" id="pdf" accept="application/pdf" required>

            <button type="submit">Upload and Extract</button>
        </form>
    </div>
</body>
</html>
