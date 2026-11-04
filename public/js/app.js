// LibTickets Frontend Application

// Configuration
const API_BASE_URL = '';
const USE_PHP_EXTENSION = true; // InfinityFree requires .php extension

// Helper to build API URLs
function apiUrl(endpoint) {
  if (USE_PHP_EXTENSION && !endpoint.endsWith('.php')) {
    return `${API_BASE_URL}${endpoint}.php`;
  }
  return `${API_BASE_URL}${endpoint}`;
}

// State
let currentEvent = null;
let selectedTickets = {};
let selectedSeats = [];
let exchangeRate = 200; // Default demonstration rate
let currentUser = null;

// Initialize application
document.addEventListener("DOMContentLoaded", () => {
  // Check URL hash for login modal immediately
  if (window.location.hash === '#login') {
    setTimeout(() => showLoginModal(), 100);
  }

  // Check URL parameter for returning to seat selection
  const urlParams = new URLSearchParams(window.location.search);
  const stage = urlParams.get('stage');

  if (stage === 'seat-selection') {
    // Restore checkout data if available
    const checkoutData = sessionStorage.getItem('checkoutData');
    if (checkoutData) {
      const data = JSON.parse(checkoutData);
      // Store for later when they proceed to checkout
      sessionStorage.setItem('savedCheckoutData', JSON.stringify(data));
    }

    // Restore selected tickets if available
    const savedTickets = sessionStorage.getItem('selectedTickets');
    if (savedTickets) {
      selectedTickets = JSON.parse(savedTickets);
    }

    // Restore selected event if available
    const savedEvent = sessionStorage.getItem('currentEvent');
    if (savedEvent) {
      currentEvent = JSON.parse(savedEvent);
      // Show seat selection
      setTimeout(() => {
        showSeatSelection();
      }, 1000);
    }
  }

  // Load data asynchronously without blocking
  loadInitialData();
  checkSession();
  loadSlideshowImages();
});

// Load initial data (parallel for faster loading)
async function loadInitialData() {
  try {
    // Load all data in parallel for faster initial render
    const [categoriesRes, citiesRes, countiesRes, rateRes] = await Promise.all([
      fetch(apiUrl('/api/categories')),
      fetch(apiUrl('/api/cities')),
      fetch(apiUrl('/api/counties')),
      fetch(apiUrl('/api/exchange-rate'))
    ]);

    const [categories, cities, counties, rateData] = await Promise.all([
      categoriesRes.json(),
      citiesRes.json(),
      countiesRes.json(),
      rateRes.json()
    ]);

    populateCategories(categories);
    populateCities(cities);
    populateCounties(counties);

    if (rateData.rate) {
      exchangeRate = rateData.rate;
    }

    // Load events and featured events
    loadEvents();
    loadFeaturedEvents();
  } catch (error) {
    console.error("Error loading initial data:", error);
  }
}

// Populate categories
function populateCategories(categories) {
  const categoryGrid = document.getElementById("category-grid");
  const categorySelect = document.getElementById("category-select");
  const filterCategory = document.getElementById("filter-category");

  // Return early if elements don't exist on this page
  if (!categoryGrid && !categorySelect && !filterCategory) {
    return;
  }

  const icons = {
    Concerts: "🎵",
    Sports: "⚽",
    Conferences: "🎤",
    Comedy: "😂",
    Festivals: "🎉",
    "Church Events": "⛪",
    Other: "📅",
  };

  if (categoryGrid) {
    categoryGrid.innerHTML = categories
      .map(
        (cat) => `
      <div class="category-card" onclick="filterByCategory('${cat.name}')">
        <div class="icon">${icons[cat.name] || "📅"}</div>
        <div>${cat.name}</div>
      </div>
    `,
      )
      .join("");
  }

  const options =
    '<option value="">All Categories</option>' +
    categories
      .map((cat) => `<option value="${cat.name}">${cat.name}</option>`)
      .join("");

  if (categorySelect) {
    categorySelect.innerHTML = options;
  }
  
  if (filterCategory) {
    filterCategory.innerHTML = options;
  }
}

