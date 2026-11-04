// LibTickets Admin Dashboard

// Configuration
const API_BASE_URL = '';

let currentSection = 'dashboard';
let currentUser = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  checkAuth();
  
  // Close sidebar when clicking on a link (mobile)
  const sidebarLinks = document.querySelectorAll('.sidebar-link');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        closeSidebar();
      }
    });
  });
});

// Toggle sidebar (for mobile)
function toggleSidebar() {
  const sidebar = document.querySelector('.admin-sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  
  sidebar.classList.toggle('open');
  document.body.classList.toggle('sidebar-open');
  
  if (sidebar.classList.contains('open')) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent body scroll when sidebar is open
  } else {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

// Close sidebar
function closeSidebar() {
  const sidebar = document.querySelector('.admin-sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  
  sidebar.classList.remove('open');
  overlay.classList.remove('active');
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}

// Close sidebar when window is resized to desktop
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    closeSidebar();
  }
});

// Check if user is authenticated
async function checkAuth() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/session.php`);
    const data = await res.json();

    if (!data.user || data.user.role !== 'admin') {
      // Redirect to main site if not admin
      window.location.href = `${API_BASE_URL}/`;
      return;
    }

    // Show dashboard
    currentUser = data.user;
    document.getElementById('admin-dashboard').classList.remove('hidden');

    // Update profile info
    document.getElementById('admin-name').textContent = data.user.name || 'Administrator';
    document.getElementById('admin-email-display').textContent = data.user.email;

    // Show dashboard section by default
    showSection('dashboard');

    loadAdminData();
  } catch (error) {
    console.error('Auth check failed:', error);
    window.location.href = `${API_BASE_URL}/`;
  }
}

// Load admin data
async function loadAdminData() {
  try {
    // Load admin logo
    loadAdminLogo();

    // Load categories and venues for forms
    const categoriesRes = await fetch(`${API_BASE_URL}/api/categories.php`);
    const categories = await categoriesRes.json();
    populateCategorySelect(categories);

    const venuesRes = await fetch(`${API_BASE_URL}/api/venues.php`);
    const venues = await venuesRes.json();
    populateVenueSelect(venues);

    // Load counties for event form
    const countiesRes = await fetch(`${API_BASE_URL}/api/counties.php`);
    const counties = await countiesRes.json();
    populateCountySelect(counties);

    // Load current exchange rate
    const rateRes = await fetch(`${API_BASE_URL}/api/exchange-rate.php`);
    const rateData = await rateRes.json();
    if (rateData.rate) {
      const rateInput = document.getElementById('exchange-rate-input');
      const currentRate = document.getElementById('current-rate');
      if (rateInput) rateInput.value = rateData.rate;
      if (currentRate) currentRate.textContent = rateData.rate;
    }

    // Load dashboard statistics
    loadDashboardStats();

    // Load events for dashboard table
    loadDashboardEvents();

    // Load events for events section
    loadEvents();

    // Load bookings
    loadBookings();

    // Load venues
    loadVenues();

    // Load categories
    loadCategories();

    // Load slideshow images
    loadSlideshowImages();

    // Load profile data
    loadProfileData();
  } catch (error) {
    console.error('Error loading admin data:', error);
  }
}

// Load profile data
async function loadProfileData() {
  try {
    if (!currentUser) return;

    document.getElementById('profile-full-name').value = currentUser.full_name || '';
    document.getElementById('profile-email').value = currentUser.email || '';
    document.getElementById('profile-phone').value = currentUser.phone || '';
    document.getElementById('profile-role').value = currentUser.role || 'Administrator';
    
    if (currentUser.created_at) {
      const createdDate = new Date(currentUser.created_at);
      document.getElementById('profile-created').value = createdDate.toLocaleDateString();
    }
    
    document.getElementById('profile-verified').value = currentUser.is_verified ? 'Yes' : 'No';
  } catch (error) {
    console.error('Error loading profile data:', error);
  }
}

// Update profile
async function updateProfile() {
  const fullName = document.getElementById('profile-full-name').value.trim();
  const phone = document.getElementById('profile-phone').value.trim();

  if (!fullName) {
    alert('Please enter your full name');
    return;
  }

  try {
    const res = await fetch('/api/admin/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ full_name: fullName, phone: phone })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to update profile');
    }

    alert('Profile updated successfully');
    
    // Update current user data
    currentUser.full_name = fullName;
    currentUser.phone = phone;
    document.getElementById('admin-name').textContent = fullName;
  } catch (error) {
    console.error('Error updating profile:', error);
    alert(error.message || 'Failed to update profile');
  }
}

// Change password
async function changePassword() {
  const currentPassword = document.getElementById('current-password').value;
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (!currentPassword || !newPassword || !confirmPassword) {
    alert('Please fill in all password fields');
    return;
  }

  if (newPassword !== confirmPassword) {
    alert('New passwords do not match');
    return;
  }

  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters');
    return;
  }

  try {
    const res = await fetch('/api/admin/change-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ 
        current_password: currentPassword, 
        new_password: newPassword 
      })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to change password');
    }

    alert('Password changed successfully');
    
    // Clear password fields
    document.getElementById('current-password').value = '';
    document.getElementById('new-password').value = '';
    document.getElementById('confirm-password').value = '';
  } catch (error) {
    console.error('Error changing password:', error);
    alert(error.message || 'Failed to change password');
  }
}

// Load admin logo
async function loadAdminLogo() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/logo.php`);
    const data = await res.json();
    const logoUrl = data.url;

    // Update all logo images in admin dashboard
    document.querySelectorAll('.sidebar-logo, .topbar-logo').forEach(img => {
      img.src = logoUrl;
    });
  } catch (error) {
    console.error('Error loading admin logo:', error);
  }
}

