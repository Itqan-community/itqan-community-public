<html dir="rtl">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <style type="text/css">
            * {
                direction: rtl;
                text-align: right;
            }
            body {
                font-family: 'Open Sans', sans-serif;
                background: white;
                color: #064e3b;
                margin: 0;
                padding: 0;
            }
            .content {
                box-sizing: border-box;
                width: 100%;
                max-width: 500px;
                margin: 0 auto;
                padding: 10px 20px;
            }
            .header {
                border-bottom: 1px solid #e8ecf3;
             }
             .header a {
                color: {{ $settings->get('theme_primary_color') }};
                text-decoration: none;
             }
             .footer {
                background: #e8ecf3;
             }
        </style>
        @if ($forumStyle !== '')
        <style>
            {!! $forumStyle !!}
        </style>
        @endif
    </head>
    <body>
    <div class="header">
        <div class="content">
            <a href="{{ $url->to('forum')->base() }}"><img src="https://community.itqan.dev/assets/files/2025-08-26/1756205766-141192-banner1.png" alt="{{ $settings->get('forum_title') }}" style="width: 100%; height: auto;"></a>
        </div>
    </div>
    <div class="content">
        {!! $body !!}
    </div>
    <div class="footer">
        <div class="content">
            <p>مرسلة من {{ $settings->get('forum_title') }}</p>
        </div>
    </div>
    </body>
</html>