// Populate cities
function populateCities(cities) {
  const citySelect = document.getElementById("city-select");
  const filterCity = document.getElementById("filter-city");

  // Return early if elements don't exist on this page
  if (!citySelect && !filterCity) {
    return;
  }

  const options =
    '<option value="">All Cities</option>' +
    cities
      .map((city) => `<option value="${city.name}">${city.name}</option>`)
      .join("");

  if (citySelect) {
    citySelect.innerHTML = options;
  }
  
  if (filterCity) {
    filterCity.innerHTML = options;
  }
}

// Populate counties
function populateCounties(counties) {
  const filterCounty = document.getElementById("filter-county");

  // Return early if element doesn't exist on this page
  if (!filterCounty) {
    return;
  }

  const options =
    '<option value="">All Counties</option>' +
    counties
      .map((county) => `<option value="${county.name}">${county.name}</option>`)
      .join("");

  filterCounty.innerHTML = options;
}

// Load events
async function loadEvents(filters = {}) {
  try {
    const queryParams = new URLSearchParams(filters);
    const res = await fetch(`${API_BASE_URL}/api/events.php?${queryParams}`);
    const events = await res.json();
    displayEvents(events);
  } catch (error) {
    console.error("Error loading events:", error);
  }
}

// Load events by category (for category pages)
async function loadEventsByCategory(categoryName) {
  try {
    // Load initial data first
    const citiesRes = await fetch(`${API_BASE_URL}/api/cities.php`);
    const cities = await citiesRes.json();
    populateCities(cities);

    const countiesRes = await fetch(`${API_BASE_URL}/api/counties.php`);
    const counties = await countiesRes.json();
    populateCounties(counties);

    // Load events filtered by category
    loadEvents({ category: categoryName });
  } catch (error) {
    console.error("Error loading events by category:", error);
  }
}