// Populate category select
function populateCategorySelect(categories) {
  const select = document.getElementById('event-category');
  select.innerHTML = '<option value="">Select Category</option>' +
    categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
}

// Populate venue select
function populateVenueSelect(venues) {
  const select = document.getElementById('event-venue');
  select.innerHTML = '<option value="">Select Venue</option>' +
    venues.map(venue => `<option value="${venue.id}">${venue.name} (${venue.city_name})</option>`).join('');
}

// Populate event select for ticket types
function populateEventSelect(events) {
  const select = document.getElementById('ticket-type-event');
  if (select) {
    select.innerHTML = '<option value="">Select Event</option>' +
      events.map(event => `<option value="${event.id}">${event.title}</option>`).join('');
  }
}

// Populate county select
function populateCountySelect(counties) {
  const select = document.getElementById('event-county');
  if (select) {
    select.innerHTML = '<option value="">Select County</option>' +
      counties.map(county => `<option value="${county.id}">${county.name}</option>`).join('');
  }
}

// Load cities for selected county
async function loadCitiesForCounty(countyId) {
  if (!countyId) {
    const citySelect = document.getElementById('venue-city');
    if (citySelect) {
      citySelect.innerHTML = '<option value="">Select City</option>';
    }
    return;
  }

  try {
    const res = await fetch(`${API_BASE_URL}/api/cities.php?county_id=${countyId}`);
    const cities = await res.json();
    
    const citySelect = document.getElementById('venue-city');
    if (citySelect) {
      citySelect.innerHTML = '<option value="">Select City</option>' +
        cities.map(city => `<option value="${city.id}">${city.name}</option>`).join('');
    }
  } catch (error) {
    console.error('Error loading cities:', error);
  }
}

// Load dashboard statistics
async function loadDashboardStats() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/dashboard-stats.php`);
    const stats = await res.json();

    document.getElementById('stat-events').textContent = stats.events || 0;
    document.getElementById('stat-tickets-sold').textContent = stats.ticketsSold || 0;
    document.getElementById('stat-tickets-left').textContent = stats.ticketsLeft || 0;
    document.getElementById('stat-bookings').textContent = stats.bookings || 0;
  } catch (error) {
    console.error('Error loading dashboard stats:', error);
  }
}

// Load dashboard events table
async function loadDashboardEvents() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/events.php`);
    const events = await res.json();

    const tbody = document.getElementById('dashboard-events-table');
    tbody.innerHTML = events.map(event => {
      const tiers = event.ticket_types ? event.ticket_types.length : 0;
      const available = event.ticket_types ?
        event.ticket_types.reduce((sum, tt) => sum + tt.available_quantity, 0) : 0;

      return `
        <tr>
          <td>${event.title}</td>
          <td>${event.venue_name || 'N/A'}</td>
          <td>${event.county_name || 'N/A'}</td>
          <td>${new Date(event.event_date).toLocaleDateString()}</td>
          <td>${tiers}</td>
          <td>${available}</td>
        </tr>
      `;
    }).join('');
  } catch (error) {
    console.error('Error loading dashboard events:', error);
  }
}

// Load ticket types
async function loadTicketTypes() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/ticket-types.php`);
    const ticketTypes = await res.json();

    const tbody = document.getElementById('ticket-types-table-body');
    tbody.innerHTML = ticketTypes.map(tt => `
      <tr>
        <td>${tt.event_title}</td>
        <td>${tt.name}</td>
        <td>${tt.price.toLocaleString()}</td>
        <td>${tt.currency}</td>
        <td>${tt.total_quantity}</td>
        <td>${tt.available_quantity}</td>
        <td>
          <button class="btn-action btn-edit" onclick="editTicketType(${tt.id})">Edit</button>
          <button class="btn-action btn-delete" onclick="deleteTicketType(${tt.id})">Delete</button>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading ticket types:', error);
  }
}

// Show ticket type form
function showTicketTypeForm() {
  document.getElementById('ticket-type-form').classList.remove('hidden');
  document.getElementById('ticket-type-form-title').textContent = 'Add Ticket Type';
  document.getElementById('ticket-type-id').value = '';
  document.getElementById('ticket-type-event').value = '';
  document.getElementById('ticket-type-name').value = '';
  document.getElementById('ticket-type-price').value = '';
  document.getElementById('ticket-type-currency').value = 'LRD';
  document.getElementById('ticket-type-quantity').value = '';
}

