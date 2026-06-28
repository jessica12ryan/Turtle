-- Seed demo data (run from setup wizard; admin user already exists as id=1)
-- Note: A default company is auto-created by ensureLandlordCompany() when
-- properties are created through the app. For seed data we insert one directly.

-- Default company (required by properties FK)
INSERT IGNORE INTO companies (id, name) VALUES (1, 'Default Company');

-- Users: staff + tenants
INSERT IGNORE INTO users (id, name, email, password, role, must_change_password, created_at, updated_at) VALUES
(2, 'Jane Manager', 'manager@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'property_manager', 0, NOW(), NOW()),
(3, 'Bob Maintenance', 'bob@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'maintenance', 0, NOW(), NOW()),
(4, 'Tom Tenant', 'tom@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(5, 'Sue Tenant', 'sue@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(6, 'Alice Landlord', 'alice@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'landlord', 0, NOW(), NOW()),
(7, 'Charlie Tenant', 'charlie@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(8, 'Diana Tenant', 'diana@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(9, 'Evan Tenant', 'evan@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(10, 'Fiona Staff', 'fiona@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'maintenance', 0, NOW(), NOW());

-- Company-user assignments (required for non-admin access scoping)
INSERT IGNORE INTO company_user (company_id, user_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 6), (1, 10);

-- Properties
INSERT IGNORE INTO properties (id, landlord_id, company_id, property_manager_id, name, address, city, province, postal_code, country) VALUES
(1, 6, 1, 2, 'The Wellington', '45 Wellington St W', 'Toronto', 'ON', 'M5V 1E3', 'CA'),
(2, 6, 1, 2, 'King Street Lofts', '120 King St E', 'Toronto', 'ON', 'M5C 1G6', 'CA'),
(3, 6, 1, 2, 'Harbourfront Condos', '300 Queens Quay W', 'Toronto', 'ON', 'M5V 1A2', 'CA'),
(4, 6, 1, 2, 'Maple Ridge Townhomes', '75 Maple Dr', 'Toronto', 'ON', 'M6A 1A1', 'CA');

-- Tenant-property assignments (some properties have multiple tenants)
INSERT IGNORE INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at) VALUES
(1, 4, 1, NOW()),
(2, 5, 1, NOW()),
(3, 7, 1, NOW()),
(4, 8, 1, NOW()),
(4, 9, 0, NOW());

-- Leases
INSERT IGNORE INTO leases (id, property_id, tenant_id, title, start_date, end_date, rent_amount, created_at, updated_at) VALUES
(1, 1, 4, 'Lease Agreement - Wellington', '2025-01-01', '2025-12-31', 2200.00, NOW(), NOW()),
(2, 2, 5, 'Lease Contract - King Street Lofts', '2025-03-01', '2026-02-28', 1850.00, NOW(), NOW()),
(3, 3, 7, 'Rental Agreement - Harbourfront', '2025-02-15', '2026-02-14', 2500.00, NOW(), NOW());

-- Tickets (mix of statuses and priorities)
INSERT IGNORE INTO tickets (id, property_id, tenant_id, subject, description, category, status, priority, assigned_to) VALUES
(1, 1, 4, 'Leaky kitchen faucet', 'The kitchen faucet has been dripping for two days. It seems to be getting worse.', 'plumbing', 'in_progress', 'medium', 3),
(2, 1, 4, 'Broken garbage disposal', 'The garbage disposal makes a loud noise when turned on. May need replacement.', 'appliances', 'open', 'low', NULL),
(3, 2, 5, 'Heating not working', 'The heating unit is blowing cold air. Temperature inside dropped to 15C.', 'hvac', 'open', 'high', NULL),
(4, 3, 7, 'Smoke detector beeping', 'Smoke detector in the hallway beeps every 30 seconds. Battery was just replaced.', 'safety', 'open', 'medium', NULL),
(5, 4, 9, 'Garage door remote broken', 'The garage door remote stopped working. Manual open still works.', 'other', 'open', 'low', NULL),
(6, 1, 4, 'AC not cooling properly', 'The air conditioner runs constantly but the temperature never drops below 24C.', 'hvac', 'resolved', 'high', 3),
(7, 2, 5, 'Overgrown front lawn', 'The front lawn has not been mowed in over three weeks.', 'exterior', 'closed', 'low', 2);

-- Ticket comments
INSERT IGNORE INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES
(1, 2, 'I have assigned this to Bob for inspection tomorrow.', 1),
(1, 3, 'I will check it out tomorrow morning around 9am.', 0),
(2, 4, 'It started making noise after I put some vegetable peels down.', 0),
(3, 2, 'This is urgent - sending Bob right away.', 1),
(3, 3, 'Arrived on site. The furnace pilot light is out. Will relight and monitor.', 0),
(4, 7, 'I replaced the battery but it is still beeping.', 0),
(6, 3, 'Found a refrigerant leak. Parts ordered, ETA 3 days.', 1),
(6, 2, 'Tenant notified about timeline for repair.', 1),
(6, 4, 'Thanks for the update. Please keep me posted.', 0);

-- Resources (links shared with tenants)
INSERT IGNORE INTO resources (id, title, url, description, created_by) VALUES
(1, 'Tenant Payment Portal', 'https://www.turtleapp.com/pay', 'Pay rent and view payment history online.', 1),
(2, 'Maintenance Request Guide', 'https://www.turtleapp.com/guides/maintenance', 'How to submit and track maintenance requests.', 2),
(3, 'Community Rules', 'https://www.turtleapp.com/guides/rules', 'Building rules and community guidelines.', 2),
(4, 'Emergency Contacts', 'https://www.turtleapp.com/guides/emergency', 'Important emergency phone numbers and procedures.', 1);
