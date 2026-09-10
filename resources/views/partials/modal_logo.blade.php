@php($logoPath = resource_path('views/logo/jftect_logo.png'))
@php($logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null)
@if ($logoData)
    <img src="data:image/png;base64,{{ $logoData }}" alt="JFTECH logo" style="height:36px; width:auto; display:block;">
@endif