// Display events
function displayEvents(events) {
  const eventsGrid = document.getElementById("events-grid");

  // Return early if element doesn't exist on this page
  if (!eventsGrid) {
    return;
  }

  if (events.length === 0) {
    eventsGrid.innerHTML =
      '<p class="text-center">No events found matching your criteria.</p>';
    return;
  }

  eventsGrid.innerHTML = events
    .map((event) => {
      const date = new Date(event.event_date);
      const month = date.toLocaleDateString("en-US", { month: "short" });
      const day = date.getDate();
      const minPrice = event.min_price || 0;

      return `
      <div class="event-card" onclick="showEventDetails(${event.id})">
        <div class="event-image">
          ${event.image_url ? `<img src="${event.image_url}" alt="${event.title}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ""}
          <div class="event-date-badge">
            <div class="month">${month}</div>
            <div class="day">${day}</div>
          </div>
          <div class="event-placeholder" style="${event.image_url ? "display:none;" : ""}">🎫</div>
        </div>
        <div class="event-content">
          <div class="event-category">${event.category_name || "Event"}</div>
          <div class="event-title">${event.title}</div>
          <div class="event-venue">${event.venue_name}, ${event.city_name}</div>
          <div class="event-footer">
            <div>
              <div class="event-price">${minPrice.toLocaleString()} LRD</div>
              <div class="event-price-label">Starting from</div>
            </div>
            <button class="event-book-btn">Get Tickets <span class="btn-arrow">→</span></button>
          </div>
        </div>
      </div>
    `;
    })
    .join("");
}

// Search events
function searchEvents() {
  const search = document.getElementById("search-input").value;
  const city = document.getElementById("city-select").value;
  const category = document.getElementById("category-select").value;

  loadEvents({ search, city, category });
}

// Apply filters
function applyFilters() {
  const city = document.getElementById("filter-city").value;
  const category = document.getElementById("filter-category").value;
  const county = document.getElementById("filter-county").value;

  loadEvents({ city, category, county });
}

// Filter by category
function filterByCategory(category) {
  document.getElementById("filter-category").value = category;
  applyFilters();
}

// Show event details
async function showEventDetails(eventId) {
  try {
    const currency = document.getElementById("currency-select").value || "LRD";
    const res = await fetch(`${API_BASE_URL}/api/events.php/${eventId}?currency=${currency}`);
    const event = await res.json();

    currentEvent = event;
    selectedTickets = {};

    // Hide main content, show details
    document.getElementById("app").classList.add("hidden");
    document.getElementById("event-details-view").classList.remove("hidden");

    // Display event header
    const eventHeader = document.getElementById("event-header");
    const date = new Date(event.event_date);
    const formattedDate = date.toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });

    eventHeader.innerHTML = `
      <h1>${event.title}</h1>
      <div class="event-meta">
        <div class="event-meta-item">📅 ${formattedDate}</div>
        <div class="event-meta-item">⏰ ${event.event_time || "TBA"}</div>
        <div class="event-meta-item">📍 ${event.venue_name}, ${event.city_name}</div>
        <div class="event-meta-item">🎭 ${event.category_name}</div>
      </div>
      <p>${event.description || ""}</p>
      <p style="margin-top: 1rem; color: var(--gray-600);">Organizer: ${event.organizer || "N/A"}</p>
    `;

    // Display ticket types
    displayTicketTypes(event.ticket_types);
  } catch (error) {
    console.error("Error loading event details:", error);
    alert("Error loading event details");
  }
}

// Display ticket types
function displayTicketTypes(ticketTypes) {
  const ticketTypesContainer = document.getElementById("ticket-types");

  if (!ticketTypes || ticketTypes.length === 0) {
    ticketTypesContainer.innerHTML =
      "<p>No tickets available for this event.</p>";
    return;
  }

  ticketTypesContainer.innerHTML = `
    <h2>Select Tickets</h2>
    ${ticketTypes
      .map(
        (tt) => `
      <div class="ticket-type-card ${tt.sold_out ? 'sold-out' : ''}">
        <div class="ticket-type-info">
          <h3>${tt.name}</h3>
          <div class="ticket-type-price">${tt.price.toLocaleString()} ${tt.currency}</div>
          <div class="ticket-type-availability ${tt.sold_out ? 'sold-out-text' : ''}">${tt.sold_out ? 'Sold Out' : tt.available_quantity + ' available'}</div>
        </div>
        <div class="quantity-selector">
          <button onclick="updateQuantity(${tt.id}, -1)" ${tt.sold_out ? 'disabled' : ''}>-</button>
          <input type="number" id="qty-${tt.id}" value="0" min="0" max="${tt.available_quantity}" readonly ${tt.sold_out ? 'disabled' : ''}>
          <button onclick="updateQuantity(${tt.id}, 1)" ${tt.sold_out ? 'disabled' : ''}>+</button>
        </div>
      </div>
    `,
      )
      .join("")}
    <button class="btn-book" onclick="proceedToSeatSelection()">Select Seats</button>
  `;
}

// Update ticket quantity
function updateQuantity(ticketTypeId, change) {
  const input = document.getElementById(`qty-${ticketTypeId}`);
  const currentValue = parseInt(input.value);
  const ticketType = currentEvent.ticket_types.find(
    (tt) => tt.id === ticketTypeId,
  );

  const newValue = currentValue + change;
  if (newValue >= 0 && newValue <= ticketType.available_quantity) {
    input.value = newValue;

    if (newValue > 0) {
      selectedTickets[ticketTypeId] = {
        ticket_type_id: ticketTypeId,
        quantity: newValue,
        price: ticketType.price,
        currency: ticketType.currency,
      };
    } else {
      delete selectedTickets[ticketTypeId];
    }
  }
}

// Proceed to seat selection
async function proceedToSeatSelection() {
  const items = Object.values(selectedTickets);

  if (items.length === 0) {
    alert("Please select at least one ticket");
    return;
  }

  // Calculate total tickets needed
  const totalTickets = items.reduce((sum, item) => sum + item.quantity, 0);

  // Load seats for the event
  try {
    const res = await fetch(`${API_BASE_URL}/api/seats.php?event_id=${currentEvent.id}`);
    
    if (!res.ok) {
      // Seats API not available, skip to checkout
      proceedToCheckout();
      return;
    }
    
    const seats = await res.json();
    
    if (!Array.isArray(seats) || seats.length === 0) {
      // No seats available, skip to checkout
      proceedToCheckout();
      return;
    }

    // Hide event details, show seat selection
    document.getElementById("event-details-view").classList.add("hidden");
    document.getElementById("seat-selection-view").classList.remove("hidden");

    // Display seat selection
    displaySeatSelection(seats, totalTickets);
  } catch (error) {
    console.error("Error loading seats:", error);
    // Skip seat selection on error
    proceedToCheckout();
  }
}

// Display seat selection
function displaySeatSelection(seats, totalTickets) {
  const seatGrid = document.getElementById("seat-grid");
  const seatInfo = document.getElementById("seat-info");

  // Group seats by row
  const seatsByRow = {};
  seats.forEach((seat) => {
    if (!seatsByRow[seat.row_number]) {
      seatsByRow[seat.row_number] = [];
    }
    seatsByRow[seat.row_number].push(seat);
  });

  // Generate seat grid HTML
  let html = '<div class="seat-stage">STAGE</div>';
  html += '<div class="seat-container">';

  Object.keys(seatsByRow)
    .sort((a, b) => a - b)
    .forEach((row) => {
      html += `<div class="seat-row">`;
      html += `<span class="row-label">Row ${row}</span>`;
      seatsByRow[row]
        .sort((a, b) => a.seat_number - b.seat_number)
        .forEach((seat) => {
          const isBooked = seat.is_booked === 1;
          const seatId = `${seat.row_number}-${seat.seat_number}`;
          html += `
        <button
          class="seat ${isBooked ? "booked" : "available"}"
          data-seat-id="${seatId}"
          data-seat-db-id="${seat.id}"
          ${isBooked ? "disabled" : ""}
          onclick="toggleSeatSelection('${seatId}')"
        >
          ${seat.seat_number}
        </button>
      `;
        });
      html += `</div>`;
    });

  html += "</div>";

  seatGrid.innerHTML = html;
  seatInfo.innerHTML = `Please select ${totalTickets} seat(s)`;

  // Reset selected seats
  selectedSeats = [];
}

// Toggle seat selection
function toggleSeatSelection(seatId) {
  const seatButton = document.querySelector(`[data-seat-id="${seatId}"]`);
  const index = selectedSeats.indexOf(seatId);

  if (index > -1) {
    // Deselect
    selectedSeats.splice(index, 1);
    seatButton.classList.remove("selected");
  } else {
    // Select
    const totalTickets = Object.values(selectedTickets).reduce(
      (sum, item) => sum + item.quantity,
      0,
    );
    if (selectedSeats.length >= totalTickets) {
      alert(`You can only select ${totalTickets} seat(s)`);
      return;
    }
    selectedSeats.push(seatId);
    seatButton.classList.add("selected");
  }

  // Update seat info
  const seatInfo = document.getElementById("seat-info");
  const remaining =
    Object.values(selectedTickets).reduce(
      (sum, item) => sum + item.quantity,
      0,
    ) - selectedSeats.length;
  seatInfo.innerHTML =
    remaining > 0
      ? `Please select ${remaining} more seat(s)`
      : `${selectedSeats.length} seat(s) selected`;
}

// Proceed to checkout
function proceedToCheckout() {
  const items = Object.values(selectedTickets);

  if (items.length === 0) {
    alert("Please select at least one ticket");
    return;
  }

  // Prepare checkout data
  const checkoutData = {
    event: currentEvent,
    items: items.map(item => {
      const ticketType = currentEvent.ticket_types.find(tt => tt.id === item.ticket_type_id);
      return {
        ticket_type_id: item.ticket_type_id,
        ticket_type_name: ticketType.name,
        ticket_name: ticketType.name,
        quantity: item.quantity,
        price_per_ticket: item.price,
        currency: item.currency
      };
    }),
    seat_selections: selectedSeats
  };

  // Save to sessionStorage
  sessionStorage.setItem('checkoutData', JSON.stringify(checkoutData));
  sessionStorage.setItem('selectedTickets', JSON.stringify(selectedTickets));
  sessionStorage.setItem('currentEvent', JSON.stringify(currentEvent));

  // Redirect to checkout page
  window.location.href = `${API_BASE_URL}/checkout.html`;
}

// Update order summary
function updateOrderSummary() {
  const currency = document.getElementById("currency-select").value;
  const orderSummary = document.getElementById("order-summary");

  let total = 0;

  const itemsHtml = Object.values(selectedTickets)
    .map((item) => {
      const ticketType = currentEvent.ticket_types.find(
        (tt) => tt.id === item.ticket_type_id,
      );
      let price = item.price;

      // Convert currency if needed
      if (item.currency !== currency) {
        if (item.currency === "USD" && currency === "LRD") {
          price = price * exchangeRate;
        } else if (item.currency === "LRD" && currency === "USD") {
          price = price / exchangeRate;
        }
      }

      const itemTotal = price * item.quantity;
      total += itemTotal;

      return `
      <div class="order-item">
        <span>${ticketType.name} x ${item.quantity}</span>
        <span>${itemTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span>
      </div>
    `;
    })
    .join("");

  orderSummary.innerHTML = `
    <h3>Order Summary</h3>
    ${itemsHtml}
    <div class="order-total">
      <span>Total</span>
      <span>${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span>
    </div>
  `;
}

// Submit booking
async function submitBooking(event) {
  event.preventDefault();

  const customerName = document.getElementById("customer-name").value;
  const customerEmail = document.getElementById("customer-email").value;
  const customerPhone = document.getElementById("customer-phone").value;
  const deliveryMethod = document.getElementById("delivery-method").value;
  const currency = document.getElementById("currency-select").value;

  const items = Object.values(selectedTickets);

  try {
    const res = await fetch(`${API_BASE_URL}/api/bookings.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        customer_name: customerName,
        customer_email: customerEmail,
        customer_phone: customerPhone,
        delivery_method: deliveryMethod,
        items: items,
        currency: currency,
        seat_selections: selectedSeats,
      }),
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || "Booking failed");
    }

    // Store booking details for confirmation page
    sessionStorage.setItem('bookingConfirmation', JSON.stringify(data));

    // Redirect to confirmation page
    window.location.href = `${API_BASE_URL}/confirmation.html`;
  } catch (error) {
    console.error("Booking error:", error);
    document.getElementById("checkout-error").textContent = error.message;
    document.getElementById("checkout-error").classList.remove("hidden");
  }
}

