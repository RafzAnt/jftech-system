{{-- The logo is the cached picture of the logo route, not a copy inside the page: a page with a payslip per payroll record would otherwise carry it hundreds of times. --}}
@php($printLogoPath = resource_path('views/logo/jftect_logo.png'))
@php($hasPrintLogo = is_file($printLogoPath))
<div class="print-company-brand{{ !empty($inverse) ? ' print-company-brand--inverse' : '' }}{{ !empty($compact) ? ' print-company-brand--compact' : '' }}">
    @if ($hasPrintLogo && empty($noLogo))
        <img class="print-company-logo" src="{{ url('/assets/jftech-logo.png') }}" alt="JFTECH IT Solutions logo">
    @endif
    <div>
        <div class="company">JFTECH IT SOLUTIONS &mdash; MAIN OFFICE</div>
        <div class="sub">Del Carmen Sur, Balilihan, Bohol<br>jftechitsolutions@gmail.com</div>
    </div>
</div>