// Edit ticket type
async function editTicketType(id) {
  console.log('Editing ticket type:', id);
  try {
    const res = await fetch(`${API_BASE_URL}/api/ticket-types.php/${id}`);
    const ticketType = await res.json();

    console.log('Ticket type data:', ticketType);

    const form = document.getElementById('ticket-type-form');
    if (form) {
      form.classList.remove('hidden');
      document.getElementById('ticket-type-form-title').textContent = 'Edit Ticket Type';
      document.getElementById('ticket-type-id').value = ticketType.id;
      document.getElementById('ticket-type-event').value = ticketType.event_id;
      document.getElementById('ticket-type-name').value = ticketType.name;
      document.getElementById('ticket-type-price').value = ticketType.price;
      document.getElementById('ticket-type-currency').value = ticketType.currency;
      document.getElementById('ticket-type-quantity').value = ticketType.total_quantity;

      // Scroll to form
      form.scrollIntoView({ behavior: 'smooth' });
    } else {
      console.error('Ticket type form not found');
      alert('Ticket type form not found');
    }
  } catch (error) {
    console.error('Error loading ticket type:', error);
    alert('Error loading ticket type details');
  }
}

// Hide ticket type form
function hideTicketTypeForm() {
  document.getElementById('ticket-type-form').classList.add('hidden');
}