// Show confirmation
async function showConfirmation(bookingData) {
  document.getElementById("checkout-view").classList.add("hidden");
  document.getElementById("confirmation-view").classList.remove("hidden");

  const bookingDetails = document.getElementById("booking-details");
  const { booking, items } = bookingData;

  bookingDetails.innerHTML = `
    <h3>Booking Details</h3>
    <div class="booking-detail-item">
      <span>Booking Reference</span>
      <span><strong>${booking.booking_reference}</strong></span>
    </div>
    <div class="booking-detail-item">
      <span>Customer</span>
      <span>${booking.customer_name}</span>
    </div>
    <div class="booking-detail-item">
      <span>Email</span>
      <span>${booking.customer_email}</span>
    </div>
    <div class="booking-detail-item">
      <span>Total Paid</span>
      <span><strong>${booking.total_amount.toLocaleString()} ${booking.currency}</strong></span>
    </div>
    <div style="margin-top: 1rem;">
      <h4>Tickets</h4>
      ${items
        .map(
          (item) => `
        <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-200);">
          <div>${item.event_title}</div>
          <div>${item.venue_name} - ${new Date(item.event_date).toLocaleDateString()}</div>
          <div>${item.ticket_name} x ${item.quantity}</div>
        </div>
      `,
        )
        .join("")}
    </div>
  `;

  // Load QR codes for each ticket with new design
  const qrSection = document.getElementById("qr-section");
  qrSection.innerHTML = "<h3>Your Tickets</h3>";
  qrSection.innerHTML += '<div class="ticket-container">';

  for (const item of items) {
    for (const ticket of item.tickets) {
      try {
        const qrRes = await fetch(`${API_BASE_URL}/api/tickets.php/${ticket.id}/qr`);
        const qrData = await qrRes.json();

        const eventDate = new Date(item.event_date);
        const formattedDate = eventDate.toLocaleDateString("en-US", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        });

        const seatNumber = ticket.seat_number || "N/A";
        const ticketSection = item.ticket_name || "GENERAL";

        qrSection.innerHTML += `
          <div class="ticket">
            <!-- White information panel (70%) -->
            <div class="ticket-info-panel">
              <div class="ticket-header">
                <h2 class="ticket-event-title">${item.event_title}</h2>
                <div class="ticket-venue-date">
                  <span>📍</span>
                  <span>${item.venue_name}, ${item.city_name || ""}</span>
                </div>
                <div class="ticket-venue-date">
                  <span>📅</span>
                  <span>${formattedDate}</span>
                </div>
              </div>

              <div class="ticket-content">
                <div class="ticket-details-left">
                  <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Ticket No.</span>
                    <span class="ticket-detail-value">${ticket.ticket_code}</span>
                  </div>
                  <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Block</span>
                    <span class="ticket-detail-value">${ticket.block || "N/A"}</span>
                  </div>
                  <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Seat</span>
                    <span class="ticket-detail-value">${seatNumber}</span>
                  </div>
                  <div class="ticket-detail-row">
                    <span class="ticket-detail-label">Entrance</span>
                    <span class="ticket-detail-value">${ticket.entrance || "Gate 1"}</span>
                  </div>
                </div>

                <div class="ticket-qr-section">
                  <div class="ticket-qr-code">
                    ${qrData.qr_code ? `<img src="${qrData.qr_code}" alt="QR Code">` : ''}
                  </div>
                  <div class="ticket-qr-label">ONE TIME VALID</div>
                </div>
              </div>

              <div class="ticket-time-section">
                <div class="ticket-time-row">
                  <span class="ticket-time-label">Start Time:</span>
                  <span class="ticket-time-value">${item.event_time || "TBA"}</span>
                </div>
                <div class="ticket-time-row">
                  <span class="ticket-time-label">Gate Opens:</span>
                  <span class="ticket-time-value">1 hour before</span>
                </div>
              </div>

              <div class="ticket-holder-section">
                <div class="ticket-holder-label">Ticket Holder</div>
                <div class="ticket-holder-name">${booking.customer_name}</div>
              </div>

              <div class="ticket-footer">
                <div class="ticket-support">
                  Support: <span class="ticket-support-number">+231777683875</span>
                </div>
                <div class="ticket-branding">Powered by LibTickets</div>
              </div>
            </div>

            <!-- Event graphic section (25%) -->
            <div class="ticket-graphic-section">
              ${item.event_image_url && item.event_image_url !== 'uploads/default-event.jpg' ? 
                `<img src="${item.event_image_url}" alt="Event" class="ticket-event-graphic">` :
                `<div class="ticket-event-graphic" style="font-size: 4rem;">🎫</div>`
              }
              <div class="ticket-website">www.libtickets.com</div>
            </div>

            <!-- Red vertical strip (5%) -->
            <div class="ticket-strip">
              <div class="ticket-strip-label">${ticketSection}</div>
            </div>
          </div>
        `;
      } catch (error) {
        console.error("Error loading QR code:", error);
      }
    }
  }

  qrSection.innerHTML += '</div>';
}

