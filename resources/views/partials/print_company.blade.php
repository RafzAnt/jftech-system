@php($printLogoPath = resource_path('views/logo/jftect_logo.png'))
@php($printLogoData = is_file($printLogoPath) ? base64_encode(file_get_contents($printLogoPath)) : null)
<div class="print-company-brand{{ !empty($inverse) ? ' print-company-brand--inverse' : '' }}{{ !empty($compact) ? ' print-company-brand--compact' : '' }}">
    @if ($printLogoData)
        <img class="print-company-logo" src="data:image/png;base64,{{ $printLogoData }}" alt="JFTECH IT Solutions logo">
    @endif
    <div>
        <div class="company">JFTECH IT SOLUTIONS</div>
        <div class="sub">Del Carmen Sur, Balilihan, Bohol<br>jftechitsolutions@gmail.com</div>
    </div>
</div>
