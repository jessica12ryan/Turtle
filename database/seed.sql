-- Seed demo data (run from setup wizard; admin user already exists as id=1)

INSERT INTO users (id, name, email, password, role, must_change_password, created_at, updated_at) VALUES
(2, 'Jane Manager', 'manager@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'property_manager', 0, NOW(), NOW()),
(3, 'Bob Maintenance', 'bob@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'maintenance', 0, NOW(), NOW()),
(4, 'Tom Tenant', 'tom@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW()),
(5, 'Sue Tenant', 'sue@turtleapp.com', '$2y$12$EN5B05AqFwvhbBZso49M0u0V3QOIeE/qSNmi/Gd0T/dipzYrn6XA2', 'tenant', 0, NOW(), NOW());

INSERT INTO companies (id, name, address, city, province, postal_code, phone) VALUES
(1, 'Turtle Properties Inc.', '123 Main Street', 'Toronto', 'Ontario', 'M5A 1A1', '555-0100');

INSERT INTO company_user (company_id, user_id) VALUES (1, 1), (1, 2), (1, 3);

INSERT INTO properties (id, landlord_id, company_id, name, address, city, province, postal_code) VALUES
(1, 1, 1, 'The Wellington', '45 Wellington St W', 'Toronto', 'Ontario', 'M5V 1E3'),
(2, 1, 1, 'King Street Lofts', '120 King St E', 'Toronto', 'Ontario', 'M5C 1G6');

INSERT INTO property_tenant (property_id, tenant_id, is_main_tenant, assigned_at) VALUES
(1, 4, 1, NOW()),
(2, 5, 1, NOW());

INSERT INTO tickets (property_id, tenant_id, subject, description, category, status, priority) VALUES
(1, 4, 'Leaky kitchen faucet', 'The kitchen faucet has been dripping for two days.', 'plumbing', 'in_progress', 'medium'),
(1, 4, 'Broken garbage disposal', 'The garbage disposal makes a loud noise when turned on.', 'appliances', 'open', 'low'),
(2, 5, 'Heating not working', 'The heating unit is blowing cold air.', 'hvac', 'open', 'high');

INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES
(1, 2, 'I have assigned this to Bob for inspection.', 1),
(1, 3, 'I will check it out tomorrow morning.', 0);
