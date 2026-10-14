/**
 * manager-store.js
 * AutoCare Central State Store mirroring MySQL database schema:
 * Users, Vehicles, ServiceCategories, SpareParts, ServiceRequests,
 * JobCards, RepairEstimates, JobCardParts, JobCardLabor, RepairTimeline,
 * Invoices, ChatMessages, Notices.
 * 
 * Automatically persists to localStorage ('autocare_manager_db').
 */

(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.AutoCareStore = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const STORAGE_KEY = 'autocare_manager_db';

  // Initial Seed Data directly modeled on database.sql
  const initialSeedData = {
    // 1. Users Table
    users: [
      { id: 1, username: 'admin', email: 'admin@autocare.com', phone: '+880 1711-000111', role: 'Admin', status: 'Active' },
      { id: 2, username: 'alex.manager', name: 'Alex Johnson', email: 'alex.j@autocare.com', phone: '+880 1711-222333', role: 'Manager', status: 'Active' },
      // Mechanics
      { id: 3, username: 'david.chui', name: 'David Chui', email: 'david.c@autocare.com', phone: '+880 1711-333444', role: 'Mechanic', specialty: 'Engine Specialist', experience: '8 Years', status: 'Available', workload: 20, avatar: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150' },
      { id: 4, username: 'sarah.jenkins', name: 'Sarah Jenkins', email: 'sarah.j@autocare.com', phone: '+880 1711-444555', role: 'Mechanic', specialty: 'Electrician', experience: '5 Years', status: 'Busy', workload: 90, avatar: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=150' },
      { id: 5, username: 'marc.lowe', name: 'Marc Lowe', email: 'marc.l@autocare.com', phone: '+880 1711-555666', role: 'Mechanic', specialty: 'Suspension Spec.', experience: '3 Years', status: 'Off Shift', workload: 0, avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150' },
      { id: 6, username: 'kamal.h', name: 'Kamal H.', email: 'kamal.h@autocare.com', phone: '+880 1711-666777', role: 'Mechanic', specialty: 'Transmission Expert', experience: '7 Years', status: 'Busy', workload: 65, avatar: 'https://i.pravatar.cc/100?img=12' },
      { id: 7, username: 'rafiq.m', name: 'Rafiq M.', email: 'rafiq.m@autocare.com', phone: '+880 1711-777888', role: 'Mechanic', specialty: 'Diagnostic Spec.', experience: '4 Years', status: 'Busy', workload: 90, avatar: 'https://i.pravatar.cc/100?img=33' },
      { id: 8, username: 'mike.davis', name: 'Mike Davis', email: 'mike.d@autocare.com', phone: '+880 1711-888999', role: 'Mechanic', specialty: 'Brake Specialist', experience: '6 Years', status: 'Busy', workload: 80, avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80' },
      // Vehicle Owners / Clients
      { id: 101, username: 'apex.logistics', name: 'Apex Logistics', contactPerson: 'Marcus Vance', email: 'ops@apexlogistics.com', phone: '(555) 123-4567', role: 'Owner', status: 'Active' },
      { id: 102, username: 'sarah.connors', name: 'Sarah Connor', email: 'sarah.c@gmail.com', phone: '(555) 345-6789', role: 'Owner', status: 'Active' },
      { id: 103, username: 'rahim.uddin', name: 'Rahim Uddin', email: 'rahim.u@gmail.com', phone: '+880 1819-112233', role: 'Owner', status: 'Active' },
      { id: 104, username: 'enterprise.log', name: 'Enterprise Logistics Co.', email: 'contact@enterpriselog.com', phone: '+880 1912-334455', role: 'Owner', status: 'Active' },
      { id: 105, username: 'nusrat.jahan', name: 'Nusrat Jahan', email: 'nusrat.j@hotmail.com', phone: '+880 1712-998877', role: 'Owner', status: 'Active' },
      { id: 106, username: 'acme.corp', name: 'Acme Logistics Corp', email: 'contact@acme.com', phone: '(555) 987-6543', role: 'Owner', status: 'Active' },
      { id: 107, username: 'global.exp', name: 'Global Express', email: 'billing@globalexp.net', phone: '(555) 456-7890', role: 'Owner', status: 'Active' },
      { id: 108, username: 'metro.trans', name: 'Metro Transport', email: 'accounts@metro.com', phone: '(555) 678-1234', role: 'Owner', status: 'Active' }
    ],

    // 2. Vehicles Table
    vehicles: [
      { id: 1, owner_id: 101, model: '2018 Ford Transit 350', registration_number: 'LND-294-K', vin: '1FTYR2XG2JKA*****' },
      { id: 2, owner_id: 102, model: '2021 Toyota Camry', registration_number: 'NYC-882-M', vin: '4T1B11HK5MU12345' },
      { id: 3, owner_id: 103, model: 'Toyota Corolla (2018)', registration_number: 'Dhaka-Metro-Ga-12-3456', vin: 'JT2BF22K1W0******' },
      { id: 4, owner_id: 104, model: 'Hino Dutro Truck', registration_number: 'Chattogram-Na-11-2233', vin: 'JH4DB7550SS******' },
      { id: 5, owner_id: 105, model: 'Honda Vezel (2020)', registration_number: 'Sylhet-Gha-15-9988', vin: 'RU3-1209384******' },
      { id: 6, owner_id: 106, model: 'Freightliner Cascadia', registration_number: 'TEX-901-TR', vin: '1FUJ...8902' },
      { id: 7, owner_id: 107, model: 'Kenworth T680', registration_number: 'CAL-442-KW', vin: '1XK...4419' },
      { id: 8, owner_id: 108, model: 'Volvo VNL 860', registration_number: 'FLA-108-VL', vin: '4V4...0021' },
      { id: 9, owner_id: 101, model: '2019 Ford F-150', registration_number: 'LND-551-F', vin: '1FTEW1E45KFA12984' }
    ],

    // 3. Service Categories
    categories: [
      { id: 1, name: 'Engine Diagnostics & Repair', description: 'Comprehensive diagnostics, tuning, sensor overhaul', base_rate: 1500.00 },
      { id: 2, name: 'Transmission Overhaul', description: 'Gearbox repair, fluid flush, torque converter replacement', base_rate: 3500.00 },
      { id: 3, name: 'Brake System Service', description: 'Brake pad replacement, rotor machining, caliper service', base_rate: 1200.00 },
      { id: 4, name: 'Electrical & Sensor Tuning', description: 'ECU mapping, wiring repair, alternator & battery check', base_rate: 1800.00 },
      { id: 5, name: 'Routine Periodic Maintenance', description: 'Engine oil, filter replacement, suspension lubrications', base_rate: 800.00 }
    ],

    // 4. Spare Parts Inventory
    spareParts: [
      { id: 1, name: 'Brake Pad Set - Front', part_number: 'BP-2049-F', price: 85.00, quantity_in_stock: 45, low_stock_threshold: 10 },
      { id: 2, name: 'Synthetic Motor Oil 5W-30', part_number: 'MO-5W30-SYN', price: 9.50, quantity_in_stock: 140, low_stock_threshold: 25 },
      { id: 3, name: 'Alternator Assembly - 130A', part_number: 'ALT-130-OEM', price: 345.00, quantity_in_stock: 4, low_stock_threshold: 5 },
      { id: 4, name: 'Transmission Overhaul Kit (OEM)', part_number: 'TR-KIT-889', price: 2800.00, quantity_in_stock: 6, low_stock_threshold: 3 },
      { id: 5, name: 'Exhaust Manifold (Cracked Replacement)', part_number: 'EXH-MAN-F150', price: 420.00, quantity_in_stock: 3, low_stock_threshold: 2 },
      { id: 6, name: 'Fuel Injector Nozzle Set', part_number: 'FI-NOZ-V6', price: 180.00, quantity_in_stock: 12, low_stock_threshold: 5 },
      { id: 7, name: 'Suspension Strut Assembly', part_number: 'SUS-STR-221', price: 220.00, quantity_in_stock: 8, low_stock_threshold: 4 }
    ],

    // 5. Service Requests (Bookings from Vehicle Owners)
    serviceRequests: [
      {
        id: 145,
        code: 'BRQ-2023-145',
        owner_id: 101,
        vehicle_id: 1,
        category_id: 2,
        requested_date: 'Oct 24, 09:00',
        priority: 'High',
        description: 'Driver reports harsh shifting between 2nd and 3rd gear. Occasional slipping when under heavy load. Check transmission fluid levels and perform diagnostic. Approved for initial teardown up to 4 hours.',
        status: 'Pending',
        created_at: '2023-10-23 16:45:00'
      },
      {
        id: 146,
        code: 'BRQ-2023-146',
        owner_id: 102,
        vehicle_id: 2,
        category_id: 5,
        requested_date: 'Oct 25, 14:30',
        priority: 'Normal',
        description: 'Scheduled standard 50k miles inspection. Oil change, cabin air filter replacement, wheel alignment, and check front brake pad wear.',
        status: 'Pending',
        created_at: '2023-10-24 09:15:00'
      },
      {
        id: 147,
        code: 'BRQ-2023-147',
        owner_id: 103,
        vehicle_id: 3,
        category_id: 1,
        requested_date: 'Oct 26, 11:00',
        priority: 'Normal',
        description: 'Engine check light illuminated with code P0304. Rough idling during cold start in morning.',
        status: 'Pending',
        created_at: '2023-10-24 11:20:00'
      },
      {
        id: 148,
        code: 'BRQ-2023-148',
        owner_id: 105,
        vehicle_id: 5,
        category_id: 4,
        requested_date: 'Oct 26, 15:00',
        priority: 'High',
        description: 'Battery drainage issue. Vehicle fails to crank after sitting for more than 24 hours. Suspected parasitic electrical draw.',
        status: 'Pending',
        created_at: '2023-10-24 14:00:00'
      }
    ],

    // 6. Job Cards (Managed by Manager, assigned to Mechanics)
    jobCards: [
      {
        id: 1045,
        code: 'JC-1045',
        work_order: '#WO-2049',
        request_id: 141,
        customer_name: 'Rahim Uddin',
        vehicle_details: 'Toyota Corolla (2018) • Dhaka-Metro-Ga-12-3456',
        vehicle_title: '2021 Toyota Camry',
        vin: '4T1B11HK5MU12345',
        manager_id: 2,
        mechanic_id: 6, // Kamal H.
        mechanic_name: 'Kamal H.',
        mechanic_initials: 'KH',
        priority: 'High',
        status: 'Repairing', // Maps to 'IN PROGRESS'
        kanban_stage: 'IN PROGRESS', // 'PENDING', 'IN PROGRESS', 'COMPLETED'
        progress_percentage: 65,
        estimated_cost: 12500.00,
        date_opened: 'Oct 24, 2023',
        delivery_date: 'Oct 28, 2023',
        service_text: 'Engine Diagnostic & Cylinder Misfire Repair'
      },
      {
        id: 1046,
        code: 'JC-1046',
        work_order: '#WO-2051',
        request_id: 142,
        customer_name: 'Enterprise Logistics Co.',
        vehicle_details: 'Hino Dutro Truck • Chattogram-Na-11-2233',
        vehicle_title: '2019 Ford F-150',
        vin: '1FTEW1E45KFA12984',
        manager_id: 2,
        mechanic_id: 8, // Mike Davis
        mechanic_name: 'Mike Davis',
        mechanic_initials: 'MD',
        priority: 'Standard',
        status: 'Diagnosis', // Maps to 'PENDING'
        kanban_stage: 'PENDING',
        progress_percentage: 30,
        estimated_cost: 45000.00,
        date_opened: 'Oct 25, 2023',
        delivery_date: 'Oct 30, 2023',
        service_text: 'Brake Pad Replacement & Transmission Overhaul'
      },
      {
        id: 1042,
        code: 'JC-1042',
        work_order: '#WO-2042',
        request_id: 140,
        customer_name: 'Nusrat Jahan',
        vehicle_details: 'Honda Vezel (2020) • Sylhet-Gha-15-9988',
        vehicle_title: 'Honda Vezel (2020)',
        vin: 'RU3-1209384******',
        manager_id: 2,
        mechanic_id: 7, // Rafiq M.
        mechanic_name: 'Rafiq M.',
        mechanic_initials: 'RM',
        priority: 'Normal',
        status: 'Testing', // Quality Control
        kanban_stage: 'IN PROGRESS',
        progress_percentage: 90,
        estimated_cost: 8200.00,
        date_opened: 'Oct 23, 2023',
        delivery_date: 'Oct 26, 2023',
        service_text: 'Electrical Sensor Tuning & Quality Control'
      },
      {
        id: 1040,
        code: 'JC-1040',
        work_order: '#WO-2038',
        request_id: 138,
        customer_name: 'Sarah Connor',
        vehicle_details: 'Toyota Camry • NYC-882-M',
        vehicle_title: '2021 Toyota Camry',
        vin: '4T1B11HK5MU12345',
        manager_id: 2,
        mechanic_id: 3, // David Chui
        mechanic_name: 'David Chui',
        mechanic_initials: 'DC',
        priority: 'High',
        status: 'Diagnosis',
        kanban_stage: 'PENDING',
        progress_percentage: 15,
        estimated_cost: 6500.00,
        date_opened: 'Oct 24, 2023',
        delivery_date: 'Oct 27, 2023',
        service_text: 'Brake Rotor Machining'
      }
    ],

    // 7. Repair Cost Estimates
    estimates: [
      {
        id: 1,
        code: 'EST-2023-001',
        job_card_id: 1042,
        job_card_code: 'JC-1042',
        job_card_title: 'Engine Overhaul',
        customer_name: 'Acme Corp Logistics',
        mechanic_name: 'John Doe',
        status: 'Draft', // Mechanic Draft / Sent / Send to Customer
        sent_date: '-',
        line_items: [
          { description: 'Diagnostic Labor', hours_or_qty: 3, unit_price: 150.00, total: 450.00 },
          { description: 'Transmission Kit (OEM)', hours_or_qty: 1, unit_price: 2800.00, total: 2800.00 }
        ],
        subtotal: 3250.00,
        tax_rate: 0.085,
        tax_amount: 276.25,
        total_estimated_cost: 3526.25
      },
      {
        id: 2,
        code: 'EST-2023-002',
        job_card_id: 1043,
        job_card_code: 'JC-1043',
        job_card_title: 'Suspension Replacement',
        customer_name: 'Global Freight Inc.',
        mechanic_name: 'David Chui',
        status: 'Sent',
        sent_date: 'Oct 12, 2023',
        line_items: [
          { description: 'Front Struts Pair', hours_or_qty: 2, unit_price: 440.00, total: 880.00 },
          { description: 'Labor (2.5 hrs)', hours_or_qty: 2.5, unit_price: 128.20, total: 320.50 }
        ],
        subtotal: 1200.50,
        tax_rate: 0.085,
        tax_amount: 102.04,
        total_estimated_cost: 1302.54
      },
      {
        id: 3,
        code: 'EST-2023-003',
        job_card_id: 1039,
        job_card_code: 'JC-1039',
        job_card_title: 'Heavy Commercial Fleet Service',
        customer_name: 'City Transit Auth.',
        mechanic_name: 'Sarah Jenkins',
        status: 'Send to Customer',
        sent_date: 'Oct 10, 2023',
        line_items: [
          { description: 'Brake Drum Replacement', hours_or_qty: 4, unit_price: 1200.00, total: 4800.00 },
          { description: 'Air Brake Line Servicing', hours_or_qty: 1, unit_price: 2500.00, total: 2500.00 },
          { description: 'Fleet Certified Labor', hours_or_qty: 8, unit_price: 200.00, total: 1600.00 }
        ],
        subtotal: 8900.00,
        tax_rate: 0.085,
        tax_amount: 756.50,
        total_estimated_cost: 9656.50
      }
    ],

    // 8. Job Card Parts Approvals (Requested by Mechanic, Approved by Manager)
    jobCardParts: [
      {
        id: 1,
        job_card_id: 1045,
        work_order: '#WO-9921',
        part_id: 1,
        part_name: 'Brake Pad Set - Front',
        part_number: 'PN: BP-2049-F',
        quantity: 2,
        unit: 'Sets',
        unit_price: 85.00,
        total_price: 170.00,
        mechanic_name: 'John Doe',
        mechanic_initials: 'JD',
        status: 'Pending Approval',
        requested_at: '2023-10-24 10:15:00'
      },
      {
        id: 2,
        job_card_id: 1046,
        work_order: '#WO-9924',
        part_id: 2,
        part_name: 'Synthetic Motor Oil 5W-30',
        part_number: 'PN: MO-5W30-SYN',
        quantity: 6,
        unit: 'Quarts',
        unit_price: 9.50,
        total_price: 57.00,
        mechanic_name: 'Sarah Miller',
        mechanic_initials: 'SM',
        status: 'Pending Approval',
        requested_at: '2023-10-24 11:30:00'
      },
      {
        id: 3,
        job_card_id: 1042,
        work_order: '#WO-9918',
        part_id: 3,
        part_name: 'Alternator Assembly - 130A',
        part_number: 'PN: ALT-130-OEM',
        quantity: 1,
        unit: 'Unit',
        unit_price: 345.00,
        total_price: 345.00,
        mechanic_name: 'John Doe',
        mechanic_initials: 'JD',
        status: 'Pending Approval',
        requested_at: '2023-10-24 13:45:00'
      }
    ],

    // 9. Invoices Table
    invoices: [
      {
        id: 89,
        invoice_number: 'INV-2023-089',
        job_card_id: 1035,
        customer_name: 'Acme Logistics Corp',
        customer_email: 'contact@acme.com',
        vehicle_name: 'Freightliner Cascadia',
        vin: '1FUJ...8902',
        date: 'Oct 24, 2023',
        total_amount: 3240.50,
        status: 'Paid',
        created_at: '2023-10-24'
      },
      {
        id: 90,
        invoice_number: 'INV-2023-090',
        job_card_id: 1038,
        customer_name: 'Global Express',
        customer_email: 'billing@globalexp.net',
        vehicle_name: 'Kenworth T680',
        vin: '1XK...4419',
        date: 'Oct 26, 2023',
        total_amount: 1150.00,
        status: 'Pending',
        created_at: '2023-10-26'
      },
      {
        id: 85,
        invoice_number: 'INV-2023-085',
        job_card_id: 1029,
        customer_name: 'Metro Transport',
        customer_email: 'accounts@metro.com',
        vehicle_name: 'Volvo VNL 860',
        vin: '4V4...0021',
        date: 'Oct 12, 2023',
        total_amount: 4890.75,
        status: 'Overdue',
        created_at: '2023-10-12'
      },
      {
        id: 91,
        invoice_number: 'INV-2023-091',
        job_card_id: 1041,
        customer_name: 'Apex Logistics',
        customer_email: 'ops@apexlogistics.com',
        vehicle_name: 'Ford Transit 350',
        vin: '1FTYR2XG2JKA*****',
        date: 'Oct 27, 2023',
        total_amount: 2450.00,
        status: 'Pending',
        created_at: '2023-10-27'
      }
    ],

    // 10. Chat Messages Table
    chatMessages: [
      {
        id: 1,
        sender_id: 8, // Mike Davis
        receiver_id: 2, // Alex Johnson (Manager)
        sender_name: 'Mike Davis',
        sender_role: 'Tech Bay 4',
        job_tag: 'JOB #8492',
        message: "Hey boss, I've got the F-150 up on the lift. The diagnostic showed a misfire on cylinder 4, but while inspecting I found something else.",
        time: '10:30 AM',
        is_incoming: true,
        attachments: []
      },
      {
        id: 2,
        sender_id: 2, // Manager
        receiver_id: 8,
        sender_name: 'You',
        sender_role: 'Workshop Manager',
        job_tag: 'JOB #8492',
        message: 'Copy that, Mike. What did you find? Is it going to affect the estimate for Fleet Logistics?',
        time: '10:35 AM',
        is_incoming: false,
        attachments: []
      },
      {
        id: 3,
        sender_id: 8,
        receiver_id: 2,
        sender_name: 'Mike Davis',
        sender_role: 'Tech Bay 4',
        job_tag: 'JOB #8492',
        message: "Found a cracked exhaust manifold. It's pretty bad. Sending pics now. We'll definitely need to update the estimate.",
        time: '10:42 AM',
        is_incoming: true,
        attachments: [
          '../../assets/images/engine.jpg',
          '../../assets/images/shop.jpg'
        ]
      }
    ],

    // 11. Repair Timeline
    repairTimeline: [
      { id: 1, job_card_id: 1045, stage: 'Diagnosis', updated_by: 2, updated_at: 'Oct 24, 09:30' },
      { id: 2, job_card_id: 1045, stage: 'Repairing', updated_by: 6, updated_at: 'Oct 24, 14:00' },
      { id: 3, job_card_id: 1042, stage: 'Quality Control', updated_by: 7, updated_at: 'Oct 25, 11:20' }
    ],

    // 12. Recent Activities feed
    recentActivities: [
      { id: 1, text: "Job Card <strong>#4092</strong> marked as 'Quality Control' by J. Smith.", time: '10 mins ago', subtext: 'Ford F-150', type: 'blue' },
      { id: 2, text: "Spare Parts Approval requested for Job Card <strong>#4095</strong>.", time: '35 mins ago', subtext: 'Toyota Camry', type: 'red' },
      { id: 3, text: "Invoice <strong>#INV-290</strong> paid by customer.", time: '1 hour ago', subtext: '৳1,240.00', type: 'gray' },
      { id: 4, text: "New Booking Request <strong>BRQ-2023-145</strong> submitted by Apex Logistics.", time: '2 hours ago', subtext: 'Ford Transit', type: 'blue' }
    ],

    // 13. Notices & Broadcasts
    notices: [
      { id: 1, title: 'Workshop Safety Audit Scheduled', content: 'Mandatory hoist and lift inspection this Thursday at 10 AM.', target_audience: 'All', created_at: 'Today' },
      { id: 2, title: 'Urgent: Low Stock on 5W-30 Oil', content: 'Synthetic oil inventory reaching minimum threshold.', target_audience: 'Managers', created_at: 'Yesterday' }
    ]
  };

  class Store {
    constructor() {
      this.data = this.load();
      this.isSynced = false;
      this.readyCallbacks = [];
      this.syncWithBackend();
    }

    onReady(callback) {
      if (this.isSynced) {
        callback(this.data);
      } else {
        this.readyCallbacks.push(callback);
      }
    }

    async syncWithBackend() {
      try {
        const response = await fetch('../../api/manager/store-sync.php');
        if (response.ok) {
          const res = await response.json();
          if (res.success && res.data) {
            this.data = Object.assign({}, this.data, res.data);
            this.isSynced = true;
            this.save();
            this.readyCallbacks.forEach(cb => {
              try { cb(this.data); } catch(e) { console.error(e); }
            });
            this.readyCallbacks = [];
            if (typeof window !== 'undefined') {
              window.dispatchEvent(new CustomEvent('autocare:store:synced', { detail: this.data }));
            }
          }
        }
      } catch (e) {
        console.warn('AutoCare Store: Backend sync skipped, using local store.', e);
      }
    }

    refreshFromDB() {
      return this.syncWithBackend();
    }

    load() {
      try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
          const parsed = JSON.parse(stored);
          const data = Object.assign({}, initialSeedData, parsed);
          // Ensure jobCardParts array exists and is populated with seed data if empty
          if (!data.jobCardParts || !Array.isArray(data.jobCardParts) || data.jobCardParts.length === 0) {
            data.jobCardParts = JSON.parse(JSON.stringify(initialSeedData.jobCardParts));
          }
          return data;
        }
      } catch (e) {
        console.warn('AutoCare Store: Failed to load from localStorage. Using defaults.', e);
      }
      return JSON.parse(JSON.stringify(initialSeedData));
    }

    save() {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.data));
      } catch (e) {
        console.error('AutoCare Store: Failed to save to localStorage.', e);
      }
    }

    reset() {
      this.data = JSON.parse(JSON.stringify(initialSeedData));
      this.save();
      return this.data;
    }

    // --- Users & Mechanics ---
    getUsers() { return this.data.users || []; }
    getMechanics() { return this.getUsers().filter(u => u.role === 'Mechanic'); }
    getOwners() { return this.getUsers().filter(u => u.role === 'Owner'); }
    getMechanicById(id) { return this.getMechanics().find(m => m.id === Number(id)); }
    
    updateMechanicWorkload(mechanicId, newWorkload, status) {
      const mech = this.getMechanicById(mechanicId);
      if (mech) {
        if (typeof newWorkload === 'number') mech.workload = Math.min(100, Math.max(0, newWorkload));
        if (status) mech.status = status;
        this.save();
      }
      return mech;
    }

    // --- Service Requests ---
    getServiceRequests() { return this.data.serviceRequests || []; }
    getServiceRequestById(id) { return this.getServiceRequests().find(r => r.id === Number(id) || r.code === id); }
    
    updateServiceRequestStatus(id, newStatus) {
      const req = this.getServiceRequestById(id);
      if (req) {
        req.status = newStatus;
        this.logActivity(`Service Request <strong>${req.code}</strong> updated to <em>${newStatus}</em>.`, 'blue', req.code);
        this.save();
        try {
          fetch('../../api/manager/booking-requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: newStatus === 'Approved' ? 'approve' : 'reject',
              appointment_id: req.id,
              reason: newStatus === 'Rejected' ? 'Declined by manager' : ''
            })
          }).catch(e => console.warn('Could not persist booking request status to DB:', e));
        } catch (e) {}
      }
      return req;
    }

    // --- Job Cards ---
    getJobCards() { return this.data.jobCards || []; }
    getJobCardById(id) { return this.getJobCards().find(j => j.id === Number(id) || j.code === id || j.work_order === id); }
    
    createJobCard(cardData) {
      const id = cardData.id || Date.now();
      const code = cardData.code || `JC-${Math.floor(1000 + Math.random() * 9000)}`;
      const workOrder = cardData.work_order || `#WO-${Math.floor(2000 + Math.random() * 8000)}`;

      const newCard = Object.assign({
        id: id,
        code: code,
        work_order: workOrder,
        customer_name: 'Customer',
        vehicle_details: 'Vehicle',
        vehicle_title: 'Vehicle',
        vin: 'VIN-PENDING',
        manager_id: 2,
        mechanic_id: null,
        mechanic_name: 'Unassigned',
        mechanic_initials: 'UA',
        priority: 'Normal',
        status: 'Diagnosis',
        kanban_stage: 'PENDING',
        progress_percentage: 10,
        estimated_cost: 0.00,
        date_opened: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        delivery_date: 'TBD',
        service_text: 'Repair Order'
      }, cardData);

      this.data.jobCards.unshift(newCard);
      this.logActivity(`New Job Card <strong>${newCard.code}</strong> (${newCard.work_order}) opened for ${newCard.customer_name}.`, 'blue', newCard.vehicle_title);
      this.save();

      // Persist to MySQL
      try {
        fetch('../../api/manager/job-cards.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create',
            customer_name: newCard.customer_name,
            vehicle_details: newCard.vehicle_details,
            service_text: newCard.service_text,
            estimated_cost: newCard.estimated_cost,
            mechanic_id: newCard.mechanic_id,
            delivery_date: newCard.delivery_date
          })
        }).then(r => r.json()).then(res => {
          if (res.success && res.job_id) {
            newCard.id = res.job_id;
            newCard.code = res.code;
            newCard.work_order = res.work_order;
            this.save();
          }
        }).catch(e => console.warn('Could not persist job card to DB:', e));
      } catch (e) {}

      return newCard;
    }

    updateJobCardStatus(id, status, kanbanStage) {
      const card = this.getJobCardById(id);
      if (card) {
        if (status) card.status = status;
        if (kanbanStage) card.kanban_stage = kanbanStage;
        
        // Auto update progress percentage based on stage
        if (kanbanStage === 'PENDING') card.progress_percentage = Math.min(card.progress_percentage || 20, 30);
        else if (kanbanStage === 'IN PROGRESS') card.progress_percentage = Math.max(card.progress_percentage || 50, 60);
        else if (kanbanStage === 'COMPLETED') card.progress_percentage = 100;

        this.addTimelineEntry(card.id, status || kanbanStage, 2);
        this.logActivity(`Job Card <strong>${card.code}</strong> updated to '${status || kanbanStage}'.`, 'blue', card.vehicle_title);
        this.save();

        // Persist to MySQL
        try {
          fetch('../../api/manager/job-cards.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update_status',
              job_id: card.id,
              status: status || card.status,
              kanban_stage: kanbanStage || card.kanban_stage,
              progress_percentage: card.progress_percentage
            })
          }).catch(e => console.warn('Could not persist job card status to DB:', e));
        } catch (e) {}
      }
      return card;
    }

    assignJobCardMechanic(jobCardId, mechanicId) {
      const card = this.getJobCardById(jobCardId);
      const mech = this.getMechanicById(mechanicId);
      if (card && mech) {
        card.mechanic_id = mech.id;
        card.mechanic_name = mech.name;
        card.mechanic_initials = mech.name.split(' ').map(n => n[0]).join('');
        mech.workload = Math.min(100, (mech.workload || 0) + 25);
        mech.status = mech.workload >= 80 ? 'Busy' : 'Available';
        this.logActivity(`Job Card <strong>${card.code}</strong> assigned to ${mech.name}.`, 'blue', card.vehicle_title);
        this.save();

        // Persist to MySQL
        try {
          fetch('../../api/manager/job-cards.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'assign_mechanic',
              job_id: card.id,
              mechanic_id: mech.id
            })
          }).catch(e => console.warn('Could not persist job assignment to DB:', e));
        } catch (e) {}
      }
      return card;
    }

    // --- Spare Parts & Approvals ---
    getSpareParts() { return this.data.spareParts || []; }
    getSparePartById(id) { return this.getSpareParts().find(p => p.id === Number(id)); }
    
    getJobCardParts() { return this.data.jobCardParts || []; }
    getJobCardPartById(id) { return this.getJobCardParts().find(p => p.id === Number(id)); }
    
    approvePartRequest(partRequestId) {
      const req = this.getJobCardPartById(partRequestId);
      if (req && req.status !== 'Approved') {
        req.status = 'Approved';
        // Deduct from spare parts inventory
        const part = this.getSparePartById(req.part_id);
        if (part) {
          part.quantity_in_stock = Math.max(0, part.quantity_in_stock - (req.quantity || 1));
        }
        this.logActivity(`Spare part request approved: <strong>${req.part_name}</strong> for ${req.work_order}.`, 'blue', `৳${req.total_price}`);
        this.save();

        // Persist to MySQL
        try {
          fetch('../../api/manager/payment-approval.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve', request_id: partRequestId })
          }).catch(e => console.warn('Could not persist part approval to DB:', e));
        } catch (e) {}
      }
      return req;
    }

    rejectPartRequest(partRequestId, reason) {
      const req = this.getJobCardPartById(partRequestId);
      if (req) {
        req.status = 'Rejected';
        req.rejection_reason = reason || 'Declined by workshop manager';
        this.logActivity(`Spare part request rejected: <strong>${req.part_name}</strong> for ${req.work_order}.`, 'red');
        this.save();

        // Persist to MySQL
        try {
          fetch('../../api/manager/payment-approval.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reject', request_id: partRequestId, reason: reason })
          }).catch(e => console.warn('Could not persist part rejection to DB:', e));
        } catch (e) {}
      }
      return req;
    }

    approveAllPartRequests() {
      const pending = this.getJobCardParts().filter(p => p.status === 'Pending Approval');
      pending.forEach(p => this.approvePartRequest(p.id));
      this.save();

      try {
        fetch('../../api/manager/payment-approval.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'approve_all' })
        }).catch(e => console.warn('Could not persist approve all to DB:', e));
      } catch (e) {}

      return pending.length;
    }

    createJobCardPart(partData) {
      const id = Date.now();
      const newPart = Object.assign({
        id: id,
        job_card_id: 1045,
        work_order: '#WO-9921',
        part_id: 1,
        part_name: 'Brake Pad Set - Front',
        part_number: 'PN: BP-2049-F',
        quantity: 1,
        unit: 'Units',
        unit_price: 85.00,
        total_price: 85.00,
        mechanic_name: 'John Doe',
        mechanic_initials: 'JD',
        status: 'Pending Approval',
        requested_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
      }, partData);

      if (!this.data.jobCardParts) this.data.jobCardParts = [];
      this.data.jobCardParts.unshift(newPart);
      this.logActivity(`Spare part requested: <strong>${newPart.part_name}</strong> for ${newPart.work_order}.`, 'blue', `৳${newPart.total_price}`);
      this.save();

      // Persist to MySQL
      try {
        fetch('../../api/manager/payment-approval.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create_request',
            job_card_id: newPart.job_card_id,
            part_id: newPart.part_id,
            quantity: newPart.quantity
          })
        }).then(r => r.json()).then(res => {
          if (res.success && res.request_id) {
            newPart.id = res.request_id;
            this.save();
          }
        }).catch(e => console.warn('Could not persist part request to DB:', e));
      } catch (e) {}

      return newPart;
    }

    resetJobCardParts() {
      this.data.jobCardParts = JSON.parse(JSON.stringify(initialSeedData.jobCardParts));
      this.save();
      return this.data.jobCardParts;
    }

    // --- Estimates ---
    getEstimates() { return this.data.estimates || []; }
    getEstimateById(id) { return this.getEstimates().find(e => e.id === Number(id) || e.code === id); }
    
    saveEstimate(estimateData) {
      let est;
      if (estimateData.id) {
        est = this.getEstimateById(estimateData.id);
        if (est) Object.assign(est, estimateData);
      }
      if (!est) {
        est = Object.assign({
          id: Date.now(),
          code: `EST-${new Date().getFullYear()}-${String(this.data.estimates.length + 1).padStart(3, '0')}`,
          status: 'Draft',
          sent_date: '-'
        }, estimateData);
        this.data.estimates.unshift(est);
      }
      this.logActivity(`Cost Estimate <strong>${est.code}</strong> updated for ${est.customer_name}.`, 'blue', `৳${est.total_estimated_cost}`);
      this.save();

      // Persist to MySQL
      try {
        fetch('../../api/manager/cost-estimation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(est)
        }).then(r => r.json()).then(res => {
          if (res.success && res.estimate_id) {
            est.id = res.estimate_id;
            if (res.code) est.code = res.code;
            this.save();
          }
        }).catch(e => console.warn('Could not persist estimate to DB:', e));
      } catch (e) {}

      return est;
    }

    // --- Invoices ---
    getInvoices() { return this.data.invoices || []; }
    getInvoiceById(id) { return this.getInvoices().find(i => i.id === Number(id) || i.invoice_number === id); }
    
    createInvoice(invoiceData) {
      const id = Date.now();
      const count = (this.data.invoices || []).length + 90;
      const num = `INV-2026-${String(count).padStart(3, '0')}`;
      const newInv = Object.assign({
        id: id,
        invoice_number: num,
        date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        status: 'Pending'
      }, invoiceData);

      this.data.invoices.unshift(newInv);
      this.logActivity(`Invoice <strong>#${newInv.invoice_number}</strong> generated for ${newInv.customer_name}.`, 'gray', `৳${newInv.total_amount}`);
      this.save();

      // Persist to MySQL
      try {
        fetch('../../api/manager/invoice-management.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create',
            job_card_id: newInv.job_card_id,
            total_amount: newInv.total_amount,
            status: newInv.status
          })
        }).then(r => r.json()).then(res => {
          if (res.success && res.invoice_id) {
            newInv.id = res.invoice_id;
            newInv.invoice_number = res.invoice_number;
            this.save();
          }
        }).catch(e => console.warn('Could not persist invoice to DB:', e));
      } catch (e) {}

      return newInv;
    }

    updateInvoiceStatus(id, newStatus) {
      const inv = this.getInvoiceById(id);
      if (inv) {
        inv.status = newStatus;
        this.logActivity(`Invoice <strong>#${inv.invoice_number}</strong> marked as ${newStatus}.`, 'gray', `৳${inv.total_amount}`);
        this.save();

        // Persist to MySQL
        try {
          fetch('../../api/manager/invoice-management.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update_status',
              invoice_id: inv.id,
              status: newStatus
            })
          }).catch(e => console.warn('Could not persist invoice status to DB:', e));
        } catch (e) {}
      }
      return inv;
    }

    // --- Chat Messages ---
    getChatMessages(userId) {
      if (!userId) return this.data.chatMessages || [];
      return (this.data.chatMessages || []).filter(m => m.sender_id === userId || m.receiver_id === userId);
    }

    sendChatMessage(messageText, attachments = [], receiverId = 8) {
      const newMsg = {
        id: Date.now(),
        sender_id: 2, // Manager
        receiver_id: receiverId,
        sender_name: 'You',
        sender_role: 'Workshop Manager',
        job_tag: 'JOB #8492',
        message: messageText,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        is_incoming: false,
        attachments: attachments || []
      };
      this.data.chatMessages.push(newMsg);
      this.save();

      // Persist to MySQL
      try {
        fetch('../../api/manager/chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            receiver_id: receiverId,
            message_text: messageText,
            job_tag: newMsg.job_tag,
            attachments: attachments
          })
        }).then(r => r.json()).then(res => {
          if (res.success && res.data && res.data.id) {
            newMsg.id = res.data.id;
            this.save();
          }
        }).catch(e => console.warn('Could not persist chat message to DB:', e));
      } catch (e) {}

      return newMsg;
    }

    simulateMechanicReply(replyText, receiverId = 8) {
      const replyMsg = {
        id: Date.now() + 1,
        sender_id: receiverId,
        receiver_id: 2,
        sender_name: 'Mike Davis',
        sender_role: 'Tech Bay 4',
        job_tag: 'JOB #8492',
        message: replyText || "Understood, boss! I'm updating the job card right away.",
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        is_incoming: true,
        attachments: []
      };
      this.data.chatMessages.push(replyMsg);
      this.save();
      return replyMsg;
    }

    // --- Timeline & Activity ---
    addTimelineEntry(jobCardId, stage, updatedBy = 2) {
      if (!this.data.repairTimeline) this.data.repairTimeline = [];
      const entry = {
        id: Date.now(),
        job_card_id: jobCardId,
        stage: stage,
        updated_by: updatedBy,
        updated_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      };
      this.data.repairTimeline.push(entry);
      this.save();
      return entry;
    }

    logActivity(text, type = 'blue', subtext = '') {
      if (!this.data.recentActivities) this.data.recentActivities = [];
      const act = {
        id: Date.now(),
        text: text,
        time: 'Just now',
        subtext: subtext,
        type: type
      };
      this.data.recentActivities.unshift(act);
      if (this.data.recentActivities.length > 20) this.data.recentActivities.pop();
      this.save();
      return act;
    }

    getRecentActivity() {
      return this.data.recentActivities || [];
    }

    getNotices() {
      return this.data.notices || [];
    }
  }

  // Export singleton instance
  const storeInstance = new Store();
  return storeInstance;
});
