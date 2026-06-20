-- Seed data for Turtle

-- Users (password = 'password' hashed with bcrypt)
INSERT INTO users (name, email, password, role, must_change_password, created_at, updated_at) VALUES
('Admin Landlord', 'admin@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'landlord', 0, NOW(), NOW()),
('Jane Manager', 'manager@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'property_manager', 0, NOW(), NOW()),
('Bob Maintenance', 'bob@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'maintenance', 0, NOW(), NOW()),
('Tom Tenant', 'tom@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
('Sue Tenant', 'sue@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW());

-- Company
INSERT INTO companies (name, address, city, province, postal_code, phone, created_at, updated_at) VALUES
('Turtle Properties Inc.', '123 Main Street', 'Toronto', 'Ontario', 'M5A 1A1', '555-0100', NOW(), NOW());

-- Company-User assignments
INSERT INTO company_user (company_id, user_id) VALUES (1, 1);
INSERT INTO company_user (company_id, user_id) VALUES (1, 2);
INSERT INTO company_user (company_id, user_id) VALUES (1, 3);

-- Properties
INSERT INTO properties (company_id, name, address, city, province, postal_code, created_at, updated_at) VALUES
(1, 'The Wellington', '45 Wellington St W', 'Toronto', 'Ontario', 'M5V 1E3', NOW(), NOW()),
(1, 'King Street Lofts', '120 King St E', 'Toronto', 'Ontario', 'M5C 1G6', NOW(), NOW());

-- Tenant assignments
INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at, created_at, updated_at) VALUES
(1, 4, 1, NOW(), NOW(), NOW()),
(2, 5, 1, NOW(), NOW(), NOW());

-- Sample tickets
INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority, created_at, updated_at) VALUES
(1, 4, 'Leaky kitchen faucet', 'The kitchen faucet has been dripping for two days.', 'plumbing', 'in_progress', 'medium', NOW(), NOW()),
(1, 4, 'Broken garbage disposal', 'The garbage disposal makes a loud noise when turned on.', 'appliances', 'open', 'low', NOW(), NOW()),
(2, 5, 'Heating not working', 'The heating unit is blowing cold air.', 'hvac', 'open', 'high', NOW(), NOW());

-- Sample comments
INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal, created_at) VALUES
(1, 2, 'I have assigned this to Bob for inspection.', 1, NOW()),
(1, 3, 'I will check it out tomorrow morning.', 0, NOW());
