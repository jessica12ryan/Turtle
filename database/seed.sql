-- Seed demo data (run from setup wizard; admin user already exists as id=1)

-- Users: staff + tenants
INSERT IGNORE INTO users (id, name, email, password, role, must_change_password, created_at, updated_at) VALUES
(2, 'Jane Manager', 'manager@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'property_manager', 0, NOW(), NOW()),
(3, 'Bob Maintenance', 'bob@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'maintenance', 0, NOW(), NOW()),
(4, 'Tom Tenant', 'tom@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(5, 'Sue Tenant', 'sue@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(6, 'Alice Landlord', 'alice@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'landlord', 0, NOW(), NOW()),
(7, 'Charlie Tenant', 'charlie@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(8, 'Diana Tenant', 'diana@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW());

-- Companies
INSERT IGNORE INTO companies (id, name, address, city, province, postal_code, phone) VALUES
(1, 'Turtle Properties Inc.', '123 Main Street', 'Toronto', 'Ontario', 'M5A 1A1', '555-0100'),
(2, 'Lakeside Properties LLC', '456 Lake Shore Blvd', 'Toronto', 'Ontario', 'M5V 1A1', '555-0200');

-- Company-user assignments
INSERT IGNORE INTO company_user (company_id, user_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 6);

-- Properties
INSERT IGNORE INTO properties (id, landlord_id, company_id, property_manager_id, name, address, city, province, postal_code, country) VALUES
(1, 1, 1, 2, 'The Wellington', '45 Wellington St W', 'Toronto', 'ON', 'M5V 1E3', 'CA'),
(2, 1, 1, 2, 'King Street Lofts', '120 King St E', 'Toronto', 'ON', 'M5C 1G6', 'CA'),
(3, 6, 2, 2, 'Harbourfront Condos', '300 Queens Quay W', 'Toronto', 'ON', 'M5V 1A2', 'CA'),
(4, 6, 2, 2, 'Maple Ridge Townhomes', '75 Maple Dr', 'Toronto', 'ON', 'M6A 1A1', 'CA');

-- Tenant-property assignments
INSERT IGNORE INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at) VALUES
(1, 4, 1, NOW()),
(2, 5, 1, NOW()),
(3, 7, 1, NOW()),
(4, 8, 1, NOW());

-- Tickets
INSERT IGNORE INTO tickets (id, property_id, tenant_id, subject, description, category, status, priority, assigned_to) VALUES
(1, 1, 4, 'Leaky kitchen faucet', 'The kitchen faucet has been dripping for two days. It seems to be getting worse.', 'plumbing', 'in_progress', 'medium', 3),
(2, 1, 4, 'Broken garbage disposal', 'The garbage disposal makes a loud noise when turned on. May need replacement.', 'appliances', 'open', 'low', NULL),
(3, 2, 5, 'Heating not working', 'The heating unit is blowing cold air. Temperature inside dropped to 15C.', 'hvac', 'open', 'high', NULL),
(4, 3, 7, 'Smoke detector beeping', 'Smoke detector in the hallway beeps every 30 seconds. Battery was just replaced.', 'safety', 'open', 'medium', NULL),
(5, 4, 8, 'Garage door remote broken', 'The garage door remote stopped working. Manual open still works.', 'other', 'open', 'low', NULL);

-- Ticket comments
INSERT IGNORE INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES
(1, 2, 'I have assigned this to Bob for inspection.', 1),
(1, 3, 'I will check it out tomorrow morning.', 0),
(2, 4, 'It started making noise after I put some vegetable peels down.', 0),
(3, 2, 'This is urgent - sending Bob right away.', 1);

-- Resources (links shared with tenants)
INSERT IGNORE INTO resources (id, title, url, description, created_by) VALUES
(1, 'Tenant Payment Portal', 'https://www.turtleapp.com/pay', 'Pay rent and view payment history online.', 1),
(2, 'Maintenance Request Guide', 'https://www.turtleapp.com/guides/maintenance', 'How to submit and track maintenance requests.', 2),
(3, 'Community Rules', 'https://www.turtleapp.com/guides/rules', 'Building rules and community guidelines.', 2),
(4, 'Emergency Contacts', 'https://www.turtleapp.com/guides/emergency', 'Important emergency phone numbers and procedures.', 1);

