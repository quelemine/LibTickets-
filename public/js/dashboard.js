// Customer Dashboard JavaScript

let currentUser = null;

// Check authentication on page load
async function checkAuth() {
  try {
    const res = await fetch('/api/session');
    const data = await res.json();

    if (!data.user || data.user.role !== 'customer') {
      window.location.href = 'index.html';
      return;
    }

    currentUser = data.user;
    loadUserInfo();
    loadDashboardData();
  } catch (error) {
    console.error('Auth check failed:', error);
    window.location.href = 'index.html';
  }
}

// Load user information
function loadUserInfo() {
  document.getElementById('user-name').textContent = currentUser.full_name || currentUser.email;
  document.getElementById('user-email').textContent = currentUser.email;
  document.getElementById('welcome-name').textContent = currentUser.full_name || currentUser.email;

  // Set initials
  const initials = (currentUser.full_name || currentUser.email).split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  document.getElementById('user-initials').textContent = initials;
  document.getElementById('profile-initials-large').textContent = initials;

  // Set profile picture if available
  if (currentUser.profile_picture) {
    document.getElementById('user-avatar').innerHTML = `<img src="${currentUser.profile_picture}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
    document.getElementById('profile-avatar-large').innerHTML = `<img src="${currentUser.profile_picture}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`;
  }

  // Load 2FA status
  loadTwoFactorStatus();
}

// Load dashboard data
async function loadDashboardData() {
  try {
    await Promise.all([
      loadDashboardStats(),
      loadCategories(),
      loadCities()
    ]);
  } catch (error) {
    console.error('Error loading dashboard data:', error);
  }
}

// Load dashboard statistics
async function loadDashboardStats() {
  try {
    const res = await fetch('/api/customer.php/dashboard-stats');
    const data = await res.json();

    document.getElementById('stat-tickets').textContent = data.totalTickets;
    document.getElementById('stat-bookings').textContent = data.totalBookings;
    document.getElementById('stat-upcoming').textContent = data.upcomingEvents.length;

    // Update notification badge
    if (data.unreadNotifications > 0) {
      document.getElementById('notification-badge').textContent = data.unreadNotifications;
    }

    // Load upcoming events
    const upcomingEventsList = document.getElementById('upcoming-events-list');
    if (data.upcomingEvents.length > 0) {
      upcomingEventsList.innerHTML = data.upcomingEvents.map(event => `
        <div class="event-item">
          <div class="event-title">${event.title}</div>
          <div class="event-date">${new Date(event.event_date).toLocaleDateString()} ${event.event_time || ''}</div>
        </div>
      `).join('');
    } else {
      upcomingEventsList.innerHTML = '<p class="no-data">No upcoming events</p>';
    }

    // Load recent bookings
    const recentBookingsList = document.getElementById('recent-bookings-list');
    if (data.recentBookings.length > 0) {
      recentBookingsList.innerHTML = data.recentBookings.map(booking => `
        <div class="booking-item">
          <div class="booking-title">${booking.event_title}</div>
          <div class="booking-date">${booking.booking_reference} - ${booking.currency} ${booking.total_amount}</div>
        </div>
      `).join('');
    } else {
      recentBookingsList.innerHTML = '<p class="no-data">No recent bookings</p>';
    }
  } catch (error) {
    console.error('Error loading dashboard stats:', error);
  }
}

// Load categories for filter
async function loadCategories() {
  try {
    const res = await fetch('/api/categories');
    const categories = await res.json();

    const categoryFilter = document.getElementById('category-filter');
    categoryFilter.innerHTML = '<option value="">All Categories</option>' +
      categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
  } catch (error) {
    console.error('Error loading categories:', error);
  }
}

// Load cities for filter
async function loadCities() {
  try {
    const res = await fetch('/api/cities');
    const cities = await res.json();

    const locationFilter = document.getElementById('location-filter');
    locationFilter.innerHTML = '<option value="">All Locations</option>' +
      cities.map(city => `<option value="${city.id}">${city.name}</option>`).join('');
  } catch (error) {
    console.error('Error loading cities:', error);
  }
}

// Show section
function showSection(section) {
  console.log('Showing section:', section);

  // Hide all sections
  document.querySelectorAll('.content-section').forEach(s => {
    s.classList.remove('active');
    s.classList.add('hidden');
  });
  document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

  // Show selected section
  const targetSection = document.getElementById(`section-${section}`);
  if (targetSection) {
    targetSection.classList.remove('hidden');
    targetSection.classList.add('active');
    console.log('Section found and activated:', section);
  } else {
    console.error('Section not found:', section);
  }

  // Update nav link
  const activeLink = document.querySelector(`.nav-link[onclick*="${section}"]`);
  if (activeLink) {
    activeLink.classList.add('active');
  }

  // Update page title
  const titles = {
    'overview': 'Dashboard',
    'discover': 'Discover Events',
    'tickets': 'My Tickets',
    'bookings': 'My Bookings',
    'profile': 'My Profile',
    'notifications': 'Notifications',
    'support': 'Help & Support'
  };
  document.getElementById('page-title').textContent = titles[section] || 'Dashboard';

  // Load section data
  if (section === 'discover') {
    loadEvents();
  } else if (section === 'tickets') {
    loadTickets();
  } else if (section === 'bookings') {
    loadBookings();
  } else if (section === 'profile') {
    loadProfile();
  } else if (section === 'notifications') {
    loadNotifications();
  }
}

