-- Entries of registered content types (THE-106). Every type registered with register_content_type()
-- shares this one table, keyed by content_type, so registering a type needs no schema change.
-- Mapped by src/Models/Entry.php.
--
-- Slugs are unique per content type, not across the table. content_type is VARCHAR(255) like the
-- other content-type columns (see 20261008_widen_content_type_columns.sql); the unique key on
-- (content_type, slug) is about 2040 bytes in utf8mb4, under InnoDB's 3072-byte limit.
CREATE TABLE entries (
    id INT AUTO_INCREMENT NOT NULL,
    content_type VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT DEFAULT NULL,
    excerpt LONGTEXT DEFAULT NULL,
    status VARCHAR(32) NOT NULL,
    published_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    modified_at DATETIME NOT NULL,
    featured_image_id INT DEFAULT NULL,
    INDEX idx_entries_type_status (content_type, status),
    UNIQUE INDEX uniq_entries_type_slug (content_type, slug),
    INDEX IDX_2DF8B3C53569D950 (featured_image_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4;

ALTER TABLE entries ADD CONSTRAINT FK_2DF8B3C53569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
