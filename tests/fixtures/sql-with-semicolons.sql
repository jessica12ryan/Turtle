-- Fixture for quote-aware SQL splitting.
CREATE TABLE sql_split_fixture (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description TEXT NOT NULL
);

-- Semicolons inside quoted values must not split this statement.
INSERT INTO sql_split_fixture (description) VALUES ('Bright; quiet street; near transit');
INSERT INTO sql_split_fixture (description) VALUES ('It''s a "test"; with quotes');
# A hash comment with a trailing semicolon;
INSERT INTO sql_split_fixture (description) VALUES ('last');