// Load events for discover section
async function loadEvents() {
  try {
    const res = await fetch('/api/events.php?status=published');
    const events = await res.json();

    const eventsGrid = document.getElementById('events-grid');
    if (events.length > 0) {
      eventsGrid.innerHTML = events.map(event => `
        <div class="event-card">
          <img src="${event.image_url || '/images/default-event.jpg'}" alt="${event.title}" class="event-card-image">
          <div class="event-card-content">
            <div class="event-card-title">${event.title}</div>
            <div class="event-card-details">
              ${new Date(event.event_date).toLocaleDateString()} ${event.event_time || ''}
            </div>
            <button class="btn-book" onclick="bookEvent(${event.id})">Book Tickets</button>
          </div>
        </div>
      `).join('');
    } else {
      eventsGrid.innerHTML = '<p class="no-data">No events available</p>';
    }
  } catch (error) {
    console.error('Error loading events:', error);
  }
}

// Filter events
async function filterEvents() {
  const searchTerm = document.getElementById('event-search').value;
  const categoryId = document.getElementById('category-filter').value;
  const locationId = document.getElementById('location-filter').value;
  const dateFilter = document.getElementById('date-filter').value;

  try {
    let url = '/api/events.php?status=published';
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (categoryId) url += `&category=${categoryId}`;
    if (locationId) url += `&location=${locationId}`;
    if (dateFilter) url += `&date=${dateFilter}`;

    const res = await fetch(url);
    const events = await res.json();

    const eventsGrid = document.getElementById('events-grid');
    if (events.length > 0) {
      eventsGrid.innerHTML = events.map(event => `
        <div class="event-card">
          <img src="${event.image_url || '/images/default-event.jpg'}" alt="${event.title}" class="event-card-image">
          <div class="event-card-content">
            <div class="event-card-title">${event.title}</div>
            <div class="event-card-details">
              ${new Date(event.event_date).toLocaleDateString()} ${event.event_time || ''}
            </div>
            <button class="btn-book" onclick="bookEvent(${event.id})">Book Tickets</button>
          </div>
        </div>
      `).join('');
    } else {
      eventsGrid.innerHTML = '<p class="no-data">No events found</p>';
    }
  } catch (error) {
    console.error('Error filtering events:', error);
  }
}

// Book event
function bookEvent(eventId) {
  // Store event ID in session storage and redirect to booking page
  sessionStorage.setItem('selectedEventId', eventId);
  window.location.href = 'index.html';
}

// Load customer tickets
async function loadTickets() {
  try {
    const res = await fetch('/api/customer.php/tickets', {
      credentials: 'include'
    });
    const tickets = await res.json();

    const ticketsList = document.getElementById('tickets-list');
    if (tickets.length > 0) {
      ticketsList.innerHTML = tickets.map(ticket => `
        <div class="ticket-item">
          <div class="ticket-header">
            <div class="ticket-title">${ticket.event_title}</div>
            <span class="ticket-status ${ticket.status}">${ticket.status}</span>
          </div>
          <div class="ticket-body">
            <div class="ticket-detail">
              <strong>Ticket:</strong> ${ticket.ticket_name}
            </div>
            <div class="ticket-detail">
              <strong>Ticket Code:</strong> ${ticket.ticket_code}
            </div>
            <div class="ticket-detail">
              <strong>Date:</strong> ${new Date(ticket.event_date).toLocaleDateString()} ${ticket.event_time || ''}
            </div>
            <div class="ticket-detail">
              <strong>Venue:</strong> ${ticket.venue_name}
            </div>
            ${ticket.seat_number ? `<div class="ticket-detail"><strong>Seat:</strong> ${ticket.seat_number}</div>` : ''}
          </div>
          <div class="ticket-actions">
            <button class="btn-book" onclick="viewTicketFull(${ticket.id})">View Full Ticket</button>
            <button class="btn-book" onclick="printTicket(${ticket.id})">Print Ticket</button>
          </div>
        </div>
      `).join('');
    } else {
      ticketsList.innerHTML = '<p class="no-data">No tickets yet. <a href="#" onclick="showSection(\'discover\')">Browse events</a> to purchase tickets.</p>';
    }
  } catch (error) {
    console.error('Error loading tickets:', error);
    const ticketsList = document.getElementById('tickets-list');
    ticketsList.innerHTML = '<p class="no-data">Error loading tickets. Please try again.</p>';
  }
}

