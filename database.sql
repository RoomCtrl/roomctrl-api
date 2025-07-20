DELETE FROM users;
DELETE FROM organizations;

INSERT INTO organizations (id, regon, name, email) VALUES
(1, '123456789', 'Acme Corp', 'contact@acme.com'),
(2, '987654321', 'Globex Ltd', 'info@globex.com');

INSERT INTO users (id, username, roles, password, first_name, last_name, first_logon_status, organization_id) VALUES
(1, 'user1', '["ROLE_ADMIN"]',  '$2y$12$n3EESLFY8bSPmKOwM5vvReOgy7R2Q.t2Ah2Lx8gIAvMusG4vps4Na', 'John', 'Doe', true, 1),
(2, 'user2', '["ROLE_USER"]',   '$2y$12$/65E04/VGL4rb/Tc1MKL6ujcX4KH72saT0L2vGwTT2857rN41uqvy', 'Jane', 'Smith', false, 1),
(3, 'user3', '["ROLE_USER"]',   '$2y$12$1ZsNzVeiaIkSLLmCojc/peRPAQmDKom/gD27b98TKOmNLZdXlSVnm', 'Alice', 'Johnson', true, 2);
