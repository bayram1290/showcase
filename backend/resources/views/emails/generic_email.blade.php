<!DOCTYPE html>
<html>
<head>
    <title>{{ $subject }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; }
        .content { padding: 20px; background-color: white; }
        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>{{ $subject }}</h2>
        </div>
        
        <div class="content">
            @if(is_array($content))
                @foreach($content as $section => $text)
                    @if(is_string($text))
                        <p><strong>{{ ucfirst(str_replace('_', ' ', $section)) }}:</strong> {{ $text }}</p>
                    @elseif(is_array($text))
                        <h3>{{ ucfirst(str_replace('_', ' ', $section)) }}</h3>
                        @foreach($text as $key => $value)
                            <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                        @endforeach
                    @endif
                @endforeach
            @else
                <p>{{ $content }}</p>
            @endif

            @if(!empty($data))
                <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                    <h4>Additional Information:</h4>
                    @foreach($data as $key => $value)
                        @if(is_string($value))
                            <p><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated message from {{ config('app.name') }}.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>