// Save ticket type
async function saveTicketType(event) {
  event.preventDefault();

  const id = document.getElementById('ticket-type-id').value;
  const eventId = document.getElementById('ticket-type-event').value;
  const name = document.getElementById('ticket-type-name').value;
  const price = parseFloat(document.getElementById('ticket-type-price').value);
  const currency = document.getElementById('ticket-type-currency').value;
  const quantity = parseInt(document.getElementById('ticket-type-quantity').value);

  try {
    let res;
    if (id) {
      // Update existing ticket type
      res = await fetch(`/api/admin/ticket-types/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_id: eventId,
          name,
          price,
          currency,
          total_quantity: quantity
        })
      });
    } else {
      // Create new ticket type
      res = await fetch('/api/admin/ticket-types', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          event_id: eventId,
          name,
          price,
          currency,
          total_quantity: quantity
        })
      });
    }

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save ticket type');
    }

    hideTicketTypeForm();
    loadTicketTypes();
    alert('Ticket type saved successfully!');
  } catch (error) {
    alert(error.message);
  }
}

// Delete ticket type
async function deleteTicketType(id) {
  if (!confirm('Are you sure you want to delete this ticket type?')) {
    return;
  }

  try {
    const res = await fetch(`/api/admin/ticket-types/${id}`, {
      method: 'DELETE'
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to delete ticket type');
    }

    loadTicketTypes();
    alert('Ticket type deleted successfully!');
  } catch (error) {
    alert(error.message);
  }
}

// Show section
function showSection(section) {
  event && event.preventDefault();

  // Hide all sections
  document.querySelectorAll('.admin-content').forEach(s => s.classList.add('hidden'));

  // Show selected section
  const sectionElement = document.getElementById(`section-${section}`);
  if (sectionElement) {
    sectionElement.classList.remove('hidden');
  }

  // Update sidebar links
  document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
  const activeLink = document.querySelector(`.sidebar-link[onclick*="${section}"]`);
  if (activeLink) {
    activeLink.classList.add('active');
  }

  // Update page title
  const titles = {
    'dashboard': 'Admin dashboard',
    'events': 'Events',
    'bookings': 'Bookings',
    'venues': 'Venues',
    'categories': 'Categories',
    'slideshow': 'Slideshow',
    'settings': 'Settings',
    'activity': 'Activity Log'
  };
  document.getElementById('page-title').textContent = titles[section] || 'Admin';

  currentSection = section;

  // Load section data
  if (section === 'dashboard') {
    loadDashboardStats();
    loadDashboardEvents();
  } else if (section === 'events') {
    loadEvents();
  } else if (section === 'bookings') {
    loadBookings();
  } else if (section === 'venues') {
    loadVenues();
  } else if (section === 'categories') {
    loadCategories();
  } else if (section === 'activity') {
    loadActivityLog();
  }
}

// Toggle profile menu
function toggleProfileMenu() {
  const menu = document.getElementById('profile-menu');
  menu.classList.toggle('hidden');
}

// Logout
async function logout() {
  try {
    await fetch(`${API_BASE_URL}/api/logout.php`, { method: 'POST' });
    window.location.href = `${API_BASE_URL}/`;
  } catch (error) {
    console.error('Logout error:', error);
  }
}

// Add ticket tier
function addTicketTier() {
  const container = document.getElementById('ticket-tiers-container');
  const newRow = document.createElement('div');
  newRow.className = 'ticket-tier-row';
  newRow.innerHTML = `
    <input type="text" placeholder="Tier name (e.g., VIP)" class="tier-name">
    <input type="number" placeholder="Quantity" class="tier-quantity" min="1">
    <input type="number" placeholder="Price" class="tier-price" step="0.01" min="0">
    <select class="tier-currency">
      <option value="LRD">LRD</option>
      <option value="USD">USD</option>
    </select>
    <button type="button" class="btn-remove-tier" onclick="removeTicketTier(this)">×</button>
  `;
  container.appendChild(newRow);
}

// Remove ticket tier
function removeTicketTier(button) {
  const container = document.getElementById('ticket-tiers-container');
  if (container.children.length > 1) {
    button.parentElement.remove();
  }
}

// Load events
async function loadEvents() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/events.php`);
    const events = await res.json();

    const tbody = document.getElementById('events-table-body');
    tbody.innerHTML = events.map(event => `
      <tr>
        <td>${event.title}</td>
        <td>${new Date(event.event_date).toLocaleDateString()}</td>
        <td>${event.venue_name}</td>
        <td>${event.county_name || 'N/A'}</td>
        <td>
          <span style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; background: ${
            event.status === 'published' ? '#d1fae5' :
            event.status === 'draft' ? '#fef3c7' :
            event.status === 'cancelled' ? '#fee2e2' : '#e5e7eb'
          }; color: ${
            event.status === 'published' ? '#065f46' :
            event.status === 'draft' ? '#92400e' :
            event.status === 'cancelled' ? '#991b1b' : '#374151'
          }">${event.status}</span>
        </td>
        <td>
          <button class="btn-action btn-edit" onclick="editEvent(${event.id})">Edit</button>
          <button class="btn-action btn-delete" onclick="deleteEvent(${event.id})">Delete</button>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading events:', error);
  }
}

// Show event form
function showEventForm() {
  document.getElementById('event-form').classList.remove('hidden');
  document.getElementById('event-form-title').textContent = 'Add New Event';
  document.getElementById('event-id').value = '';
  document.getElementById('event-title').value = '';
  document.getElementById('event-venue').value = '';
  document.getElementById('event-county').value = '';
  document.getElementById('event-date').value = '';
  document.getElementById('event-description').value = '';
  document.getElementById('event-time').value = '';
  document.getElementById('event-category').value = '';
  document.getElementById('event-image').value = '';
  document.getElementById('event-file').value = '';
  document.getElementById('event-status').value = 'published';

  // Reset ticket tiers
  const container = document.getElementById('ticket-tiers-container');
  container.innerHTML = `
    <div class="ticket-tier-row">
      <input type="text" placeholder="Tier name (e.g., VIP)" class="tier-name">
      <input type="number" placeholder="Quantity" class="tier-quantity" min="1">
      <input type="number" placeholder="Price" class="tier-price" step="0.01" min="0">
      <select class="tier-currency">
        <option value="LRD">LRD</option>
        <option value="USD">USD</option>
      </select>
      <button type="button" class="btn-remove-tier" onclick="removeTicketTier(this)">×</button>
    </div>
  `;
}

// Hide event form
function hideEventForm() {
  document.getElementById('event-form').classList.add('hidden');
}

// Edit event
async function editEvent(eventId) {
  console.log('Editing event:', eventId);
  try {
    const res = await fetch(`${API_BASE_URL}/api/events.php/${eventId}`);
    const event = await res.json();

    console.log('Event data:', event);

    document.getElementById('event-form').classList.remove('hidden');
    document.getElementById('event-form-title').textContent = 'Edit Event';
    document.getElementById('event-id').value = event.id;
    document.getElementById('event-title').value = event.title;
    document.getElementById('event-venue').value = event.venue_id || '';
    document.getElementById('event-county').value = event.county_id || '';
    document.getElementById('event-date').value = event.event_date ? event.event_date.split('T')[0] : '';
    document.getElementById('event-description').value = event.description || '';
    document.getElementById('event-time').value = event.event_time || '';
    document.getElementById('event-category').value = event.category_id || '';
    document.getElementById('event-image').value = event.image_url || '';
    document.getElementById('event-status').value = event.status || 'published';

    // Load ticket types for this event
    const ticketTypesRes = await fetch(`${API_BASE_URL}/api/ticket-types.php?event_id=${eventId}`);
    const ticketTypes = await ticketTypesRes.json();

    const container = document.getElementById('ticket-tiers-container');
    if (ticketTypes.length > 0) {
      container.innerHTML = ticketTypes.map(tt => `
        <div class="ticket-tier-row">
          <input type="text" placeholder="Tier name" class="tier-name" value="${tt.name}">
          <input type="number" placeholder="Quantity" class="tier-quantity" min="1" value="${tt.total_quantity}">
          <input type="number" placeholder="Price" class="tier-price" step="0.01" min="0" value="${tt.price}">
          <select class="tier-currency">
            <option value="LRD" ${tt.currency === 'LRD' ? 'selected' : ''}>LRD</option>
            <option value="USD" ${tt.currency === 'USD' ? 'selected' : ''}>USD</option>
          </select>
          <button type="button" class="btn-remove-tier" onclick="removeTicketTier(this)">×</button>
        </div>
      `).join('');
    } else {
      container.innerHTML = `
        <div class="ticket-tier-row">
          <input type="text" placeholder="Tier name (e.g., VIP)" class="tier-name">
          <input type="number" placeholder="Quantity" class="tier-quantity" min="1">
          <input type="number" placeholder="Price" class="tier-price" step="0.01" min="0">
          <select class="tier-currency">
            <option value="LRD">LRD</option>
            <option value="USD">USD</option>
          </select>
          <button type="button" class="btn-remove-tier" onclick="removeTicketTier(this)">×</button>
        </div>
      `;
    }

    // Scroll to form
    document.getElementById('event-form').scrollIntoView({ behavior: 'smooth' });
  } catch (error) {
    console.error('Error loading event:', error);
    alert('Error loading event details');
  }
}

// Save event
async function saveEvent(event) {
  event.preventDefault();

  const eventId = document.getElementById('event-id').value;
  const fileInput = document.getElementById('event-file');
  let imageUrl = document.getElementById('event-image').value;

  // Upload file if selected
  if (fileInput.files.length > 0) {
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    try {
      const uploadRes = await fetch('/api/upload', {
        method: 'POST',
        body: formData
      });

      console.log('Upload response status:', uploadRes.status);
      console.log('Upload response headers:', uploadRes.headers);

      const responseText = await uploadRes.text();
      console.log('Upload response text:', responseText);

      if (!uploadRes.ok) {
        throw new Error(`Upload failed with status ${uploadRes.status}: ${responseText}`);
      }

      const uploadData = JSON.parse(responseText);
      imageUrl = uploadData.url;
    } catch (error) {
      console.error('File upload error:', error);
      alert('File upload failed: ' + error.message);
      return;
    }
  }

  const eventData = {
    title: document.getElementById('event-title').value,
    description: document.getElementById('event-description').value,
    category_id: document.getElementById('event-category').value || null,
    venue_id: document.getElementById('event-venue').value || null,
    county_id: document.getElementById('event-county').value || null,
    event_date: document.getElementById('event-date').value,
    event_time: document.getElementById('event-time').value,
    image_url: imageUrl,
    status: document.getElementById('event-status').value
  };

  try {
    const url = eventId ? `/api/admin/events/${eventId}` : '/api/admin/events';
    const method = eventId ? 'PUT' : 'POST';

    const res = await fetch(url, {
      method: method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(eventData)
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save event');
    }

    // Save ticket tiers
    const savedEventId = eventId || data.id;
    const tierRows = document.querySelectorAll('.ticket-tier-row');

    // Delete existing ticket types for this event first (to prevent duplicates)
    if (eventId) {
      await fetch(`/api/admin/ticket-types/event/${eventId}`, {
        method: 'DELETE'
      });
    }

    for (const row of tierRows) {
      const name = row.querySelector('.tier-name').value;
      const quantity = parseInt(row.querySelector('.tier-quantity').value);
      const price = parseFloat(row.querySelector('.tier-price').value);
      const currency = row.querySelector('.tier-currency').value;

      if (name && quantity && price) {
        await fetch('/api/admin/ticket-types', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            event_id: savedEventId,
            name,
            price,
            currency,
            total_quantity: quantity
          })
        });
      }
    }

    hideEventForm();
    loadEvents();
    loadDashboardStats();
    loadDashboardEvents();
    alert('Event saved successfully!');
  } catch (error) {
    alert(error.message);
  }
}

// Delete event
async function deleteEvent(eventId) {
  if (!confirm('Are you sure you want to delete this event?')) {
    return;
  }

  try {
    const res = await fetch(`/api/admin/events/${eventId}`, {
      method: 'DELETE'
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to delete event');
    }

    alert('Event deleted successfully');
    loadEvents();
  } catch (error) {
    console.error('Error deleting event:', error);
    alert(error.message);
  }
}

// Load bookings
async function loadBookings() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/bookings.php`);
    const bookings = await res.json();

    const tbody = document.getElementById('bookings-table-body');
    tbody.innerHTML = bookings.map(booking => `
      <tr>
        <td><strong>${booking.booking_reference}</strong></td>
        <td>${booking.customer_name}</td>
        <td>${booking.customer_email}</td>
        <td>${booking.total_amount.toLocaleString()}</td>
        <td>${booking.currency}</td>
        <td>${booking.total_tickets || 0}</td>
        <td>${new Date(booking.created_at).toLocaleDateString()}</td>
        <td>
          <span style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; background: ${
            booking.status === 'confirmed' ? '#d1fae5' : '#fee2e2'
          }; color: ${
            booking.status === 'confirmed' ? '#065f46' : '#991b1b'
          };">${booking.status}</span>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading bookings:', error);
  }
}

// Load activity log
async function loadActivityLog() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/activity-log.php`);
    const logs = await res.json();

    const tbody = document.getElementById('activity-log-table-body');
    tbody.innerHTML = logs.map(log => `
      <tr>
        <td>${new Date(log.created_at).toLocaleString()}</td>
        <td>${log.customer_name || 'System'}</td>
        <td>Booking</td>
        <td>Booking #${log.booking_reference}</td>
        <td>${log.ticket_count} ticket(s) for ${log.currency} ${log.total_amount.toLocaleString()}</td>
        <td>${log.status}</td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading activity log:', error);
  }
}

// Validate ticket
async function validateTicket() {
  const qrToken = document.getElementById('qr-token-input').value.trim();

  if (!qrToken) {
    alert('Please enter a QR token');
    return;
  }

  try {
    const res = await fetch('/api/tickets.php/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ qr_token: qrToken })
    });

    const data = await res.json();

    const resultDiv = document.getElementById('validation-result');
    resultDiv.classList.remove('hidden');

    if (data.valid) {
      resultDiv.className = 'validation-result valid';
      resultDiv.innerHTML = `
        <h3>✓ Valid Ticket</h3>
        <div class="booking-details">
          <h4>Booking Details</h4>
          <p><strong>Booking Reference:</strong> ${data.ticket.booking_reference}</p>
          <p><strong>Customer:</strong> ${data.ticket.customer_name}</p>
          <p><strong>Email:</strong> ${data.ticket.customer_email}</p>
          <p><strong>Total Paid:</strong> ${data.ticket.total_amount.toLocaleString()} ${data.ticket.currency}</p>
          <h4>Tickets</h4>
          <p><strong>${data.ticket.event_title}</strong></p>
          <p>${data.ticket.venue_name} - ${new Date(data.ticket.event_date).toLocaleDateString()}</p>
          <p><strong>${data.ticket.ticket_name} x ${data.ticket.quantity || 1}</strong></p>
          ${data.ticket.seat_number ? `<p><strong>Seat:</strong> ${data.ticket.seat_number}</p>` : ''}
        </div>
        <p style="margin-top: 1rem;"><strong>Ticket admitted at: ${new Date().toLocaleTimeString()}</strong></p>
      `;
    } else {
      resultDiv.className = 'validation-result invalid';
      resultDiv.innerHTML = `
        <h3>✗ Invalid Ticket</h3>
        <p>${data.message}</p>
        ${data.used_at ? `<p>Previously used at: ${new Date(data.used_at).toLocaleString()}</p>` : ''}
      `;
    }
  } catch (error) {
    console.error('Error validating ticket:', error);
    alert('Error validating ticket');
  }
}

// Update exchange rate
async function updateExchangeRate() {
  const rate = parseFloat(document.getElementById('exchange-rate-input').value);

  if (!rate || rate <= 0) {
    alert('Please enter a valid exchange rate');
    return;
  }

  try {
    const res = await fetch('/api/exchange-rate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rate })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to update exchange rate');
    }

    alert('Exchange rate updated successfully');
    document.getElementById('current-rate').textContent = rate;
  } catch (error) {
    console.error('Error updating exchange rate:', error);
    alert(error.message);
  }
}

// Save general settings
async function saveGeneralSettings() {
  const settings = {
    site_name: document.getElementById('site-name').value,
    site_tagline: document.getElementById('site-tagline').value,
    contact_email: document.getElementById('contact-email').value,
    contact_phone: document.getElementById('contact-phone').value,
    contact_location: document.getElementById('contact-location').value
  };

  try {
    const res = await fetch('/api/admin/settings/general', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings)
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save general settings');
    }

    alert('General settings saved successfully');
  } catch (error) {
    console.error('Error saving general settings:', error);
    alert(error.message);
  }
}

// Save social settings
async function saveSocialSettings() {
  const settings = {
    facebook: document.getElementById('social-facebook').value,
    twitter: document.getElementById('social-twitter').value,
    instagram: document.getElementById('social-instagram').value
  };

  try {
    const res = await fetch('/api/admin/settings/social', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings)
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save social settings');
    }

    alert('Social settings saved successfully');
  } catch (error) {
    console.error('Error saving social settings:', error);
    alert(error.message);
  }
}

// Save theme settings
async function saveThemeSettings() {
  const settings = {
    primary_color: document.getElementById('theme-primary').value,
    accent_color: document.getElementById('theme-accent').value
  };

  try {
    const res = await fetch('/api/admin/settings/theme', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings)
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save theme settings');
    }

    alert('Theme settings saved successfully. Refresh the page to see changes.');
  } catch (error) {
    console.error('Error saving theme settings:', error);
    alert(error.message);
  }
}

// Upload logo
async function uploadLogo() {
  const fileInput = document.getElementById('logo-file');

  if (fileInput.files.length === 0) {
    alert('Please select a logo file to upload');
    return;
  }

  const formData = new FormData();
  formData.append('file', fileInput.files[0]);

  try {
    const res = await fetch('/api/admin/upload-logo', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to upload logo');
    }

    alert('Logo uploaded successfully!');
    document.getElementById('current-logo-preview').innerHTML = `
      <img src="${data.url}" alt="Current Logo" style="max-height: 100px;">
    `;
    fileInput.value = '';

    // Reload the logo in the dashboard
    loadAdminLogo();
  } catch (error) {
    console.error('Error uploading logo:', error);
    alert(error.message);
  }
}

// Logout
async function logout() {
  try {
    await fetch(`${API_BASE_URL}/api/logout.php`, { method: 'POST' });
    window.location.href = `${API_BASE_URL}/`;
  } catch (error) {
    console.error('Error logging out:', error);
  }
}

// Venue Management Functions
function showVenueForm() {
  document.getElementById('venue-form').classList.remove('hidden');
  document.getElementById('venue-form-title').textContent = 'Add New Venue';
  document.getElementById('venue-id').value = '';
  document.getElementById('venue-name').value = '';
  document.getElementById('venue-county').value = '';
  document.getElementById('venue-city').value = '';
  document.getElementById('venue-address').value = '';
  document.getElementById('venue-capacity').value = '';
  document.getElementById('venue-description').value = '';
}

function hideVenueForm() {
  document.getElementById('venue-form').classList.add('hidden');
}

async function saveVenue(event) {
  event.preventDefault();

  const id = document.getElementById('venue-id').value;
  const name = document.getElementById('venue-name').value;
  const county = document.getElementById('venue-county').value;
  const city = document.getElementById('venue-city').value;
  const address = document.getElementById('venue-address').value;
  const capacity = document.getElementById('venue-capacity').value;
  const description = document.getElementById('venue-description').value;

  try {
    let res;
    if (id) {
      // Update existing venue
      res = await fetch(`/api/admin/venues/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, county, city, address, capacity, description })
      });
    } else {
      // Create new venue
      res = await fetch('/api/admin/venues', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, county, city, address, capacity, description })
      });
    }

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to save venue');
    }

    alert('Venue saved successfully!');
    hideVenueForm();
    loadVenues();
    populateVenueSelect(); // Update venue dropdown in event form
  } catch (error) {
    console.error('Error saving venue:', error);
    alert(error.message);
  }
}