// View full ticket in new tab
function viewTicketFull(ticketId) {
  window.open(`/api/tickets.php/${ticketId}/html`, '_blank');
}

// Print ticket
function printTicket(ticketId) {
  const ticketWindow = window.open(`/api/tickets.php/${ticketId}/html`, '_blank');
  ticketWindow.onload = function() {
    ticketWindow.print();
  };
}

// View ticket QR code
async function viewTicketQR(ticketId) {
  try {
    const res = await fetch(`/api/tickets.php/${ticketId}/qr`);
    const data = await res.json();

    // Show QR code in modal
    const qrModal = document.createElement('div');
    qrModal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    `;
    qrModal.innerHTML = `
      <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
        <h3 style="color: #002059; margin-bottom: 1rem;">Ticket QR Code</h3>
        <img src="${data.qr_code}" alt="QR Code" style="max-width: 100%; margin-bottom: 1rem;">
        <p style="color: #666; margin-bottom: 1rem;">Ticket: ${data.ticket.ticket_code}</p>
        <button onclick="this.parentElement.parentElement.remove()" style="padding: 0.5rem 1rem; background: #002059; color: white; border: none; border-radius: 6px; cursor: pointer;">Close</button>
      </div>
    `;
    document.body.appendChild(qrModal);
    qrModal.onclick = (e) => {
      if (e.target === qrModal) qrModal.remove();
    };
  } catch (error) {
    console.error('Error loading QR code:', error);
    alert('Failed to load QR code');
  }
}

// Load customer bookings
async function loadBookings() {
  try {
    const res = await fetch('/api/customer.php/bookings');
    const bookings = await res.json();

    const bookingsList = document.getElementById('bookings-list');
    if (bookings.length > 0) {
      bookingsList.innerHTML = bookings.map(booking => `
        <div class="booking-item">
          <div class="booking-title">${booking.event_title}</div>
          <div class="booking-date">Reference: ${booking.booking_reference}</div>
          <div class="booking-date">Date: ${new Date(booking.event_date).toLocaleDateString()} ${booking.event_time || ''}</div>
          <div class="booking-date">Venue: ${booking.venue_name}, ${booking.city_name || ''}</div>
          <div class="booking-date">Total: ${booking.currency} ${booking.total_amount}</div>
          <span class="ticket-status ${booking.status}">${booking.status}</span>
        </div>
      `).join('');
    } else {
      bookingsList.innerHTML = '<p class="no-data">No bookings yet</p>';
    }
  } catch (error) {
    console.error('Error loading bookings:', error);
  }
}

// Load profile
async function loadProfile() {
  try {
    const res = await fetch('/api/customer.php/profile');
    const profile = await res.json();

    document.getElementById('profile-full-name').value = profile.full_name || '';
    document.getElementById('profile-email').value = profile.email || '';
    document.getElementById('profile-phone').value = profile.phone || '';
  } catch (error) {
    console.error('Error loading profile:', error);
  }
}

// Save profile
document.getElementById('profile-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const fullName = document.getElementById('profile-full-name').value;
  const email = document.getElementById('profile-email').value;
  const phone = document.getElementById('profile-phone').value;

  try {
    const res = await fetch('/api/customer.php/profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ full_name: fullName, email: email, phone: phone })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to update profile');
    }

    alert('Profile updated successfully!');
    loadUserInfo();
  } catch (error) {
    console.error('Error updating profile:', error);
    alert(error.message);
  }
});

// Change password
document.getElementById('password-form').addEventListener('submit', async (e) => {
  e.preventDefault();

  const currentPassword = document.getElementById('current-password').value;
  const newPassword = document.getElementById('new-password').value;
  const confirmPassword = document.getElementById('confirm-password').value;

  if (newPassword !== confirmPassword) {
    alert('Passwords do not match');
    return;
  }

  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters');
    return;
  }

  try {
    const res = await fetch('/api/customer.php/change-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
      })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to change password');
    }

    alert('Password changed successfully!');
    document.getElementById('password-form').reset();
  } catch (error) {
    console.error('Error changing password:', error);
    alert(error.message);
  }
});

// Two-Factor Authentication
async function loadTwoFactorStatus() {
  try {
    const res = await fetch('/api/user.php/2fa-status');
    const data = await res.json();

    const statusDiv = document.getElementById('two-factor-status');
    const statusText = document.getElementById('two-factor-status-text');
    const enableBtn = document.getElementById('btn-enable-2fa');
    const disableBtn = document.getElementById('btn-disable-2fa');

    if (data.two_factor_enabled) {
      statusDiv.className = 'two-factor-status enabled';
      statusText.textContent = 'Two-factor authentication is currently enabled';
      enableBtn.style.display = 'none';
      disableBtn.style.display = 'inline-block';
    } else {
      statusDiv.className = 'two-factor-status disabled';
      statusText.textContent = 'Two-factor authentication is currently disabled';
      enableBtn.style.display = 'inline-block';
      disableBtn.style.display = 'none';
    }
  } catch (error) {
    console.error('Error loading 2FA status:', error);
  }
}

async function enableTwoFactor() {
  try {
    const res = await fetch('/api/enable-2fa', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'include'
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to send verification code');
    }

    alert(`Verification code sent to ${data.email}. Check your email (or console in development).`);
    document.getElementById('two-factor-verify').style.display = 'block';
    document.getElementById('two-factor-actions').style.display = 'none';
  } catch (error) {
    console.error('Error enabling 2FA:', error);
    alert(error.message);
  }
}

async function confirmTwoFactor() {
  const code = document.getElementById('two-factor-code').value;

  if (!code || code.length !== 6) {
    alert('Please enter a valid 6-digit code');
    return;
  }

  try {
    const res = await fetch('/api/confirm-2fa', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify({ code })
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to verify code');
    }

    alert('Two-factor authentication enabled successfully!');
    document.getElementById('two-factor-verify').style.display = 'none';
    document.getElementById('two-factor-actions').style.display = 'flex';
    document.getElementById('two-factor-code').value = '';
    loadTwoFactorStatus();
  } catch (error) {
    console.error('Error confirming 2FA:', error);
    alert(error.message);
  }
}

function cancelTwoFactor() {
  document.getElementById('two-factor-verify').style.display = 'none';
  document.getElementById('two-factor-actions').style.display = 'flex';
  document.getElementById('two-factor-code').value = '';
}

async function disableTwoFactor() {
  if (!confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')) {
    return;
  }

  try {
    const res = await fetch('/api/disable-2fa', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'include'
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to disable 2FA');
    }

    alert('Two-factor authentication disabled successfully!');
    loadTwoFactorStatus();
  } catch (error) {
    console.error('Error disabling 2FA:', error);
    alert(error.message);
  }
}

// Upload profile picture
async function uploadProfilePicture(event) {
  const file = event.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  try {
    const res = await fetch('/api/customer.php/upload-profile-picture', {
      method: 'POST',
      body: formData
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || 'Failed to upload profile picture');
    }

    alert('Profile picture updated successfully!');
    loadUserInfo();
  } catch (error) {
    console.error('Error uploading profile picture:', error);
    alert(error.message);
  }
}

// Load notifications
async function loadNotifications() {
  try {
    const res = await fetch('/api/customer.php/notifications');
    const notifications = await res.json();

    const notificationsList = document.getElementById('notifications-list');
    if (notifications.length > 0) {
      notificationsList.innerHTML = notifications.map(notification => `
        <div class="notification-item ${notification.is_read ? '' : 'unread'}">
          <div class="notification-title">${notification.title}</div>
          <div class="notification-message">${notification.message}</div>
          <div class="notification-time">${new Date(notification.created_at).toLocaleString()}</div>
          ${!notification.is_read ? `<button onclick="markAsRead(${notification.id})" style="margin-top: 0.5rem; padding: 0.25rem 0.5rem; background: #002059; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Mark as Read</button>` : ''}
        </div>
      `).join('');
    } else {
      notificationsList.innerHTML = '<p class="no-data">No notifications</p>';
    }
  } catch (error) {
    console.error('Error loading notifications:', error);
  }
}

// Mark notification as read
async function markAsRead(notificationId) {
  try {
    const res = await fetch(`/api/customer.php/notifications/${notificationId}/read`, {
      method: 'PUT'
    });

    if (!res.ok) {
      throw new Error('Failed to mark as read');
    }

    loadNotifications();
    loadDashboardStats(); // Update badge
  } catch (error) {
    console.error('Error marking notification as read:', error);
  }
}

// Mark all notifications as read
async function markAllAsRead() {
  try {
    const res = await fetch('/api/customer.php/notifications/read-all', {
      method: 'PUT'
    });

    if (!res.ok) {
      throw new Error('Failed to mark all as read');
    }

    loadNotifications();
    loadDashboardStats(); // Update badge
  } catch (error) {
    console.error('Error marking all as read:', error);
  }
}

// Logout
async function logout() {
  try {
    await fetch('/api/logout', { method: 'POST' });
    window.location.href = 'index.html';
  } catch (error) {
    console.error('Logout error:', error);
  }
}

// Initialize
checkAuth();