// Navigation functions
function goBack() {
  document.getElementById("event-details-view").classList.add("hidden");
  document.getElementById("app").classList.remove("hidden");
  currentEvent = null;
  selectedTickets = {};
}

function goBackToEvent() {
  document.getElementById("checkout-view").classList.add("hidden");
  document.getElementById("seat-selection-view").classList.add("hidden");
  document.getElementById("event-details-view").classList.remove("hidden");
  selectedSeats = [];
}

function goHome() {
  document.getElementById("confirmation-view").classList.add("hidden");
  document.getElementById("app").classList.remove("hidden");
  currentEvent = null;
  selectedTickets = {};
  loadEvents();
}

// Menu toggle
function toggleMenu() {
  const nav = document.getElementById("main-nav");
  nav.classList.toggle("active");
}

// Authentication
function showLoginModal() {
  const signupModal = document.getElementById("signup-modal");
  const loginModal = document.getElementById("login-modal");
  
  // Check if modals exist on this page
  if (!loginModal) {
    console.warn("Login modal not found on this page");
    // Redirect to home page with login hash
    window.location.href = `${API_BASE_URL}/#login`;
    return;
  }
  
  if (signupModal) {
    signupModal.classList.add("hidden");
  }
  
  loginModal.classList.remove("hidden");
}