async function loadVenues() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/venues.php`);
    const venues = await res.json();

    const tbody = document.getElementById('venues-table-body');
    tbody.innerHTML = venues.map(venue => `
      <tr>
        <td>${venue.name}</td>
        <td>${venue.county || 'N/A'}</td>
        <td>${venue.city_name || venue.city || 'N/A'}</td>
        <td>${venue.address || 'N/A'}</td>
        <td>${venue.capacity || 'N/A'}</td>
        <td>
          <button class="btn-admin-action" onclick="editVenue(${venue.id})">Edit</button>
          <button class="btn-admin-action" onclick="deleteVenue(${venue.id})">Delete</button>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading venues:', error);
  }
}

async function editVenue(id) {
  try {
    const res = await fetch(`${API_BASE_URL}/api/venues.php/${id}`);
    const venue = await res.json();

    document.getElementById('venue-form').classList.remove('hidden');
    document.getElementById('venue-form-title').textContent = 'Edit Venue';
    document.getElementById('venue-id').value = venue.id;
    document.getElementById('venue-name').value = venue.name;
    document.getElementById('venue-county').value = venue.county || '';
    document.getElementById('venue-city').value = venue.city_name || venue.city || '';
    document.getElementById('venue-address').value = venue.address || '';
    document.getElementById('venue-capacity').value = venue.capacity || '';
    document.getElementById('venue-description').value = venue.description || '';

    // Scroll to form
    document.getElementById('venue-form').scrollIntoView({ behavior: 'smooth' });
  } catch (error) {
    console.error('Error loading venue:', error);
    alert('Error loading venue details');
  }
}

