
import { InitApp } from '@/helpers/main'
import EmployeeOffcanvas from './components/EmployeeOffcanvas.vue'
import ChangePassword from './components/ChangePassword.vue'
import ServiceOffcanvas from './components/ServiceOffcanvas.vue'

// Debug: Check if component file exists
let StaffWorkingHours = null;
try {
  StaffWorkingHours = (await import('./components/StaffWorkingHours.vue')).default;
  console.log('✅ StaffWorkingHours component loaded successfully');
} catch (error) {
  console.error('❌ Failed to load StaffWorkingHours component:', error);
}

// Debug: Check if SubmitButton exists
let SubmitButton = null;
try {
  SubmitButton = (await import('@/vue/components/form-elements/FormFooter.vue')).default;
  console.log('✅ SubmitButton component loaded successfully');
} catch (error) {
  console.error('❌ Failed to load SubmitButton component:', error);
}

import VueTelInput from 'vue3-tel-input'
import 'vue3-tel-input/dist/vue3-tel-input.css'

// Import dependencies for Staff Working Hours component
let Multiselect = null;
let FlatPickr = null;

try {
  Multiselect = (await import('@vueform/multiselect')).default;
  await import('@vueform/multiselect/themes/default.css');
  console.log('✅ Multiselect loaded successfully');
} catch (error) {
  console.error('❌ Failed to load Multiselect:', error);
}

try {
  FlatPickr = (await import('vue-flatpickr-component')).default;
  await import('flatpickr/dist/flatpickr.css');
  console.log('✅ FlatPickr loaded successfully');
} catch (error) {
  console.error('❌ Failed to load FlatPickr:', error);
}

const app = InitApp()
console.log('✅ App initialized');

// Register plugins
app.use(VueTelInput)

// Register components
app.component('employee-offcanvas', EmployeeOffcanvas)
app.component('change-password', ChangePassword)
app.component('service-view-offcanvas', ServiceOffcanvas)

if (StaffWorkingHours) {
  app.component('staff-working-hours', StaffWorkingHours)
  console.log('✅ StaffWorkingHours component registered');
}

if (SubmitButton) {
  app.component('submit-button', SubmitButton)
  console.log('✅ SubmitButton component registered');
}

// Register dependencies for Staff Working Hours
if (Multiselect) {
  app.component('Multiselect', Multiselect)
  console.log('✅ Multiselect component registered');
}

if (FlatPickr) {
  app.component('FlatPickr', FlatPickr)
  console.log('✅ FlatPickr component registered');
}

const mountElement = document.querySelector('[data-render="app"]');
if (mountElement) {
  console.log('✅ Mount element found:', mountElement);
  app.mount('[data-render="app"]')
  console.log('✅ App mounted successfully');
} else {
  console.error('❌ Mount element [data-render="app"] not found');
}