-- 1. Appointments (owner 101: Ford Transit = vehicle 1, Ford F-150 = vehicle 9; owner 102: Camry = vehicle 2)
INSERT IGNORE INTO Appointments (appointment_id, code, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, priority, status, created_at) VALUES
(201, 'BRQ-2026-201', 101, 9, 1, 1, '2026-10-02 09:00:00', 'Check engine light on, rough idle. Scanner shows misfire on cylinder 4.', 'High', 'Approved', '2026-09-30 18:20:00'),
(202, 'BRQ-2026-202', 101, 1, 1, 3, '2026-10-04 10:30:00', 'Grinding noise from the front wheels when braking.', 'Normal', 'Approved', '2026-10-01 08:05:00'),
(203, 'BRQ-2026-203', 101, 9, 2, 5, '2026-09-12 11:00:00', 'Routine 30k km service: oil, filters, general inspection.', 'Normal', 'Completed', '2026-09-08 10:00:00'),
(204, 'BRQ-2026-204', 101, 1, 1, 5, '2026-10-20 09:00:00', 'Oil change and tire rotation.', 'Normal', 'Pending', '2026-10-05 12:40:00'),
(205, 'BRQ-2026-205', 102, 2, 1, 4, '2026-10-05 14:00:00', 'Battery warning light and slow cranking in the morning.', 'Normal', 'Approved', '2026-10-03 09:15:00');

-- 2. Job cards linked to the appointments (mechanic 3 = David Chui, mechanic 8 = Mike Davis)
INSERT IGNORE INTO JobCards (job_id, code, work_order, appointment_id, manager_id, mechanic_id, status, kanban_stage, progress_percentage, fault_report, service_text, estimated_cost, delivery_date, start_date, completion_date, created_at) VALUES
(2001, 'JC-2001', '#WO-3001', 201, 2, 3, 'Repairing', 'IN PROGRESS', 60, 'Cylinder 4 misfire traced to a failed ignition coil. Injector on cylinder 4 partially clogged.', 'Engine Diagnostic & Ignition Repair', 816.46, 'Oct 09, 2026', '2026-10-02 09:30:00', NULL, '2026-10-02 09:30:00'),
(2002, 'JC-2002', '#WO-3002', 202, 2, 3, 'Diagnosis', 'PENDING', 20, 'Front brake pads worn to 2mm. Rotors within spec.', 'Front Brake Pad Replacement', 417.73, 'Oct 10, 2026', '2026-10-04 11:00:00', NULL, '2026-10-04 11:00:00'),
(2003, 'JC-2003', '#WO-3003', 203, 2, 8, 'Completed', 'COMPLETED', 100, 'Routine service completed. No issues found.', 'Routine Periodic Maintenance', 224.60, 'Sep 13, 2026', '2026-09-12 11:15:00', '2026-09-13 15:00:00', '2026-09-12 11:15:00'),
(2004, 'JC-2004', '#WO-3004', 205, 2, 3, 'Testing', 'IN PROGRESS', 90, 'Parasitic drain from aftermarket alarm module. Alternator output low.', 'Electrical Fault Tracing', 1106.70, 'Oct 08, 2026', '2026-10-05 14:30:00', NULL, '2026-10-05 14:30:00');

-- 3. Repair timeline
INSERT IGNORE INTO RepairTimeline (timeline_id, job_id, stage, updated_by, updated_at) VALUES
(201, 2001, 'Diagnosis', 3, '2026-10-02 10:00:00'),
(202, 2001, 'Repairing', 3, '2026-10-03 09:00:00'),
(203, 2002, 'Diagnosis', 3, '2026-10-04 11:30:00'),
(204, 2003, 'Diagnosis', 8, '2026-09-12 11:30:00'),
(205, 2003, 'Repairing', 8, '2026-09-12 14:00:00'),
(206, 2003, 'Testing', 8, '2026-09-13 10:00:00'),
(207, 2003, 'Completed', 8, '2026-09-13 15:00:00'),
(208, 2004, 'Diagnosis', 3, '2026-10-05 15:00:00'),
(209, 2004, 'Repairing', 3, '2026-10-06 09:00:00'),
(210, 2004, 'Testing', 3, '2026-10-06 16:00:00');

-- 4. Parts used / requested
INSERT IGNORE INTO JobParts (job_part_id, job_id, part_id, quantity, unit_price, total_price, status, rejection_reason, requested_at) VALUES
(201, 2001, 6, 1, 180.00, 180.00, 'Approved', NULL, '2026-10-02 12:00:00'),
(202, 2001, 2, 5, 9.50, 47.50, 'Pending Approval', NULL, '2026-10-03 09:20:00'),
(203, 2002, 1, 1, 85.00, 85.00, 'Pending Approval', NULL, '2026-10-04 12:10:00'),
(204, 2003, 2, 6, 9.50, 57.00, 'Approved', NULL, '2026-09-12 11:40:00'),
(205, 2004, 3, 1, 345.00, 345.00, 'Approved', NULL, '2026-10-05 16:00:00');

