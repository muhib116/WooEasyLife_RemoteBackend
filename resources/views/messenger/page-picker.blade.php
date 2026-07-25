<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose Facebook Page</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .wrap {
            max-width: 520px;
            margin: 40px auto;
            padding: 0 16px;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        h1 {
            font-size: 1.15rem;
            margin: 0 0 6px;
        }
        p {
            margin: 0 0 16px;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.45;
        }
        .page {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            text-align: left;
        }
        .page:hover {
            border-color: #0866FF;
            background: #f0f7ff;
        }
        .page img, .page .avatar {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            object-fit: cover;
            background: #e2e8f0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #0866FF;
            font-weight: 700;
            flex-shrink: 0;
        }
        .name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .id {
            font-size: 0.75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Choose your Facebook Page</h1>
        <p>Select the shop Page Woo Easy Life should use for Messenger.</p>

        @foreach($pages as $page)
            <form method="POST" action="{{ url('/api/messenger/oauth/select-page') }}">
                @csrf
                <input type="hidden" name="picker_token" value="{{ $picker_token }}">
                <input type="hidden" name="page_id" value="{{ $page['id'] }}">
                <button type="submit" class="page">
                    @if(!empty($page['picture']))
                        <img src="{{ $page['picture'] }}" alt="">
                    @else
                        <span class="avatar">f</span>
                    @endif
                    <span>
                        <span class="name">{{ $page['name'] }}</span><br>
                        <span class="id">{{ $page['id'] }}</span>
                    </span>
                </button>
            </form>
        @endforeach
    </div>
</div>
</body>
</html>
