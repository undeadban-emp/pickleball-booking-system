import Alpine from 'alpinejs';
import bookingCalendar from './booking-calendar';
import availabilityGrid from './availability-grid';
import matchScoreboard from './match-scoreboard';

window.Alpine = Alpine;
Alpine.data('bookingCalendar', bookingCalendar);
Alpine.data('availabilityGrid', availabilityGrid);
Alpine.data('matchScoreboard', matchScoreboard);
Alpine.start();
