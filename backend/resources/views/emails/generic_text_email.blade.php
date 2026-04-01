{{ $subject }}

{{ str_repeat('=', strlen($subject)) }}

@if(is_array($content))
@foreach($content as $section => $text)
@if(is_string($text))
{{ ucfirst(str_replace('_', ' ', $section)) }}: {{ $text }}
@elseif(is_array($text))
{{ ucfirst(str_replace('_', ' ', $section)) }}:
@foreach($text as $key => $value)
  {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}
@endforeach
@endif
@endforeach
@else
{{ $content }}
@endif

@if(!empty($data))

Additional Information:
@foreach($data as $key => $value)
@if(is_string($value))
{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}
@endif
@endforeach
@endif

---
This is an automated message from {{ config('app.name') }}.
Please do not reply to this email.