function hideLoginModal() {
  const loginModal = document.getElementById("login-modal");
  
  if (loginModal) {
    loginModal.classList.add("hidden");
  }
}

function togglePassword(inputId, button) {
  const input = document.getElementById(inputId);
  if (!input || !button) return;

  const isPassword = input.type === "password";

  input.type = isPassword ? "text" : "password";

  // Toggle icon between eye and eye-slash
  if (isPassword) {
    button.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <line x1="1" y1="1" x2="23" y2="23"></line>
      </svg>
    `;
  } else {
    button.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
    `;
  }
}

async function handleLogin(event) {
  event.preventDefault();

  const email = document.getElementById("login-email").value;
  const password = document.getElementById("login-password").value;

  try {
    const res = await fetch(`${API_BASE_URL}/api/login.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || "Login failed");
    }

    currentUser = data.user;
    hideLoginModal();
    updateAuthUI();

    // Redirect to admin page if user is admin
    if (currentUser.role === "admin") {
      window.location.href = `${API_BASE_URL}/admin.html`;
    } else {
      alert("Login successful!");
    }
  } catch (error) {
    alert(error.message);
  }
}

function showSignupModal() {
  const loginModal = document.getElementById("login-modal");
  const signupModal = document.getElementById("signup-modal");
  
  // Check if modals exist on this page
  if (!signupModal) {
    console.warn("Signup modal not found on this page");
    // Redirect to home page
    window.location.href = `${API_BASE_URL}/`;
    return;
  }
  
  if (loginModal) {
    loginModal.classList.add("hidden");
  }
  
  signupModal.classList.remove("hidden");
}

function hideSignupModal() {
  const signupModal = document.getElementById("signup-modal");
  
  if (signupModal) {
    signupModal.classList.add("hidden");
  }
}

async function handleSignup(event) {
  event.preventDefault();

  const name = document.getElementById("signup-name").value;
  const email = document.getElementById("signup-email").value;
  const password = document.getElementById("signup-password").value;
  const confirm = document.getElementById("signup-confirm").value;

  if (password !== confirm) {
    alert("Passwords do not match");
    return;
  }

  try {
    const res = await fetch(`${API_BASE_URL}/api/signup.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || "Signup failed");
    }

    hideSignupModal();
    alert("Account created successfully! Please log in.");
  } catch (error) {
    alert(error.message);
  }
}