-- 5. Labor logged
INSERT IGNORE INTO JobLabor (labor_id, job_id, description, hours, hourly_rate) VALUES
(201, 2001, 'Diagnostic scan and misfire tracing', 2.00, 150.00),
(202, 2001, 'Ignition coil replacement', 1.50, 150.00),
(203, 2003, 'Oil and filter change, multi-point inspection', 1.00, 150.00),
(204, 2004, 'Electrical fault tracing and alarm module isolation', 3.00, 150.00),
(205, 2004, 'Alternator replacement', 1.50, 150.00);

-- 6. Estimates (owners only see non-Draft estimates)
INSERT IGNORE INTO RepairEstimates (estimate_id, code, job_id, status, sent_date, line_items, subtotal, tax_rate, tax_amount, total_estimated_cost, created_at) VALUES
(201, 'EST-2026-201', 2001, 'Send to Customer', 'Oct 03, 2026',
 '[{"description":"Diagnostic Labor","hours_or_qty":2,"unit_price":150,"total":300},{"description":"Ignition Coil Replacement Labor","hours_or_qty":1.5,"unit_price":150,"total":225},{"description":"Fuel Injector Nozzle Set","hours_or_qty":1,"unit_price":180,"total":180},{"description":"Synthetic Motor Oil 5W-30","hours_or_qty":5,"unit_price":9.5,"total":47.5}]',
 752.50, 0.085, 63.96, 816.46, '2026-10-03 09:30:00'),
(202, 'EST-2026-202', 2002, 'Draft', '-',
 '[{"description":"Brake Pad Replacement Labor","hours_or_qty":2,"unit_price":150,"total":300},{"description":"Brake Pad Set - Front","hours_or_qty":1,"unit_price":85,"total":85}]',
 385.00, 0.085, 32.73, 417.73, '2026-10-04 12:30:00'),
(203, 'EST-2026-203', 2003, 'Approved', 'Sep 12, 2026',
 '[{"description":"Oil and Filter Change Labor","hours_or_qty":1,"unit_price":150,"total":150},{"description":"Synthetic Motor Oil 5W-30","hours_or_qty":6,"unit_price":9.5,"total":57}]',
 207.00, 0.085, 17.60, 224.60, '2026-09-12 12:00:00'),
(204, 'EST-2026-204', 2004, 'Approved', 'Oct 05, 2026',
 '[{"description":"Electrical Fault Tracing Labor","hours_or_qty":3,"unit_price":150,"total":450},{"description":"Alternator Replacement Labor","hours_or_qty":1.5,"unit_price":150,"total":225},{"description":"Alternator Assembly - 130A","hours_or_qty":1,"unit_price":345,"total":345}]',
 1020.00, 0.085, 86.70, 1106.70, '2026-10-05 16:30:00');

-- 7. Invoices
INSERT IGNORE INTO Invoices (invoice_id, invoice_number, job_id, customer_id, total_amount, status, issued_date, paid_date) VALUES
(201, 'INV-2026-201', 2003, 101, 224.60, 'Paid', '2026-09-13 16:00:00', '2026-09-14 10:20:00');

-- 8. Repair photos (existing images under public_html/assets/images)
INSERT IGNORE INTO RepairPhotos (photo_id, job_id, photo_url, description, uploaded_at) VALUES
(201, 2001, '../../assets/images/repair-gallery-1.jpg', 'Vehicle raised on the lift for inspection', '2026-10-02 10:15:00'),
(202, 2001, '../../assets/images/engine.jpg', 'Cylinder 4 ignition coil removed', '2026-10-02 11:30:00'),
(203, 2001, '../../assets/images/repair-gallery-3.jpg', 'New coil installed', '2026-10-03 13:45:00'),
(204, 2002, '../../assets/images/chat-brake-pad-wear.jpg', 'Front pad wear measured at 2mm', '2026-10-04 11:40:00'),
(205, 2004, '../../assets/images/repair-gallery-2.jpg', 'Alarm module wiring traced', '2026-10-06 10:00:00');

-- 9. Chat messages (owner 101 <-> manager 2, owner 101 <-> mechanic 3, mechanic 3 <-> manager 2)
INSERT IGNORE INTO ChatMessages (message_id, sender_id, receiver_id, job_tag, message_text, attachment_url, is_read, created_at) VALUES
(201, 101, 2, 'JC-2001', 'Hi, any update on the F-150? The check engine light came on again on the way in.', NULL, 1, '2026-10-02 09:45:00'),
(202, 2, 101, 'JC-2001', 'Thanks for letting us know. David is running diagnostics now, we will send an estimate shortly.', NULL, 1, '2026-10-02 10:05:00'),
(203, 2, 101, 'JC-2001', 'The estimate for the ignition repair is ready for your approval.', NULL, 0, '2026-10-03 09:35:00'),
(204, 3, 101, 'JC-2001', 'The failed coil was on cylinder 4. I have uploaded photos to your repair gallery.', NULL, 0, '2026-10-03 13:50:00'),
(205, 3, 2, 'JC-2001', 'Need approval for 5 quarts of 5W-30 on JC-2001.', NULL, 1, '2026-10-03 09:25:00'),
(206, 2, 3, 'JC-2001', 'Approving now. Please also check the injector on cylinder 4.', NULL, 1, '2026-10-03 09:40:00'),
(207, 2, 3, 'JC-2002', 'JC-2002 is yours as well, brake pads on the Transit.', NULL, 0, '2026-10-04 11:05:00');