async function deleteVenue(id) {
  if (!confirm('Are you sure you want to delete this venue?')) {
    return;
  }

  try {
    const res = await fetch(`/api/admin/venues/${id}`, {
      method: 'DELETE'
    });

    if (!res.ok) {
      const data = await res.json();
      throw new Error(data.error || 'Failed to delete venue');
    }

    alert('Venue deleted successfully!');
    loadVenues();
    populateVenueSelect(); // Update venue dropdown in event form
  } catch (error) {
    console.error('Error deleting venue:', error);
    alert(error.message);
  }
}

// Load categories
async function loadCategories() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/categories.php`);
    const categories = await res.json();

    const tbody = document.getElementById('categories-table-body');
    tbody.innerHTML = categories.map(cat => `
      <tr>
        <td>${cat.name}</td>
        <td>
          ${cat.image_url ? 
            `<img src="${cat.image_url}" alt="${cat.name}" style="max-width: 100px; max-height: 60px; object-fit: cover; border-radius: 4px;">` :
            '<span style="color: #999;">No image</span>'
          }
        </td>
        <td>
          <button class="btn-action btn-edit" onclick="editCategory(${cat.id})">Edit Image</button>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading categories:', error);
  }
}

// Edit category image
async function editCategory(categoryId) {
  const fileInput = document.createElement('input');
  fileInput.type = 'file';
  fileInput.accept = 'image/*';
  fileInput.onchange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);

    try {
      const uploadRes = await fetch('/api/upload', {
        method: 'POST',
        body: formData
      });

      if (!uploadRes.ok) {
        throw new Error('Failed to upload image');
      }

      const uploadData = await uploadRes.json();
      const imageUrl = uploadData.url;

      // Update category with new image URL
      const res = await fetch(`/api/admin/categories.php?id=${categoryId}&_method=PUT`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ image_url: imageUrl })
      });

      if (!res.ok) {
        const errorData = await res.json();
        throw new Error(errorData.error || 'Failed to update category');
      }

      alert('Category image updated successfully!');
      loadCategories();
    } catch (error) {
      console.error('Error updating category image:', error);
      alert(error.message);
    }
  };
  fileInput.click();
}

