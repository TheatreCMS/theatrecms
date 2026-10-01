-- The example plugin's own table, applied by `bin/theatrecms migrate` alongside core's migrations.
CREATE TABLE examples (
    id INTEGER NOT NULL PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);
