-- SQL script to populate the "users" table with test data

INSERT INTO users (username, roles, password, first_name, last_name, first_logon_status) VALUES
('user1', '["ROLE_ADMIN"]', '$2y$12$n3EESLFY8bSPmKOwM5vvReOgy7R2Q.t2Ah2Lx8gIAvMusG4vps4Na', 'John', 'Doe', true),
('user2', '["ROLE_USER"]', '$2y$12$/65E04/VGL4rb/Tc1MKL6ujcX4KH72saT0L2vGwTT2857rN41uqvy', 'Jane', 'Smith', false),
('user3', '["ROLE_USER"]', '$2y$12$1ZsNzVeiaIkSLLmCojc/peRPAQmDKom/gD27b98TKOmNLZdXlSVnm', 'Alice', 'Johnson', true);