// Slideshow management functions
function showSlideshowForm() {
  document.getElementById('slideshow-form').classList.remove('hidden');
  document.getElementById('slideshow-form').dataset.editId = '';
  document.getElementById('slideshow-image').value = '';
  document.getElementById('slideshow-title').value = '';
  document.getElementById('slideshow-order').value = '1';
  document.getElementById('slideshow-active').checked = true;
}

function hideSlideshowForm() {
  document.getElementById('slideshow-form').classList.add('hidden');
}

async function saveSlideshowImage(event) {
  event.preventDefault();

  const editId = document.getElementById('slideshow-form').dataset.editId;
  const imageFile = document.getElementById('slideshow-image').files[0];
  const title = document.getElementById('slideshow-title').value;
  const order = document.getElementById('slideshow-order').value;
  const active = document.getElementById('slideshow-active').checked ? 1 : 0;

  if (!imageFile && !editId) {
    alert('Please select an image');
    return;
  }

  try {
    let imageUrl = '';

    if (imageFile) {
      // Upload image
      const formData = new FormData();
      formData.append('file', imageFile);

      const uploadRes = await fetch('/api/upload', {
        method: 'POST',
        body: formData
      });

      if (!uploadRes.ok) {
        throw new Error('Failed to upload image');
      }

      const uploadData = await uploadRes.json();
      imageUrl = uploadData.url;
    }

    if (editId) {
      // Update existing
      const updateData = { title, order, active };
      if (imageUrl) {
        updateData.image_url = imageUrl;
      }

      const res = await fetch(`/api/admin/slideshow.php?id=${editId}&_method=PUT`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updateData)
      });

      if (!res.ok) {
        throw new Error('Failed to update slideshow image');
      }
    } else {
      // Create new
      const res = await fetch('/api/admin/slideshow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image_url: imageUrl, title, order, active })
      });

      if (!res.ok) {
        throw new Error('Failed to save slideshow image');
      }
    }

    hideSlideshowForm();
    loadSlideshowImages();
  } catch (error) {
    console.error('Error saving slideshow image:', error);
    alert(error.message);
  }
}

