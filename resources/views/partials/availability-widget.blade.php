@if (\App\Models\OperatingHours::current()->booking_widget_style === 'by_court')
    @include('partials.availability-widget-by-court')
@else
    @include('partials.availability-widget-grid')
@endif
