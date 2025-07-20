DELETE FROM contact_details;
DELETE FROM users;
DELETE FROM organizations;

-- Reset sequences
ALTER SEQUENCE contact_details_id_seq RESTART WITH 1;
ALTER SEQUENCE users_id_seq RESTART WITH 1;
ALTER SEQUENCE organizations_id_seq RESTART WITH 1;

INSERT INTO organizations (id, regon, name, email) VALUES
(1, '123456789', 'Acme Corp', 'contact@acme.com'),
(2, '987654321', 'Globex Ltd', 'info@globex.com');

INSERT INTO contact_details (id, street_name, street_number, flat_number, post_code, city, email, phone) VALUES
(1, 'Main Street', '123', NULL, '00-001', 'New York', 'john.doe@example.com', '+1-555-123-4567'),
(2, 'Broadway', '45', '3A', '00-002', 'Los Angeles', 'jane.smith@example.com', '+1-555-765-4321'),
(3, 'Park Avenue', '78', '15B', '00-003', 'Chicago', 'alice.johnson@example.com', '+1-555-987-6543');

INSERT INTO users (id, username, roles, password, first_name, last_name, first_logon_status, organization_id, contact_detail_id) VALUES
(1, 'user1', '["ROLE_ADMIN"]',  '$2y$12$n3EESLFY8bSPmKOwM5vvReOgy7R2Q.t2Ah2Lx8gIAvMusG4vps4Na', 'John', 'Doe', true, 1, 1),
(2, 'user2', '["ROLE_USER"]',   '$2y$12$/65E04/VGL4rb/Tc1MKL6ujcX4KH72saT0L2vGwTT2857rN41uqvy', 'Jane', 'Smith', false, 1, 2),
(3, 'user3', '["ROLE_USER"]',   '$2y$12$1ZsNzVeiaIkSLLmCojc/peRPAQmDKom/gD27b98TKOmNLZdXlSVnm', 'Alice', 'Johnson', true, 2, 3);