async function loadSlideshowImages() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/slideshow.php`);
    const images = await res.json();

    const tbody = document.getElementById('slideshow-table-body');
    tbody.innerHTML = images.map(img => `
      <tr>
        <td>
          <img src="${img.image_url}" alt="${img.title || 'Slide'}" style="width: 100px; height: 60px; object-fit: cover; border-radius: 4px;">
        </td>
        <td>${img.title || '-'}</td>
        <td>${img.order}</td>
        <td>${img.active ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>'}</td>
        <td>
          <button class="btn-action" onclick="editSlideshowImage(${img.id})">Edit</button>
          <button class="btn-action btn-danger" onclick="deleteSlideshowImage(${img.id})">Delete</button>
        </td>
      </tr>
    `).join('');
  } catch (error) {
    console.error('Error loading slideshow images:', error);
  }
}

async function editSlideshowImage(id) {
  try {
    const res = await fetch(`${API_BASE_URL}/api/slideshow.php?id=${id}`);
    const image = await res.json();

    document.getElementById('slideshow-form').classList.remove('hidden');
    document.getElementById('slideshow-form').dataset.editId = id;
    document.getElementById('slideshow-title').value = image.title || '';
    document.getElementById('slideshow-order').value = image.order;
    document.getElementById('slideshow-active').checked = image.active === 1;
    document.getElementById('slideshow-image').value = ''; // Don't pre-fill file input
  } catch (error) {
    console.error('Error loading slideshow image:', error);
    alert(error.message);
  }
}

async function deleteSlideshowImage(id) {
  if (!confirm('Are you sure you want to delete this image?')) {
    return;
  }

  try {
    const res = await fetch(`/api/admin/slideshow.php?id=${id}&_method=DELETE`, {
      method: 'POST'
    });

    if (!res.ok) {
      throw new Error('Failed to delete image');
    }

    loadSlideshowImages();
  } catch (error) {
    console.error('Error deleting slideshow image:', error);
    alert(error.message);
  }
}

