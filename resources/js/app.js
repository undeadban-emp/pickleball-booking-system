import Alpine from 'alpinejs';
import bookingCalendar from './booking-calendar';
import availabilityGrid from './availability-grid';

window.Alpine = Alpine;
Alpine.data('bookingCalendar', bookingCalendar);
Alpine.data('availabilityGrid', availabilityGrid);
Alpine.start();