async function checkSession() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/session.php`);
    const data = await res.json();

    if (data.user) {
      currentUser = data.user;
      updateAuthUI();
    }
  } catch (error) {
    console.error("Error checking session:", error);
  }
}

function updateAuthUI() {
  const adminLink = document.getElementById("admin-link");

  if (adminLink) {
    if (currentUser && currentUser.role === "admin") {
      adminLink.style.display = "block";
    } else {
      adminLink.style.display = "none";
    }
  }
}

// Slideshow functionality
let currentSlide = 0;
let slideshowInterval;

async function loadSlideshowImages() {
  try {
    const res = await fetch(`${API_BASE_URL}/api/slideshow.php`);
    const images = await res.json();

    const slideshowContainer = document.getElementById("hero-slideshow");
    if (!slideshowContainer) return;

    // Filter only active images
    const activeImages = images.filter((img) => img.active === 1);

    if (activeImages.length === 0) {
      // Fallback to gradient slides if no images
      slideshowContainer.innerHTML = `
        <div class="slide active" style="background: linear-gradient(135deg, #002059 0%, #003087 50%, #002059 100%);"></div>
        <div class="slide" style="background: linear-gradient(135deg, #c41e3a 0%, #8b0000 50%, #c41e3a 100%);"></div>
        <div class="slide" style="background: linear-gradient(135deg, #1a5f2a 0%, #0d3d1a 50%, #1a5f2a 100%);"></div>
      `;
    } else {
      // Load images from database
      slideshowContainer.innerHTML = activeImages
        .map(
          (img, index) => `
        <div class="slide ${index === 0 ? "active" : ""}" style="background-image: linear-gradient(rgba(0, 32, 91, 0.6), rgba(0, 32, 91, 0.6)), url('${API_BASE_URL}${img.image_url}');"></div>
      `,
        )
        .join("");
    }

    startSlideshow();
  } catch (error) {
    console.error("Error loading slideshow images:", error);
    // Fallback to gradient slides on error
    const slideshowContainer = document.getElementById("hero-slideshow");
    if (slideshowContainer) {
      slideshowContainer.innerHTML = `
        <div class="slide active" style="background: linear-gradient(135deg, #002059 0%, #003087 50%, #002059 100%);"></div>
        <div class="slide" style="background: linear-gradient(135deg, #c41e3a 0%, #8b0000 50%, #c41e3a 100%);"></div>
        <div class="slide" style="background: linear-gradient(135deg, #1a5f2a 0%, #0d3d1a 50%, #1a5f2a 100%);"></div>
      `;
      startSlideshow();
    }
  }
}

function startSlideshow() {
  const slides = document.querySelectorAll(".slide");
  if (slides.length === 0) return;

  // Start the slideshow
  slideshowInterval = setInterval(() => {
    slides[currentSlide].classList.remove("active");
    currentSlide = (currentSlide + 1) % slides.length;
    slides[currentSlide].classList.add("active");
  }, 5000); // Change slide every 5 seconds
}

// Featured Events Carousel
let currentCarouselSlide = 0;
let carouselInterval;
let featuredEvents = [];

async function loadFeaturedEvents() {
  try {
    // Check if featured events section exists on this page
    const featuredSection = document.querySelector(".featured-events");
    if (!featuredSection) {
      return; // Featured events not on this page
    }

    const res = await fetch(`${API_BASE_URL}/api/events.php?limit=5`);
    const events = await res.json();

    if (events.length === 0) {
      featuredSection.style.display = "none";
      return;
    }

    featuredEvents = events.slice(0, 5); // Take first 5 events
    renderCarousel();
    startCarouselAutoplay();
  } catch (error) {
    console.error("Error loading featured events:", error);
  }
}

function renderCarousel() {
  const carousel = document.getElementById("featured-carousel");
  const dotsContainer = document.getElementById("carousel-dots");

  if (!carousel || featuredEvents.length === 0) return;

  // Render slides
  carousel.innerHTML = featuredEvents
    .map((event) => {
      const date = new Date(event.event_date);
      const month = date.toLocaleDateString("en-US", { month: "short" });
      const day = date.getDate();
      const imageUrl = event.image_url || "/uploads/default-event.jpg";

      return `
      <div class="carousel-slide" onclick="showEventDetails(${event.id})" style="cursor: pointer;">
        <img src="${imageUrl}" alt="${event.title}" onerror="this.src='https://via.placeholder.com/1200x400/002059/ffffff?text=LibTickets'">
        <div class="carousel-content">
          <h3>${event.title}</h3>
          <p>${event.venue_name || "TBD"} • ${new Date(event.event_date).toLocaleDateString()}</p>
          <button class="event-book-btn" style="display: inline-flex;">View Details →</button>
        </div>
      </div>
    `;
    })
    .join("");

  // Render dots
  dotsContainer.innerHTML = featuredEvents
    .map(
      (_, index) => `
    <div class="carousel-dot ${index === 0 ? "active" : ""}" onclick="goToSlide(${index})"></div>
  `,
    )
    .join("");
}

function moveCarousel(direction) {
  if (featuredEvents.length === 0) return;

  currentCarouselSlide =
    (currentCarouselSlide + direction + featuredEvents.length) %
    featuredEvents.length;
  updateCarousel();
  resetCarouselAutoplay();
}

function goToSlide(index) {
  currentCarouselSlide = index;
  updateCarousel();
  resetCarouselAutoplay();
}

function updateCarousel() {
  const carousel = document.getElementById("featured-carousel");
  const dots = document.querySelectorAll(".carousel-dot");

  if (!carousel) return;

  carousel.style.transform = `translateX(-${currentCarouselSlide * 100}%)`;

  // Update dots
  dots.forEach((dot, index) => {
    dot.classList.toggle("active", index === currentCarouselSlide);
  });
}

function startCarouselAutoplay() {
  if (featuredEvents.length <= 1) return;

  carouselInterval = setInterval(() => {
    currentCarouselSlide = (currentCarouselSlide + 1) % featuredEvents.length;
    updateCarousel();
  }, 5000); // Change slide every 5 seconds
}

function resetCarouselAutoplay() {
  clearInterval(carouselInterval);
  startCarouselAutoplay();
}

// Dark Mode Toggle
function toggleDarkMode() {
  document.body.classList.toggle("dark-mode");
  localStorage.setItem(
    "darkMode",
    document.body.classList.contains("dark-mode"),
  );
}

// Load saved dark mode preference
function loadDarkModePreference() {
  if (localStorage.getItem("darkMode") === "true") {
    document.body.classList.add("dark-mode");
  }
}

// Load dark mode preference on page load
document.addEventListener("DOMContentLoaded", () => {
  loadDarkModePreference();
});

